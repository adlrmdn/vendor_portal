{{--
    One marker/fabric-type block of a cutting plan. Used both for the
    server-rendered initial blocks and (with $block/$computed = null) as the
    JS "add block" template — see resources/views/subcon/admin/cutting-plan.blade.php.
    Params: $bi (index or '__BI__'), $block (SubconCuttingPlanBlock|null),
            $computed (array|null), $sizes (array of size codes)
--}}
@php
    $n = fn ($v) => number_format((float) ($v ?? 0));
    $n4 = fn ($v) => number_format((float) ($v ?? 0), 4);
    $groups = $block?->groups ?? [];
    $computedGroups = $computed['groups'] ?? [];
    $totals = $computed['totals'] ?? null;
    $estQtyCuttPlan = $computed['estQtyCuttPlan'] ?? null;
    $fabricAvailableYds = $computed['fabricAvailableYds'] ?? null;
    $fabricAvailableSource = $computed['fabricAvailableSource'] ?? null;
@endphp
<div class="card mb-4 plan-block" data-block-index="{{ $bi }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="row g-2 flex-grow-1">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Marker Type / Fabric</label>
                    <input type="text" list="fabric-labels" class="form-control form-control-sm block-marker-type"
                           name="blocks[{{ $bi }}][marker_type]" value="{{ $block->marker_type ?? '' }}" placeholder="e.g. SHELL" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Cutt Width</label>
                    <input type="text" class="form-control form-control-sm" name="blocks[{{ $bi }}][cutt_width]" value="{{ $block->cutt_width ?? '' }}" placeholder='60"'>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Full Width</label>
                    <input type="text" class="form-control form-control-sm" name="blocks[{{ $bi }}][full_width]" value="{{ $block->full_width ?? '' }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted mb-1">GSM</label>
                    <input type="text" class="form-control form-control-sm" name="blocks[{{ $bi }}][gsm]" value="{{ $block->gsm ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Date</label>
                    <input type="date" class="form-control form-control-sm" name="blocks[{{ $bi }}][plan_date]" value="{{ $block?->plan_date?->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">+TOL %</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm block-tolerance"
                           name="blocks[{{ $bi }}][tolerance_pct]" value="{{ $block->tolerance_pct ?? 0 }}">
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm remove-block ms-2" title="Remove block"><i class="fas fa-trash"></i></button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-1 plan-groups-table" style="font-size:.8rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">Group</th>
                        @foreach($sizes as $size)<th class="text-end" style="width:64px;">{{ $size }}</th>@endforeach
                        <th class="text-end" style="width:64px;">Total</th>
                        <th style="width:90px;">Jml Layer</th>
                        <th style="width:110px;">Marker Len (yds)</th>
                        <th class="text-end" style="width:90px;">Keb Fabric</th>
                        <th style="width:36px;"></th>
                    </tr>
                </thead>
                <tbody class="plan-groups-body">
                    @foreach($groups as $gi => $group)
                        @include('subcon.partials.cutting-plan-group-row', ['bi' => $bi, 'gi' => $gi, 'group' => $group, 'computedGroup' => $computedGroups[$gi] ?? null, 'sizes' => $sizes])
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm add-group mb-3">
            <i class="fas fa-plus me-1"></i> Add Ratio Group
        </button>

        <div class="row g-3 small mb-3">
            <div class="col-auto"><span class="text-muted">Total Qty:</span> <strong class="block-total-qty">{{ $n($totals['total_qty'] ?? 0) }}</strong> pcs</div>
            <div class="col-auto"><span class="text-muted">Total Fabric:</span> <strong class="block-total-fabric">{{ $n($totals['total_fabric_yds'] ?? 0) }}</strong> yds</div>
            <div class="col-auto"><span class="text-muted">Avg Cons:</span> <strong class="block-avg-cons">{{ $n4($totals['avg_cons_yds_per_pc'] ?? 0) }}</strong> yds/pc</div>
            <div class="col-auto"><span class="text-muted">+TOL Fabric:</span> <strong class="block-tol-fabric">{{ $n($totals['tol_adjusted_yds'] ?? 0) }}</strong> yds</div>
        </div>

        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">kg/pc (optional)</label>
                <input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="blocks[{{ $bi }}][kg_per_pc]" value="{{ $block->kg_per_pc ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Fabric Available override (yds)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm block-fabric-override"
                       name="blocks[{{ $bi }}][fabric_available_override]" value="{{ $block->fabric_available_override ?? '' }}">
                <div class="form-text block-fabric-hint">
                    @if($fabricAvailableYds !== null)
                        Auto: {{ $n($fabricAvailableYds) }} yds ({{ str_replace('_', ' ', $fabricAvailableSource) }}) — leave blank to use it
                    @else
                        No linked fabric-available figure yet for this marker type — enter one above, or leave blank.
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Est. Qty Cutt Plan</div>
                <div class="fw-bold block-est-qty">{{ $estQtyCuttPlan !== null ? $n($estQtyCuttPlan).' pcs' : '—' }}</div>
            </div>
        </div>

        <div class="mt-3">
            <label class="form-label small text-muted mb-1">Notes</label>
            <textarea class="form-control form-control-sm" name="blocks[{{ $bi }}][notes]" rows="2">{{ $block->notes ?? '' }}</textarea>
        </div>
    </div>
</div>
