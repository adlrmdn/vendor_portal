<?php

namespace App\Http\Controllers;

use App\Models\SubconCuttingPlanBlock;
use App\Models\SubconOrder;
use App\Services\SubconCuttingPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubconCuttingPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! in_array(Auth::user()->role, ['admin', 'subcon_admin'])) {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function show(string $id, SubconCuttingPlanService $service)
    {
        $order = SubconOrder::with('vendor')->findOrFail($id);

        $orderQtyBySize = $service->sizesForOrder($order);
        $fabricLines = $service->fabricLinesByLabel($order);

        $blocks = SubconCuttingPlanBlock::where('order_id', $order->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubconCuttingPlanBlock $block) use ($service, $orderQtyBySize, $fabricLines) {
                $computed = $service->computeBlock($block->toArray(), $orderQtyBySize, $fabricLines);

                return ['block' => $block, 'computed' => $computed];
            });

        return view('subcon.admin.cutting-plan', [
            'order' => $order,
            'orderQtyBySize' => $orderQtyBySize,
            'fabricLines' => $fabricLines,
            'blocks' => $blocks,
        ]);
    }

    public function save(Request $request, string $id)
    {
        $order = SubconOrder::findOrFail($id);

        $data = $request->validate([
            'blocks' => 'nullable|array',
            'blocks.*.marker_type' => 'required|string|max:255',
            'blocks.*.cutt_width' => 'nullable|string|max:255',
            'blocks.*.full_width' => 'nullable|string|max:255',
            'blocks.*.gsm' => 'nullable|string|max:255',
            'blocks.*.plan_date' => 'nullable|date',
            'blocks.*.tolerance_pct' => 'nullable|numeric|min:0',
            'blocks.*.kg_per_pc' => 'nullable|numeric|min:0',
            'blocks.*.fabric_available_override' => 'nullable|numeric|min:0',
            'blocks.*.notes' => 'nullable|string',
            'blocks.*.groups' => 'nullable|array',
            'blocks.*.groups.*.jml_layer' => 'nullable|numeric|min:0',
            'blocks.*.groups.*.marker_length' => 'nullable|numeric|min:0',
            'blocks.*.groups.*.rasio' => 'nullable|array',
            'blocks.*.groups.*.rasio.*' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($order, $data) {
            // Whole-document replace: this page is edited as one document
            // (like the source Excel file), not incrementally by other
            // systems, so there's no need to track stable per-block identity.
            SubconCuttingPlanBlock::where('order_id', $order->id)->delete();

            foreach (($data['blocks'] ?? []) as $i => $b) {
                $groups = array_values(array_map(function ($g) {
                    return [
                        'jml_layer' => isset($g['jml_layer']) ? (float) $g['jml_layer'] : 0,
                        'marker_length' => isset($g['marker_length']) ? (float) $g['marker_length'] : 0,
                        'rasio' => array_map('floatval', $g['rasio'] ?? []),
                    ];
                }, $b['groups'] ?? []));

                SubconCuttingPlanBlock::create([
                    'order_id' => $order->id,
                    'marker_type' => $b['marker_type'],
                    'cutt_width' => $b['cutt_width'] ?? null,
                    'full_width' => $b['full_width'] ?? null,
                    'gsm' => $b['gsm'] ?? null,
                    'plan_date' => $b['plan_date'] ?? null,
                    'tolerance_pct' => $b['tolerance_pct'] ?? 0,
                    'kg_per_pc' => $b['kg_per_pc'] ?? null,
                    'fabric_available_override' => $b['fabric_available_override'] ?? null,
                    'notes' => $b['notes'] ?? null,
                    'groups' => $groups,
                    'sort_order' => $i,
                ]);
            }
        });

        return back()->with('success', 'Cutting plan saved.');
    }
}
