@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Purchase Orders</h1>
        </div>

        <!-- Filters -->
        <div class="card mb-4 po-filter-card">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.purchase-orders') }}">
                    <div class="po-search-bar mb-3">
                        <i class="fas fa-search po-search-icon"></i>
                        <input type="text" class="form-control form-control-lg po-search-input" name="search"
                            placeholder="Search by PO number, PC, PLM or style name..." value="{{ request('search') }}">
                        @if(request('search'))
                            <a href="{{ route('admin.purchase-orders', array_filter(request()->except(['search', 'page']))) }}"
                                class="po-search-clear" title="Clear search">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                        <button class="btn btn-primary po-search-submit" type="submit">Search</button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select searchable" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing
                                </option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed
                                </option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="item_number" class="form-label">Item Number</label>
                            <input type="text" class="form-control" id="item_number" name="item_number"
                                placeholder="e.g. FAB-1023" value="{{ request('item_number') }}">
                        </div>

                        <div class="col-md-2">
                            <label for="style" class="form-label">Style</label>
                            <input type="text" class="form-control" id="style" name="style"
                                placeholder="e.g. MOC Eagle Blue" value="{{ request('style') }}">
                        </div>

                        <div class="col-md-3">
                            <label for="vendor_id" class="form-label">Vendor</label>
                            <select class="form-select searchable" id="vendor_id" name="vendor_id">
                                <option value="">All Vendors</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" {{ request('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                        {{ $vendor->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-grid gap-2 w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="{{ route('admin.purchase-orders', ['reset' => 1]) }}" class="btn btn-secondary py-1" style="font-size: 0.8rem;">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <form action="{{ route('admin.purchase-orders') }}" method="GET" class="d-flex align-items-center">
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                    @if(request('vendor_id')) <input type="hidden" name="vendor_id" value="{{ request('vendor_id') }}"> @endif
                    @if(request('item_number')) <input type="hidden" name="item_number" value="{{ request('item_number') }}"> @endif
                    @if(request('style')) <input type="hidden" name="style" value="{{ request('style') }}"> @endif
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
                                <th style="width: 22%;">PO Details</th>
                                <th style="width: 20%;">Items</th>
                                <th style="width: 15%;">Dates</th>
                                <th style="width: 13%;">Item Status</th>
                                <th style="width: 12%;">Amount</th>
                                <th style="width: 8%;">Status</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseOrders as $order)
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
                                        <div class="po-items-cell small">
                                            @forelse($order->items as $item)
                                                <div class="po-items-row">
                                                    <span class="fw-semibold">{{ $item->item_number }}</span>
                                                    @if($item->batch)
                                                        <div class="text-muted">{{ $item->batch }}</div>
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="text-muted">&mdash;</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-nowrap">
                                            <div class="text-muted">Order: {{ $order->order_date->format('M d, Y') }}</div>
                                            <div>
                                                Delivery:
                                                @if($order->delivery_date)
                                                    @if($order->delivery_date->isPast() && $order->status !== 'completed')
                                                        <span class="text-danger fw-bold">{{ $order->delivery_date->format('M d, Y') }}</span>
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
                                        <span class="text-muted me-1">{{ $order->currency }}</span>{{ number_format($order->total_amount, 2) }}
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

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal{{ $order->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('admin.purchase-order.update', $order->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit PO: {{ $order->po_number }}</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status">
                                                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                                                    <option value="processing" {{ $order->status == 'processing' ? 'selected' : '' }}>Processing</option>
                                                                    <option value="completed" {{ $order->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                                                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Delivery Date</label>
                                                                <input type="date" class="form-control" name="delivery_date"
                                                                    value="{{ $order->delivery_date ? $order->delivery_date->format('Y-m-d') : '' }}">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
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

        .po-search-bar {
            position: relative;
            display: flex;
            align-items: center;
        }

        .po-search-icon {
            position: absolute;
            left: 1.1rem;
            color: #6c757d;
            font-size: 1.05rem;
            pointer-events: none;
        }

        .po-search-input {
            padding-left: 2.75rem;
            padding-right: 6.5rem;
            border-radius: 0.6rem;
            border: 2px solid #dee2e6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .po-search-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .po-search-clear {
            position: absolute;
            right: 5.75rem;
            color: #6c757d;
        }

        .po-search-submit {
            position: absolute;
            right: 0.35rem;
            border-radius: 0.4rem;
        }

        .po-items-cell {
            max-height: 130px;
            overflow-y: auto;
        }

        .po-items-row:not(:last-child) {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #e9ecef;
        }
    </style>

@include('partials.searchable-select')
@endsection