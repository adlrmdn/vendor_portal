<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SubconOrder;
use App\Models\SubconOrderItem;
use App\Models\Vendor;
use App\Services\SubconProductionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncD365SubconOrders extends Command
{
    /**
     * Subcon counterpart of d365:sync-orders.
     *
     * Pulls REAL purchase orders from D365 for vendors flagged as `type = subcon`
     * in the local DB and materialises them as subcon work orders
     * (subcon_orders + subcon_order_items). The PO number is used verbatim as the
     * work order number, exactly like the fabric pipeline.
     */
    protected $signature = 'd365:sync-subcon-orders
                            {--days=60 : Only sync POs created within the last N days}
                            {--vendor=* : Limit to specific vendor code(s); defaults to all subcon vendors}
                            {--company=mpg : D365 legal entity (dataAreaId) to restrict to. REQUIRED to avoid cross-company vendor-code collisions (e.g. V0246 = KNK in mpg but PUMA CAT in mpr)}
                            {--dry-run : Fetch and report only; write nothing}';

    protected $description = 'Fetch subcon vendors\' POs from D365 and sync them into subcon work orders';

    public function handle(SubconProductionService $production)
    {
        $this->info('Starting D365 subcon ETL pipeline...');

        // Resolve which vendors to sync (subcon only).
        $vendorQuery = Vendor::where('type', 'subcon');
        if ($only = $this->option('vendor')) {
            $vendorQuery->whereIn('vendor_code', $only);
        }
        $vendors = $vendorQuery->pluck('id', 'vendor_code')->toArray();

        if (empty($vendors)) {
            $this->error('No subcon vendors found to sync.');

            return Command::FAILURE;
        }
        $this->info('Target subcon vendors: '.implode(', ', array_keys($vendors)));

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        // 1. Fetch headers for these vendor accounts, confirmed, within window.
        $cutoff = Carbon::now()->subDays((int) $this->option('days'))->format('Y-m-d\TH:i:s\Z');
        $vendorClause = '('.implode(' or ', array_map(
            fn ($code) => "OrderVendorAccountNumber eq '$code'",
            array_keys($vendors)
        )).')';
        $filters = [
            $vendorClause,
            "DocumentApprovalStatus eq Microsoft.Dynamics.DataEntities.VersioningDocumentState'Confirmed'",
            "CreatedDateTime1 ge $cutoff",
        ];

        // Vendor account codes are company-scoped in D365 — the same code maps to
        // different legal vendors across companies. Restrict to one legal entity.
        if ($company = $this->option('company')) {
            $filters[] = "dataAreaId eq '$company'";
        }

        $localActivePos = SubconOrder::whereNotIn('status', ['completed', 'cancelled'])->pluck('order_number')->toArray();

        $this->info('Fetching PO headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $filters);
        $this->info('Fetched '.count($headers).' header records.');

        $fetchedPoNumbers = [];
        foreach ($headers as $h) {
            $fetchedPoNumbers[] = $h['PurchaseOrderNumber'];
        }

        $missingActivePos = array_diff($localActivePos, $fetchedPoNumbers);
        if (! empty($missingActivePos)) {
            $this->info('Fetching headers for '.count($missingActivePos).' local active POs outside cutoff...');
            foreach (array_chunk($missingActivePos, 20) as $chunk) {
                $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk);
                $missingFilters = ['('.implode(' or ', $parts).')'];
                if (! empty($company)) {
                    $missingFilters[] = "dataAreaId eq '$company'";
                }
                $missingHeaders = $this->fetchRecords('PurchaseOrderHeadersV2', $missingFilters);
                $headers = array_merge($headers, $missingHeaders);
            }
        }

        if (empty($headers)) {
            $this->info('No subcon orders to sync.');

            return Command::SUCCESS;
        }

        // 2. Map PO -> local vendor id (skip any vendor we do not track).
        $poMap = [];      // po_number => vendor_id
        $poHeaders = [];  // po_number => header
        foreach ($headers as $h) {
            $code = $h['OrderVendorAccountNumber'] ?? null;
            if (! $code || ! isset($vendors[$code])) {
                continue;
            }
            $poNumber = $h['PurchaseOrderNumber'];
            $poMap[$poNumber] = $vendors[$code];
            $poHeaders[$poNumber] = $h;
        }
        $this->info('Matched '.count($poMap).' POs to subcon vendors.');
        if (empty($poMap)) {
            return Command::SUCCESS;
        }

        // 3. Fetch lines in batches of 20 POs.
        $allLines = [];
        foreach (array_chunk(array_keys($poMap), 20) as $chunk) {
            $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk);
            $lineFilters = ['('.implode(' or ', $parts).')'];
            if (! empty($company)) {
                $lineFilters[] = "dataAreaId eq '$company'";
            }
            $batch = $this->fetchRecords('PurchaseOrderLinesV2', $lineFilters);
            $allLines = array_merge($allLines, $batch);
        }
        $this->info('Fetched '.count($allLines).' line records.');

        // Group lines per PO.
        $linesByPo = [];
        foreach ($allLines as $l) {
            $linesByPo[$l['PurchaseOrderNumber']][] = $l;
        }

        if ($this->option('dry-run')) {
            foreach ($poMap as $poNumber => $vendorId) {
                $this->line(sprintf(
                    '  %s  (%d items)  created %s',
                    $poNumber,
                    count($linesByPo[$poNumber] ?? []),
                    $poHeaders[$poNumber]['CreatedDateTime1'] ?? '?'
                ));
            }
            $this->warn('Dry run: nothing written.');

            return Command::SUCCESS;
        }

        // Resolve garment style names from VSM so the order title is the style
        // (e.g. "Minimal Ane Shirt Blue") rather than the generic PO number.
        // POs not yet present in the VSM snapshot fall back to the old title.
        $styles = $production->stylesForPos(array_keys($poMap));

        // Resolve Distribution IDs from VSM mapping & D365 TOC_DT entity.
        $this->info('Resolving PLM activities from VSM...');
        $poNumbers = array_keys($poMap);
        $poPlmMap = [];
        $uniquePlmIds = [];
        try {
            $poLines = DB::connection('vsm')->table('po_lines')
                ->whereIn('PurchaseOrderNumber', $poNumbers)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->get(['PurchaseOrderNumber', 'PLMId']);
            foreach ($poLines as $l) {
                $poPlmMap[$l->PurchaseOrderNumber][] = $l->PLMId;
                $uniquePlmIds[] = $l->PLMId;
            }
            $uniquePlmIds = array_unique($uniquePlmIds);
        } catch (\Throwable $e) {
            $this->warn('Failed to fetch PO lines from VSM: '.$e->getMessage());
        }

        $plmSoMap = [];
        if (! empty($uniquePlmIds)) {
            $this->info('Resolving Sales Orders from VSM plm_trans...');
            try {
                $trans = DB::connection('vsm')->table('plm_trans')
                    ->whereIn('PLMId', $uniquePlmIds)
                    ->where('ActivityName', 'SO Intercompany')
                    ->whereNotNull('ActivityNo')
                    ->where('ActivityNo', '!=', '')
                    ->get(['PLMId', 'ActivityNo']);
                foreach ($trans as $t) {
                    $plmSoMap[$t->PLMId] = $t->ActivityNo;
                }
            } catch (\Throwable $e) {
                $this->warn('Failed to fetch SO mapping from VSM: '.$e->getMessage());
            }
        }

        $soDstMap = [];
        $salesOrders = array_unique(array_values($plmSoMap));
        if (! empty($salesOrders)) {
            $this->info('Fetching Distribution IDs from D365 TOC_DT...');
            foreach (array_chunk($salesOrders, 20) as $chunk) {
                $filterParts = array_map(fn ($so) => "SOID eq '$so'", $chunk);
                $filter = '('.implode(' or ', $filterParts).')';
                try {
                    $tocRecords = $this->fetchRecords('TOC_DT', [$filter], ['SOID', 'DistributionID']);
                    foreach ($tocRecords as $rec) {
                        if (! empty($rec['SOID']) && ! empty($rec['DistributionID'])) {
                            $soDstMap[$rec['SOID']] = $rec['DistributionID'];
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn('Failed to fetch Distribution IDs from D365: '.$e->getMessage());
                }
            }
        }

        $poDstMap = [];
        foreach ($poMap as $poNumber => $vendorId) {
            $plmIds = $poPlmMap[$poNumber] ?? [];
            $dstIds = [];
            foreach ($plmIds as $plmId) {
                if ($so = $plmSoMap[$plmId] ?? null) {
                    if ($dst = $soDstMap[$so] ?? null) {
                        $dstIds[] = $dst;
                    }
                }
            }
            if (! empty($dstIds)) {
                $poDstMap[$poNumber] = implode(' / ', array_unique($dstIds));
            }
        }        // 4. Transform + persist.
        DB::beginTransaction();
        try {
            $createdOrders = 0;
            $createdItems = 0;
            $retitled = 0;
            $deletedOrders = 0;
            $reassigned = 0;

            foreach ($poMap as $poNumber => $vendorId) {
                $h = $poHeaders[$poNumber];

                // --- 1. Check if Finished ---
                $isFinished = false;

                // A. Check D365 PurchaseOrderStatus — only a genuinely terminal
                // status counts as finished. 'Received' is just goods-receipt on
                // the procurement side (fabric landed in the warehouse); it says
                // nothing about whether the subcon CMT paperwork (cutting/gramasi/
                // labels) is done, and treating it as "finished" here was hard-
                // deleting (or blocking creation of) orders whose portal workflow
                // was still active or even mid-approval.
                $d365Status = $h['PurchaseOrderStatus'] ?? null;
                if ($d365Status && in_array($d365Status, ['Invoiced', 'Canceled'], true)) {
                    $isFinished = true;
                }

                // B. Check production order status (from VSM production group lines)
                $groups = $production->forPo($poNumber);
                if (! $isFinished && ! empty($groups)) {
                    $allVsmLines = [];
                    foreach ($groups as $g) {
                        if (! empty($g['lines'])) {
                            foreach ($g['lines'] as $line) {
                                $allVsmLines[] = $line;
                            }
                        }
                    }
                    if (! empty($allVsmLines)) {
                        // Only a fully *Completed* group counts as finished here.
                        // 'ReportedFinished' means sewing reported done, but the
                        // subcon paperwork (cutting/gramasi/labels) may still be
                        // pending — removing the order then would yank it mid-flow.
                        $finishedLines = array_filter($allVsmLines, function ($line) {
                            return in_array($line->ProdStatus, ['Completed']);
                        });
                        if (count($finishedLines) === count($allVsmLines)) {
                            $isFinished = true;
                        }
                    }
                }

                // C. Hard override: never call it finished while the QC Console
                // still has an ACTIVE packaging project on this production group.
                // D365 procurement status (A) and VSM sewing status (B) both lag
                // or are simply orthogonal to whether the subcon paperwork
                // (cutting/gramasi/labels, or the QC Console's own inspection →
                // Final Approval → Director chain) is actually done — this has
                // repeatedly caused orders to be hard-deleted (or never created)
                // while still mid-flow. The console itself is authoritative for
                // "done": it flips the project to completed/removed/removed_completed
                // when it's truly finished (SubconOrder::QMS_INACTIVE_PROJECT_STATUSES).
                if ($isFinished) {
                    // VSM first: it's the live production-system source of truth for
                    // grouping (see below), whereas D365's TOC_ProductionGroup is a
                    // point-in-time mirror that can go stale after a mid-production
                    // regroup — which would otherwise make this guard miss an active
                    // QMS project sitting under the group VSM now reports.
                    $tocPrgForGuard = null;
                    if (! empty($groups)) {
                        foreach ($groups as $g) {
                            if (! empty($g['production_group'])) {
                                $tocPrgForGuard = $g['production_group'];
                                break;
                            }
                        }
                    }
                    if (! $tocPrgForGuard) {
                        $tocPrgForGuard = $h['TOC_ProductionGroup'] ?? null;
                    }
                    if ($tocPrgForGuard && Schema::connection('qms')->hasTable('packaging_projects')) {
                        $activeProject = DB::connection('qms')->table('packaging_projects')
                            ->where('production_group', $tocPrgForGuard)
                            ->whereNotIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES)
                            ->exists();
                        if ($activeProject) {
                            $isFinished = false;
                        }
                    }

                    // D. Second, independent override: an in-flight Final Approval
                    // (Head Office has signed but the Director hasn't yet cleanly
                    // closed it — either never reached them, or they rejected it
                    // for revision, which resets director_approval_signature back
                    // to null) is unambiguous proof the subcon paperwork isn't
                    // done. Checked separately from packaging_projects.status
                    // above because that status has, in practice, not always
                    // caught this — an order was hard-deleted and silently
                    // recreated under a new id mid-Director-review, orphaning its
                    // subcon_fabric_reconciliations (MPG/PO/2606/01345 incident).
                    if ($isFinished && $tocPrgForGuard
                        && Schema::connection('qms')->hasTable('packaging_projects')
                        && Schema::connection('qms')->hasTable('packaging_project_sessions')) {
                        $projectIds = DB::connection('qms')->table('packaging_projects')
                            ->where('production_group', $tocPrgForGuard)
                            ->pluck('project_id');
                        if ($projectIds->isNotEmpty()) {
                            $inFlightApproval = DB::connection('qms')->table('packaging_project_sessions')
                                ->whereIn('project_id', $projectIds)
                                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                                ->where(function ($q) {
                                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                                })
                                ->exists();
                            if ($inFlightApproval) {
                                $isFinished = false;
                            }
                        }
                    }
                }

                if ($isFinished) {
                    $existingLocal = SubconOrder::where('order_number', $poNumber)->first();
                    if ($existingLocal) {
                        // Aged deletion: a PO looking "finished" doesn't mean the
                        // subcon paperwork is done — give it a grace period from
                        // the first time we observe it as finished, so a status
                        // flip doesn't instantly yank an order that's still being
                        // worked (or was just approved) before this run.
                        $agingDays = (int) Setting::getValue('subcon_finished_aging_days', 3);
                        if (! $existingLocal->d365_finished_detected_at) {
                            $existingLocal->update(['d365_finished_detected_at' => now()]);
                            $this->info("PO {$poNumber} looks finished — aging {$agingDays}d before deletion.");
                        } elseif ($existingLocal->d365_finished_detected_at->lte(now()->subDays($agingDays))) {
                            $existingLocal->delete();
                            $deletedOrders++;
                            $this->info("PO {$poNumber} finished and aged past {$agingDays}d. Deleted/removed from local portal.");
                        } else {
                            $this->info("PO {$poNumber} still aging (first seen finished {$existingLocal->d365_finished_detected_at}).");
                        }
                    } else {
                        $this->info("PO {$poNumber} is finished on D365. Skipping creation.");
                    }

                    continue; // Skip creating or syncing this order further
                }

                // --- 2. Create/Update SubconOrder ---
                $orderDate = isset($h['CreatedDateTime1'])
                    ? Carbon::parse($h['CreatedDateTime1'])->format('Y-m-d')
                    : now()->format('Y-m-d');
                $dueDate = isset($h['RequestedDeliveryDate'])
                    ? Carbon::parse($h['RequestedDeliveryDate'])->format('Y-m-d')
                    : null;
                $distributionId = $poDstMap[$poNumber] ?? null;

                // Resolve Production Group: VSM (production_group_lines, live) wins
                // over D365's TOC_ProductionGroup header field. The header field is
                // set once and can silently go stale after a mid-production regroup
                // in VSM — trusting it over VSM left the local order (and hence the
                // QC Console's project matching, which keys off VSM's current group)
                // permanently pinned to a group no one uses anymore.
                $tocPrg = null;
                if (! empty($groups)) {
                    foreach ($groups as $g) {
                        if (! empty($g['production_group'])) {
                            $tocPrg = $g['production_group'];
                            break;
                        }
                    }
                }
                if (! $tocPrg) {
                    $tocPrg = $h['TOC_ProductionGroup'] ?? null;
                }

                // forPo() came up empty above (typical for a "standalone" CMT
                // production group with no PLM link — its only other path is a
                // local subcon_orders lookup, which is empty for a PO not yet
                // synced). We already know the group from the D365 header though,
                // so resolve size/style data directly from it — this is what makes
                // title/sizes_count correct on the very same run that creates the
                // order, instead of only self-healing on the next sync pass.
                if (empty($groups) && $tocPrg) {
                    $groups = $production->forProductionGroup($tocPrg);
                }

                $summary = $production->summarize($groups);
                $sizesCount = $summary['size_count'] ?? 0;

                $style = $styles[$poNumber] ?? ($summary['style'] ?? null);
                $title = $style
                    ?: (! empty($h['VendorOrderReference'])
                        ? $h['VendorOrderReference']
                        : ('Work Order '.$poNumber));

                $order = SubconOrder::firstOrCreate(
                    ['order_number' => $poNumber],
                    [
                        'id' => (string) Str::uuid(),
                        'vendor_id' => $vendorId,
                        'status' => 'pending',
                        'title' => $title,
                        'description' => null,
                        'order_date' => $orderDate,
                        'due_date' => $dueDate,
                        'notes' => $h['ReasonComment'] ?? null,
                        'distribution_id' => $distributionId,
                        'workflow_stage' => SubconOrder::STAGE_CUTTING,
                        'production_group' => $tocPrg,
                        'sizes_count' => $sizesCount,
                    ]
                );

                if ($order->wasRecentlyCreated) {
                    $createdOrders++;
                    $this->restoreFabricReconciliationFromQms($order);
                } else {
                    $updates = [];
                    // D365 can reassign a PO to a different vendor account after
                    // it was first synced (e.g. a data-entry correction). Since
                    // vendor_id gates portal visibility, a stale value here
                    // silently hides the PO from its real vendor while leaving it
                    // visible to whoever it was originally (wrongly) attached to.
                    if ($order->vendor_id !== $vendorId) {
                        Log::warning('Subcon PO vendor reassigned by D365 sync', [
                            'order_number' => $poNumber,
                            'from_vendor_id' => $order->vendor_id,
                            'to_vendor_id' => $vendorId,
                        ]);
                        $updates['vendor_id'] = $vendorId;
                        $reassigned++;
                    }
                    if ($style && $order->title !== $style) {
                        $updates['title'] = $style;
                        $retitled++;
                    }
                    if ($distributionId && $order->distribution_id !== $distributionId) {
                        $updates['distribution_id'] = $distributionId;
                    }
                    if ($tocPrg && $order->production_group !== $tocPrg) {
                        $updates['production_group'] = $tocPrg;
                    }
                    if ($sizesCount !== $order->sizes_count) {
                        $updates['sizes_count'] = $sizesCount;
                    }
                    if ($order->d365_finished_detected_at) {
                        $updates['d365_finished_detected_at'] = null;
                    }
                    if (! empty($updates)) {
                        $order->update($updates);
                    }
                }

                // --- 3. Create Order Items ---
                foreach ($linesByPo[$poNumber] ?? [] as $l) {
                    $item = SubconOrderItem::firstOrCreate(
                        ['order_id' => $order->id, 'item_number' => $l['ItemNumber']],
                        [
                            'id' => (string) Str::uuid(),
                            'description' => $l['LineDescription'] ?? $l['ItemNumber'],
                            'quantity' => $l['OrderedPurchaseQuantity'] ?? 0.0,
                            'unit' => $l['PurchaseUnitSymbol'] ?? 'PCS',
                            'status' => 'pending',
                            'notes' => null,
                        ]
                    );
                    if ($item->wasRecentlyCreated) {
                        $createdItems++;
                    }
                }

                // --- 4. Sync Qty Cutting and Gramasi from D365 (data only — never
                // touches workflow_stage; see the note below the loop) ---
                $jobTransService = app(\App\Services\D365JobTransactionService::class);
                foreach ($groups as $g) {
                    if (empty($g['production_group'])) {
                        continue;
                    }

                    $erpDetails = $jobTransService->fetchJobTransactionDetails($g['production_group']);

                    // Process cutting quantities
                    if (! empty($erpDetails['cutting'])) {
                        foreach ($g['lines'] as $line) {
                            $size = $line->Size;
                            if (isset($erpDetails['cutting'][$size])) {
                                $qty = $erpDetails['cutting'][$size];

                                // Match on size, not prod_id: D365 can reissue a
                                // size's ProdId mid-order, and keying on the old
                                // prod_id would leave it in place and insert a
                                // duplicate row for the same size.
                                \App\Models\SubconCuttingReport::updateOrCreate(
                                    ['order_id' => $order->id, 'size' => $size],
                                    [
                                        'prod_id' => $line->ProdId,
                                        'cutting_qty' => $qty,
                                    ]
                                );
                            }
                        }
                    }

                    // Process gramasi
                    if (! empty($erpDetails['gramasi'])) {
                        foreach ($g['lines'] as $line) {
                            $size = $line->Size;
                            if (isset($erpDetails['gramasi'][$size])) {
                                // Convert kg from D365 to grams locally (multiply by 1000)
                                $grams = (float) $erpDetails['gramasi'][$size] * 1000;

                                \App\Models\SubconCuttingReport::updateOrCreate(
                                    ['order_id' => $order->id, 'size' => $size],
                                    [
                                        'prod_id' => $line->ProdId,
                                        'gramasi' => $grams,
                                    ]
                                );
                            }
                        }
                    }
                }

                // D365 cutting/gramasi values are mirrored onto the local report
                // above, but the sync must never push the order into the approval
                // queue itself — only a vendor's actual in-portal submit
                // (SubconVendorController::submitCuttingReport/submitGramasi) does
                // that, since that's also where the mandatory fabric
                // consumption/deduction calc (SubconConsumptionService::persist(),
                // documented in CLAUDE.md as the merged "cutting = calculate +
                // approve" gate) happens. Auto-promoting here used to fire a
                // "pending approval" with no vendor action behind it — and, worse,
                // if the order had just been declined (reject_gate still set), the
                // very next sync run would silently bounce it right back into
                // cutting_review with the same stale data, without the vendor ever
                // touching it. See [[subcon-approval-silent-spawn]].
            }

            DB::commit();
            $changes = array_filter([
                $createdOrders > 0 ? "$createdOrders new" : null,
                $createdItems > 0 ? "$createdItems items" : null,
                $retitled > 0 ? "$retitled retitled" : null,
                $deletedOrders > 0 ? "$deletedOrders removed" : null,
                $reassigned > 0 ? "$reassigned reassigned" : null,
            ]);
            $this->info('Synced: '.($changes ? implode(', ', $changes).'.' : 'no changes.'));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during insertion: '.$e->getMessage());
            if (app()->runningUnitTests()) {
                throw $e;
            }

            return Command::FAILURE;
        }
    }

    /**
     * A recreated order (new UUID, minted because the previous row for this
     * order_number was hard-deleted — see the Finished guard above) starts
     * with an empty subcon_fabric_reconciliations, orphaning whatever
     * consumption/deduction figures were entered under the old order_id. If
     * the QC Console already published a snapshot for this production group
     * (packaging_project_fabric_lines — keyed by production_group, so it
     * survives the delete), restore it here rather than leaving the cutting/
     * HO approval forms showing blank Fabric Sent/Cons. Plan/Deduction for an
     * order that was, in reality, already reconciled. Best-effort: an
     * unreachable/older QMS schema just skips silently.
     */
    private function restoreFabricReconciliationFromQms(SubconOrder $order): void
    {
        $pg = trim((string) ($order->production_group ?? ''));
        if ($pg === '') {
            return;
        }

        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_fabric_lines')) {
                return;
            }

            $lines = DB::connection('qms')->table('packaging_project_fabric_lines')
                ->where('production_group', $pg)
                ->get();

            foreach ($lines as $l) {
                \App\Models\SubconFabricReconciliation::updateOrCreate(
                    ['order_id' => $order->id, 'label' => $l->label],
                    [
                        'short_roll' => $l->short_roll ?? 0,
                        'sisa_kain' => $l->sisa_kain ?? 0,
                        'kepala_kain' => $l->kepala_kain ?? 0,
                        'retur_kain' => $l->return_kain ?? 0, // QMS return_kain → portal retur_kain
                        'fabric_sent' => $l->fabric_sent,
                        'consumption_plan' => $l->consumption_plan,
                        'cutt_plan' => $l->cutt_plan,
                        'actual_consumption' => $l->actual_consumption,
                        'overconsumption' => $l->overconsumption ?? null,
                        'fabric_price' => $l->fabric_price ?? null,
                        'deduction' => $l->deduction ?? null,
                    ]
                );
            }

            if ($lines->isNotEmpty()) {
                Log::info('Subcon fabric reconciliation restored from QMS snapshot after order recreation', [
                    'order_number' => $order->order_number,
                    'production_group' => $pg,
                    'fabric_count' => $lines->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Subcon fabric reconciliation restore skipped', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getD365Token()
    {
        $tenantId = config('services.d365.tenant_id');
        $clientId = config('services.d365.client_id');
        $clientSecret = config('services.d365.client_secret');
        $resource = config('services.d365.resource');

        $cacheKey = 'd365_access_token';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => "{$resource}/.default",
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Cache::put($cacheKey, $data['access_token'], $data['expires_in'] - 300);

            return $data['access_token'];
        }

        $this->error('D365 Auth Error: '.$response->body());

        return null;
    }

    private function fetchRecords($table, $filters = [], $select = [])
    {
        $resource = config('services.d365.resource');
        $url = "{$resource}/data/{$table}";

        $queryParams = ['cross-company' => 'true'];
        if (! empty($filters)) {
            $queryParams['$filter'] = implode(' and ', $filters);
        }
        if (! empty($select)) {
            $queryParams['$select'] = implode(',', $select);
        }

        $allRecords = [];
        $nextLink = $url.'?'.http_build_query($queryParams);

        while ($nextLink) {
            $token = $this->getD365Token();
            $response = Http::withToken($token)->acceptJson()->get($nextLink);

            if (! $response->successful()) {
                $this->error('D365 Fetch Error: '.$response->status().' '.$response->body());
                break;
            }

            $body = $response->json();
            if (isset($body['value'])) {
                $allRecords = array_merge($allRecords, $body['value']);
            }
            $nextLink = $body['@odata.nextLink'] ?? null;
        }

        return $allRecords;
    }
}
