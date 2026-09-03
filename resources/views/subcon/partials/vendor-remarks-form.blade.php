{{--
    Vendor remarks — free notes, no approval, saved anytime, visible to
    approvers. Deliberately placed right after Delivery Note Attachment,
    just before each stage's submit button — placed at the top of the page
    it was getting missed before scrolling down to submit.
    Params: $order
--}}
<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <form method="POST" action="{{ route('subcon.vendor.orders.remarks', $order->id) }}">
            @csrf
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="remarks" class="fw-semibold small text-secondary text-uppercase mb-0" style="letter-spacing:.05em;">
                    <i class="fas fa-comment-dots me-1"></i> Remarks
                </label>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i> Save Remarks</button>
            </div>
            <textarea name="remarks" id="remarks" rows="3" class="form-control form-control-sm" placeholder="Notes for this work order — visible to approvers. No approval needed; save anytime.">{{ old('remarks', $order->remarks) }}</textarea>
        </form>
    </div>
</div>
