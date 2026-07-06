@extends('layouts.app')

@section('title', 'Requested Approvals')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Requested Approvals</h2>
        <small class="text-muted">Cutting reports & gramasi submissions awaiting your decision</small>
    </div>
    <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary">
        <i class="fas fa-clipboard-list me-1"></i> All Work Orders
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Work Order</th>
                        <th>Vendor</th>
                        <th>Awaiting</th>
                        <th>Submitted</th>
                        <th class="text-end pe-4" style="width: 260px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php $gate = $order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW ? 'cutting' : 'gramasi'; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="fw-semibold text-decoration-none font-monospace">{{ $order->order_number }}</a>
                                <div class="text-muted small">{{ $order->title }}</div>
                            </td>
                            <td>{{ $order->vendor?->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $gate === 'gramasi' ? 'info' : 'warning' }}-subtle text-{{ $gate === 'gramasi' ? 'info' : 'warning' }} border border-{{ $gate === 'gramasi' ? 'info' : 'warning' }} border-opacity-25">
                                    {{ $gate === 'gramasi' ? 'Gramasi & Blister' : 'Cutting Report' }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $order->updated_at?->diffForHumans() }}</td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    @if($gate === 'cutting')
                                        {{-- Cutting approval needs fabric-consumption entry, so route to the
                                             order page (or use the no-login email link) rather than one-click. --}}
                                        <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-sm btn-success">
                                            <i class="fas fa-check me-1"></i> Review &amp; Approve
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}" class="m-0"
                                              onsubmit="return confirm('Approve the gramasi & blister capacity for {{ $order->order_number }}?');">
                                            @csrf
                                            <input type="hidden" name="gate" value="{{ $gate }}">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('subcon.admin.orders.decline', $order->id) }}" class="m-0"
                                          onsubmit="return confirm('Return this to the vendor for changes?');">
                                        @csrf
                                        <input type="hidden" name="gate" value="{{ $gate }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i> Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                No approvals pending. You're all caught up.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($orders->hasPages())
    <div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection
