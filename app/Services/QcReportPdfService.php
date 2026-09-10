<?php

namespace App\Services;

use App\Models\SubconOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side "standard" renderer for the QC packaging inspection report —
 * the same document the QC Console builds client-side (PrintReport.tsx →
 * html2canvas → jsPDF). Having it here means the fully-signed PDF can be
 * (re)generated at ANY approval milestone without the console app being open:
 * approval-email attachments, the final 3-signature document written back to
 * packaging_projects.verified_doc, the RPA payloads' signed_doc, and the
 * completion notification.
 *
 * All data comes from the shared QMS DB. Calculations are 1:1 ports of the
 * console's PrintReport.tsx / calculations.ts (cumulative yield matrix,
 * 1%-limit production-reject penalty at 70% of sales price, fabric
 * overconsumption lines, manual deduction lines). Layout matches structurally,
 * not pixel-for-pixel (DomPDF vs html2canvas).
 *
 * Best-effort: any failure returns null and is logged — callers fall back to
 * the console-written verified_doc or send without an attachment.
 */
class QcReportPdfService
{
    private const CYCLE_NAMES = ['Baseline / CMT-Cut', 'Pre Final', '1st Final', '2nd Final', '3rd Final'];

    private const SIZE_ORDER = ['5XS', '4XS', '3XS', '2XS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', '2XL', 'XXL', '3XL', 'XXXL', '4XL', '5XL', '6XL'];

    public function __construct(private SubconProductionService $production) {}

