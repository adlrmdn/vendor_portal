{{--
    Blister & sack capacity entry — single values that apply across all sizes.
    Visible from the cutting stage but locked until the gramasi stage, where
    blister becomes editable and required (sack stays optional, default 50).
    Params: $order, $editable (bool), $required (bool)
--}}
@php($editable = $editable ?? false)
@php($required = $required ?? false)
{{-- The sack column defaults to 50 in the DB, so show the placeholder (like
     blister) unless a non-default value was explicitly chosen. Empty still
     saves/derives as 50 downstream. --}}
@php($sackValue = ($order->sack_capacity && (int) $order->sack_capacity !== 50) ? $order->sack_capacity : null)
<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body d-flex flex-wrap align-items-start gap-4 p-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label for="blister_capacity" class="fw-semibold mb-0">
                <i class="fas fa-box me-1 text-secondary"></i> Blister Capacity
                <span class="text-muted fw-normal small d-block d-md-inline">(pieces per blister — applies across all sizes)</span>
            </label>
            <input type="number" id="blister_capacity" name="blister_capacity" min="1" step="1" inputmode="numeric"
                   class="form-control form-control-sm" style="width: 140px;"
                   value="{{ old('blister_capacity', $order->blister_capacity) }}"
                   placeholder="e.g. 12"
                   {{ $editable ? '' : 'disabled' }} {{ $required ? 'required' : '' }}>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label for="sack_capacity" class="fw-semibold mb-0">
                <i class="fas fa-gift me-1 text-secondary"></i> Sack (Karung) Capacity
                <span class="text-muted fw-normal small d-block d-md-inline">(pieces per sack — WH Replenish &amp; WH Online)</span>
            </label>
            <input type="number" id="sack_capacity" name="sack_capacity" min="1" step="1" inputmode="numeric"
                   class="form-control form-control-sm" style="width: 140px;"
                   value="{{ old('sack_capacity', $sackValue) }}"
                   placeholder="e.g. 50"
                   {{ $editable ? '' : 'disabled' }}>
        </div>
    </div>
</div>
