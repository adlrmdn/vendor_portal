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
            @if($editable)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-cons-fabric"><i class="fas fa-plus me-1"></i> Add fabric</button>
            @endif
        </div>

        @if(empty($fabricLines) && ! $editable)
            <div class="text-muted small">No fabric linked for this style yet, so there is nothing to reconcile.</div>
        @else
            @if(empty($fabricLines))
                <div class="text-muted small mb-2">No fabric linked from VSM for this style — use "Add fabric" above if one was used.</div>
            @endif
            <style>
                .consumption-table > thead > tr > th,
                .consumption-table > tbody > tr.cons-data-row > td {
                    border-right: 1px solid rgba(0,0,0,0.08);
                }
                .consumption-table > thead > tr > th:last-child,
                .consumption-table > tbody > tr.cons-data-row > td:last-child {
                    border-right: none;
                }
            </style>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 consumption-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-end" style="width:96px; font-size:.75rem; white-space:nowrap;">Short Roll</th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">Sisa Kain (Utuh)</th>
                            <th class="text-end" style="width:96px; font-size:.75rem; white-space:nowrap;">Kepala Kain</th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">Retur Kain</th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">Goods Receive</th>
                            <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;{{ $editable ? 'background:#fff3cd;color:#7a5a00;' : '' }}">Fabric Sent @if($editable)<i class="fas fa-pen ms-1" style="font-size:.6rem;"></i>@endif</th>
                            <th class="text-end" style="width:130px; font-size:.75rem; white-space:nowrap;{{ $editable ? 'background:#fff3cd;color:#7a5a00;' : '' }}">Cons. Plan @if($editable)<i class="fas fa-pen ms-1" style="font-size:.6rem;"></i>@endif</th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">Cutt Plan</th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">Actual Cons.</th>
                            <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;">Overconsumption</th>
                            <th class="text-end" style="width:150px; font-size:.75rem; white-space:nowrap;">Fabric Price (IDR)</th>
                            <th class="text-end" style="width:150px; font-size:.75rem; white-space:nowrap;">Deduction</th>
                        </tr>
                    </thead>
                    <tbody id="cons-rows">
                        @foreach($fabricLines as $i => $fl)
                            @php
                                $waste = (float) ($fl['retur_kain'] ?? 0);
                                $unit = $fl['unit'] ?? null;
                                // Small muted unit tag placed under a value, consistent with
                                // the existing "as of {date}" / "converted:" annotations below.
                                $unitTag = fn ($u) => $u ? '<div class="text-muted" style="font-size:.72rem;line-height:1.3;">'.e($u).'</div>' : '';
                            @endphp
                            {{-- Fabric description on its own full-width line, calculation columns below --}}
                            @php $displayLabel = $fl['display_label'] ?? $fl['label']; @endphp
                            <tr class="table-light">
                                <td colspan="12" class="fw-semibold small py-2">
                                    <i class="fas fa-scroll text-secondary me-1"></i> {{ $displayLabel }}
                                    @if(! empty($fl['item_number']) || ! empty($fl['inventory_group']))
                                        <span class="text-muted fw-normal ms-2" style="font-size:.72rem;">
                                            @if(! empty($fl['item_number']))Item: {{ $fl['item_number'] }}@endif
                                            @if(! empty($fl['item_number']) && ! empty($fl['inventory_group'])) &middot; @endif
                                            @if(! empty($fl['inventory_group']))Inv. Group: {{ $fl['inventory_group'] }}@endif
                                        </span>
                                    @endif
                                    @if($displayLabel !== $fl['label'])
                                        <div class="text-muted fw-normal" style="font-size:.68rem;" title="The PO line's own description text — kept for traceability, but the item master name above is what's actually correct.">
                                            <i class="fas fa-triangle-exclamation me-1"></i>PO line text: {{ $fl['label'] }}
                                        </div>
                                    @endif
                                    @if($editable)
                                        <input type="hidden" name="fabrics[{{ $i }}][label]" value="{{ $fl['label'] }}">
                                    @endif
                                </td>
                            </tr>
                            <tr class="cons-data-row" data-waste="{{ $waste }}">
                                @foreach(['short_roll','sisa_kain','kepala_kain','retur_kain'] as $rc)
                                    <td class="text-end">
                                        @if($editable)
                                            <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                                   class="form-control form-control-sm text-end cons-waste{{ $rc === 'retur_kain' ? ' cons-retur' : '' }}" style="width:84px;"
                                                   name="fabrics[{{ $i }}][{{ $rc }}]"
                                                   value="{{ old('fabrics.'.$i.'.'.$rc, $fl[$rc] ?? 0) }}" placeholder="0">
                                            {!! $unitTag($unit) !!}
                                            @if($rc === 'retur_kain' && ($fl['retur_kain_source'] ?? null) === 'qc_console')
                                                <div class="text-info" style="font-size:.65rem;white-space:nowrap;" title="Measured by QC on the console; overrides the vendor-entered value.">
                                                    <i class="fas fa-clipboard-check"></i> QC input
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ $fmt2($fl[$rc] ?? 0) }}</span>
                                            {!! $unitTag($unit) !!}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end">
                                    <span class="text-muted">{{ $fmt2($fl['goods_receive'] ?? null) }}</span>
                                    {!! $fl['goods_receive'] !== null ? $unitTag($unit) : '' !!}
                                    @if(! empty($fl['goods_receive_date']))
                                        <div class="text-muted" style="font-size:.65rem;white-space:nowrap;" title="Last delivery date from the D365 packing slip">
                                            as of {{ \Illuminate\Support\Carbon::parse($fl['goods_receive_date'])->format('d M Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-sent" style="width:110px;background:#fffaf0;border-color:#f0c000;font-weight:600;"
                                               name="fabrics[{{ $i }}][fabric_sent]"
                                               value="{{ old('fabrics.'.$i.'.fabric_sent', $fl['fabric_sent']) }}" placeholder="0">
                                        {!! $unitTag($unit) !!}
                                    @else
                                        <span class="fw-semibold">{{ $fmt2($fl['fabric_sent']) }}</span>
                                        {!! $unitTag($unit) !!}
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.0001" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-plan" style="width:120px;background:#fffaf0;border-color:#f0c000;font-weight:600;"
                                               name="fabrics[{{ $i }}][consumption_plan]"
                                               value="{{ old('fabrics.'.$i.'.consumption_plan', $fl['consumption_plan']) }}" placeholder="0">
                                        {!! $unitTag($unit ? $unit.'/pc' : null) !!}
                                    @else
                                        <span class="fw-semibold">{{ $fmtCons($fl['consumption_plan']) }}</span>
                                        {!! $unitTag($unit ? $unit.'/pc' : null) !!}
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold cons-cutt">{{ $fmt2($fl['cutt_plan']) }}</span>
                                    {!! $unitTag($fl['cutt_plan'] !== null ? 'pcs' : null) !!}
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold cons-actual">{{ $fmtCons($fl['actual_consumption']) }}</span>
                                    {!! $unitTag($fl['actual_consumption'] !== null && $unit ? $unit.'/pc' : null) !!}
                                </td>
                                <td class="text-end fw-semibold cons-over">{{ $fl['overconsumption'] !== null ? $fmt2($fl['overconsumption'] * 100).'%' : '—' }}</td>
                                <td class="text-end">
                                    @if($editable)
                                        <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-price" style="width:130px;"
                                               name="fabrics[{{ $i }}][fabric_price]"
                                               value="{{ old('fabrics.'.$i.'.fabric_price', $fl['fabric_price']) }}" placeholder="0">
                                        {!! $unitTag($unit ? 'Rp / '.$unit : null) !!}
                                        {!! $unitTag($fl['fabric_price_source'] ?? null) !!}
                                    @else
                                        <span class="fw-semibold">{{ $fmt2($fl['fabric_price']) }}</span>
                                        {!! $unitTag($fl['fabric_price'] !== null && $unit ? 'Rp / '.$unit : null) !!}
                                        {!! $unitTag($fl['fabric_price_source'] ?? null) !!}
                                    @endif
                                </td>
                                <td class="text-end fw-semibold cons-ded" style="white-space:nowrap;">{{ $money($fl['deduction']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="11" class="text-end fw-semibold">Total Deduction</td>
                            <td class="text-end fw-bold cons-ded-total" style="white-space:nowrap;">{{ $money($totalDeduction) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($editable)
                    <div class="form-text mt-1">
                        Cutt Plan = ROUNDDOWN((Fabric Sent − Retur Kain) ÷ Cons. Plan).
                        Actual Cons. = (Fabric Sent − Retur Kain) ÷ Total Qty Cut ({{ $fmt2($totalCut) }}).
                        Overconsumption = (Actual Cons. − Cons. Plan) ÷ Cons. Plan.
                        Deduction = MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price — charged only when Overconsumption exceeds 3%.
                        Fabric Price is prefilled from the fabric PO; convert to IDR here if the source is another currency.
                        The waste columns (Short Roll / Sisa Kain / Kepala Kain / Retur Kain) are prefilled from the vendor's cutting report — override them here if needed. Saved when you approve the cutting report.
                        Goods Receive is the confirmed receipt quantity from D365 (packing slip records) for the linked fabric PO — reference only, not editable.
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
                        // Cutt Plan is a piece count → fixed 2 decimals. The unit tag
                        // below each value is static (server-rendered) and untouched here.
                        if (cuttEl) cuttEl.textContent = (fs > 0 && cp > 0) ? Math.floor((fs - waste) / cp).toFixed(2) : '—';
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
                    const rows = document.getElementById('cons-rows');
                    // Delegated so rows added later (via "Add fabric") are covered too.
                    rows.addEventListener('input', function (e) {
                        if (e.target.matches('.cons-sent, .cons-plan, .cons-price, .cons-waste')) {
                            recompute(e.target.closest('tr'));
                            updateTotal();
                        }
                    });
                    rows.addEventListener('click', function (e) {
                        const btn = e.target.closest('.cons-remove');
                        if (! btn) return;
                        const labelRow = btn.closest('tr');
                        const dataRow = labelRow.nextElementSibling;
                        labelRow.remove();
                        if (dataRow) dataRow.remove();
                        updateTotal();
                    });

                    // "Add fabric" — a manually-entered fabric VSM does not carry (e.g. a
                    // material never linked/synced from D365). Same shape as a VSM row,
                    // but with an editable label and no Goods Receive (no PO to read it from).
                    const addBtn = document.getElementById('add-cons-fabric');
                    let rIdx = {{ count($fabricLines) }};
                    if (addBtn) {
                        addBtn.addEventListener('click', function () {
                            const i = rIdx++;
                            const labelRow = document.createElement('tr');
                            labelRow.className = 'table-light';
                            labelRow.innerHTML = '<td colspan="11" class="py-2">'
                                + '<input type="text" class="form-control form-control-sm d-inline-block" style="max-width:420px;" name="fabrics[' + i + '][label]" placeholder="Fabric description" required>'
                                + '</td><td class="text-end py-2"><button type="button" class="btn btn-sm btn-outline-danger cons-remove" title="Remove"><i class="fas fa-times"></i></button></td>';

                            const dataRow = document.createElement('tr');
                            dataRow.className = 'cons-data-row';
                            dataRow.dataset.waste = '0';
                            let cells = '';
                            ['short_roll', 'sisa_kain', 'kepala_kain', 'retur_kain'].forEach(function (c) {
                                cells += '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end cons-waste' + (c === 'retur_kain' ? ' cons-retur' : '') + '" style="width:84px;" name="fabrics[' + i + '][' + c + ']" value="0"></td>';
                            });
                            cells += '<td class="text-end"><span class="text-muted">—</span></td>';
                            cells += '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end cons-sent" style="width:110px;background:#fffaf0;border-color:#f0c000;font-weight:600;" name="fabrics[' + i + '][fabric_sent]" value="0"></td>';
                            cells += '<td class="text-end"><input type="number" step="0.0001" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end cons-plan" style="width:120px;background:#fffaf0;border-color:#f0c000;font-weight:600;" name="fabrics[' + i + '][consumption_plan]" value="0"></td>';
                            cells += '<td class="text-end"><span class="fw-semibold cons-cutt">—</span></td>';
                            cells += '<td class="text-end"><span class="fw-semibold cons-actual">—</span></td>';
                            cells += '<td class="text-end fw-semibold cons-over">—</td>';
                            cells += '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end cons-price" style="width:130px;" name="fabrics[' + i + '][fabric_price]" value="0"></td>';
                            cells += '<td class="text-end fw-semibold cons-ded" style="white-space:nowrap;">—</td>';
                            dataRow.innerHTML = cells;

                            rows.appendChild(labelRow);
                            rows.appendChild(dataRow);
                        });
                    }
                })();
                </script>
            @endif
        @endif
    </div>
</div>
