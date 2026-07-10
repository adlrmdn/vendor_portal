{{-- Read-only vendor remarks, shown to approvers. Always rendered (even when
     empty) for a consistent approval view. Pass ['remarks' => ...]. --}}
<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <div class="fw-semibold small text-secondary text-uppercase mb-2" style="letter-spacing:.05em;">
            <i class="fas fa-comment-dots me-1"></i> Vendor Remarks
        </div>
        @if(!empty($remarks))
            <div class="small" style="white-space:pre-wrap;">{{ $remarks }}</div>
        @else
            <div class="small text-muted fst-italic">No remarks provided.</div>
        @endif
    </div>
</div>
