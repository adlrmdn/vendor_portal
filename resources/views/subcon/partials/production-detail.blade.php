{{--
    Garment production detail for a subcon CMT PO.
    Expects: $productionGroups (array from App\Services\SubconProductionService::forPo)
    Read-only enrichment — renders nothing intrusive when the PO has no PLM link.
--}}
@php
    $cuttingReports = $cuttingReports ?? collect();
    // Editing mode for the size table: 'cutting' (qty editable), 'gramasi'
    // (gramasi editable), or 'view' (read-only). Defaults to read-only.
    $mode = $mode ?? 'view';
    $editCutting = $mode === 'cutting';
    $editGramasi = $mode === 'gramasi';
    $prodBadge = fn ($s) => match ($s) {
        'Completed', 'ReportedFinished' => 'success',
        'StartedUp'                     => 'warning',
        'Released'                      => 'info',
        'CostEstimated', 'Created'      => 'secondary',
        default                         => 'secondary',
    };
    // Cut Plan total: the fabric-bottleneck cutt_plan ((fabric_sent - retur_kain) /
    // consumption_plan, per SubconConsumptionService) across the order's fabric
    // lines. A garment needs
    // ALL its fabrics, so the achievable plan is capped by the scarcest one — take the
    // smallest cutt_plan, never sum them. Null (not zero) until at least one fabric's
    // consumption has been entered at cutting approval, so the column stays "—" instead
    // of showing a misleading 0.
    $cuttPlanTotal = collect($fabricLines ?? [])->pluck('cutt_plan')->filter(fn ($v) => $v !== null)->min();
@endphp

<style>
    .subcon-order-card {
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
    }
    .specs-badge {
        font-family: var(--bs-font-monospace);
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        color: #495057;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }
    .modern-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    .modern-table thead {
        background-color: #f8f9fa;
    }
    .modern-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #495057;
        font-weight: 700;
        border-bottom: 2px solid #dee2e6;
        padding: 14px 16px !important;
    }
    .modern-table td {
        padding: 14px 16px !important;
        border-bottom: 1px solid #dee2e6;
        color: #212529;
    }
    .modern-table tbody tr:nth-of-type(even) {
        background-color: #f8f9fa;
    }
    .modern-table tbody tr:hover {
        background-color: #f1f3f5;
    }
    .size-pill {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #e9ecef;
        color: #212529;
        font-weight: 700;
        font-size: 0.85rem;
        border: 1px solid #ced4da;
    }
    .modern-input {
        background-color: #ffffff !important;
        border: 1px solid #ced4da !important;
        border-radius: 6px !important;
        padding: 0.4rem 0.6rem !important;
        font-size: 0.85rem !important;
        color: #212529 !important;
        transition: all 0.15s ease-in-out;
        text-align: right;
        font-weight: 500;
    }
    .modern-input:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15) !important;
        outline: 0;
    }
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
    }
    .status-indicator::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .status-indicator-warning {
        background: #fff9db;
        color: #f08c00;
    }
    .status-indicator-warning::before {
        background: #f08c00;
    }
    .status-indicator-success {
        background: #ebfbee;
        color: #2b8a3e;
    }
    .status-indicator-success::before {
        background: #2b8a3e;
    }
    .status-indicator-info {
        background: #e7f5ff;
        color: #1c7ed6;
    }
    .status-indicator-info::before {
        background: #1c7ed6;
    }
    .status-indicator-secondary {
        background: #f1f3f5;
        color: #868e96;
    }
    .status-indicator-secondary::before {
        background: #868e96;
    }
</style>

