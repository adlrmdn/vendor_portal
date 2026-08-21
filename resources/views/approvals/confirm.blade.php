@extends('layouts.app')

@section('title', 'Confirm '.($action === 'approve' ? 'Approval' : 'Decline'))
@section('bare', '1')

@php
    $isApprove = $action === 'approve';
    $typeLabel = $amendmentRequest->type === 'partial_shipment' ? 'Partial Shipment' : 'Tolerance Amendment';
@endphp

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body text-center p-5">
                    <div class="mb-3">
                        <i class="fas {{ $isApprove ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-warning' }} fa-3x"></i>
                    </div>
                    <h4 class="fw-bold mb-2">{{ $isApprove ? 'Approve' : 'Decline' }} this {{ strtolower($typeLabel) }} request?</h4>
                    <p class="text-muted mb-4">
                        @if($isApprove)
                            This will update the item's tolerance and notify the vendor.
                        @else
                            This will reject the request and notify the vendor. No changes will be made to the item.
                        @endif
                    </p>

                    <dl class="row small text-start mb-4">
                        <dt class="col-5 text-muted fw-normal">PO Number</dt>
                        <dd class="col-7 fw-semibold">{{ $amendmentRequest->poItem->purchaseOrder->po_number }}</dd>
                        <dt class="col-5 text-muted fw-normal">Item Number</dt>
                        <dd class="col-7">{{ $amendmentRequest->poItem->item_number }}</dd>
                        <dt class="col-5 text-muted fw-normal">Request Type</dt>
                        <dd class="col-7">{{ $typeLabel }}</dd>
                    </dl>

                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <button type="submit" class="btn {{ $isApprove ? 'btn-success' : 'btn-danger' }} px-4">
                            <i class="fas {{ $isApprove ? 'fa-check' : 'fa-circle-xmark' }} me-1"></i> Confirm {{ $isApprove ? 'Approval' : 'Decline' }}
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">If you didn't mean to do this, just close this window — nothing has been recorded yet.</p>
        </div>
    </div>
</div>
@endsection
