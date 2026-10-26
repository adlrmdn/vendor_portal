<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSubconAddProcessToD365;
use App\Models\SubconAddProcessReport;
use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Services\D365JobTransactionService;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Add Process (ADD-Was/ADD-Embro/ADD-Sab/ADD-Pleat/...) tracking, independent
 * of the CMT-only Work Orders flow — a production group is reported on
 * directly by its D365 ProductionGroup code, whether or not it belongs to a
 * subcon (CMT) work order at all (in-house production groups carry the same
 * ADD-* route steps but never get a SubconOrder row). Each operation on a
 * production group is its own independent report/submit — a PRG with two
 * ADD-* steps (e.g. ADD-Was and ADD-Embro) is reported and synced to D365 as
 * two entirely separate actions, not one combined form.
 */
class SubconAddProcessController extends Controller
{
    // Access is enforced by the 'auth' + 'role:admin,subcon_admin' middleware
    // on the /subcon/admin route group (routes/web.php).

    public function index(Request $request, SubconProductionService $production, D365JobTransactionService $d365)
    {
        $prg = trim((string) $request->query('prg', ''));

        $cmtOrder = null;
        $addProcessPos = [];
        $productionGroups = [];
        $summary = ['style' => null, 'size_count' => 0, 'total_qty' => 0.0];
        $steps = [];
        // Every ADD-* step D365's route has for this group, before the
        // confirmed-PO gate below — kept separately so the "nothing here"
        // message can say *why* (no step at all vs. step exists but no
        // confirmed PO yet) instead of one generic empty state.
        $allOperations = [];
        $operations = [];
        // Each operation's own AddPro PO (or null) — a group can carry more
        // than one ADD-* step with a separate commercial PO each, so this
        // can't just be "the group's PO(s)" shown once; see
        // SubconProductionService::matchAddProcessPoForOperation()'s docblock.
        $operationPos = [];
        $existingReports = collect();
        $cutQuantities = [];
        $liveAddProcessQty = [];

        if ($prg !== '') {
            // Excludes an Add Process PO flagged onto subcon_orders itself
            // (see SubconOrder::isAddProcess()'s docblock) — this group can
            // have one of those alongside its real CMT order, and only the
            // real one belongs in the "CMT (Subcontracted)" section below.
            $cmtOrder = SubconOrder::with('vendor')->where('production_group', $prg)
                ->whereNull('add_process_operation')
                ->first();
            // Already Confirmed-only (see addProcessPurchaseOrdersForGroup()'s
            // docblock) — a Draft/InReview/Rejected PO simply won't appear
            // here, which is what drives the filter below.
            $addProcessPos = $production->addProcessPurchaseOrdersForGroup($prg);
            $productionGroups = $production->forProductionGroup($prg);
            $summary = $production->summarize($productionGroups);
            $steps = $production->addProcessStepsForGroup($prg);
            $allOperations = array_keys($steps);
            foreach ($allOperations as $operation) {
                $operationPos[$operation] = $production->matchAddProcessPoForOperation($addProcessPos, $operation);
            }
            // Nothing to report on until D365 has a confirmed commercial PO
            // for THIS operation — mirrors SubconAdminController::
            // addProcessOrdersList()'s same rule for the Work Orders list.
            $operations = array_values(array_filter($allOperations, fn ($op) => $operationPos[$op] !== null));
            $existingReports = SubconAddProcessReport::where('production_group', $prg)
                ->get()
                ->groupBy('operation');
            $cutQuantities = $production->cutQuantitiesForGroup($prg);

            // Live D365 Good/Reject qty per operation — confirmed live that
            // the factory's own ADD-* job transaction in D365 can already
            // carry real TotalGoodQty/TotalRejectQty well before anyone has
            // typed anything into this page's local form (D365's shop-floor
            // reporting is independent of this portal).
            //
            // computeYield()/computeDeduction() (used by the Official Report
            // send) read ONLY the local subcon_add_process_reports table, with
            // no D365 fallback of their own — so merely *displaying* the D365
            // prefill isn't enough: Send Official Report would silently
            // compute a zero good/reject qty (and so a zero deduction) until
            // someone clicks Submit. Since the values already match D365
            // exactly, that submit click saves nothing new and pushes back
            // numbers D365 already has — pure busywork. So: auto-persist the
            // D365 prefill into the local table here (never touching a prod_id
            // that already has its own saved row — a real local edit always
            // wins), WITHOUT dispatching SyncSubconAddProcessToD365 (nothing
            // changed from D365's point of view, there's nothing to push).
            // This genuinely is the one safe place for a GET to write: it's
            // idempotent (a second load finds the row already there and skips
            // it) and it only ever materialises what D365 itself already
            // reports, never anything the admin hasn't effectively already
            // confirmed by way of it being real shop-floor data.
            $lines = collect($productionGroups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
            foreach ($operations as $operation) {
                $liveAddProcessQty[$operation] = $d365->fetchAddProcessQuantities($prg, $operation);
                if (empty($liveAddProcessQty[$operation])) {
                    continue;
                }

                $existingForOp = $existingReports->get($operation, collect())->keyBy('prod_id');
                $autoFilled = false;
                foreach ($lines as $line) {
                    if ($existingForOp->has($line->ProdId)) {
                        continue;
                    }
                    $liveQty = $liveAddProcessQty[$operation][$line->Size] ?? null;
                    $good = (int) ($liveQty['good'] ?? 0);
                    $reject = (int) ($liveQty['reject'] ?? 0);
                    if ($good <= 0 && $reject <= 0) {
                        continue;
                    }
                    SubconAddProcessReport::updateOrCreate(
                        ['production_group' => $prg, 'prod_id' => $line->ProdId, 'operation' => $operation],
                        ['size' => $line->Size, 'qty' => $good, 'reject_qty' => $reject]
                    );
                    $autoFilled = true;
                }
                if ($autoFilled) {
                    // Refresh so $opReports (built from $existingReports in the
                    // view) sees the rows just created — otherwise this request
                    // would still render them as the D365-prefill fallback.
                    $existingReports = SubconAddProcessReport::where('production_group', $prg)
                        ->get()
                        ->groupBy('operation');
                }
            }

            // cutQuantitiesForGroup() only has data once D365's own CMT-Cut job
            // transaction is started/confirmed on the shop floor — that can lag
            // well behind the vendor's own cutting-report submission in the
            // portal (confirmed live: group MPG/PRG/2607/000255's CMT-Cut job
            // was still IsStarted=No while the vendor's local cutting report
            // already had real qty for every size). Fall back to the CMT
            // order's own subcon_cutting_reports for any size D365 doesn't
            // have yet, so "Cut Qty" isn't a blank dash when the vendor has
            // genuinely already reported it — just not yet synced to D365.
            if ($cmtOrder) {
                $localCutQuantities = SubconCuttingReport::where('order_id', $cmtOrder->id)
                    ->get()
                    ->keyBy('size')
                    ->map(fn ($r) => ['good' => (float) $r->cutting_qty, 'reject' => 0.0, 'source' => 'local'])
                    ->all();
                $cutQuantities = $cutQuantities + $localCutQuantities;
            }
        }

        $officialReports = [];
        $officialReportGates = [];
        if ($prg !== '') {
            $officialReportsService = app(\App\Services\SubconOfficialReportService::class);
            $officialReports = \App\Models\SubconOfficialReport::where('production_group', $prg)
                ->get()->keyBy('operation');
            foreach ($allOperations as $operation) {
                if (isset($officialReports[$operation])) {
                    continue; // the view never shows the gate result once a report exists
                }
                $officialReportGates[$operation] = $officialReportsService->gate($prg, $operation);
            }
        }

        return view('subcon.admin.add-process.index', compact('prg', 'cmtOrder', 'addProcessPos', 'productionGroups', 'summary', 'steps', 'allOperations', 'operations', 'operationPos', 'existingReports', 'cutQuantities', 'liveAddProcessQty', 'officialReports', 'officialReportGates'));
    }

    public function submit(Request $request, SubconProductionService $production)
    {
        $data = $request->validate([
            'production_group' => 'required|string',
            'operation' => 'required|string',
            'reports' => 'required|array',
            'reports.*.prod_id' => 'required|string',
            'reports.*.size' => 'required|string',
            'reports.*.qty' => 'nullable|integer|min:0',
            'reports.*.reject_qty' => 'nullable|integer|min:0',
        ]);

        $productionGroup = $data['production_group'];
        $operation = $data['operation'];

        // Mirrors the view's skip-when-unconfirmed/skip-when-invoiced rules
        // (see index()/the blade) — re-checked here since neither operation's
        // form is rendered in those states, but the route itself has no
        // other guard against a direct POST.
        $matchedPo = $production->matchAddProcessPoForOperation(
            $production->addProcessPurchaseOrdersForGroup($productionGroup),
            $operation
        );
        if (! $matchedPo) {
            return redirect()->route('subcon.admin.add-process.index', ['prg' => $productionGroup])
                ->with('error', "{$operation} has no confirmed purchase order yet — reporting isn't available until D365 confirms one.");
        }
        if (strcasecmp($matchedPo['status'] ?? '', 'Invoiced') === 0) {
            return redirect()->route('subcon.admin.add-process.index', ['prg' => $productionGroup])
                ->with('error', "{$operation} already has an invoiced purchase order ({$matchedPo['po_number']}) — reporting is closed.");
        }

        $changed = [];

        DB::transaction(function () use ($productionGroup, $operation, $data, &$changed) {
            foreach ($data['reports'] as $row) {
                $qty = (int) ($row['qty'] ?? 0);
                $rejectQty = (int) ($row['reject_qty'] ?? 0);

                $report = SubconAddProcessReport::updateOrCreate(
                    ['production_group' => $productionGroup, 'prod_id' => $row['prod_id'], 'operation' => $operation],
                    ['size' => $row['size'], 'qty' => $qty, 'reject_qty' => $rejectQty]
                );

                if (($report->wasChanged(['qty', 'reject_qty']) || $report->wasRecentlyCreated) && ($qty > 0 || $rejectQty > 0)) {
                    $changed[] = ['size' => $row['size'], 'qty' => $qty, 'reject_qty' => $rejectQty];
                }
            }
        });

        $message = empty($changed)
            ? "Saved — no new {$operation} quantities to sync to D365."
            : "Saved — D365 sync queued for {$operation}.";

        if (! empty($changed)) {
            SyncSubconAddProcessToD365::dispatch($productionGroup, $operation, $changed);
        }

        return redirect()->route('subcon.admin.add-process.index', ['prg' => $productionGroup])->with('success', $message);
    }
}
