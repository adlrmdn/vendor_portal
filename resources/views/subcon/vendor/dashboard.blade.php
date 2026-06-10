@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Dashboard</h2>
    <span class="text-muted small">{{ auth()->user()->vendor?->name }}</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-white bg-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ $stats['total_orders'] }}</div>
                    <div class="small">Total Orders</div>
                </div>
                <i class="fas fa-clipboard-list fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-white bg-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ $stats['active_orders'] }}</div>
                    <div class="small">Active</div>
                </div>
                <i class="fas fa-spinner fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-white bg-success">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-4 fw-bold">{{ $stats['completed'] }}</div>
                    <div class="small">Completed</div>
                </div>
                <i class="fas fa-check-circle fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Work Orders</h5>
        <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Title</th>
                        <th>Items</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                    <tr>
                        <td><code>{{ $order->order_number }}</code></td>
                        <td>{{ $order->title }}</td>
                        <td>{{ $order->items->count() }}</td>
                        <td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </td>
                        <td>
                            <a href="{{ route('subcon.vendor.orders.view', $order->id) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No work orders assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
