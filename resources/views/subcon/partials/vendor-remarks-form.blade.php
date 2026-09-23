{{--
    Vendor remarks — free notes, no approval, saved anytime, visible to
    approvers. Can be placed on Production Stage, Material Recon, etc.
    Params: 
      $order
      $remarksId (optional unique DOM ID)
--}}
@php
    $fieldId = $remarksId ?? 'remarks';
@endphp
<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <form method="POST" action="{{ route('subcon.vendor.orders.remarks', $order->id) }}">
            @csrf
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="{{ $fieldId }}" class="fw-semibold small text-secondary text-uppercase mb-0" style="letter-spacing:.05em;">
                    <i class="fas fa-comment-dots me-1"></i> {{ __('Remarks') }}
                </label>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i> {{ __('Save Remarks') }}</button>
            </div>
            <textarea name="remarks" id="{{ $fieldId }}" rows="3" class="form-control form-control-sm" placeholder="{{ __('Notes for this work order — visible to approvers. No approval needed; save anytime.') }}">{{ old('remarks', $order->remarks) }}</textarea>
        </form>
    </div>
</div>
