{{--
    Per-fabric reconciliation (leftover fabric measured at cutting). One row per
    fabric type, identified by its label (description + unit). Editable on the
    cutting report — where the vendor may also add any fabric VSM does not carry —
    and read-only elsewhere. "Sisa Kain" is always labelled "Sisa Kain (Utuh)".
    Params:
      $fabricLines  array from SubconProductionService::fabricLinesForPo()
      $fabricRecon  Collection of SubconFabricReconciliation keyed by label
      $editable     bool
--}}
@php
    $editable = $editable ?? false;
    $fabricLines = $fabricLines ?? [];
    $fabricRecon = $fabricRecon ?? collect();
    $cols = ['short_roll' => 'Short Roll', 'sisa_kain' => 'Sisa Kain (Utuh)', 'kepala_kain' => 'Kepala Kain', 'retur_kain' => 'Retur Kain'];

    // Merge VSM fabric lines with any recon rows VSM does not carry (fabrics the
    // vendor added previously), so both persist across edits. VSM-sourced rows
    // keep a fixed label; vendor-added rows have an editable label.
    $vsmLabels = array_map(fn ($fl) => $fl['label'], $fabricLines);
    $seed = [];
    foreach ($fabricLines as $fl) {
        $rec = $fabricRecon->get($fl['label']);
        $seed[] = [
            'label' => $fl['label'],
            'fabric_sent' => (float) ($fl['fabric_sent'] ?? 0),
            'from_vsm' => true,
            'short_roll' => $rec ? (float) $rec->short_roll : 0,
            'sisa_kain' => $rec ? (float) $rec->sisa_kain : 0,
            'kepala_kain' => $rec ? (float) $rec->kepala_kain : 0,
            'retur_kain' => $rec ? (float) $rec->retur_kain : 0,
        ];
    }
    foreach ($fabricRecon as $rec) {
        if (! in_array($rec->label, $vsmLabels, true)) {
            $seed[] = [
                'label' => $rec->label,
                'fabric_sent' => null,
                'from_vsm' => false,
                'short_roll' => (float) $rec->short_roll,
                'sisa_kain' => (float) $rec->sisa_kain,
                'kepala_kain' => (float) $rec->kepala_kain,
                'retur_kain' => (float) $rec->retur_kain,
            ];
        }
    }
@endphp

<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold small text-secondary text-uppercase" style="letter-spacing:.05em;">
                <i class="fas fa-ruler-horizontal me-1"></i> Fabric Reconciliation
                <span class="text-muted fw-normal text-lowercase">(per fabric — meters/native unit)</span>
            </div>
            @if($editable)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-recon-fabric"><i class="fas fa-plus me-1"></i> Add fabric</button>
            @endif
        </div>

        @if(! $editable && empty($seed))
            <div class="text-muted small">No linked fabric found in VSM for this style, so there are no fabrics to reconcile.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:240px;">Fabric</th>
                            @foreach($cols as $label)
                                <th class="text-end" style="width:120px; font-size:.75rem; white-space:nowrap;">{{ $label }}</th>
                            @endforeach
                            @if($editable)<th style="width:40px;"></th>@endif
                        </tr>
                    </thead>
                    <tbody id="recon-rows">
                        @foreach($seed as $i => $s)
                            <tr>
                                <td>
                                    @if($s['from_vsm'])
                                        <div class="small fw-semibold">{{ $s['label'] }}</div>
                                        @if($editable)
                                            <input type="hidden" name="fabrics_recon[{{ $i }}][label]" value="{{ $s['label'] }}">
                                        @endif
                                    @elseif($editable)
                                        <input type="text" class="form-control form-control-sm" name="fabrics_recon[{{ $i }}][label]"
                                               value="{{ old('fabrics_recon.'.$i.'.label', $s['label']) }}" placeholder="Fabric description" required>
                                    @else
                                        <div class="small fw-semibold">{{ $s['label'] }}</div>
                                    @endif
                                </td>
                                @foreach($cols as $name => $label)
                                    <td class="text-end">
                                        @if($editable)
                                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                                   class="form-control form-control-sm text-end d-inline-block" style="width:100px;"
                                                   name="fabrics_recon[{{ $i }}][{{ $name }}]"
                                                   value="{{ old('fabrics_recon.'.$i.'.'.$name, $s[$name]) }}"
                                                   placeholder="0">
                                        @else
                                            <span class="fw-semibold">{{ number_format((float) $s[$name], 2) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if($editable)
                                    <td class="text-end">
                                        @unless($s['from_vsm'])
                                            <button type="button" class="btn btn-sm btn-outline-danger recon-remove" title="Remove"><i class="fas fa-times"></i></button>
                                        @endunless
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($editable)
                <div class="form-text mt-1">Add a fabric here if one you used is not listed above.</div>
            @endif
        @endif
    </div>
</div>

@if($editable)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('recon-rows');
    const addBtn = document.getElementById('add-recon-fabric');
    if (! rows || ! addBtn) return;

    // Continue indices after the server-rendered rows so nothing collides.
    let rIdx = {{ count($seed) }};
    const cols = ['short_roll', 'sisa_kain', 'kepala_kain', 'retur_kain'];

    addBtn.addEventListener('click', function () {
        const i = rIdx++;
        const tr = document.createElement('tr');
        let cells = '<td><input type="text" class="form-control form-control-sm" name="fabrics_recon[' + i + '][label]" placeholder="Fabric description" required></td>';
        cols.forEach(function (c) {
            cells += '<td class="text-end"><input type="number" step="0.01" min="0" inputmode="decimal" class="form-control form-control-sm text-end d-inline-block" style="width:100px;" name="fabrics_recon[' + i + '][' + c + ']" value="0"></td>';
        });
        cells += '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger recon-remove" title="Remove"><i class="fas fa-times"></i></button></td>';
        tr.innerHTML = cells;
        rows.appendChild(tr);
    });

    rows.addEventListener('click', function (e) {
        const btn = e.target.closest('.recon-remove');
        if (btn) btn.closest('tr').remove();
    });
});
</script>
@endif