@forelse($productionGroups as $g)
    @php
        $totalQty = collect($g['lines'])->sum('Qty');
        $totalCuttingQty = collect($g['lines'])->sum(fn($line) => $cuttingReports->get($line->ProdId)?->cutting_qty ?? 0);
        // Balance = Qty Cut − Cut Plan once Cut Plan is available, else Qty Cut − Order Qty.
        // Under-cut is negative, over-cut positive.
        $totalBalanceBase = $cuttPlanTotal !== null ? $cuttPlanTotal : $totalQty;
        $totalBalance = $totalCuttingQty - $totalBalanceBase;
        $anyCut = $totalCuttingQty > 0;
        $totalBalClass = ! $anyCut ? 'text-muted' : ($totalBalance < 0 ? 'text-danger' : 'text-success');
        $pcsSuffix = ' <span class="text-muted small fw-normal">pcs</span>';
        $totalBalText = ! $anyCut ? '—' : ($totalBalance > 0 ? '+'.number_format($totalBalance) : number_format($totalBalance)).$pcsSuffix;
    @endphp
    <div class="subcon-order-card mb-4">
        <!-- Sizing Table Area -->
        <div class="p-0 bg-white">
            @if(!empty($g['lines']))
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 modern-table">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 90px;">Size</th>
                                <th style="width: 150px;">PRD ID</th>
                                <th style="width: 150px;">Status</th>
                                <th class="text-end" style="width: 120px;">Order Qty</th>
                                <th class="text-end" style="width: 110px;">Cut Plan</th>
                                <th class="text-end" style="width: 120px;">Cut Qty</th>
                                <th class="text-end" style="width: 110px;">Balance</th>
                                <th class="text-end pe-4" style="width: 130px;">Gramasi (g)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($g['lines'] as $line)
                                @php
                                    $orderQty = (int) $line->Qty;
                                    $cutQty = $cuttingReports->get($line->ProdId)?->cutting_qty;
                                    $hasCut = $cutQty !== null;
                                    // Size's share of the plan, prorated by its share of the order qty:
                                    // orderQty / totalOrderQty * cuttPlanTotal.
                                    $cutPlanQty = ($cuttPlanTotal !== null && $totalQty > 0)
                                        ? (int) round($orderQty / $totalQty * $cuttPlanTotal)
                                        : null;
                                    // Balance measures against Cut Plan once it's available (the real
                                    // ceiling for this size); falls back to Order Qty until then.
                                    $balanceBase = $cutPlanQty !== null ? $cutPlanQty : $orderQty;
                                    $rowBalance = (int) ($cutQty ?? 0) - $balanceBase;
                                    $balClass = ! $hasCut ? 'text-muted' : ($rowBalance < 0 ? 'text-danger' : 'text-success');
                                    $balText = ! $hasCut ? '—' : ($rowBalance > 0 ? '+'.number_format($rowBalance) : number_format($rowBalance)).$pcsSuffix;
                                @endphp
                                <tr>
                                    @if($editCutting || $editGramasi)
                                        <input type="hidden" name="reports[{{ $loop->parent->index }}_{{ $loop->index }}][prod_id]" value="{{ $line->ProdId }}">
                                        <input type="hidden" name="reports[{{ $loop->parent->index }}_{{ $loop->index }}][size]" value="{{ $line->Size }}">
                                    @endif

                                    <td class="ps-4"><span class="size-pill">{{ $line->Size ?: '—' }}</span></td>
                                    <td><code class="text-secondary small font-monospace">{{ $line->ProdId }}</code></td>
                                    <td><span class="status-indicator status-indicator-{{ $prodBadge($line->ProdStatus) }}">{{ $line->ProdStatus ?: '—' }}</span></td>
                                    <td class="text-end fw-semibold text-dark">{{ number_format($orderQty) }} <span class="text-muted small fw-normal">pcs</span></td>

                                    {{-- Cut Plan: this size's share of the fabric-bottleneck plan total --}}
                                    <td class="text-end {{ $cutPlanQty !== null ? 'fw-semibold text-dark' : 'text-muted' }}">
                                        @if($cutPlanQty !== null)
                                            {{ number_format($cutPlanQty) }} <span class="text-muted small fw-normal">pcs</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    {{-- Cut Qty --}}
                                    @if($editCutting)
                                        <td class="text-end">
                                            {{-- QoL: select the prefilled 0 on focus (typing replaces it instead of
                                                 producing '60'/'06') and strip accidental leading zeros. --}}
                                            <input type="number" name="reports[{{ $loop->parent->index }}_{{ $loop->index }}][cutting_qty]"
                                                   class="form-control form-control-sm modern-input d-inline-block cutting-qty-input" style="width: 90px;"
                                                   value="{{ $cutQty ?? '' }}" min="0" step="1"
                                                   data-order-qty="{{ $orderQty }}"
                                                   data-cut-plan="{{ $cutPlanQty ?? '' }}"
                                                   data-balance-target="bal_{{ $loop->parent->index }}_{{ $loop->index }}"
                                                   onfocus="if(!parseFloat(this.value))this.select()"
                                                   oninput="if(/^0\d/.test(this.value))this.value=this.value.replace(/^0+(?=\d)/,'')"
                                                   inputmode="numeric" placeholder="0">
                                        </td>
                                    @else
                                        <td class="text-end fw-semibold text-primary">
                                            @if($cutQty !== null)
                                                {{ number_format($cutQty) }} <span class="text-muted small fw-normal">pcs</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif

                                    {{-- Balance: Cut Qty − Cut Plan once Cut Plan is available, else the
                                         original Cut Qty − Order Qty. Shows only once Qty Cut is entered --}}
                                    <td class="text-end">
                                        <span id="bal_{{ $loop->parent->index }}_{{ $loop->index }}"
                                              class="balance-cell fw-semibold {{ $balClass }}"
                                              data-order-qty="{{ $orderQty }}"
                                              data-cut-plan="{{ $cutPlanQty ?? '' }}">{!! $balText !!}</span>
                                    </td>

                                    {{-- Gramasi (always grams) --}}
                                    @if($editGramasi)
                                        <td class="text-end pe-4">
                                            <input type="number" step="0.01" name="reports[{{ $loop->parent->index }}_{{ $loop->index }}][gramasi]"
                                                   class="form-control form-control-sm modern-input d-inline-block gramasi-input" style="width: 72px;"
                                                   onfocus="if(!parseFloat(this.value))this.select()"
                                                   oninput="if(/^0\d/.test(this.value))this.value=this.value.replace(/^0+(?=\d)/,'')"
                                                   value="{{ $cuttingReports->get($line->ProdId)?->gramasi ?? '' }}" min="0" inputmode="decimal" placeholder="0.00">
                                            <span class="text-muted small fw-normal ms-1">g</span>
                                        </td>
                                    @else
                                        <td class="text-end fw-semibold text-success pe-4">
                                            @if($cuttingReports->get($line->ProdId)?->gramasi !== null)
                                                {{ number_format($cuttingReports->get($line->ProdId)->gramasi, 2) }} <span class="text-muted small fw-normal">g</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold border-top border-secondary border-opacity-20">
                                <td class="ps-4 text-secondary">TOTAL</td>
                                <td></td>
                                <td></td>
                                <td class="text-end text-dark">{{ number_format($totalQty) }} <span class="text-muted small fw-normal">pcs</span></td>
                                <td class="text-end {{ $cuttPlanTotal !== null ? 'text-dark' : 'text-muted' }}">
                                    @if($cuttPlanTotal !== null)
                                        {{ number_format($cuttPlanTotal) }} <span class="text-muted small fw-normal">pcs</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-primary">
                                    <span id="total-cutting-qty-val" class="fw-bold">{{ number_format($totalCuttingQty) }}</span> <span class="text-muted small fw-normal">pcs</span>
                                </td>
                                <td class="text-end">
                                    <span id="total-balance-val" class="fw-bold {{ $totalBalClass }}"
                                          data-cut-plan-total="{{ $cuttPlanTotal ?? '' }}"
                                          data-total-order-qty="{{ $totalQty }}">{!! $totalBalText !!}</span>
                                </td>
                                <td class="pe-4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    <p class="small mb-0">No production size lines found for this style.</p>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="subcon-order-card p-5 text-center text-muted">
        <i class="fas fa-info-circle fa-2x mb-3 text-secondary"></i>
        <p class="mb-0">
            No linked production detail. This order isn't yet tied to a PLM activity in the production system.
        </p>
    </div>
@endforelse

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fmt = new Intl.NumberFormat();
    const inputs = Array.from(document.querySelectorAll('.cutting-qty-input'));
    const totalCutEl = document.getElementById('total-cutting-qty-val');
    const totalBalEl = document.getElementById('total-balance-val');
    // The submit button lives in the page (outside this partial) and targets
    // the stage form via form="stageForm".
    const submitBtn = document.querySelector('button[type="submit"][form="stageForm"]');

    // Balance = Qty Cut − Cut Plan once Cut Plan is available, else Qty Cut − Order
    // Qty. Under-cut negative, over-cut positive.
    function balClass(b, has) {
        if (!has) return 'text-muted';
        return b < 0 ? 'text-danger' : 'text-success';
    }
    function balText(b, has) {
        if (!has) return '—';
        return (b > 0 ? '+' : '') + fmt.format(b) + ' <span class="text-muted small fw-normal">pcs</span>';
    }
    function parseOrNull(raw) {
        return (raw !== '' && raw !== undefined) ? parseInt(raw) : null;
    }

    const cutPlanTotal = totalBalEl ? parseOrNull(totalBalEl.dataset.cutPlanTotal) : null;
    const totalOrderQty = totalBalEl ? (parseOrNull(totalBalEl.dataset.totalOrderQty) || 0) : 0;

    function recompute() {
        let totalCut = 0, anyFilled = false;

        inputs.forEach(input => {
            const orderQty = parseInt(input.dataset.orderQty) || 0;
            const cutPlan = parseOrNull(input.dataset.cutPlan);
            const balanceBase = cutPlan !== null ? cutPlan : orderQty;
            const raw = input.value.trim();
            const has = raw !== '';
            const cut = parseInt(raw) || 0;
            if (has) anyFilled = true;
            totalCut += cut;

            const balance = cut - balanceBase;

            const target = document.getElementById(input.dataset.balanceTarget);
            if (target) {
                target.innerHTML = balText(balance, has);
                target.className = 'balance-cell fw-semibold ' + balClass(balance, has);
            }
        });

        if (totalCutEl) totalCutEl.textContent = fmt.format(totalCut);
        if (totalBalEl) {
            const totalBalanceBase = cutPlanTotal !== null ? cutPlanTotal : totalOrderQty;
            const tb = totalCut - totalBalanceBase;
            const has = totalCut > 0;
            totalBalEl.innerHTML = balText(tb, has);
            totalBalEl.className = 'fw-bold ' + balClass(tb, has);
        }

        if (submitBtn) {
            submitBtn.disabled = !anyFilled;
        }
    }

    inputs.forEach(input => {
        input.addEventListener('input', recompute);
        input.addEventListener('change', recompute);
    });

    if (inputs.length) recompute();
});
</script>
