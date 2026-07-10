@extends('layouts.app')

@section('title', 'Cutting Report Approval')
@section('bare', '1')

@section('content')
<div class="container-fluid py-5 px-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill text-bg-warning">Cutting Approval</span>
                    </div>
                    <h4 class="fw-bold mb-1">Cutting Report Approval</h4>
                    <p class="text-muted mb-4">
                        Review the submitted cutting report, enter the fabric consumption
                        (<strong>Fabric Sent</strong> and <strong>Cons. Plan</strong> per fabric), then
                        <strong>Approve</strong>. The consumption is saved and the report approved in one step.
                    </p>

                    <dl class="row small mb-4">
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Work Order</dt>
                        <dd class="col-7 col-sm-9 fw-semibold font-monospace">{{ $order->order_number }}</dd>
                        @if($order->title)
                            <dt class="col-5 col-sm-3 text-muted fw-normal">Style</dt>
                            <dd class="col-7 col-sm-9">{{ $order->title }}</dd>
                        @endif
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Vendor</dt>
                        <dd class="col-7 col-sm-9">{{ $order->vendor?->name ?? '—' }}</dd>
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Total Qty Cut</dt>
                        <dd class="col-7 col-sm-9">{{ number_format($totalCut) }} pcs</dd>
                    </dl>

                    @include('subcon.partials.remarks', ['remarks' => $order->remarks ?? null])

                    @include('subcon.partials.production-detail', [
                        'productionGroups' => $productionGroups ?? [],
                        'cuttingReports' => $cuttingReports ?? collect(),
                        'mode' => 'view',
                    ])

                    <form method="POST" action="{{ $submitUrl }}"
                          onsubmit="return confirm('Approve the cutting report and save the entered consumption?');">
                        @csrf

                        @include('subcon.partials.consumption-input', [
                            'order' => $order,
                            'fabricLines' => $fabricLines,
                            'totalCut' => $totalCut,
                            'editable' => true,
                        ])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ $declineUrl }}"
                               class="btn btn-outline-danger px-4 fw-semibold"
                               onclick="return confirm('Return this cutting report to the vendor for changes?');">
                                <i class="fas fa-circle-xmark me-1"></i> Reject
                            </a>
                            <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                                <i class="fas fa-circle-check me-1"></i> Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">This link is signed and unique to this work order.</p>
        </div>
    </div>
</div>
@endsection
