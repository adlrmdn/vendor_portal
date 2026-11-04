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
      $order            SubconOrder
      $fabricLines      array from SubconProductionService::fabricLinesWithData()
      $totalCut         int (basis for actual consumption)
      $editable         bool
      $showAccessory    bool — false (default). true: also render the Accessory
                        return-quantity sub-section (subcon.partials.material-recon-accessory)
                        below the Fabric table, as a second sub-section of this
                        same "Material Reconciliation & Consumption" card — used
                        on the Final Approval / Report Validation form, which has
                        no other place to show/edit it.
      $accessoryLines   array from SubconProductionService::accessoryLinesForPo() — required when $showAccessory
      $accessoryRecon   Collection of MaterialReturnLine (item_type=accessory) keyed by label — required when $showAccessory
      $accessoryGoodsReceive  array from SubconProductionService::resolveGoodsReceipts($accessoryLines), keyed by label — required when $showAccessory
      $accessoryIssue   array from SubconProductionService::materialIssueForOrder(), keyed by ItemNumber (not label) — required when $showAccessory
      $accessoryClaimByLabel  array from QcApprovalController::hoApprovalForm(), keyed by label —
                        expected_retur/balance/claim per accessory, as of the last save (see
                        that method's docblock for why it can't be live-recomputed like the
                        Fabric table's deduction). Optional; defaults to [] (no claim columns
                        shown — e.g. on the vendor's own cutting-report page, which never
                        passes it since HO hasn't reviewed/priced anything yet).
      $accessoryClaimTotal  float — sum of the above, shown as its own footer line
                        alongside (not merged into) the live Fabric Total Deduction.
      $accessoryEditable  ?bool — editability of the Accessory sub-section specifically,
                        independent of $editable (the Fabric table's own flag). Defaults to
                        $editable. Needed because the regular admin order page shows Fabric
                        as editable during the cutting-review gate (saved by that gate's own
                        approve action) but has no save path for accessories at all — only
                        QcApprovalController::hoApprove()/hoSendApproval() persist
                        accessories_recon, so this page must always pass false explicitly
                        there rather than silently discarding edited accessory rows.
--}}
@php
    $editable = $editable ?? false;
    $fabricLines = $fabricLines ?? [];
    $totalCut = (int) ($totalCut ?? 0);
    $showAccessory = $showAccessory ?? false;
    $accessoryGoodsReceive = $accessoryGoodsReceive ?? [];
    $accessoryIssue = $accessoryIssue ?? [];
    $accessoryClaimByLabel = $accessoryClaimByLabel ?? [];
    $accessoryClaimTotal = (float) ($accessoryClaimTotal ?? 0);
    $accessoryEditable = $accessoryEditable ?? $editable;
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
                <i class="fas fa-ruler-horizontal me-1"></i> Material Reconciliation &amp; Consumption
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
            <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing:.05em;">
                <i class="fas fa-scroll me-1"></i> Fabric
                <span class="text-muted fw-normal text-lowercase">(per fabric)</span>
            </div>
            @if($editable)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-cons-fabric"><i class="fas fa-plus me-1"></i> Add fabric</button>
            @endif
        </div>
        <div class="text-muted small mb-2">
            Total Qty Cut (basis for Actual Cons. / Cutt Plan below): <strong>{{ $fmt2($totalCut) }}</strong> pcs
        </div>

        @if(empty($fabricLines) && ! $editable)
            <div class="text-muted small">No fabric linked for this style yet, so there is nothing to reconcile.</div>
        @else
            @if(empty($fabricLines))
                <div class="text-muted small mb-2">No fabric linked from VSM for this style — use "Add fabric" above if one was used.</div>
            @endif
            <style>
                /* Light divider between every column, bolder divider at the
                   boundary of each of the 4 logical groups (Waste / Receipt &
                   sent / Consumption / Pricing) so the grouping reads all the
                   way down the table, not just in the header. */
                .consumption-table > thead > tr.cons-col-header > th,
                .consumption-table > tbody > tr.cons-data-row > td {
                    border-right: 1px solid rgba(0,0,0,0.05);
                }
                .consumption-table > thead > tr.cons-col-header > th:nth-child(3),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(4),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(5),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(7),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(11),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(3),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(4),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(5),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(7),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(11) {
                    border-right: 2px solid rgba(0,0,0,0.13);
                }
                /* Real background highlight, not just small text, so which
                   columns matter is visible at a glance, not just on hover:
                   Short Roll/Sisa Kain/Kepala Kain (muted grey) are the
                   breakdown that rolls up into Retur Kain — they don't act on
                   their own. Retur Kain and Shortage (soft red) are the two
                   figures that actually come off Fabric Sent. */
                .consumption-table > thead > tr.cons-col-header > th:nth-child(1),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(2),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(3),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(1),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(2),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(3) {
                    background: #f5f6f7;
                }
                .consumption-table > thead > tr.cons-col-header > th:nth-child(4),
                .consumption-table > thead > tr.cons-col-header > th:nth-child(5),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(4),
                .consumption-table > tbody > tr.cons-data-row > td:nth-child(5) {
                    background: #fdf0f0;
                }
                .consumption-table > thead > tr.cons-col-header > th:last-child,
                .consumption-table > tbody > tr.cons-data-row > td:last-child {
                    border-right: none;
                }
                .consumption-table > thead > tr.cons-group-header > th {
                    font-size: .64rem;
                    font-weight: 600;
                    color: #5b6472;
                    background: #eef1f4;
                    border-bottom: 1px solid rgba(0,0,0,0.08);
                    padding: .25rem .5rem .2rem;
                }
                .consumption-table > thead > tr.cons-group-header > th:not(:last-child) {
                    border-right: 2px solid rgba(0,0,0,0.13);
                }
                /* Numeric columns line up on their digits, like a spreadsheet,
                   so magnitudes are easy to compare down a column at a glance. */
                .consumption-table > tbody > tr.cons-data-row > td {
                    font-variant-numeric: tabular-nums;
                }
                .consumption-table .cons-unit {
                    font-size: .7rem;
                    color: #6c757d;
                    margin-left: .2rem;
                    white-space: nowrap;
                }
                .consumption-table .cons-ref-icon {
                    font-size: .68rem;
                    margin-left: .25rem;
                    cursor: help;
                }
                .cons-fabric-banner {
                    border-top: 2px solid rgba(0,0,0,0.08);
                }
                .cons-fabric-banner:first-child {
                    border-top: none;
                }
                /* Visible (not hover-only) flag on every waste/shortage header
                   showing whether that column actually reduces Fabric Sent —
                   Retur Kain + Shortage do; Short Roll/Sisa Kain/Kepala Kain
                   are recorded for reference only. */
                .cons-net-flag {
                    display: block;
                    font-size: .58rem;
                    font-weight: 700;
                    color: #b02a37;
                    white-space: nowrap;
                }
                .cons-ref-flag {
                    display: block;
                    font-size: .58rem;
                    font-weight: 500;
                    color: #9aa1a8;
                    white-space: nowrap;
                }
            </style>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 consumption-table">
                    <thead class="table-light">
                        {{-- Group header: the 13 columns below read as 6 logical
                             groups, each carrying its own explanation as a
                             hover tooltip (replaces one long paragraph that used
                             to sit below the table, disconnected from the data
                             it was explaining). The first two groups are a
                             breakdown → total pair, not independent columns:
                             Short Roll + Sisa Kain + Kepala Kain roll up into
                             Retur Kain — they aren't three more things sitting
                             next to it. Shortage, Receipt & sent, Consumption
                             (the derived formulas), and Pricing follow. --}}
                        <tr class="cons-group-header">
                            <th colspan="3" class="text-center">
                                {{ __('Short Roll + Sisa + Kepala') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Prefilled from the vendor\'s cutting report — override here if needed. These three roll up into Retur Kain (next column); they are not independently subtracted from Fabric Sent.') }}"></i>
                            </th>
                            <th colspan="1" class="text-center">
                                {{ __('= Retur Kain') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Retur Kain: total physical fabric returned — normally Short Roll + Sisa Kain + Kepala Kain, but editable independently here if it needs correcting. This is the figure (flagged below) actually subtracted from Fabric Sent.') }}"></i>
                            </th>
                            <th colspan="1" class="text-center">
                                {{ __('Shortage') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Selisih/Shortage has no vendor-side entry — it\'s approver-only, entered here. Subtracted from Fabric Sent alongside Retur Kain (flagged below).') }}"></i>
                            </th>
                            <th colspan="2" class="text-center">
                                {{ __('Receipt & sent') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Goods Receive is the confirmed receipt quantity from D365 (packing slip records). Fabric Sent is pinned to it and not editable for any PO-linked fabric; a manually added fabric (no linked PO) has no Goods Receive, so its Fabric Sent stays typed.') }}"></i>
                            </th>
                            <th colspan="4" class="text-center">
                                {{ __('Consumption') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Cutt Plan = ROUNDDOWN((Fabric Sent − Retur Kain − Shortage) ÷ Cons. Plan). Actual Cons. = same ÷ Total Qty Cut. Overconsumption = (Actual Cons. − Cons. Plan) ÷ Cons. Plan.') }}"></i>
                            </th>
                            <th colspan="2" class="text-center">
                                {{ __('Pricing') }}
                                <i class="fas fa-info-circle ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Fabric Price is prefilled from the fabric PO — convert to IDR here if the source is another currency. Deduction = MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price, charged only when Overconsumption exceeds 3%.') }}"></i>
                            </th>
                        </tr>
                        <tr class="cons-col-header">
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Short Roll') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Short Roll: fabric roll shortage or defect roll deducted from raw issues.') }}"></i>
                                <span class="cons-ref-flag">{{ __('reference only') }}</span>
                            </th>
                            <th class="text-end" style="width:100px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Sisa Kain') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Sisa Kain (utuh): intact, unused fabric rolls returned to warehouse.') }}"></i>
                                <span class="cons-ref-flag">{{ __('reference only') }}</span>
                            </th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Kepala Kain') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Kepala Kain: end remnants and edge pieces generated during cutting.') }}"></i>
                                <span class="cons-ref-flag">{{ __('reference only') }}</span>
                            </th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Retur Kain') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Retur Kain: total physical fabric returned. Formula: Net Fabric = Fabric Sent - (Retur Kain + Shortage).') }}"></i>
                                <span class="cons-net-flag">{{ __('− Net Fabric') }}</span>
                            </th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Shortage') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Selisih/Shortage: fabric quantity shortfall vs. what should be on hand, approver-entered. Also subtracted from Fabric Sent alongside Retur Kain.') }}"></i>
                                <span class="cons-net-flag">{{ __('− Net Fabric') }}</span>
                            </th>
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Goods Receive') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Goods Receive: raw fabric quantity officially received and confirmed by warehouse.') }}"></i>
                            </th>
                            {{-- No blanket "editable" highlight/pencil here: unlike Cons.
                                 Plan, Fabric Sent is editable only for a manually added
                                 fabric (no VSM/PO link) — for the normal, VSM-linked case
                                 it's pinned to Goods Receive and read-only (see the plain
                                 vs. highlighted input per row below). A table-wide header
                                 highlight would claim it's always typed in, which it isn't. --}}
                            <th class="text-end" style="width:110px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Fabric Sent') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Fabric Sent: total fabric issued to subcontractor for this production group. Pinned to Goods Receive for a VSM-linked fabric; editable only for a manually added one.') }}"></i>
                            </th>
                            <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;{{ $editable ? 'background:#fff3cd;color:#7a5a00;' : '' }}">
                                {{ __('Cons. Plan') }} @if($editable)<i class="fas fa-pen ms-1" style="font-size:.6rem;"></i>@endif
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Cons. Plan: budgeted consumption per garment piece engineered by Pattern & Marker team.') }}"></i>
                            </th>
                            <th class="text-end" style="width:90px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Cutt Plan') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Cutt Plan = (Fabric Sent - Retur Kain - Shortage) / Cons. Plan. Expected garment yield based on net fabric used.') }}"></i>
                            </th>
                            <th class="text-end" style="width:100px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Actual Cons.') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Actual Cons. = (Fabric Sent - Retur Kain - Shortage) / Total Qty Cut. Measured consumption per garment piece.') }}"></i>
                            </th>
                            <th class="text-end" style="width:100px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Overcons.') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Overconsumption: variance percentage exceeding standard consumption plan threshold.') }}"></i>
                            </th>
                            <th class="text-end" style="width:140px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Fabric Price') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Fabric Price (IDR): unit cost per meter/yard retrieved from Dynamics 365 purchase agreement.') }}"></i>
                            </th>
                            <th class="text-end" style="width:140px; font-size:.75rem; white-space:nowrap;">
                                {{ __('Deduction') }}
                                <i class="fas fa-info-circle text-muted ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Deduction: penalty cost charged via Debit Note when actual consumption exceeds allowance.') }}"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="cons-rows">
                        @foreach($fabricLines as $i => $fl)
                            @php
                                $waste = (float) ($fl['retur_kain'] ?? 0) + (float) ($fl['shortage'] ?? 0);
                                $unit = $fl['unit'] ?? null;
                                // Unit stays on the same line as its value (not a line of its
                                // own) so a row reads in one glance instead of wrapping to two.
                                $unitTag = fn ($u) => $u ? ' <span class="cons-unit">'.e($u).'</span>' : '';
                                // Secondary/reference figures (Material Issue, receive date,
                                // price source, QC override) move behind a small info icon
                                // instead of a second visible line — keeps the primary number
                                // the only thing you have to read, detail is one hover away.
                                $refIcon = fn ($title, $icon = 'fa-circle-info', $color = 'text-muted') => $title
                                    ? ' <i class="fas '.$icon.' '.$color.' cons-ref-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="'.e($title).'"></i>'
                                    : '';
                            @endphp
                            {{-- Fabric description on its own full-width line, calculation columns below --}}
                            @php $displayLabel = $fl['display_label'] ?? $fl['label']; @endphp
                            <tr class="table-light cons-fabric-banner">
                                <td colspan="13" class="py-2">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="fw-semibold small"><i class="fas fa-scroll text-secondary me-1"></i>{{ $displayLabel }}</span>
                                        @if(! empty($fl['item_number']))
                                            <span class="badge rounded-pill text-bg-light border fw-normal" style="font-size:.68rem;">Item {{ $fl['item_number'] }}</span>
                                        @endif
                                        @if(! empty($fl['inventory_group']))
                                            <span class="badge rounded-pill text-bg-light border fw-normal" style="font-size:.68rem;">{{ $fl['inventory_group'] }}</span>
                                        @endif
                                    </div>
                                    @if($editable)
                                        <input type="hidden" name="fabrics[{{ $i }}][label]" value="{{ $fl['label'] }}">
                                    @endif
                                </td>
                            </tr>
                            <tr class="cons-data-row" data-waste="{{ $waste }}">
                                @foreach(['short_roll','sisa_kain','kepala_kain','retur_kain','shortage'] as $rc)
                                    <td class="text-end">
                                        @if($editable)
                                            <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                                   class="form-control form-control-sm text-end cons-waste{{ $rc === 'retur_kain' ? ' cons-retur' : ($rc === 'shortage' ? ' cons-shortage' : '') }}" style="width:84px;"
                                                   name="fabrics[{{ $i }}][{{ $rc }}]"
                                                   value="{{ old('fabrics.'.$i.'.'.$rc, $fl[$rc] ?? 0) }}" placeholder="0">
                                            {!! $unitTag($unit) !!}
                                            @if($rc === 'retur_kain' && ($fl['retur_kain_source'] ?? null) === 'qc_console')
                                                {!! $refIcon('Measured by QC on the console; overrides the vendor-entered value.', 'fa-clipboard-check', 'text-info') !!}
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
                                        {!! $refIcon('Received as of '.\Illuminate\Support\Carbon::parse($fl['goods_receive_date'])->format('d M Y').' (D365 packing slip).') !!}
                                    @endif
                                </td>
                                @php $vsmLinked = $fl['vsm_linked'] ?? true; @endphp
                                <td class="text-end">
                                    @if($editable && ! $vsmLinked)
                                        <input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()"
                                               class="form-control form-control-sm text-end cons-sent" style="width:110px;background:#fffaf0;border-color:#f0c000;font-weight:600;"
                                               name="fabrics[{{ $i }}][fabric_sent]"
                                               value="{{ old('fabrics.'.$i.'.fabric_sent', $fl['fabric_sent'] ?? '') }}" placeholder="0">
                                        {!! $unitTag($unit) !!}
                                    @else
                                        {{-- VSM-linked fabric: Fabric Sent is pinned to Goods Receive, not typed. --}}
                                        <span class="fw-semibold cons-sent" data-value="{{ $fl['goods_receive'] ?? '' }}">{{ $fmt2($fl['goods_receive'] ?? null) }}</span>
                                        {!! $unitTag($unit) !!}
                                    @endif
                                    @if(($fl['fabric_sent_issue'] ?? null) !== null)
                                        <div class="text-muted" style="font-size:.72rem;line-height:1.3;" title="Material Issue (D365 production journal), reference only.">Material Issue: {{ $fmt2($fl['fabric_sent_issue']) }}</div>
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
                                        {!! $refIcon($fl['fabric_price_source'] ?? null) !!}
                                    @else
                                        <span class="fw-semibold">{{ $fmt2($fl['fabric_price']) }}</span>
                                        {!! $unitTag($fl['fabric_price'] !== null && $unit ? 'Rp / '.$unit : null) !!}
                                        {!! $refIcon($fl['fabric_price_source'] ?? null) !!}
                                    @endif
                                </td>
                                <td class="text-end fw-semibold cons-ded" style="white-space:nowrap;">{{ $money($fl['deduction']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="12" class="text-end fw-semibold">Total Deduction</td>
                            <td class="text-end fw-bold cons-ded-total" style="white-space:nowrap;">{{ $money($totalDeduction) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($editable)
                    {{-- Per-column meaning (what each field IS) lives on the group
                         headers above (hover the ⓘ next to each group) instead of
                         one paragraph down here disconnected from the data it
                         describes. What's left here is the brief formula recap —
                         how Cutt Plan/Actual Cons./Overconsumption/Deduction are
                         actually computed — since that's worth seeing without
                         hunting across 4 separate tooltips, plus the one fact
                         that isn't a "what does this column mean" question. --}}
                    @php
                        // Aligned label column + monospace expression, one
                        // formula per line — reads as a system of equations
                        // (the "=" signs line up) instead of prose sentences
                        // run together with periods.
                        $formulaMono = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color:#3c4248;';
                    @endphp
                    <div class="form-text mt-2">
                        <div class="d-flex gap-2 mb-1">
                            <span class="fw-semibold text-nowrap" style="min-width:108px;">{{ __('Cutt Plan') }}</span>
                            <span style="{{ $formulaMono }}">= ROUNDDOWN((Fabric Sent − Retur Kain − Shortage) ÷ Cons. Plan)</span>
                        </div>
                        <div class="d-flex gap-2 mb-1">
                            <span class="fw-semibold text-nowrap" style="min-width:108px;">{{ __('Actual Cons.') }}</span>
                            <span style="{{ $formulaMono }}">= (Fabric Sent − Retur Kain − Shortage) ÷ Total Qty Cut ({{ $fmt2($totalCut) }})</span>
                        </div>
                        <div class="d-flex gap-2 mb-1">
                            <span class="fw-semibold text-nowrap" style="min-width:108px;">{{ __('Overconsumption') }}</span>
                            <span style="{{ $formulaMono }}">= (Actual Cons. − Cons. Plan) ÷ Cons. Plan</span>
                        </div>
                        <div class="d-flex gap-2 mb-1">
                            <span class="fw-semibold text-nowrap" style="min-width:108px;">{{ __('Deduction') }}</span>
                            <span style="{{ $formulaMono }}">= MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price <span class="text-muted" style="font-family:inherit;">({{ __('only above 3%') }})</span></span>
                        </div>
                        <div class="text-muted mt-1">{{ __('Saved when you approve the cutting report.') }}</div>
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
                        // Fabric Sent is either a typed input (manual "Add fabric" row,
                        // no Goods Receive to pin to) or a read-only span carrying the
                        // pinned Goods Receive value in data-value.
                        const sentEl = tr.querySelector('.cons-sent');
                        const fs = sentEl ? parseFloat(sentEl.tagName === 'INPUT' ? sentEl.value : sentEl.dataset.value) : NaN;
                        const cp = parseFloat(tr.querySelector('.cons-plan')?.value);
                        const price = parseFloat(tr.querySelector('.cons-price')?.value);
                        // Waste subtracted from Fabric Sent = Retur Kain + Shortage. The
                        // other three columns are still captured but don't reduce consumption.
                        const returEl = tr.querySelector('.cons-retur');
                        const shortageEl = tr.querySelector('.cons-shortage');
                        const waste = (returEl ? (parseFloat(returEl.value) || 0) : (parseFloat(tr.dataset.waste) || 0))
                            + (shortageEl ? (parseFloat(shortageEl.value) || 0) : 0);
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
                            labelRow.innerHTML = '<td colspan="12" class="py-2">'
                                + '<input type="text" class="form-control form-control-sm d-inline-block" style="max-width:420px;" name="fabrics[' + i + '][label]" placeholder="Fabric description" required>'
                                + '</td><td class="text-end py-2"><button type="button" class="btn btn-sm btn-outline-danger cons-remove" title="Remove"><i class="fas fa-times"></i></button></td>';

                            const dataRow = document.createElement('tr');
                            dataRow.className = 'cons-data-row';
                            dataRow.dataset.waste = '0';
                            let cells = '';
                            ['short_roll', 'sisa_kain', 'kepala_kain', 'retur_kain', 'shortage'].forEach(function (c) {
                                cells += '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end cons-waste' + (c === 'retur_kain' ? ' cons-retur' : (c === 'shortage' ? ' cons-shortage' : '')) + '" style="width:84px;" name="fabrics[' + i + '][' + c + ']" value="0"></td>';
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

        @if($showAccessory)
            <hr class="my-3">
            @include('subcon.partials.material-recon-accessory', [
                'accessoryLines' => $accessoryLines ?? [],
                'accessoryRecon' => $accessoryRecon ?? collect(),
                'accessoryGoodsReceive' => $accessoryGoodsReceive,
                'accessoryIssue' => $accessoryIssue,
                'accessoryClaimByLabel' => $accessoryClaimByLabel,
                'editable' => $accessoryEditable,
            ])

            @if(! empty($accessoryClaimByLabel))
                <div class="d-flex justify-content-end align-items-center gap-3 mt-2 pt-2 border-top">
                    <div class="text-muted small">
                        Accessory Retur Shortfall
                        <span class="fst-italic" title="Not live — Expected Retur needs D365 consumption data with no client-side formula. Recalculates after you Save.">(as of last save)</span>
                    </div>
                    <div class="fw-semibold">{{ $money($accessoryClaimTotal) }}</div>
                </div>
                <div class="d-flex justify-content-end align-items-center gap-3 mt-1">
                    <div class="fw-semibold">Grand Total (Fabric + Accessory)</div>
                    <div class="fw-bold text-danger cons-grand-total">{{ $money($totalDeduction + $accessoryClaimTotal) }}</div>
                </div>
                @if($editable)
                    <script>
                    (function () {
                        // Static — the accessory claim has no client-side formula (see
                        // the docblock above), so re-adding this fixed figure on top
                        // of the live fabric sum is the most the Grand Total can do
                        // without a page reload.
                        const accessoryClaimTotal = {{ (float) $accessoryClaimTotal }};
                        function fmtMoney(n) {
                            return 'Rp ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                        const fabricTotalEl = document.querySelector('.cons-ded-total');
                        const grandTotalEl = document.querySelector('.cons-grand-total');
                        if (! fabricTotalEl || ! grandTotalEl) return;
                        const observer = new MutationObserver(function () {
                            const fabricTotal = parseFloat(fabricTotalEl.textContent.replace(/[^0-9.-]/g, '')) || 0;
                            grandTotalEl.textContent = fmtMoney(fabricTotal + accessoryClaimTotal);
                        });
                        observer.observe(fabricTotalEl, { childList: true, characterData: true, subtree: true });
                    })();
                    </script>
                @endif
            @endif
        @endif
    </div>
</div>
