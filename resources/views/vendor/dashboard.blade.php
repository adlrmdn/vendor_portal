@extends('layouts.app')

@section('title', 'Vendor Dashboard')

@section('content')
    <div class="container-fluid">
        <h1 class="h3 mb-4">Vendor Dashboard</h1>

        <!-- Welcome Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4>Welcome, {{ auth()->user()->name }}!</h4>
                        <p class="text-muted mb-0">
                            @if(auth()->user()->vendor)
                                You are logged in as a vendor for <strong>{{ auth()->user()->vendor->name }}</strong>.
                                Here you can manage your purchase orders, update roll details, and generate packing slips.
                            @else
                                Vendor account information not found. Please contact administrator.
                            @endif
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        @if(auth()->user()->vendor)
                            <div class="badge bg-info fs-6 p-2">
                                <i class="fas fa-id-card me-2"></i>{{ auth()->user()->vendor->vendor_code }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Active POs</h6>
                                <h3 class="card-title mb-0">{{ $stats['active_pos'] ?? 0 }}</h3>
                            </div>
                            <div class="text-primary">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <a href="{{ route('vendor.purchase-orders', ['status' => 'pending']) }}" class="text-decoration-none">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Pending Items</h6>
                                    <h3 class="card-title mb-0 text-dark">{{ $stats['pending_items'] ?? 0 }}</h3>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="{{ route('vendor.purchase-orders', ['status' => 'processing']) }}" class="text-decoration-none">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Processing Items</h6>
                                    <h3 class="card-title mb-0 text-dark">{{ $stats['processing_items'] ?? 0 }}</h3>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-cogs fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-3">
                <a href="{{ route('vendor.purchase-orders', ['status' => 'completed']) }}" class="text-decoration-none">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Completed POs</h6>
                                    <h3 class="card-title mb-0 text-dark">{{ $stats['completed_pos'] ?? 0 }}</h3>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Purchase Orders -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Purchase Orders</h5>
                <a href="{{ route('vendor.purchase-orders') }}" class="btn btn-sm btn-primary">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Order Date</th>
                                <th>Delivery Date</th>
                                <th>Items</th>
                                <th>Pending Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td>
                                        <strong>{{ $order->po_number }}</strong>
                                        @if($order->reference)
                                            <br><small class="text-muted">PC: {{ $order->reference }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $order->order_date->format('M d, Y') }}</td>
                                    <td>
                                        @if($order->delivery_date)
                                            @if($order->delivery_date->isPast() && $order->status !== 'completed')
                                                <span class="text-danger fw-bold">{{ $order->delivery_date->format('M d, Y') }}</span>
                                            @else
                                                {{ $order->delivery_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Not set</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $order->unique_items_count }} {{ Str::plural('item', $order->unique_items_count) }}
                                        @if($order->items_count > $order->unique_items_count)
                                            <br><small class="text-muted">({{ $order->items_count }} Batches)</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->pending_unique_items_count > 0)
                                            <span class="badge bg-warning">{{ $order->pending_unique_items_count }} pending</span>
                                        @else
                                            <span class="badge bg-success">All processed</span>
                                        @endif
                                    </td>
                                    <td>{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $order->status }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('vendor.purchase-order.view', $order->id) }}"
                                                class="btn btn-sm btn-info" title="View & Update">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($order->items->contains('status', 'completed'))
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                    data-bs-target="#quickSlipModal{{ $order->id }}" title="Generate Packing Slip">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-2x mb-3"></i>
                                            <p>No purchase orders assigned to you</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Generate Modals -->
    @foreach($recentOrders as $order)
        @if($order->items->contains('status', 'completed'))
            <div class="modal fade" id="quickSlipModal{{ $order->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('vendor.quick-packing-slip', $order->id) }}" method="POST" target="_blank">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Quick Generate Packing Slip</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Generate packing slip for PO: <strong>{{ $order->po_number }}</strong></p>
                                <p class="text-muted small">This will include all processed items under this PO.</p>
                                <div class="mb-3 mt-4">
                                    <label class="form-label fw-bold">Delivery Note Number <span class="text-danger">*</span></label>
                                    <input type="text" name="delivery_note" class="form-control" placeholder="Enter Delivery Note Number" required oninput="this.form.querySelector('button[type=\'submit\']').disabled = this.value.trim() === ''">
                                    <div class="form-text text-muted">Required before generating the packing slip.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success" disabled>Generate Slip</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-processing {
            background-color: #0dcaf0;
            color: #000;
        }

        .badge-completed {
            background-color: #198754;
        }
    </style>
@endsection