    /** Render the report for a session as raw PDF bytes (null on failure). */
    public function render(string $projectId, string $sessionId): ?string
    {
        @ini_set('memory_limit', '512M');
        try {
            $ctx = $this->context($projectId, $sessionId);
            if ($ctx === null) {
                return null;
            }

            return Pdf::loadView('qc.pdf.inspection-report', $ctx)
                ->setPaper('a4', 'portrait')
                ->setOption('isRemoteEnabled', true)
                ->output();
        } catch (\Throwable $e) {
            Log::error('QC report PDF render failed', [
                'project' => $projectId,
                'session' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Render as the console-compatible `data:application/pdf;base64,…` URI. */
    public function renderDataUri(string $projectId, string $sessionId): ?string
    {
        $bytes = $this->render($projectId, $sessionId);

        return $bytes === null ? null : 'data:application/pdf;base64,'.base64_encode($bytes);
    }

    /**
     * Debit note (deduction) variant: the same inspection report, with a new
     * debit-note page (matching "DEBIT NOTE - CMT.xlsx") prepended ahead of it
     * — see resources/views/qc/pdf/deduction-report.blade.php. Document
     * No/Invoice Date are null at queue time (rendered blank, pending RPA);
     * the RPA bot fills them in later by overlaying the two fields onto this
     * same PDF, it does not re-render the Blade layout.
     */
    public function renderDeductionDataUri(
        string $projectId,
        string $sessionId,
        string $po,
        string $vendorName,
        float $amount,
        ?string $documentNo = null,
        ?string $invoiceDate = null,
    ): ?string {
        @ini_set('memory_limit', '512M');
        try {
            $ctx = $this->context($projectId, $sessionId);
            if ($ctx === null) {
                return null;
            }

            $style = $this->production->stylesForPos([$po])[$po] ?? null;

            $ctx = array_merge($ctx, [
                'debitPo' => $po,
                'debitVendor' => $vendorName,
                'debitCustomerRef' => trim(($style ? $style.' - ' : '').$po),
                'debitAmount' => $amount,
                'debitDocumentNo' => $documentNo,
                'debitInvoiceDate' => $invoiceDate,
                'debitAmountWords' => $this->terbilang($amount),
            ]);

            $bytes = Pdf::loadView('qc.pdf.deduction-report', $ctx)
                ->setPaper('a4', 'portrait')
                ->setOption('isRemoteEnabled', true)
                ->output();

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            Log::error('QC deduction report PDF render failed', [
                'project' => $projectId,
                'session' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Indonesian amount-in-words ("terbilang"), rupiah-rounded. Standard
     * recursive algorithm — no package pulls in a spellout library for two
     * fields on one document.
     */
    private function terbilang(float $amount): string
    {
        $n = (int) round(abs($amount));
        if ($n === 0) {
            return 'NOL RUPIAH';
        }

        $words = ['', 'SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA', 'ENAM', 'TUJUH', 'DELAPAN', 'SEMBILAN', 'SEPULUH',
            'SEBELAS'];

        $spell = function (int $n) use (&$spell, $words): string {
            if ($n < 12) {
                return $words[$n];
            }
            if ($n < 20) {
                return trim($spell($n - 10).' BELAS');
            }
            if ($n < 100) {
                return trim($spell(intdiv($n, 10)).' PULUH '.$spell($n % 10));
            }
            if ($n < 200) {
                return trim('SERATUS '.$spell($n - 100));
            }
            if ($n < 1000) {
                return trim($spell(intdiv($n, 100)).' RATUS '.$spell($n % 100));
            }
            if ($n < 2000) {
                return trim('SERIBU '.$spell($n - 1000));
            }
            if ($n < 1000000) {
                return trim($spell(intdiv($n, 1000)).' RIBU '.$spell($n % 1000));
            }
            if ($n < 1000000000) {
                return trim($spell(intdiv($n, 1000000)).' JUTA '.$spell($n % 1000000));
            }

            return trim($spell(intdiv($n, 1000000000)).' MILIAR '.$spell($n % 1000000000));
        };

        return trim(preg_replace('/\s+/', ' ', $spell($n))).' RUPIAH';
    }

    /**
     * Write the rendered document to packaging_projects.verified_doc — the
     * canonical store the console previews and the final-email job attaches.
     */
    public function writeVerifiedDoc(string $projectId, string $dataUri): void
    {
        try {
            DB::connection('qms')->table('packaging_projects')
                ->where('project_id', $projectId)
                ->update(['verified_doc' => $dataUri, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: verified_doc write failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Assemble everything the Blade template needs from QMS. Mirrors the shape
     * the console feeds PrintReport (project + sessions + report lines +
     * defect images + fabric/deduction lines + staged remarks).
     *
     * @return array<string,mixed>|null
     */
    public function context(string $projectId, string $sessionId): ?array
    {
        $qms = DB::connection('qms');

        $project = $qms->table('packaging_projects')->where('project_id', $projectId)->first();
        $session = $qms->table('packaging_project_sessions')->where('session_id', $sessionId)->first();
        if (! $project || ! $session) {
            Log::warning('QC report PDF: project/session not found', ['project' => $projectId, 'session' => $sessionId]);

            return null;
        }

        $sessions = $qms->table('packaging_project_sessions')
            ->where('project_id', $projectId)->orderBy('cycle_number')->get();

        $allLines = $qms->table('packaging_project_reports')
            ->where('project_id', $projectId)
            ->orderBy('line_no')->orderBy('global_display_order')->get();
        $baseLines = $allLines->whereNull('session_id')->values();
        $linesBySession = $allLines->whereNotNull('session_id')->groupBy('session_id');

        $reportLines = ($linesBySession[$sessionId] ?? collect())
            ->sortBy(fn ($l) => $this->sizeSortKey((string) ($l->size_val ?? '')))->values();

        // Cumulative helpers over sessions with cycle_number <= active cycle.
        $activeCycle = (int) $session->cycle_number;
        $priorSessions = $sessions->filter(fn ($s) => (int) $s->cycle_number <= $activeCycle);
        $cumulative = function (string $field, string $size) use ($priorSessions, $linesBySession): int {
            $sum = 0;
            foreach ($priorSessions as $s) {
                $line = ($linesBySession[$s->session_id] ?? collect())->first(fn ($l) => ($l->size_val ?? null) === $size);
                $sum += (int) ($line->{$field} ?? 0);
            }

            return $sum;
        };
        $qtyByCycle = function (int $cycle, string $size) use ($sessions, $linesBySession): float {
            $s = $sessions->first(fn ($s) => (int) $s->cycle_number === $cycle);
            if (! $s) {
                return 0;
            }
            $line = ($linesBySession[$s->session_id] ?? collect())->first(fn ($l) => ($l->size_val ?? null) === $size);

            return (float) ($line->session_qty ?? 0);
        };
        // Partial-delivery cycle groups — mirrors the console's job_trans_raf
        // RAF grouping (queue_job_trans_raf_internal): final_1 = cycles [1,2]
        // (Pre Final + 1st Final summed), final_2 = cycle [3], final_3 = [4].
        $qtyByCycleGroup = function (array $cycles, string $size) use ($qtyByCycle): float {
            $sum = 0.0;
            foreach ($cycles as $cycle) {
                $sum += $qtyByCycle($cycle, $size);
            }

            return $sum;
        };

        // Yield-matrix rows — 1:1 port of PrintReport's rowData block.
        $rows = [];
        $totals = array_fill_keys([
            'orderQty', 'cuttingQty', 'goodGarments', 'rejectProduksi', 'rejectBahan',
            'rejectCutting', 'rejectSewing', 'rejectPrinting', 'rejectEmbro', 'rejectWashing',
            'rejectFinishing', 'rejectBtj', 'totalReject', 'barangHilang', 'wip',
            'qtyI', 'qtyII', 'qtyIII', 'totalDeliveryFG', 'goodsReceiveDelivery',
        ], 0);

        foreach ($reportLines as $line) {
            $size = (string) ($line->size_val ?? '');
            $baseLine = $baseLines->first(fn ($b) => ($b->size_val ?? null) === $size);
            $cuttingQty = (int) ($baseLine->total_good_qty ?? 0);

            $rBahan = $cumulative('reject_bahan', $size);
            $rCutting = $cumulative('reject_cutting', $size);
            $rSewing = $cumulative('reject_sewing', $size);
            $rFinishing = $cumulative('reject_finishing', $size);
            $rPrinting = $cumulative('reject_printing', $size);
            $rEmbro = $cumulative('reject_embro', $size);
            $rWashing = $cumulative('reject_washing', $size);
            $btj = $cumulative('btj', $size);
            $bHilang = $cumulative('barang_hilang', $size);

            $rejectProduksi = $rCutting + $rSewing + $rFinishing + $rPrinting + $rEmbro + $rWashing;
            $totalReject = $rBahan + $rejectProduksi + $btj + $bHilang;

            $otherGood = 0;
            foreach ($priorSessions as $s) {
                if ($s->session_id === $sessionId) {
                    continue;
                }
                $l = ($linesBySession[$s->session_id] ?? collect())->first(fn ($l) => ($l->size_val ?? null) === $size);
                $otherGood += (float) ($l->session_qty ?? 0);
            }
            $goodGarments = $otherGood + (float) ($line->session_qty ?? 0);

            $qtyI = $qtyByCycleGroup([1, 2], $size);
            $qtyII = $qtyByCycleGroup([3], $size);
            $qtyIII = $qtyByCycleGroup([4], $size);
            $totalDeliveryFG = $goodGarments;
            $wip = max(0, $cuttingQty - $totalDeliveryFG - $totalReject);
            $goodsReceiveDelivery = $goodGarments + $rBahan + $rejectProduksi;

            $row = [
                'size' => $size !== '' ? $size : '—',
                'orderQty' => (int) ($line->qty_order ?? 0),
                'cuttingQty' => $cuttingQty,
                'goodGarments' => $goodGarments,
                'rejectProduksi' => $rejectProduksi,
                'rejectBahan' => $rBahan,
                'rejectCutting' => $rCutting,
                'rejectSewing' => $rSewing,
                'rejectPrinting' => $rPrinting,
                'rejectEmbro' => $rEmbro,
                'rejectWashing' => $rWashing,
                'rejectFinishing' => $rFinishing,
                'rejectBtj' => $btj,
                'totalReject' => $totalReject,
                'barangHilang' => $bHilang,
                'wip' => $wip,
                'qtyI' => $qtyI,
                'qtyII' => $qtyII,
                'qtyIII' => $qtyIII,
                'totalDeliveryFG' => $totalDeliveryFG,
                'goodsReceiveDelivery' => $goodsReceiveDelivery,
            ];
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $row[$k];
            }
            $rows[] = $row;
        }

        // Penalty deductions — port of calculations.ts (active session lines only).
        $deductions = $this->calculateDeductions($projectId, $sessionId, $project, $reportLines, $baseLines);

        // Fabric + manual deduction lines, defect images, staged remarks.
        $fabricLines = collect();
        try {
            $fabricLines = $qms->table('packaging_project_fabric_lines')
                ->where('project_id', $projectId)->orderBy('created_at')->get();
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: fabric lines read failed', ['error' => $e->getMessage()]);
        }

        // Resolve the subcon order behind this project (production_group), the
        // same lookup QcApprovalController::subconContext() does — used below to
        // (a) enrich the fabric lines with the D365 inventory group + raw VSM
        // ordered qty (Task 1: fabric type + over/underdelivery), and (b) pull
        // this order's Material Flow return attachments (Task 2). Best-effort:
        // a miss here just leaves both enrichments off.
        $subcon = $this->resolveSubconOrder($project);

        // BOM Final (VSM `bom_lines`) — the style's complete material list. Used
        // ONLY as a completeness check now (does every BOM material appear
        // somewhere in our PO-derived fabric/accessory lists?). Its RequiredQty
        // is a SCALED TOTAL for whichever order last triggered a BOM Final
        // recalculation for this PLM (one row per PLM in `bom_final_rm_status`
        // — there is no per-order history), so dividing it by cutting qty is
        // only correct when this order happens to be the most recent one to
        // touch that PLM. The real per-pc consumption RATE (not a total, so
        // this staleness risk barely applies to it) comes from D365's
        // `TOC_FinalRMPrices` instead — see $finalRmConsumption below.
        $bomMaterials = $subcon ? $this->bomMaterialsForOrder($subcon) : [];
        $fabricItemNumbers = [];

        // Real per-pc consumption rate, direct from D365 (TOC_FinalRMPrices,
        // TOC_Consumption) — NOT derived by dividing bom_lines' RequiredQty by
        // this order's cutting qty. See D365JobTransactionService::
        // fetchFinalRMConsumption()'s docblock for why this is a materially
        // better source than the RequiredQty division this replaced. Keyed by
        // ItemId, used for the accessory table's Cons. Plan (BOM/pc) column.
        $finalRmConsumption = [];
        if ($subcon && ! empty($subcon->order_number)) {
            try {
                $plmIdsForOrder = DB::connection('vsm')->table('po_lines')
                    ->where('PurchaseOrderNumber', $subcon->order_number)
                    ->whereNotNull('PLMId')->where('PLMId', '!=', '')
                    ->distinct()->pluck('PLMId')->all();
                if (! empty($plmIdsForOrder)) {
                    $finalRmConsumption = app(D365JobTransactionService::class)->fetchFinalRMConsumption($plmIdsForOrder);
                }
            } catch (\Throwable $e) {
                Log::warning('QC report PDF: final RM consumption fetch failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        // Real, already-posted material consumption — VSM `material_issue_lines`
        // (D365's production journal), keyed by ItemNumber, scoped to this
        // order's OWN production runs via ProductionGroup. No division, no
        // cross-PO risk — see materialIssueForOrder()'s docblock. `proposal`
        // and `consumption` both feed the fabric cards (reference cross-checks
        // against the admin-entered Fabric Sent / Actual Cons. — same figures
        // that already prefill the live Final Approval / Report Validation
        // form's Fabric Sent field via fabricLinesWithData()) and the accessory
        // table (its Mats Sent fallback + actual Consumption column, replacing
        // the one this service used to derive from BOM Final).
        $materialIssue = $subcon ? $this->production->materialIssueForOrder((string) $subcon->production_group) : [];

        // Enrich each fabric line with its D365 inventory group ("fabric type"),
        // item number, the RAW VSM-sourced ordered qty (fabricLinesForPo()'s
        // fabric_sent, i.e. the PO's OrderedPurchaseQuantity BEFORE any admin
        // override that may since have changed $f->fabric_sent on the qms row)
        // so over/underdelivery can be compared against what was actually
        // ordered, and the real posted D365 consumption. One batched
        // fabricLinesForPo() call for the whole report, not per row.
        if ($subcon && ! empty($subcon->order_number) && $fabricLines->isNotEmpty()) {
            try {
                $vsmFabricLines = $this->production->fabricLinesForPo((string) $subcon->order_number);
                $vsmByLabel = [];
                foreach ($vsmFabricLines as $vl) {
                    $vsmByLabel[$vl['label']] = $vl;
                    foreach (array_filter(array_map('trim', explode(',', (string) ($vl['item_number'] ?? '')))) as $it) {
                        $fabricItemNumbers[$it] = true;
                    }
                }
                foreach ($fabricLines as $f) {
                    $vl = $vsmByLabel[$f->label] ?? null;
                    $f->inventory_group = $vl['inventory_group'] ?? null;
                    $f->item_number = $vl['item_number'] ?? null;
                    $orderedQty = isset($vl['fabric_sent']) ? (float) $vl['fabric_sent'] : null;
                    $f->ordered_qty = $orderedQty;
                    $goodsReceive = isset($f->goods_receive) ? (float) $f->goods_receive : null;
                    $f->delivery_pct = ($orderedQty !== null && $orderedQty > 0 && $goodsReceive !== null)
                        ? (($goodsReceive - $orderedQty) / $orderedQty) * 100
                        : null;

                    $f->issue_consumption = null;
                    $f->issue_proposal = null;
                    $f->issue_all_posted = true;
                    foreach (array_filter(array_map('trim', explode(',', (string) $f->item_number))) as $it) {
                        if (isset($materialIssue[$it])) {
                            $f->issue_consumption = ($f->issue_consumption ?? 0) + $materialIssue[$it]['consumption'];
                            $f->issue_proposal = ($f->issue_proposal ?? 0) + $materialIssue[$it]['proposal'];
                            $f->issue_all_posted = $f->issue_all_posted && $materialIssue[$it]['all_posted'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('QC report PDF: fabric line enrichment failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        // Material Flow return-attachment pages (Task 2) — one page per file,
        // appended after the report. Best-effort: a wms/S3 miss just skips the
        // whole section (never blocks the rest of the PDF).
        $attachments = collect();
        if ($subcon) {
            try {
                $attachments = app(MaterialReturnService::class)->attachmentsFor($subcon)
                    ->map(function ($att) {
                        $isImage = str_starts_with((string) $att->mime_type, 'image/');
                        $isPdf = $att->mime_type === 'application/pdf';
                        $imageData = null;
                        if ($isImage) {
                            try {
                                $bytes = Storage::disk($att->s3_disk)->get($att->s3_path);
                                if ($bytes !== null && $bytes !== '') {
                                    $imageData = 'data:'.$att->mime_type.';base64,'.base64_encode($bytes);
                                }
                            } catch (\Throwable $e) {
                                Log::warning('QC report PDF: attachment image fetch failed', ['attachment_id' => $att->id, 'error' => $e->getMessage()]);
                            }
                        } elseif ($isPdf) {
                            try {
                                $bytes = Storage::disk($att->s3_disk)->get($att->s3_path);
                                if ($bytes !== null && $bytes !== '') {
                                    $imageData = $this->rasterizePdfFirstPage($bytes);
                                    if ($imageData !== null) {
                                        $isImage = true;
                                    }
                                }
                            } catch (\Throwable $e) {
                                Log::warning('QC report PDF: attachment PDF rasterize failed', ['attachment_id' => $att->id, 'error' => $e->getMessage()]);
                            }
                        }

                        return [
                            'is_image' => $isImage,
                            'image_data' => $imageData,
                            'filename' => $att->original_filename,
                            'note' => $att->note,
                            'role' => $att->uploaded_by_role,
                            'name' => $att->uploaded_by_name,
                            'uploaded_at' => $att->uploaded_at,
                        ];
                    });
            } catch (\Throwable $e) {
                Log::warning('QC report PDF: material return attachments read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        // Accessory reconciliation ("Retur Qty") — the other half of Material
        // Recon (fabric's is $fabricLines above). No deduction formula applies,
        // but the same "compare declared vs. an independently-derived expected
        // figure" cross-check fabric gets does: Expected Retur = Mats Sent −
        // Estimated Consumption (see below). Feeds section 6 (Granular
        // Breakdown). Union of the PO-line-derived list (accessoryLinesForPo)
        // and BOM Final ($bomMaterials, minus fabric item numbers) — BOM is
        // authoritative for completeness: a real order was found where BOM
        // carried an accessory item never surfaced on the PO's own accessory
        // lines at all.
        // `mats_sent`: the admin-entered MaterialReturnLine.qty_sent wins when
        // present; otherwise falls back to D365's own posted ProposalBOMQuantity
        // ($materialIssue) — same "saved value wins, otherwise a real prefill"
        // precedent resolveFabricPricing() already uses for Fabric Price.
        // `consumption` (a raw total quantity) reads $materialIssue's real
        // posted ConsumptionBOMQuantity directly — genuine actual usage,
        // including any unplanned/supplemental draws.
        // `cons_plan_bom` is the REAL per-pc design rate from D365
        // TOC_FinalRMPrices ($finalRmConsumption) — NOT derived by dividing
        // bom_lines' RequiredQty by cutting qty (that approach was replaced:
        // RequiredQty is a scaled total for whichever order last recalculated
        // the BOM, so dividing it was only correct by coincidence; TOC_Consumption
        // is already the per-unit rate, no division needed).
        // `est_consumption` = Σ(rate × that row's own SIZE's real cutting
        // qty) — the plan scaled back up to a total using genuine production
        // output (never D365's own order-qty planning figures — see
        // fetchFinalRMConsumption()'s docblock for why that mattered), and
        // what Expected Retur is actually computed against (NOT the real
        // `consumption` total: real Consumption can include ad-hoc
        // supplemental postings with no Proposal behind them, which made
        // Mats Sent − real Consumption swing arbitrarily/falsely negative).
        // `actual_cons` is still the real $consumption total ÷ that same
        // applicable qty, shown as a rate for reference/comparison only.
        $accessoryBreakdown = [];
        if ($subcon && ! empty($subcon->order_number)) {
            try {
                $accessoryLines = $this->production->accessoryLinesForPo((string) $subcon->order_number);
                $accessoryRecon = app(MaterialReturnService::class)->linesFor($subcon)
                    ->where('item_type', 'accessory')->keyBy('label');

                // Goods Receive: same D365 packing-slip lookup fabric uses
                // (resolveGoodsReceipts), scoped to each accessory's OWN source
                // PO(s) — NOT the subcon/CMT PO, which only ever receives the
                // finished-garment service line, never raw accessory receipts.
                $accGoodsReceive = [];
                try {
                    $accGoodsReceive = $this->production->resolveGoodsReceipts($accessoryLines);
                } catch (\Throwable $e) {
                    Log::warning('QC report PDF: accessory goods receipt fetch failed', ['project' => $projectId, 'error' => $e->getMessage()]);
                }

                $cuttingQtyForAcc = (float) ($totals['cuttingQty'] ?? 0);
                // Real per-size cutting qty (production's actual output, from
                // the QC yield matrix built earlier in this method — see
                // $rows) — used to scale each TOC_FinalRMPrices row's rate
                // back up to a total. Deliberately NOT D365's own
                // TOC_QtyOrder/OrderQty (planning figures): scaling by those
                // made "Mats Sent vs. this scaled-up rate" tautological,
                // since Mats Sent's own fallback (Material Issue's
                // ProposalBOMQuantity) is ALSO computed by D365 against order
                // qty — comparing plan-scaled-by-order-qty against
                // plan-scaled-by-order-qty always cancels to ~0 regardless of
                // what actually happened (confirmed live on real orders).
                // Cutting qty is genuinely independent, real production data,
                // so it's the only basis capable of showing real signal.
                $cuttingQtyBySize = [];
                foreach ($rows as $r) {
                    if (($r['size'] ?? '—') !== '—') {
                        $cuttingQtyBySize[$r['size']] = (float) ($r['cuttingQty'] ?? 0);
                    }
                }

                $seenItemNumbers = [];
                $buildAccRow = function (string $vsmLabel, string $displayLabel, ?string $itemNumberCsv, string $unit, ?float $qty, ?float $matsSentAdmin, ?float $priceAdmin = null, ?float $priceVsm = null) use ($bomMaterials, $finalRmConsumption, $materialIssue, $accGoodsReceive, $cuttingQtyForAcc, $cuttingQtyBySize, &$seenItemNumbers) {
                    $itemNums = array_filter(array_map('trim', explode(',', (string) $itemNumberCsv)));
                    $inBom = false;
                    $consPlanBom = null;
                    $consPlanConsistent = true;
                    $estConsumption = null;
                    $consumption = null;
                    $matsSentD365 = null;
                    $allPosted = true;
                    foreach ($itemNums as $it) {
                        $seenItemNumbers[$it] = true;
                        if (isset($bomMaterials[$it])) {
                            $inBom = true;
                        }
                        if (isset($finalRmConsumption[$it])) {
                            // consumption: real per-pc rate from D365
                            // TOC_FinalRMPrices — a rate, not a total, so
                            // multiple item numbers on one grouped row are
                            // averaged (below), not summed, for DISPLAY.
                            // est_consumption sums rate × THAT row's own
                            // size's REAL cutting qty (whole-order cutting
                            // qty when the row has no size) — never the whole
                            // order's cutting qty for a size-specific row, or
                            // a size-specific item's total comes out inflated
                            // by however much of the order that size doesn't
                            // cover.
                            $consPlanBom = ($consPlanBom ?? 0) + $finalRmConsumption[$it]['consumption'];
                            $consPlanConsistent = $consPlanConsistent && $finalRmConsumption[$it]['consistent'];
                            foreach ($finalRmConsumption[$it]['rows'] as $rmRow) {
                                $qtyBasis = ($rmRow['size'] !== null && isset($cuttingQtyBySize[$rmRow['size']]))
                                    ? $cuttingQtyBySize[$rmRow['size']]
                                    : $cuttingQtyForAcc;
                                $estConsumption = ($estConsumption ?? 0) + $rmRow['consumption'] * $qtyBasis;
                            }
                        }
                        if (isset($materialIssue[$it])) {
                            $consumption = ($consumption ?? 0) + $materialIssue[$it]['consumption'];
                            $matsSentD365 = ($matsSentD365 ?? 0) + $materialIssue[$it]['proposal'];
                            $allPosted = $allPosted && $materialIssue[$it]['all_posted'];
                        }
                    }
                    if ($consPlanBom !== null && count($itemNums) > 1) {
                        $consPlanBom /= count($itemNums);
                    }
                    $goodsReceive = $accGoodsReceive[$vsmLabel]['qty'] ?? null;
                    $matsSent = $matsSentAdmin ?? $matsSentD365;
                    $matsSentSource = $matsSentAdmin !== null ? 'admin' : ($matsSentD365 !== null ? 'd365' : null);
                    // Actual Cons. (pc) must divide by the SAME applicable qty
                    // Cons. Plan's rate is scoped to — derived back out as
                    // est_consumption ÷ cons_plan_bom (e.g. 306 ÷ 1.00 = 306
                    // for a size-S-only label) — NOT the whole order's cutting
                    // qty. Dividing a size-specific item's real consumption by
                    // the whole order's qty produced a misleadingly tiny rate
                    // (e.g. 0.2061/pc) sitting right next to a Cons. Plan rate
                    // of 1.0000/pc for the exact same quantity, even though
                    // the two totals actually agreed exactly. Falls back to
                    // the whole order's cutting qty only when there's no BOM
                    // rate to derive the real scope from.
                    $applicableQty = ($estConsumption !== null && $consPlanBom !== null && $consPlanBom != 0)
                        ? $estConsumption / $consPlanBom
                        : $cuttingQtyForAcc;
                    $actualConsRate = ($consumption !== null && $applicableQty > 0) ? $consumption / $applicableQty : null;

                    // Expected Retur = Mats Sent − Estimated Consumption (the
                    // BOM-plan rate × this material's own applicable qty —
                    // e.g. sent 2,000, plan 1.00/pc, cutting qty 1,800 →
                    // estimated 1,800 needed → expected retur 200). NOT real
                    // Consumption: real posted usage can include unplanned
                    // supplemental draws with no Proposal behind them, which
                    // would make Sent − real Consumption swing on noise
                    // unrelated to whether material is actually left over to
                    // return. When a form's Mats Sent gets submitted with the
                    // D365-suggested prefill left untouched, this correctly
                    // nets to ~0 — that IS the right answer for that data (sent
                    // exactly what the plan called for, nothing more), not a
                    // bug; it only reads as "always zero" on orders where
                    // nobody has yet entered a Mats Sent that actually differs
                    // from the plan. NOT Goods Receive − Consumption either:
                    // Goods Receive is scoped to whatever PO the accessory was
                    // bought on, which is often ONE PO shared across every
                    // colorway of a style (unlike fabric, bought per
                    // colorway) — that batch-wide figure isn't this order's
                    // own share.
                    $expectedRetur = ($matsSent !== null && $estConsumption !== null)
                        ? $matsSent - $estConsumption
                        : null;

                    // Price: same precedence as fabric's fabric_price
                    // (fabricLinesWithData()) — the admin's saved
                    // MaterialReturnLine.unit_price wins, else the VSM-derived
                    // weighted-average reference (accessoryLinesForPo()'s
                    // unit_price). Value (ref) is informational only — there is
                    // no deduction formula for accessories, unlike fabric.
                    $price = $priceAdmin ?? $priceVsm;
                    $value = ($price !== null && $consumption !== null) ? $price * $consumption : null;

                    return [
                        'display_label' => $displayLabel,
                        'item_number' => $itemNumberCsv ?: null,
                        'unit' => $unit ?: 'PCS',
                        'qty' => $qty,
                        'mats_sent' => $matsSent,
                        'mats_sent_source' => $matsSentSource,
                        'goods_receive' => $goodsReceive,
                        'consumption' => $consumption,
                        'consumption_all_posted' => $allPosted,
                        'cons_plan_bom' => $consPlanBom,
                        'cons_plan_consistent' => $consPlanConsistent,
                        'est_consumption' => $estConsumption,
                        'actual_cons' => $actualConsRate,
                        'cutting_qty' => $cuttingQtyForAcc,
                        'expected_retur' => $expectedRetur,
                        'price' => $price,
                        'value' => $value,
                        'from_bom_only' => false,
                        'in_bom' => $inBom,
                    ];
                };

                $accVsmLabels = array_map(fn ($al) => $al['label'], $accessoryLines);
                foreach ($accessoryLines as $al) {
                    $rec = $accessoryRecon->get($al['label']);
                    $accessoryBreakdown[] = $buildAccRow(
                        $al['label'],
                        $al['display_label'] ?? $al['label'],
                        $al['item_number'] ?? null,
                        $al['unit'] ?: 'PCS',
                        $rec ? (float) $rec->qty_declared : null,
                        $rec && isset($rec->qty_sent) ? (float) $rec->qty_sent : null,
                        $rec && $rec->unit_price !== null ? (float) $rec->unit_price : null,
                        $al['unit_price'] ?? null
                    );
                }
                foreach ($accessoryRecon as $rec) {
                    if (! in_array($rec->label, $accVsmLabels, true)) {
                        $accessoryBreakdown[] = $buildAccRow(
                            $rec->label,
                            $rec->label,
                            null,
                            $rec->unit ?: 'PCS',
                            (float) $rec->qty_declared,
                            isset($rec->qty_sent) ? (float) $rec->qty_sent : null,
                            $rec->unit_price !== null ? (float) $rec->unit_price : null
                        );
                    }
                }

                // BOM Final items never covered by a PO recon line at all
                // (fabric item numbers excluded — those live in $fabricLines).
                foreach ($bomMaterials as $itemNum => $bm) {
                    if (isset($fabricItemNumbers[$itemNum]) || isset($seenItemNumbers[$itemNum])) {
                        continue;
                    }
                    $row = $buildAccRow($bm['description'], $bm['description'], $itemNum, $bm['unit'] ?: 'PCS', null, null);
                    $row['from_bom_only'] = true;
                    $accessoryBreakdown[] = $row;
                }
            } catch (\Throwable $e) {
                Log::warning('QC report PDF: accessory reconciliation read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        // Cut Plan per size — same fabric-bottleneck proration as the vendor/admin
        // work order page (production-detail.blade.php): take the SMALLEST
        // cutt_plan across fabrics (the bottleneck, never summed), then give each
        // size its proportional share of order qty. Null until at least one
        // fabric's consumption has actually been entered.
        $cuttPlanTotal = $fabricLines->pluck('cutt_plan')->filter(fn ($v) => $v !== null)->min();
        $totalOrderQtyForCutPlan = $totals['orderQty'];
        foreach ($rows as &$row) {
            $row['cutPlan'] = ($cuttPlanTotal !== null && $totalOrderQtyForCutPlan > 0)
                ? (int) round($row['orderQty'] / $totalOrderQtyForCutPlan * $cuttPlanTotal)
                : null;
        }
        unset($row);
        $totals['cutPlan'] = $cuttPlanTotal !== null ? (int) $cuttPlanTotal : null;

        $deductionLines = collect();
        try {
            $deductionLines = $qms->table('packaging_session_deduction_lines')
                ->where('session_id', $sessionId)->orderBy('created_at')->get();
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: deduction lines read failed', ['error' => $e->getMessage()]);
        }

        // Defect photos: filter strictly by active session to avoid mixing 1st final and 2nd final defect data
        $defectImages = collect();
        try {
            $defectImages = $qms->table('packaging_defect_images')
                ->where('project_id', $projectId)->orderBy('captured_at')->get()
                ->filter(function ($img) use ($sessionId, $sessions) {
                    if (! empty($img->session_id)) {
                        return $img->session_id === $sessionId;
                    }

                    return $sessions->count() <= 1;
                })->values();
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: defect images read failed', ['error' => $e->getMessage()]);
        }

        $remarks = null;
        try {
            $remarks = $qms->table('packaging_project_remarks')
                ->where('production_group', (string) ($project->production_group ?? ''))
                ->value('remarks');
        } catch (\Throwable $e) {
            // staged remarks table is optional
        }

        $logoPath = public_path('images/mp-logo-report.png');

        return [
            'project' => $project,
            'session' => $session,
            'cycleName' => $this->cycleName($activeCycle),
            'rows' => $rows,
            'totals' => $totals,
            'deductions' => $deductions,
            'fabricLines' => $fabricLines,
            'accessoryBreakdown' => $accessoryBreakdown,
            'deductionLines' => $deductionLines,
            'defectImages' => $defectImages,
            'attachments' => $attachments,
            'remarks' => $remarks,
            'logoData' => is_file($logoPath)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
                : null,
            'checklist' => $this->checklistData($session),
            'signatures' => [
                'inspector' => $this->inspectorSignature($session),
                'factory' => $this->factorySignature($session),
                'ho' => $this->parseSignature((string) ($session->ho_approval_signature ?? '')),
                'director' => $this->parseSignature((string) ($session->director_approval_signature ?? '')),
            ],
            'generatedAt' => now('Asia/Jakarta')->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * Penalty deductions for one session — 1:1 port of the console's
     * calculations.ts (`rejectProduksiPenalty` + `barangHilangPenalty`):
     * production reject exceeding the 1%-of-cutting-qty limit, and lost items
     * (barang hilang), both charged at 70% of the garment's sales price.
     * Scoped to this session's own report lines only (not cumulative across
     * cycles) — matches the console's per-session penalty, not the cumulative
     * yield-matrix figures shown elsewhere in the report.
     *
     * $project/$reportLines/$baseLines may be passed in by context() to avoid
     * re-querying what it already loaded; omit them to call this standalone
     * (e.g. from RpaQueueService::deductionTotal()).
     *
     * @return array{penaltyPrice:float,exceedingRejectQty:int,sumRejectProduksi:int,sumCuttingQty:int,sumBarangHilang:int,rejectProduksiPenalty:float,barangHilangPenalty:float}
     */
    public function calculateDeductions(
        string $projectId,
        string $sessionId,
        ?object $project = null,
        ?\Illuminate\Support\Collection $reportLines = null,
        ?\Illuminate\Support\Collection $baseLines = null,
    ): array {
        $empty = [
            'penaltyPrice' => 0.0,
            'exceedingRejectQty' => 0,
            'sumRejectProduksi' => 0,
            'sumCuttingQty' => 0,
            'sumBarangHilang' => 0,
            'rejectProduksiPenalty' => 0.0,
            'barangHilangPenalty' => 0.0,
        ];

        try {
            $qms = DB::connection('qms');
            $project ??= $qms->table('packaging_projects')->where('project_id', $projectId)->first(['sales_price']);
            if (! $project) {
                return $empty;
            }

            $baseLines ??= $qms->table('packaging_project_reports')
                ->where('project_id', $projectId)->whereNull('session_id')->get(['size_val', 'total_good_qty']);
            $reportLines ??= $qms->table('packaging_project_reports')
                ->where('project_id', $projectId)->where('session_id', $sessionId)
                ->get(['size_val', 'reject_cutting', 'reject_sewing', 'reject_finishing', 'reject_printing', 'reject_embro', 'reject_washing', 'barang_hilang']);
        } catch (\Throwable $e) {
            Log::warning('QC report deductions: read failed', ['project' => $projectId, 'session' => $sessionId, 'error' => $e->getMessage()]);

            return $empty;
        }

        $penaltyPrice = (float) ($project->sales_price ?? 0) * 0.70;
        $sumRejProd = 0;
        $sumCutQty = 0;
        $sumBarangHilang = 0;
        foreach ($reportLines as $line) {
            $sumRejProd += (int) ($line->reject_cutting ?? 0) + (int) ($line->reject_sewing ?? 0)
                + (int) ($line->reject_finishing ?? 0) + (int) ($line->reject_printing ?? 0)
                + (int) ($line->reject_embro ?? 0) + (int) ($line->reject_washing ?? 0);
            $baseLine = $baseLines->first(fn ($b) => ($b->size_val ?? null) === ($line->size_val ?? null));
            $sumCutQty += (int) ($baseLine->total_good_qty ?? 0);
            $sumBarangHilang += (int) ($line->barang_hilang ?? 0);
        }
        $allowedLimit = (int) floor($sumCutQty * 0.01);
        $exceedingRejectQty = max(0, $sumRejProd - $allowedLimit);

        return [
            'penaltyPrice' => $penaltyPrice,
            'exceedingRejectQty' => $exceedingRejectQty,
            'sumRejectProduksi' => $sumRejProd,
            'sumCuttingQty' => $sumCutQty,
            'sumBarangHilang' => $sumBarangHilang,
            'rejectProduksiPenalty' => $exceedingRejectQty * $penaltyPrice,
            'barangHilangPenalty' => $sumBarangHilang * $penaltyPrice,
        ];
    }

    public function cycleName(int $cycle): string
    {
        return self::CYCLE_NAMES[$cycle] ?? ('Cycle '.$cycle);
    }

    /**
     * Resolve the subcon order behind a QMS project: project_id →
     * packaging_projects.production_group → subcon_orders. Same resolution as
     * QcApprovalController::subconContext(), duplicated here (rather than
     * threaded through as a parameter) so context() stays a single self-contained
     * entry point for every caller (printDraft(), document(), email jobs, RPA
     * payloads). Best-effort — null on any miss, never throws.
     */
    private function resolveSubconOrder(object $project): ?SubconOrder
    {
        try {
            $productionGroup = $project->production_group ?? null;
            if (! $productionGroup) {
                return null;
            }

            return SubconOrder::where('production_group', $productionGroup)->first();
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: subcon order resolve failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Complete BOM Final material list (fabric + accessory) for the order's PLM
     * style, read straight from VSM `bom_lines` (+ `item_master` for a display
     * name). `RequiredQty` is the BOM's OWN total — verified by cross-checking
     * `po_lines` that the same PLMId is shared by every PO ever cut against
     * this style (18 POs on one sampled style), not just this one order — so it
     * can NOT be safely divided by this order's own qty to get a per-pc figure
     * (that would need this order's share of the shared total, which isn't
     * derivable from local data). Returned as a raw reference total only.
     * Keyed by ItemId (D365 item number) so callers can cross-reference against
     * fabric/accessory lines already resolved by item number elsewhere.
     *
     * @return array<string, array{description: string, unit: ?string, required_qty: float}>
     */
    private function bomMaterialsForOrder(SubconOrder $order): array
    {
        try {
            $plmIds = DB::connection('vsm')->table('po_lines')
                ->where('PurchaseOrderNumber', $order->order_number)
                ->whereNotNull('PLMId')->where('PLMId', '!=', '')
                ->distinct()->pluck('PLMId')->all();
            if (empty($plmIds)) {
                return [];
            }

            $rows = DB::connection('vsm')->table('bom_lines')
                ->whereIn('PLMId', $plmIds)
                ->get(['ItemId', 'RequiredQty', 'Unit']);
            if ($rows->isEmpty()) {
                return [];
            }

            $itemIds = $rows->pluck('ItemId')->unique()->values()->all();
            $names = DB::connection('vsm')->table('item_master')
                ->whereIn('ItemId', $itemIds)->pluck('SearchName', 'ItemId');

            $out = [];
            foreach ($rows as $r) {
                $id = (string) $r->ItemId;
                $out[$id] ??= ['description' => $names[$id] ?? $id, 'unit' => $r->Unit, 'required_qty' => 0.0];
                $out[$id]['required_qty'] += (float) $r->RequiredQty;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('QC report PDF: BOM materials read failed', ['order' => $order->order_number ?? null, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Rasterizes a PDF's first page to a PNG data URI so a material-return
     * delivery-note attachment can be embedded in the report the same way an
     * image attachment already is (DomPDF only lays out HTML/images, it
     * cannot embed another PDF's pages as-is). Tries the Imagick extension
     * first (its PDF delegate shells out to Ghostscript), then falls back to
     * calling Ghostscript directly if Imagick isn't compiled in. Best-effort:
     * returns null on any failure (missing Imagick/Ghostscript, corrupt PDF,
     * encrypted file, ...) and the caller shows the "open the original file"
     * placeholder instead — never blocks the rest of the report.
     */
    private function rasterizePdfFirstPage(string $pdfBytes): ?string
    {
        $tmpBase = sys_get_temp_dir().'/qcret_'.bin2hex(random_bytes(8));
        $tmpPdf = $tmpBase.'.pdf';
        $tmpPng = $tmpBase.'.png';

        try {
            file_put_contents($tmpPdf, $pdfBytes);

            if (extension_loaded('imagick')) {
                try {
                    $imagick = new \Imagick();
                    $imagick->setResolution(150, 150);
                    $imagick->readImage($tmpPdf.'[0]');
                    $imagick->setImageFormat('png');
                    $imagick->setImageBackgroundColor('white');
                    $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    $blob = $imagick->getImageBlob();
                    $imagick->clear();
                    if ($blob !== '') {
                        return 'data:image/png;base64,'.base64_encode($blob);
                    }
                } catch (\Throwable $e) {
                    Log::warning('QC report PDF: Imagick PDF rasterize failed, trying Ghostscript', ['error' => $e->getMessage()]);
                }
            }

            $gsBinary = trim((string) shell_exec('which gs 2>/dev/null'));
            if ($gsBinary !== '') {
                $cmd = escapeshellarg($gsBinary)
                    .' -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r150'
                    .' -dFirstPage=1 -dLastPage=1'
                    .' -sOutputFile='.escapeshellarg($tmpPng).' '.escapeshellarg($tmpPdf)
                    .' 2>&1';
                shell_exec($cmd);
                if (is_file($tmpPng) && filesize($tmpPng) > 0) {
                    return 'data:image/png;base64,'.base64_encode(file_get_contents($tmpPng));
                }
            }

            return null;
        } finally {
            @unlink($tmpPdf);
            @unlink($tmpPng);
        }
    }

    /** @return list<array{label:string,checked:bool}> checked checklist items */
    private function checklistData(object $session): array
    {
        $fields = [
            'check_wash' => 'Washing',
            'check_style_as_sample' => 'Style as Sample',
            'check_main_label' => 'Main Label',
            'check_flag_fit_label' => 'Flag/Fit Label',
            'check_print_embro_artwork' => 'Print/Embro Artwork',
            'check_hangtag' => 'Hangtag',
            'check_waist_tag' => 'Waist Tag',
            'check_barcode' => 'Barcode',
            'check_packing_list' => 'Packing List',
            'check_shipping_mark' => 'Shipping Mark',
        ];
        $out = [];
        foreach ($fields as $field => $label) {
            if (! empty($session->{$field})) {
                $out[] = [
                    'label' => $label,
                    'checked' => true,
                ];
            }
        }
        foreach ([1, 2] as $n) {
            $label = trim((string) ($session->{'check_other_'.$n.'_label'} ?? ''));
            if ($label !== '' && ! empty($session->{'check_other_'.$n})) {
                $out[] = [
                    'label' => $label,
                    'checked' => true,
                ];
            }
        }

        return $out;
    }

    /** @return array{state:string,name:string,date:string,role:string} */
    private function inspectorSignature(object $session): array
    {
        $ts = $session->ended_at ?? $session->started_at ?? null;

        return [
            'state' => 'signed',
            'name' => (string) ($session->inspector ?? ''),
            'email' => trim((string) ($session->inspector_email ?? '')),
            'date' => $ts ? \Carbon\Carbon::parse($ts)->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') : '',
            'role' => 'Inspector',
        ];
    }

    /** @return array{state:string,name:string,email:string,date:string,role:string} */
    private function factorySignature(object $session): array
    {
        // Identity split: the NAME is what the inspector typed in the console
        // (factory_representative); the EMAIL is the approval recipient — the
        // stamp shows the email, the footer shows the name over the role.
        $email = trim((string) ($session->approval_email ?? ''));
        $name = trim((string) ($session->factory_representative ?? ''))
            ?: (string) ($session->approved_by ?? $email);
        $date = ! empty($session->approved_at)
            ? \Carbon\Carbon::parse($session->approved_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i:s')
            : '';

        if (($session->approval_status ?? null) === 'approved') {
            return ['state' => 'signed', 'name' => $name ?: 'Representative', 'email' => $email, 'date' => $date, 'role' => 'Factory Representative'];
        }
        if (($session->approval_status ?? null) === 'rejected') {
            return ['state' => 'rejected', 'name' => $name ?: 'Representative', 'email' => $email, 'date' => $date, 'role' => 'Factory Representative'];
        }
        $parsed = $this->parseSignature((string) ($session->approval_signature ?? ''));
        $parsed['name'] = trim((string) ($session->factory_representative ?? '')) ?: $parsed['name'];
        $parsed['email'] = $parsed['email'] !== '' ? $parsed['email'] : $email;
        $parsed['role'] = 'Factory Representative';

        return $parsed;
    }

    /**
     * Same prefix contract the console parses: 'Digitally Signed: <name>
     * [UTC+07:00: <ts>]' / 'Rejected: …'. The name part may carry the actor's
     * address as 'Name <email>' (attributed approvals) — split it out so the
     * template can render the email on the stamp and the name over the role.
     *
     * @return array{state:string,name:string,email:string,date:string,role:string}
     */
    public function parseSignature(string $sig): array
    {
        $out = ['state' => 'pending', 'name' => '', 'email' => '', 'date' => '', 'role' => ''];
        if (trim($sig) === '') {
            return $out;
        }

        foreach (['Digitally Signed:' => 'signed', 'Rejected:' => 'rejected'] as $prefix => $state) {
            if (str_contains($sig, $prefix)) {
                $out['state'] = $state;
                $rest = trim(substr($sig, strpos($sig, $prefix) + strlen($prefix)));
                if (preg_match('/^(.*?)\s*\[UTC\+07:00:\s*([^\]]+)\]/', $rest, $m)) {
                    $out['name'] = trim($m[1]);
                    // Embedded raw as 'Y-m-d H:i:s' — reformat to the same
                    // 'd-m-Y H:i:s' the inspector/factory boxes use so all
                    // signature dates render in one consistent format.
                    try {
                        $out['date'] = \Carbon\Carbon::parse(trim($m[2]))->format('d-m-Y H:i:s');
                    } catch (\Throwable $e) {
                        $out['date'] = trim($m[2]);
                    }
                } else {
                    $out['name'] = $rest;
                }
                if (preg_match('/^(.*?)\s*<([^>]+)>$/', $out['name'], $mm)) {
                    $out['name'] = trim($mm[1]);
                    $out['email'] = trim($mm[2]);
                }
                if ($out['name'] === '' && $out['email'] !== '') {
                    $out['name'] = $out['email'];
                }

                return $out;
            }
        }
        $out['name'] = $sig;

        return $out;
    }

    /** Numeric sizes first (ascending), then the fixed S–6XL order, then alpha. */
    private function sizeSortKey(string $size): array
    {
        $clean = strtoupper(trim($size));
        if (is_numeric($clean)) {
            return [0, (float) $clean, $clean];
        }
        $idx = array_search($clean, self::SIZE_ORDER, true);
        if ($idx !== false) {
            return [1, $idx, $clean];
        }

        return [2, 0, $clean];
    }
}
