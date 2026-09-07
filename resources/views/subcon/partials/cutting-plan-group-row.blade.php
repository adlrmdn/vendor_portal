{{--
    One ratio-group's 3 rows (input row + computed Qty Marker + computed Sisa
    Marker) inside a cutting-plan block's groups table. The JS in
    cutting-plan.blade.php relies on these being exactly 3 sibling <tr>s
    (tr.nextElementSibling / .nextElementSibling) — don't add rows in between.
    Params: $bi, $gi (index or '__GI__'), $group (array|null),
            $computedGroup (array|null), $sizes (array of size codes)
--}}
@php
    $n = fn ($v) => number_format((float) ($v ?? 0));
    $rasio = $group['rasio'] ?? [];
    $qtyMarker = $computedGroup['qty_marker'] ?? [];
    $sisaMarker = $computedGroup['sisa_marker'] ?? [];
    $letter = is_numeric($gi) ? chr(65 + (int) $gi) : '';
@endphp
<tr class="plan-group" data-group-index="{{ $gi }}">
    <td class="fw-semibold group-letter">{{ $letter }}</td>
    @foreach($sizes as $size)
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end group-rasio"
                   name="blocks[{{ $bi }}][groups][{{ $gi }}][rasio][{{ $size }}]" value="{{ $rasio[$size] ?? '' }}" data-size="{{ $size }}" placeholder="0">
        </td>
    @endforeach
    <td class="text-end group-ratio-total fw-semibold">{{ $n(array_sum($rasio)) }}</td>
    <td>
        <input type="number" step="1" min="0" class="form-control form-control-sm text-end group-jml-layer"
               name="blocks[{{ $bi }}][groups][{{ $gi }}][jml_layer]" value="{{ $group['jml_layer'] ?? '' }}" placeholder="0">
    </td>
    <td>
        <input type="number" step="0.001" min="0" class="form-control form-control-sm text-end group-marker-length"
               name="blocks[{{ $bi }}][groups][{{ $gi }}][marker_length]" value="{{ $group['marker_length'] ?? '' }}" placeholder="0.000">
    </td>
    <td class="text-end group-keb-fabric">{{ $n($computedGroup['keb_fabric'] ?? 0) }}</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-group" title="Remove group"><i class="fas fa-times"></i></button></td>
</tr>
<tr class="plan-group-computed small text-muted">
    <td class="text-end">Qty Marker →</td>
    @foreach($sizes as $size)<td class="text-end group-qty-marker" data-size="{{ $size }}">{{ $n($qtyMarker[$size] ?? 0) }}</td>@endforeach
    <td colspan="4"></td>
</tr>
<tr class="plan-group-computed small text-muted border-bottom border-2">
    <td class="text-end">Sisa Marker →</td>
    @foreach($sizes as $size)<td class="text-end group-sisa-marker" data-size="{{ $size }}">{{ $n($sisaMarker[$size] ?? 0) }}</td>@endforeach
    <td colspan="4"></td>
</tr>
