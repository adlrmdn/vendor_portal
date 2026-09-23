{{--
    Accessory return-quantity sub-section. Standalone (no own <form>/card
    wrapper) so it can be nested inside a parent card at any hierarchy level —
    under fabric-reconciliation.blade.php's Fabric group (the vendor's own
    order page) or under consumption-input.blade.php's "Material Reconciliation
    & Consumption" card (the Final Approval / Report Validation form), so it
    always reads as "Fabric" / "Accessory" sub-sections of one parent, never a
    second unrelated card.

    Params:
      $accessoryLines  array from SubconProductionService::accessoryLinesForPo()
      $accessoryRecon  Collection of MaterialReturnLine (item_type=accessory) keyed by label
      $accessoryGoodsReceive  array from SubconProductionService::resolveGoodsReceipts($accessoryLines), keyed by label
      $accessoryIssue  array from SubconProductionService::materialIssueForOrder(), keyed by ItemNumber (not label)
      $accessoryClaimByLabel  array keyed by label (the same 'label' persistence key as
                       $accessoryRecon, NOT display_label) — expected_retur/balance/claim
                       per accessory, from QcReportPdfService's accessoryBreakdown. Optional;
                       when empty (e.g. the vendor's own cutting-report page, before a
                       QC-console project/session exists — HO hasn't priced/reviewed
                       anything yet), the Expected Retur/Balance columns are omitted
                       entirely rather than shown empty.
      $editable        bool
      $returQtyOnly    bool — false (default). true: this is the vendor's own view
                       (fabric-reconciliation.blade.php, vendor-only — see that file).
                       The vendor only ever reports how much they're returning:
                         - Goods Receive and Claim are dropped entirely (not vendor's
                           concern / not vendor's business to see what they'd be
                           charged before HO has even reviewed it).
                         - Mats Sent and Price render read-only always, even when
                           $editable — those are admin/HO-entered figures at Final
                           Approval / Report Validation, never vendor-set.
                         - Retur Qty is the ONLY real <input>, when $editable.
                         - Expected Retur / Balance still show (informational) when
                           $accessoryClaimByLabel has data, so the vendor can see
                           what's expected before entering their own figure.
                         - No "Add accessory" button, no per-row remove — the vendor
                           reports against the existing list, never adds a priced item.
                       consumption-input.blade.php (HO's form) leaves this false, and
                       keeps the original full-edit shape (Goods Receive/Mats Sent/
                       Price/Claim, add/remove rows).
    "Mats Sent" (qty_sent) mirrors fabric's fabric_sent: admin-entered at
    Final Approval / Report Validation, null until then. "Price" (unit_price)
    and "Goods Receive" mirror fabric's fabric_price / goods_receive the same
    way: Price is admin-editable (saved value wins, else the VSM-derived
    reference from accessoryLinesForPo()'s unit_price), Goods Receive is
    read-only D365 receipt data. "mats_sent_issue" is the D365 Material Issue
    posting's proposal quantity ($accessoryIssue) — a prefill/placeholder only
    (shown when no admin qty_sent is saved yet), never persisted itself; only
    what the admin actually saves to qty_sent is persisted.
--}}
@php
    $accessoryLines = $accessoryLines ?? [];
    $accessoryRecon = $accessoryRecon ?? collect();
    $accessoryGoodsReceive = $accessoryGoodsReceive ?? [];
    $accessoryIssue = $accessoryIssue ?? [];
    $accessoryClaimByLabel = $accessoryClaimByLabel ?? [];
    $showClaim = ! empty($accessoryClaimByLabel);
    $editable = $editable ?? false;
    $returQtyOnly = $returQtyOnly ?? false;
    // The vendor never sees Claim (money owed) — only Price/HO review makes
    // that meaningful — nor Goods Receive (not their concern). Expected
    // Retur/Balance (quantity only) still show when available.
    $showExpectedBalance = $showClaim;

    // Sum the D365 Material Issue "proposal" qty across every item number a
    // grouped accessory line carries (accessoryLinesForPo() joins them with
    // ", " into item_number) — same split QcReportPdfService's buildAccRow
    // uses. Returns null when none of the item numbers have a posting.
    $sumIssueProposal = function (?string $itemNumberCsv) use ($accessoryIssue) {
        $sum = null;
        foreach (array_filter(array_map('trim', explode(',', (string) $itemNumberCsv))) as $it) {
            if (isset($accessoryIssue[$it])) {
                $sum = ($sum ?? 0) + $accessoryIssue[$it]['proposal'];
            }
        }

        return $sum;
    };

    $accVsmLabels = array_map(fn ($al) => $al['label'], $accessoryLines);
    $accSeed = [];
    foreach ($accessoryLines as $al) {
        $rec = $accessoryRecon->get($al['label']);
        $accSeed[] = [
            'label' => $al['label'],
            'display_label' => $al['display_label'] ?? $al['label'],
            'item_number' => $al['item_number'] ?? null,
            'unit' => $al['unit'] ?: 'PCS',
            'from_vsm' => true,
            'qty' => $rec ? (float) $rec->qty_declared : 0,
            'mats_sent' => $rec ? $rec->qty_sent : null,
            'mats_sent_issue' => $sumIssueProposal($al['item_number'] ?? null),
            'goods_receive' => $accessoryGoodsReceive[$al['label']]['qty'] ?? null,
            'goods_receive_date' => $accessoryGoodsReceive[$al['label']]['date'] ?? null,
            // Admin's saved value wins; otherwise the VSM-derived reference
            // price prefills — same precedence as fabricLinesWithData().
            'price' => $rec && $rec->unit_price !== null ? (float) $rec->unit_price : ($al['unit_price'] ?? null),
            'claim' => $accessoryClaimByLabel[$al['label']] ?? null,
        ];
    }
    foreach ($accessoryRecon as $rec) {
        if (! in_array($rec->label, $accVsmLabels, true)) {
            $accSeed[] = [
                'label' => $rec->label,
                'display_label' => $rec->label,
                'item_number' => null,
                'unit' => $rec->unit ?: 'PCS',
                'from_vsm' => false,
                'qty' => (float) $rec->qty_declared,
                'mats_sent' => $rec->qty_sent,
                // No VSM label/item_number to look up against for a
                // manually-added row — no Goods Receive/reference-price/
                // Material Issue link exists for it.
                'mats_sent_issue' => null,
                'goods_receive' => null,
                'goods_receive_date' => null,
                'price' => $rec->unit_price,
                'claim' => $accessoryClaimByLabel[$rec->label] ?? null,
            ];
        }
    }

    // Small muted unit tag under a value — consistent across the fabric partials.
    $accUnitTag = fn ($u) => $u ? '<div class="text-muted" style="font-size:.72rem;line-height:1.3;">'.e($u).'</div>' : '';
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing:.05em;">
        <i class="fas fa-shapes me-1"></i> Accessory
    </div>
    @if($editable)
        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-recon-accessory"><i class="fas fa-plus me-1"></i> {{ __('Add accessory') }}</button>
    @endif
</div>

@if(! $editable && empty($accSeed))
    <div class="text-muted small">{{ __('No accessory return quantities declared.') }}</div>
@else
    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th style="min-width:240px;">{{ __('Item') }}</th>
                    @if(! $returQtyOnly)
                        <th class="text-end" style="width:120px;">{{ __('Goods Receive') }}</th>
                    @endif
                    <th class="text-end" style="width:120px;">{{ __('Mats Sent') }}</th>
                    <th class="text-end" style="width:120px;">{{ __('Retur Qty') }}</th>
                    @if($returQtyOnly)
                        @if($showExpectedBalance)
                            <th class="text-end" style="width:110px;">{{ __('Expected Retur') }}</th>
                            <th class="text-end" style="width:100px;">{{ __('Balance') }}</th>
                        @endif
                    @else
                        <th class="text-end" style="width:130px;">{{ __('Price') }}</th>
                        @if($showClaim)
                            <th class="text-end" style="width:110px;">{{ __('Expected Retur') }}</th>
                            <th class="text-end" style="width:100px;">{{ __('Balance') }}</th>
                            <th class="text-end" style="width:120px;">{{ __('Claim') }}</th>
                        @endif
                    @endif
                    @if($editable)<th style="width:40px;"></th>@endif
                </tr>
            </thead>
            <tbody id="recon-accessory-rows">
                @foreach($accSeed as $i => $a)
                    @php($estConsumption = $a['claim']['est_consumption'] ?? null)
                    <tr class="recon-acc-row" @if($estConsumption !== null) data-est-consumption="{{ $estConsumption }}" @endif>
                        <td>
                            @if($a['from_vsm'])
                                <span class="small fw-semibold">{{ $a['display_label'] }}</span>
                                @if(! empty($a['item_number']))
                                    <span class="badge rounded-pill text-bg-light border fw-normal" style="font-size:.66rem;">Item {{ $a['item_number'] }}</span>
                                @endif
                                @if($editable)
                                    <input type="hidden" name="accessories_recon[{{ $i }}][label]" value="{{ $a['label'] }}">
                                    <input type="hidden" name="accessories_recon[{{ $i }}][unit]" value="{{ $a['unit'] }}">
                                @endif
                            @elseif($editable && ! $returQtyOnly)
                                <input type="text" class="form-control form-control-sm" name="accessories_recon[{{ $i }}][label]"
                                       value="{{ old('accessories_recon.'.$i.'.label', $a['label']) }}" placeholder="Accessory description" required>
                            @else
                                <div class="small fw-semibold">{{ $a['label'] }}</div>
                                @if($editable)
                                    {{-- returQtyOnly: a pre-existing manual (non-VSM) row still
                                         needs its label/unit to post so the qty below matches it —
                                         just not editable as free text. --}}
                                    <input type="hidden" name="accessories_recon[{{ $i }}][label]" value="{{ $a['label'] }}">
                                    <input type="hidden" name="accessories_recon[{{ $i }}][unit]" value="{{ $a['unit'] }}">
                                @endif
                            @endif
                        </td>
                        @if(! $returQtyOnly)
                            <td class="text-end">
                                <span class="text-muted">{{ $a['goods_receive'] !== null ? number_format((float) $a['goods_receive'], 2) : '—' }}</span>
                                {!! $accUnitTag($a['goods_receive'] !== null ? ($a['unit'] ?? null) : null) !!}
                                @if(! empty($a['goods_receive_date']))
                                    <div class="text-muted small">as of {{ \Illuminate\Support\Carbon::parse($a['goods_receive_date'])->format('d M Y') }}</div>
                                @endif
                            </td>
                        @endif
                        <td class="text-end">
                            @if($editable && ! $returQtyOnly)
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       onfocus="if(!parseFloat(this.value))this.select()"
                                       class="form-control form-control-sm text-end d-inline-block recon-acc-mats-sent" style="width:100px;"
                                       name="accessories_recon[{{ $i }}][mats_sent]" value="{{ old('accessories_recon.'.$i.'.mats_sent', $a['mats_sent'] ?? $a['mats_sent_issue'] ?? '') }}" placeholder="0">
                                {!! $accUnitTag($a['unit'] ?? null) !!}
                                @if($a['mats_sent'] === null && $a['mats_sent_issue'] !== null)
                                    <div class="text-muted" style="font-size:.72rem;line-height:1.3;">Material Issue: {{ number_format($a['mats_sent_issue'], 2) }}</div>
                                @endif
                            @else
                                @php($accMatsSentShown = $a['mats_sent'] ?? $a['mats_sent_issue'])
                                <span class="fw-semibold">{{ $accMatsSentShown !== null ? number_format((float) $accMatsSentShown, 2) : '—' }}</span>
                                {!! $accUnitTag($a['unit'] ?? null) !!}
                                @if($a['mats_sent'] === null && $a['mats_sent_issue'] !== null)
                                    <div class="text-muted small fst-italic">(Material Issue)</div>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">
                            @if($editable)
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       onfocus="if(!parseFloat(this.value))this.select()"
                                       class="form-control form-control-sm text-end d-inline-block recon-acc-qty" style="width:100px;"
                                       name="accessories_recon[{{ $i }}][qty]" value="{{ old('accessories_recon.'.$i.'.qty', $a['qty']) }}" placeholder="0">
                                @if($a['from_vsm'] || $returQtyOnly)
                                    {!! $accUnitTag($a['unit'] ?? null) !!}
                                @else
                                    <input type="text" class="form-control form-control-sm d-inline-block mt-1" style="width:100px;"
                                           name="accessories_recon[{{ $i }}][unit]" value="{{ old('accessories_recon.'.$i.'.unit', $a['unit']) }}" placeholder="Unit">
                                @endif
                            @else
                                <span class="fw-semibold">{{ number_format((float) $a['qty'], 2) }}</span>
                                {!! $accUnitTag($a['unit'] ?? null) !!}
                            @endif
                        </td>
                        @if($returQtyOnly && $showExpectedBalance)
                            @php($c = $a['claim'])
                            <td class="text-end text-muted">
                                {{ $c && $c['expected_retur'] !== null ? number_format((float) $c['expected_retur'], 2) : '—' }}
                                {!! $accUnitTag($c && $c['expected_retur'] !== null ? ($a['unit'] ?? null) : null) !!}
                            </td>
                            <td class="text-end {{ $c && ($c['balance'] ?? 0) < 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ $c && $c['balance'] !== null ? number_format((float) $c['balance'], 2) : '—' }}
                            </td>
                        @endif
                        @if(! $returQtyOnly)
                            <td class="text-end">
                                @if($editable)
                                    <input type="number" step="0.01" min="0" inputmode="decimal"
                                           onfocus="if(!parseFloat(this.value))this.select()"
                                           class="form-control form-control-sm text-end d-inline-block" style="width:110px;"
                                           name="accessories_recon[{{ $i }}][price]" value="{{ old('accessories_recon.'.$i.'.price', $a['price'] ?? '') }}" placeholder="0">
                                    {!! $accUnitTag('Rp / '.($a['unit'] ?: 'pc')) !!}
                                @else
                                    <span class="fw-semibold">{{ $a['price'] !== null ? number_format((float) $a['price'], 2) : '—' }}</span>
                                    {!! $accUnitTag($a['price'] !== null ? 'Rp / '.($a['unit'] ?: 'pc') : null) !!}
                                @endif
                            </td>
                        @endif
                        @if(! $returQtyOnly && $showClaim)
                            @php($c = $a['claim'])
                            <td class="text-end text-muted">
                                <span class="recon-acc-expected-retur">{{ $c && $c['expected_retur'] !== null ? number_format((float) $c['expected_retur'], 2) : '—' }}</span>
                                {!! $accUnitTag($c && $c['expected_retur'] !== null ? ($a['unit'] ?? null) : null) !!}
                            </td>
                            <td class="text-end recon-acc-balance-cell {{ $c && ($c['balance'] ?? 0) < 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                <span class="recon-acc-balance">{{ $c && $c['balance'] !== null ? number_format((float) $c['balance'], 2) : '—' }}</span>
                            </td>
                            <td class="text-end {{ $c && ($c['claim'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ $c && $c['claim'] !== null ? 'Rp '.number_format((float) $c['claim'], 2) : '—' }}
                            </td>
                        @endif
                        @if($editable)
                            <td class="text-end">
                                @unless($a['from_vsm'])
                                    <button type="button" class="btn btn-sm btn-outline-danger recon-accessory-remove" title="Remove"><i class="fas fa-times"></i></button>
                                @endunless
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($editable)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accRows = document.getElementById('recon-accessory-rows');
    const accAddBtn = document.getElementById('add-recon-accessory');
    if (! accRows || ! accAddBtn) return;

    let aIdx = {{ count($accSeed) }};
    const isReturOnly = {{ $returQtyOnly ? 'true' : 'false' }};

    accAddBtn.addEventListener('click', function () {
        const i = aIdx++;
        const tr = document.createElement('tr');
        if (isReturOnly) {
            tr.innerHTML =
                '<td><input type="text" class="form-control form-control-sm" name="accessories_recon[' + i + '][label]" placeholder="{{ __('Accessory description') }}" required></td>' +
                '<td class="text-end text-muted">—</td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="accessories_recon[' + i + '][qty]" value="0">' +
                    '<input type="text" class="form-control form-control-sm d-inline-block mt-1" style="width:100px;" name="accessories_recon[' + i + '][unit]" value="PCS" placeholder="Unit"></td>' +
                {!! $showExpectedBalance ? "'<td class=\"text-end text-muted\">—</td><td class=\"text-end text-muted\">—</td>' +" : "'' +" !!}
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger recon-accessory-remove" title="Remove"><i class="fas fa-times"></i></button></td>';
        } else {
            tr.innerHTML =
                '<td><input type="text" class="form-control form-control-sm" name="accessories_recon[' + i + '][label]" placeholder="Accessory description" required></td>' +
                '<td class="text-end text-muted">—</td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="accessories_recon[' + i + '][mats_sent]" value="0"></td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="accessories_recon[' + i + '][qty]" value="0">' +
                    '<input type="text" class="form-control form-control-sm d-inline-block mt-1" style="width:100px;" name="accessories_recon[' + i + '][unit]" value="PCS" placeholder="Unit"></td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:110px;" name="accessories_recon[' + i + '][price]" value="0"></td>' +
                {!! $showClaim ? "'<td class=\"text-end text-muted\">—</td><td class=\"text-end text-muted\">—</td><td class=\"text-end text-muted\">—</td>' +" : "'' +" !!}
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger recon-accessory-remove" title="Remove"><i class="fas fa-times"></i></button></td>';
        }
        accRows.appendChild(tr);
    });

    accRows.addEventListener('click', function (e) {
        const btn = e.target.closest('.recon-accessory-remove');
        if (btn) btn.closest('tr').remove();
    });

    // Live "what if" estimation: as HO edits Mats Sent or Retur Qty, recompute
    // Expected Retur / Balance right away instead of only after a save. Each
    // row's own Est. Consumption (data-est-consumption) is a D365 BOM-rate ×
    // cutting-qty figure baked in server-side — it has no client-side formula
    // and does NOT depend on what's typed here, so it's held constant while
    // Mats Sent/Retur Qty change. Same formula as QcReportPdfService's
    // buildAccRow (Expected Retur = max(0, Mats Sent − Est. Consumption);
    // Balance = Retur Qty − Expected Retur), so this preview can't drift from
    // what actually gets computed/saved server-side on submit. Rows with no
    // baseline (no data-est-consumption — e.g. no QC-console session yet, or
    // this specific item never resolved a D365 consumption rate) are left
    // showing their last-saved "—" untouched; there's nothing to estimate from.
    function fmtAccQty(n) {
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function recomputeAccRow(tr) {
        const estConsumption = parseFloat(tr.dataset.estConsumption);
        if (isNaN(estConsumption)) return;

        const matsSentEl = tr.querySelector('.recon-acc-mats-sent');
        const expectedEl = tr.querySelector('.recon-acc-expected-retur');
        if (! matsSentEl || ! expectedEl) return;

        const matsSent = parseFloat(matsSentEl.value);
        const balanceCell = tr.querySelector('.recon-acc-balance-cell');
        const balanceEl = tr.querySelector('.recon-acc-balance');
        if (isNaN(matsSent)) {
            expectedEl.textContent = '—';
            if (balanceEl) balanceEl.textContent = '—';

            return;
        }

        const expected = Math.max(0, matsSent - estConsumption);
        expectedEl.textContent = fmtAccQty(expected);

        const qtyEl = tr.querySelector('.recon-acc-qty');
        if (! qtyEl || ! balanceEl || ! balanceCell) return;
        const returQty = parseFloat(qtyEl.value);
        if (isNaN(returQty)) {
            balanceEl.textContent = '—';

            return;
        }
        const balance = returQty - expected;
        balanceEl.textContent = fmtAccQty(balance);
        balanceCell.classList.toggle('text-danger', balance < 0);
        balanceCell.classList.toggle('fw-semibold', balance < 0);
        balanceCell.classList.toggle('text-muted', balance >= 0);
    }
    accRows.addEventListener('input', function (e) {
        if (e.target.matches('.recon-acc-mats-sent, .recon-acc-qty')) {
            recomputeAccRow(e.target.closest('tr'));
        }
    });
});
</script>
@endif
