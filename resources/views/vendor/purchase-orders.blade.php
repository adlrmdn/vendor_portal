@extends('layouts.app')

@section('title', 'My Purchase Orders')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">My Purchase Orders</h1>
            <form action="{{ route('vendor.purchase-orders') }}" method="GET" class="d-flex w-50">
                <div class="input-group">
                    <select name="status" class="form-select" style="max-width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ ($status ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ ($status ?? '') == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ ($status ?? '') == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Search PO or PC..."
                        value="{{ $search ?? '' }}">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    @if($search ?? false)
                        <a href="{{ route('vendor.purchase-orders') }}" class="btn btn-outline-danger" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <form action="{{ route('vendor.purchase-orders') }}" method="GET" class="d-flex align-items-center">
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                    <span class="me-2 fw-bold text-muted small text-uppercase">Show</span>
                    <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="10" {{ ($perPage ?? 25) == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <span class="ms-2 fw-bold text-muted small text-uppercase">entries</span>
                </form>
            </div>
        </div>

        <!-- Purchase Orders Table -->
        <div class="card">
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
                            @forelse($purchaseOrders as $order)
                                            <tr>
                                                <td>
                                                    <strong>{{ $order->po_number }}</strong>
                                                    @if($order->reference)
                                                        <br><small class="text-muted text-nowrap">PC: {{ $order->reference }}</small>
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

            <!-- Pagination -->
            @if($purchaseOrders->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $purchaseOrders->links('custom-pagination') }}
                </div>
            @endif
        </div>
    </div>
    </div>

    <!-- Quick Generate Modals -->
    @foreach($purchaseOrders as $order)
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