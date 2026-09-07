@extends('layouts.app')

@section('title', 'Approvals')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Approvals</h2>
        <small class="text-muted">Tolerance amendments &amp; partial shipment requests awaiting your decision</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.workflow') }}" class="btn btn-outline-secondary">
            <i class="fas fa-sitemap me-1"></i> Workflow Settings
        </a>
        <a href="{{ route('admin.purchase-orders') }}" class="btn btn-outline-secondary">
            <i class="fas fa-file-invoice me-1"></i> Purchase Orders
        </a>
    </div>
</div>

<form method="GET" action="{{ route('admin.approvals') }}" class="mb-3">
    <div class="input-group" style="max-width: 420px;">
        <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search PO, item or vendor…">
        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>PO / Item</th>
                        <th>Vendor</th>
                        <th>Details</th>
                        <th>Reason</th>
                        <th>Submitted</th>
                        <th class="text-end pe-4" style="width: 220px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        @php $item = $req->poItem; $po = $item?->purchaseOrder; @endphp
                        <tr>
                            <td>
                                @if($req->type === 'partial_shipment')
                                    <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-25">Partial Shipment</span>
                                @else
                                    <span class="badge bg-info-subtle text-info border border-info border-opacity-25">Tolerance Amendment</span>
                                @endif
                            </td>
                            <td>
                                @if($po)
                                    <a href="{{ route('admin.purchase-order.view', $po->id) }}" class="fw-semibold text-decoration-none font-monospace">{{ $po->po_number }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                <div class="text-muted small">Item {{ $item?->item_number ?? '—' }}</div>
                            </td>
                            <td>{{ $po?->vendor?->name ?? '—' }}</td>
                            <td class="small">
                                @if($req->type === 'partial_shipment')
                                    Requested qty: <strong>{{ number_format((float) $req->requested_qty, 2) }} {{ $item?->unit }}</strong>
                                @else
                                    Underdelivery: {{ number_format((float) $req->old_underdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_underdelivery, 2) }}%</strong><br>
                                    Overdelivery: {{ number_format((float) $req->old_overdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_overdelivery, 2) }}%</strong>
                                    @if($item)
                                        @php $newLimits = $item->getQuantityLimitsForTolerance((float) $req->new_underdelivery, (float) $req->new_overdelivery); @endphp
                                        <div class="text-muted">Allowed qty at requested %: {{ number_format($newLimits['min'], 2) }} &ndash; {{ number_format($newLimits['max'], 2) }} {{ $item->unit }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="small text-muted" style="max-width: 260px;">{{ \Illuminate\Support\Str::limit($req->reason, 120) }}</td>
                            <td class="small text-muted">{{ $req->created_at?->diffForHumans() }}</td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    <form method="POST" action="{{ route('admin.approvals.approve', $req->id) }}" class="m-0"
                                          onsubmit="return confirm('Approve this {{ $req->type === 'partial_shipment' ? 'partial shipment' : 'tolerance amendment' }} request?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.approvals.decline', $req->id) }}" class="m-0"
                                          onsubmit="return confirm('Decline this request?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i> Decline</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if($requests->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                {{ $search !== '' ? 'No pending approvals match "'.$search.'".' : "No approvals pending. You're all caught up." }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($requests->hasPages())
    <div class="mt-3">{{ $requests->links() }}</div>
@endif
@endsection
