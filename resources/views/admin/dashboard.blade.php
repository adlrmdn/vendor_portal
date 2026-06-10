@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="container-fluid">
        <h1 class="h3 mb-4">Admin Dashboard</h1>

        <!-- Welcome Card -->
        <!-- (Optional: Can remain same or be removed for admin) -->

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total POs</h6>
                                <h3 class="card-title mb-0">{{ $stats['total_pos'] ?? 0 }}</h3>
                            </div>
                            <div class="text-primary">
                                <i class="fas fa-file-invoice fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <a href="{{ route('admin.purchase-orders') }}" class="text-decoration-none">
                    <div class="card stat-card" style="border-left-color: #ffc107;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Active POs</h6>
                                    <h3 class="card-title mb-0 text-dark">{{ $stats['active_pos'] ?? 0 }}</h3>
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
                <a href="{{ route('admin.purchase-orders', ['status' => 'completed']) }}" class="text-decoration-none">
                    <div class="card stat-card" style="border-left-color: #198754;">
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

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Active Vendors</h6>
                                <h3 class="card-title mb-0">{{ $stats['total_vendors'] ?? 0 }}</h3>
                            </div>
                            <div class="text-info">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Purchase Orders -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Purchase Orders</h5>
                <a href="{{ route('admin.purchase-orders') }}" class="btn btn-sm btn-primary">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 30%;">PO Details</th>
                                <th style="width: 20%;">Dates</th>
                                <th style="width: 15%;">Item Status</th>
                                <th style="width: 15%;">Amount</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr class="align-middle">
                                    <td>
                                        <div class="fw-bold text-nowrap">{{ $order->po_number }}</div>
                                        @if($order->reference)
                                            <div class="small text-muted">PC: {{ $order->reference }}</div>
                                        @endif
                                        <div class="small text-muted text-truncate" style="max-width: 250px;"
                                            title="{{ $order->vendor->vendor_code ?? '' }} - {{ $order->vendor->name ?? '' }}">
                                            {{ $order->vendor->vendor_code ?? '' }} - {{ $order->vendor->name ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-nowrap">
                                            <div class="text-muted">Order: {{ $order->order_date->format('M d, Y') }}</div>
                                            <div>
                                                Delivery:
                                                @if($order->delivery_date)
                                                    @if($order->delivery_date->isPast() && $order->status !== 'completed')
                                                        <span
                                                            class="text-danger fw-bold">{{ $order->delivery_date->format('M d, Y') }}</span>
                                                    @else
                                                        {{ $order->delivery_date->format('M d, Y') }}
                                                    @endif
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <span class="small text-nowrap">
                                                {{ $order->unique_items_count }} {{ Str::plural('item', $order->unique_items_count) }}
                                                @if($order->items_count > $order->unique_items_count)
                                                    <span class="text-muted small">({{ $order->items_count }} Batches)</span>
                                                @endif
                                            </span>
                                            @if($order->pending_unique_items_count > 0)
                                                <span class="badge bg-warning text-dark">{{ $order->pending_unique_items_count }} pending</span>
                                            @else
                                                <span class="badge bg-success">All processed</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap small">
                                        <span
                                            class="text-muted me-1">{{ $order->currency }}</span>{{ number_format($order->total_amount, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $order->status }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.purchase-order.view', $order->id) }}"
                                                class="btn btn-sm btn-info text-white" title="View & Update">
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
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-2x mb-3"></i>
                                            <p>No purchase orders found</p>
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
                        <form action="{{ route('admin.quick-packing-slip', $order->id) }}" method="POST" target="_blank">
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

        .badge-cancelled {
            background-color: #dc3545;
        }

        .stat-card {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-left: 0.25rem solid;
            height: 100%;
        }

        .stat-card:nth-child(1) {
            border-left-color: #4e73df;
        }

        .stat-card:nth-child(2) {
            border-left-color: #ffc107;
        }

        .stat-card:nth-child(3) {
            border-left-color: #198754;
        }

        .stat-card:nth-child(4) {
            border-left-color: #36b9cc;
        }
    </style>
@endsection