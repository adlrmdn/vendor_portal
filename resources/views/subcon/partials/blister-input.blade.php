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
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 bg-light">
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label for="blister_capacity" class="fw-semibold mb-0">
                    <i class="fas fa-box me-1 text-primary"></i> {{ __('Blister Capacity') }}:
                </label>
                <div class="input-group input-group-sm" style="width: 175px;">
                    <input type="number" id="blister_capacity" name="blister_capacity" min="1" step="1" inputmode="numeric"
                           class="form-control"
                           onfocus="if(!parseFloat(this.value))this.select()"
                           oninput="if(/^0\d/.test(this.value))this.value=this.value.replace(/^0+(?=\d)/,'')"
                           value="{{ old('blister_capacity', $order->blister_capacity) }}"
                           placeholder="20"
                           {{ $editable ? '' : 'disabled' }} {{ $required ? 'required' : '' }}>
                    <span class="input-group-text bg-white text-muted">{{ __('pcs / blister') }}</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label for="sack_capacity" class="fw-semibold mb-0">
                    <i class="fas fa-gift me-1 text-success"></i> {{ __('Sack (Karung) Capacity') }}:
                </label>
                <div class="input-group input-group-sm" style="width: 175px;">
                    <input type="number" id="sack_capacity" name="sack_capacity" min="1" step="1" inputmode="numeric"
                           class="form-control"
                           onfocus="if(!parseFloat(this.value))this.select()"
                           oninput="if(/^0\d/.test(this.value))this.value=this.value.replace(/^0+(?=\d)/,'')"
                           value="{{ old('sack_capacity', $sackValue) }}"
                           placeholder="80"
                           {{ $editable ? '' : 'disabled' }}>
                    <span class="input-group-text bg-white text-muted">{{ __('pcs / sack') }}</span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-link p-0 text-secondary text-decoration-none" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="left" title="{{ __('Packaging Capacity') }}" data-bs-content="{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}">
                <i class="fas fa-info-circle text-primary fs-5"></i>
            </button>
            <span class="small text-muted d-none d-lg-inline">{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}</span>
        </div>
    </div>
</div>
