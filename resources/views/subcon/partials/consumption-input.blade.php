{{--
    Fabric reconciliation (read-only, vendor-entered) + consumption (admin-entered
    at cutting-report approval). One row per fabric, keyed by label. When editable
    the admin types Fabric Sent + Consumption Plan; Cutt Plan and Actual Cons. are
    computed live.

    Inputs-only: this partial renders just the table + fields (no <form>). The
    caller wraps it in the form that ALSO approves — persisting consumption and
    approving the cutting gate is one atomic action. See the admin order view and
    the no-login subcon.cutting-approval-form.
    Params:
      $order        SubconOrder
      $fabricLines  array from SubconProductionService::fabricLinesWithData()
      $totalCut     int (basis for actual consumption)
      $editable     bool
--}}
@php
    $editable = $editable ?? false;
    $fabricLines = $fabricLines ?? [];
    $totalCut = (int) ($totalCut ?? 0);
    $totalDeduction = 0.0;
    foreach ($fabricLines as $fl) {
        $totalDeduction += (float) ($fl['deduction'] ?? 0);
    }

    // Fixed 2 decimals (.00) — for everything except the consumption figures.
    $fmt2 = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }

        return number_format((float) $v, 2);
    };

    // Money (IDR): "Rp " + thousands + 2 decimals — the uniform currency standard
    // for figures that are always IDR (Deduction, Total Deduction).
    $money = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }

        return 'Rp '.number_format((float) $v, 2);
    };

    // Consumption figures (Cons. Plan, Actual Cons.): min 2, max 4 decimals — keep
    // the tail when present (e.g. 2.242 stays 2.242), pad whole numbers to .00.
    $fmtCons = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }
        $v = (float) $v;
        $trimmed = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
        $dec = ($pos = strpos($trimmed, '.')) !== false ? strlen(substr($trimmed, $pos + 1)) : 0;

        return number_format($v, max(2, $dec));
    };
@endphp

