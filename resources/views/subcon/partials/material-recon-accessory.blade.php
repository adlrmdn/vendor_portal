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
      $editable        bool
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
    $editable = $editable ?? false;

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
        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-recon-accessory"><i class="fas fa-plus me-1"></i> Add accessory</button>
    @endif
</div>

@if(! $editable && empty($accSeed))
    <div class="text-muted small">No accessory return quantities declared.</div>
@else
    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th style="min-width:240px;">Item</th>
                    <th class="text-end" style="width:120px;">Goods Receive</th>
                    <th class="text-end" style="width:120px;">Mats Sent</th>
                    <th class="text-end" style="width:120px;">Retur Qty</th>
                    <th class="text-end" style="width:130px;">Price</th>
                    @if($editable)<th style="width:40px;"></th>@endif
                </tr>
            </thead>
            <tbody id="recon-accessory-rows">
                @foreach($accSeed as $i => $a)
                    <tr>
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
                            @elseif($editable)
                                <input type="text" class="form-control form-control-sm" name="accessories_recon[{{ $i }}][label]"
                                       value="{{ old('accessories_recon.'.$i.'.label', $a['label']) }}" placeholder="Accessory description" required>
                            @else
                                <div class="small fw-semibold">{{ $a['label'] }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="text-muted">{{ $a['goods_receive'] !== null ? number_format((float) $a['goods_receive'], 2) : '—' }}</span>
                            {!! $accUnitTag($a['goods_receive'] !== null ? ($a['unit'] ?? null) : null) !!}
                            @if(! empty($a['goods_receive_date']))
                                <div class="text-muted small">as of {{ \Illuminate\Support\Carbon::parse($a['goods_receive_date'])->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($editable)
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       onfocus="if(!parseFloat(this.value))this.select()"
                                       class="form-control form-control-sm text-end d-inline-block" style="width:100px;"
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
                                       class="form-control form-control-sm text-end d-inline-block" style="width:100px;"
                                       name="accessories_recon[{{ $i }}][qty]" value="{{ old('accessories_recon.'.$i.'.qty', $a['qty']) }}" placeholder="0">
                                @if($a['from_vsm'])
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

    accAddBtn.addEventListener('click', function () {
        const i = aIdx++;
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm" name="accessories_recon[' + i + '][label]" placeholder="Accessory description" required></td>' +
            '<td class="text-end text-muted">—</td>' +
            '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="accessories_recon[' + i + '][mats_sent]" value="0"></td>' +
            '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="accessories_recon[' + i + '][qty]" value="0">' +
                '<input type="text" class="form-control form-control-sm d-inline-block mt-1" style="width:100px;" name="accessories_recon[' + i + '][unit]" value="PCS" placeholder="Unit"></td>' +
            '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" onfocus="if(!parseFloat(this.value))this.select()" class="form-control form-control-sm text-end d-inline-block" style="width:110px;" name="accessories_recon[' + i + '][price]" value="0"></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger recon-accessory-remove" title="Remove"><i class="fas fa-times"></i></button></td>';
        accRows.appendChild(tr);
    });

    accRows.addEventListener('click', function (e) {
        const btn = e.target.closest('.recon-accessory-remove');
        if (btn) btn.closest('tr').remove();
    });
});
</script>
@endif
