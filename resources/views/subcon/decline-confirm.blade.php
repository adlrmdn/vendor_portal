@extends('layouts.app')

@section('title', 'Confirm Rejection')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    <div class="mb-3"><i class="fas fa-triangle-exclamation fa-3x text-warning"></i></div>
                    <h4 class="fw-bold mb-2">Reject the {{ $gate === 'gramasi' ? 'gramasi & blister capacity' : 'cutting report' }}?</h4>
                    <p class="text-muted mb-4">This returns the work order to the vendor for changes. The reason you enter below is shown to the vendor.</p>

                    <dl class="row small text-start mb-4">
                        <dt class="col-5 text-muted fw-normal">Work Order</dt>
                        <dd class="col-7 fw-semibold">{{ $order->order_number }}</dd>
                        @if($order->title)
                            <dt class="col-5 text-muted fw-normal">Style</dt>
                            <dd class="col-7">{{ $order->title }}</dd>
                        @endif
                        <dt class="col-5 text-muted fw-normal">Vendor</dt>
                        <dd class="col-7">{{ $order->vendor?->name ?? '—' }}</dd>
                    </dl>

                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <div class="mb-3 text-start">
                            <label for="reason" class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" rows="3" maxlength="1000" required class="form-control form-control-sm" placeholder="What needs to change before resubmitting?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-circle-xmark me-1"></i> Confirm Rejection
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">If you didn't mean to reject, just close this window — nothing has been recorded yet.</p>
        </div>
    </div>
</div>
@endsection
