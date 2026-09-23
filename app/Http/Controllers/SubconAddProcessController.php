<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSubconAddProcessToD365;
use App\Models\SubconAddProcessReport;
use App\Models\SubconOrder;
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

    public function index(Request $request, SubconProductionService $production)
    {
        $prg = trim((string) $request->query('prg', ''));

        $cmtOrder = null;
        $addProcessPos = [];
        $productionGroups = [];
        $operations = [];
        $existingReports = collect();
        $cutQuantities = [];

        if ($prg !== '') {
            $cmtOrder = SubconOrder::with('vendor')->where('production_group', $prg)->first();
            $addProcessPos = $production->addProcessPurchaseOrdersForGroup($prg);
            $productionGroups = $production->forProductionGroup($prg);
            $operations = $production->addProcessOperationsForGroup($prg);
            $existingReports = SubconAddProcessReport::where('production_group', $prg)
                ->get()
                ->groupBy('operation');
            $cutQuantities = $production->cutQuantitiesForGroup($prg);
        }

        $officialReports = [];
        $officialReportGates = [];
        if ($prg !== '') {
            $officialReportsService = app(\App\Services\SubconOfficialReportService::class);
            $officialReports = \App\Models\SubconOfficialReport::where('production_group', $prg)
                ->get()->keyBy('operation');
            foreach ($operations as $operation) {
                if (isset($officialReports[$operation])) {
                    continue; // the view never shows the gate result once a report exists
                }
                $officialReportGates[$operation] = $officialReportsService->gate($prg, $operation);
            }
        }

        return view('subcon.admin.add-process.index', compact('prg', 'cmtOrder', 'addProcessPos', 'productionGroups', 'operations', 'existingReports', 'cutQuantities', 'officialReports', 'officialReportGates'));
    }

    public function submit(Request $request)
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
