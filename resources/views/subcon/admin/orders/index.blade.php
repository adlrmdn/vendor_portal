@extends('layouts.app')

@section('title', 'Work Orders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Work Orders</h2>
    <a href="{{ route('subcon.admin.orders.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Order
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="card card-body mb-4">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Order number..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="vendor_id" class="form-select">
                <option value="">All Vendors</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                @foreach(['pending', 'in_progress', 'completed', 'cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Vendor</th>
                        <th>Title</th>
                        <th>Items</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td><code>{{ $order->order_number }}</code></td>
                        <td>{{ $order->vendor?->name ?? '—' }}</td>
                        <td>{{ $order->title }}</td>
                        <td>{{ $order->items->count() }}</td>
                        <td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @php
                                $badge = match($order->status) {
                                    'pending'     => 'secondary',
                                    'in_progress' => 'warning',
                                    'completed'   => 'success',
                                    'cancelled'   => 'danger',
                                    default       => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </td>
                        <td>
                            <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">{{ $orders->links('custom-pagination') }}</div>
    @endif
</div>
@endsection