<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing:.05em;">
                <i class="fas fa-ruler-horizontal me-1"></i> Fabric Reconciliation &amp; Consumption
                <span class="text-muted fw-normal text-lowercase">(per fabric)</span>
            </div>
            <span class="text-muted small">Total Qty Cut: <strong>{{ $fmt2($totalCut) }}</strong> pcs</span>
        </div>

        @if(empty($fabricLines))
            <div class="text-muted small">No fabric linked for this style yet, so there is nothing to reconcile.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-end" style="width:96px; font-size:.75rem; white-space:nowrap;">Short Roll</th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">Sisa Kain (Utuh)</th>
                            <th class="text-end" style="width:96px; font-size:.75rem; white-space:nowrap;">Kepala Kain</th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">Retur Kain</th>
                            <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;{{ $editable ? 'background:#fff3cd;color:#7a5a00;' : '' }}">Fabric Sent @if($editable)<i class="fas fa-pen ms-1" style="font-size:.6rem;"></i>@endif</th>
                            <th class="text-end" style="width:130px; font-size:.75rem; white-space:nowrap;{{ $editable ? 'background:#fff3cd;color:#7a5a00;' : '' }}">Cons. Plan @if($editable)<i class="fas fa-pen ms-1" style="font-size:.6rem;"></i>@endif</th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">Cutt Plan</th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">Actual Cons.</th>
                            <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;">Overconsumption</th>
                            <th class="text-end" style="width:150px; font-size:.75rem; white-space:nowrap;">Fabric Price (IDR)</th>
                            <th class="text-end" style="width:150px; font-size:.75rem; white-space:nowrap;">Deduction</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fabricLines as $i => $fl)
                            @php $waste = (float) ($fl['retur_kain'] ?? 0); @endphp
                            {{-- Fabric description on its own full-width line, calculation columns below --}}
                            <tr class="table-light">
                                <td colspan="11" class="fw-semibold small py-2">
                                    <i class="fas fa-scroll text-secondary me-1"></i> {{ $fl['label'] }}
                                    @if($editable)
                                        <input type="hidden" name="fabrics[{{ $i }}][label]" value="{{ $fl['label'] }}">
                                    @endif
                                </td>
                            </tr>
                            <tr data-waste="{{ $waste }}">
                                @foreach(['short_roll','sisa_kain','kepala_kain','retur_kain'] as $rc)
                                    <td class="text-end">
                                        @if($editable)
                                            <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                                   class="form-control form-control-sm text-end cons-waste{{ $rc === 'retur_kain' ? ' cons-retur' : '' }}" style="width:84px;"
                                                   name="fabrics[{{ $i }}][{{ $rc }}]"
                                                   value="{{ old('fabrics.'.$i.'.'.$rc, $fl[$rc] ?? 0) }}" placeholder="0">
                                        @else
                                            <span class="text-muted">{{ $fmt2($fl[$rc] ?? 0) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-sent" style="width:110px;background:#fffaf0;border-color:#f0c000;font-weight:600;"
                                               name="fabrics[{{ $i }}][fabric_sent]"
                                               value="{{ old('fabrics.'.$i.'.fabric_sent', $fl['fabric_sent']) }}" placeholder="0">
                                    @else
                                        <span class="fw-semibold">{{ $fmt2($fl['fabric_sent']) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.0001" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-plan" style="width:120px;background:#fffaf0;border-color:#f0c000;font-weight:600;"
                                               name="fabrics[{{ $i }}][consumption_plan]"
                                               value="{{ old('fabrics.'.$i.'.consumption_plan', $fl['consumption_plan']) }}" placeholder="0">
                                    @else
                                        <span class="fw-semibold">{{ $fmtCons($fl['consumption_plan']) }}</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold cons-cutt">{{ $fmt2($fl['cutt_plan']) }}</td>
                                <td class="text-end fw-semibold cons-actual">{{ $fmtCons($fl['actual_consumption']) }}</td>
                                <td class="text-end fw-semibold cons-over">{{ $fl['overconsumption'] !== null ? $fmt2($fl['overconsumption'] * 100).'%' : '—' }}</td>
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-price" style="width:130px;"
                                               name="fabrics[{{ $i }}][fabric_price]"
                                               value="{{ old('fabrics.'.$i.'.fabric_price', $fl['fabric_price']) }}" placeholder="0">
                                        @if(!empty($fl['fabric_price_source'] ?? null))
                                            <div class="text-muted small" style="font-size:.7rem;">converted: {{ $fl['fabric_price_source'] }}</div>
                                        @endif
                                    @else
                                        <span class="fw-semibold">{{ $fmt2($fl['fabric_price']) }}</span>
                                        @if(!empty($fl['fabric_price_source'] ?? null))
                                            <div class="text-muted small" style="font-size:.7rem;">from {{ $fl['fabric_price_source'] }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end fw-semibold cons-ded" style="white-space:nowrap;">{{ $money($fl['deduction']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="10" class="text-end fw-semibold">Total Deduction</td>
                            <td class="text-end fw-bold cons-ded-total" style="white-space:nowrap;">{{ $money($totalDeduction) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($editable)
                    <div class="form-text mt-1">
                        Cutt Plan = ROUNDDOWN(Fabric Sent ÷ Cons. Plan).
                        Actual Cons. = (Fabric Sent − Retur Kain) ÷ Total Qty Cut ({{ $fmt2($totalCut) }}).
                        Overconsumption = (Actual Cons. − Cons. Plan) ÷ Cons. Plan.
                        Deduction = MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price — charged only when Overconsumption exceeds 3%.
                        Fabric Price is prefilled from the fabric PO; convert to IDR here if the source is another currency.
                        The waste columns (Short Roll / Sisa Kain / Kepala Kain / Retur Kain) are prefilled from the vendor's cutting report — override them here if needed. Saved when you approve the cutting report.
                    </div>
                <script>
                (function () {
                    const total = {{ $totalCut }};
                    // Consumption figures: min 2, max 4 decimals (keep the tail).
                    function fmtCons(n) {
                        const r = Math.round(n * 10000) / 10000;
                        const s = r.toString();
                        const dec = s.indexOf('.') !== -1 ? s.split('.')[1].length : 0;
                        return r.toFixed(Math.max(2, dec));
                    }
                    // Money (IDR) → "Rp " + thousands + 2 decimals (uniform standard).
                    function fmtMoney(n) {
                        return 'Rp ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                    const TOLERANCE = 0.03;
                    function recompute(tr) {
                        const fs = parseFloat(tr.querySelector('.cons-sent')?.value);
                        const cp = parseFloat(tr.querySelector('.cons-plan')?.value);
                        const price = parseFloat(tr.querySelector('.cons-price')?.value);
                        // Waste subtracted from Fabric Sent = ONLY Retur Kain. The other
                        // three columns are still captured but don't reduce consumption.
                        const returEl = tr.querySelector('.cons-retur');
                        const waste = returEl
                            ? (parseFloat(returEl.value) || 0)
                            : (parseFloat(tr.dataset.waste) || 0);
                        const cuttEl = tr.querySelector('.cons-cutt');
                        const actEl = tr.querySelector('.cons-actual');
                        const overEl = tr.querySelector('.cons-over');
                        const dedEl = tr.querySelector('.cons-ded');
                        const act = (fs > 0 && total > 0) ? (fs - waste) / total : null;
                        // Cutt Plan is a piece count → fixed 2 decimals.
                        if (cuttEl) cuttEl.textContent = (fs > 0 && cp > 0) ? Math.floor(fs / cp).toFixed(2) : '—';
                        if (actEl) actEl.textContent = act !== null ? fmtCons(act) : '—';
                        // Overconsumption % → fixed 2 decimals.
                        if (overEl) overEl.textContent = (act !== null && cp > 0) ? ((act - cp) / cp * 100).toFixed(2) + '%' : '—';
                        // Deduction: overuse beyond the 3% tolerance × qty × price.
                        let ded = null;
                        if (act !== null && cp > 0 && price > 0) {
                            const excess = act - cp * (1 + TOLERANCE);
                            ded = excess > 0 ? excess * total * price : 0;
                        }
                        if (dedEl) dedEl.textContent = ded !== null ? fmtMoney(ded) : '—';
                    }
                    function updateTotal() {
                        let sum = 0;
                        document.querySelectorAll('.cons-ded').forEach(function (el) {
                            // Strip the "Rp " prefix + thousands separators before parsing.
                            const v = parseFloat(el.textContent.replace(/[^0-9.-]/g, ''));
                            if (! isNaN(v)) sum += v;
                        });
                        const t = document.querySelector('.cons-ded-total');
                        if (t) t.textContent = fmtMoney(sum);
                    }
                    document.querySelectorAll('.cons-sent, .cons-plan, .cons-price, .cons-waste').forEach(function (el) {
                        el.addEventListener('input', function () { recompute(el.closest('tr')); updateTotal(); });
                    });
                })();
                </script>
            @endif
        @endif
    </div>
</div>
