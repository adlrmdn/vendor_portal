@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    <!-- Header with Eyebrow -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Fabric Administration</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Overview</span>
            </div>
            <h1 class="apple-page-title mb-0">Admin Dashboard</h1>
        </div>
        <div>
            <a href="{{ route('admin.purchase-orders') }}" class="apple-btn-primary">
                <span>Browse All POs</span>
                <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <!-- Apple HIG Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="apple-stat-card">
                <div>
                    <div class="apple-stat-title">Total POs</div>
                    <div class="apple-stat-value">{{ number_format($stats['total_pos'] ?? 0) }}</div>
                </div>
                <div class="apple-stat-icon-wrap icon-wrap-blue">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('admin.purchase-orders', ['status' => 'active']) }}" class="text-decoration-none">
                <div class="apple-stat-card">
                    <div>
                        <div class="apple-stat-title">Active POs</div>
                        <div class="apple-stat-value">{{ number_format($stats['active_pos'] ?? 0) }}</div>
                    </div>
                    <div class="apple-stat-icon-wrap icon-wrap-amber">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('admin.purchase-orders', ['status' => 'completed']) }}" class="text-decoration-none">
                <div class="apple-stat-card">
                    <div>
                        <div class="apple-stat-title">Completed POs</div>
                        <div class="apple-stat-value">{{ number_format($stats['completed_pos'] ?? 0) }}</div>
                    </div>
                    <div class="apple-stat-icon-wrap icon-wrap-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('admin.vendors') }}" class="text-decoration-none">
                <div class="apple-stat-card">
                    <div>
                        <div class="apple-stat-title">Active Vendors</div>
                        <div class="apple-stat-value">{{ number_format($stats['total_vendors'] ?? 0) }}</div>
                    </div>
                    <div class="apple-stat-icon-wrap icon-wrap-indigo">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Purchase Orders Card -->
    <div class="apple-card apple-table-container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom" style="border-color: #ebebf0 !important;">
            <div>
                <h5 class="mb-0 fw-bold fs-6 text-dark">Recent Purchase Orders</h5>
                <span class="small text-muted">Latest procurement orders requiring monitoring or processing</span>
            </div>
            <a href="{{ route('admin.purchase-orders') }}" class="apple-header-link">
                <span>View All Orders</span>
                <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 23%;">PO Details</th>
                        <th style="width: 23%;">Items</th>
                        <th style="width: 14%;">Timeline</th>
                        <th style="width: 13%;">Item Fulfillment</th>
                        <th style="width: 12%; text-align: right;">Amount</th>
                        <th style="width: 8%; text-align: center;">Status</th>
                        <th style="width: 7%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr class="apple-table-row">
                            <!-- PO Details -->
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('admin.purchase-order.view', $order->id) }}"
                                           class="apple-po-number"
                                           title="View order {{ $order->po_number }}">
                                            {{ $order->po_number }}
                                        </a>
                                        <button type="button"
                                                class="apple-copy-btn"
                                                onclick="copyPoNumber('{{ $order->po_number }}', this)"
                                                title="Copy PO number">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                    @if($order->reference)
                                        <div>
                                            <span class="apple-sub-tag pc-tag" title="Preliminary Contract Reference">
                                                <i class="far fa-file-lines me-1 opacity-75"></i>PC: {{ $order->reference }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="apple-vendor-info text-truncate"
                                         title="{{ $order->vendor->vendor_code ?? '' }} - {{ $order->vendor->name ?? '' }}">
                                        <i class="far fa-building me-1 opacity-50"></i>
                                        <span class="fw-medium text-dark">{{ $order->vendor->vendor_code ?? '' }}</span>
                                        <span class="opacity-75">&bull; {{ $order->vendor->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Items (NO SCROLLBARS! Clean Apple card previews with popover for extras) -->
                            <td>
                                @php
                                    $itemCount = $order->items->count();
                                @endphp
                                @if($itemCount === 0)
                                    <span class="text-muted small">&mdash;</span>
                                @else
                                    <div class="apple-items-stack">
                                        {{-- Render first item --}}
                                        @php $firstItem = $order->items->first(); @endphp
                                        <div class="apple-item-chip-box">
                                            <div class="apple-item-num-row">
                                                <span class="apple-item-number">{{ $firstItem->item_number }}</span>
                                                @if($firstItem->status)
                                                    <span class="apple-micro-status {{ $firstItem->status }}">
                                                        {{ ucfirst($firstItem->status) }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($firstItem->batch)
                                                <div class="apple-item-style text-truncate" title="{{ $firstItem->batch }}">
                                                    {{ $firstItem->batch }}
                                                </div>
                                            @endif
                                        </div>

                                        {{-- If exactly 2 items, render the second cleanly --}}
                                        @if($itemCount === 2)
                                            @php $secondItem = $order->items->get(1); @endphp
                                            <div class="apple-item-chip-box mt-1">
                                                <div class="apple-item-num-row">
                                                    <span class="apple-item-number">{{ $secondItem->item_number }}</span>
                                                    @if($secondItem->status)
                                                        <span class="apple-micro-status {{ $secondItem->status }}">
                                                            {{ ucfirst($secondItem->status) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($secondItem->batch)
                                                    <div class="apple-item-style text-truncate" title="{{ $secondItem->batch }}">
                                                        {{ $secondItem->batch }}
                                                    </div>
                                                @endif
                                            </div>
                                        {{-- If more than 2 items, show interactive drawer badge --}}
                                        @elseif($itemCount > 2)
                                            <div class="mt-1 d-flex align-items-center gap-2">
                                                <div class="dropdown">
                                                    <button type="button"
                                                            class="apple-more-items-pill dropdown-toggle"
                                                            id="itemsDropdownAdminDash{{ $order->id }}"
                                                            data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        <i class="fas fa-layer-group me-1"></i>
                                                        +{{ $itemCount - 1 }} more items
                                                    </button>
                                                    <div class="dropdown-menu apple-items-dropdown shadow-lg p-2"
                                                         aria-labelledby="itemsDropdownAdminDash{{ $order->id }}">
                                                        <div class="apple-dropdown-title px-2 py-1">
                                                            All Items in {{ $order->po_number }}
                                                        </div>
                                                        <div class="apple-dropdown-items-list">
                                                            @foreach($order->items as $idx => $extraItem)
                                                                <div class="apple-dropdown-item {{ $idx > 0 ? 'border-top' : '' }} py-2 px-2">
                                                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                                                        <span class="fw-semibold text-dark">{{ $extraItem->item_number }}</span>
                                                                        <span class="apple-micro-status {{ $extraItem->status }}">
                                                                            {{ ucfirst($extraItem->status) }}
                                                                        </span>
                                                                    </div>
                                                                    @if($extraItem->batch)
                                                                        <div class="small text-muted text-wrap mt-1">
                                                                            {{ $extraItem->batch }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="apple-total-badge">{{ $itemCount }} total</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Dates & Timeline -->
                            <td>
                                <div class="apple-timeline-stack">
                                    <div class="apple-date-line">
                                        <span class="apple-date-caption">Order:</span>
                                        <span class="apple-date-val">{{ $order->order_date ? $order->order_date->format('M d, Y') : '—' }}</span>
                                    </div>
                                    <div class="apple-date-line mt-1">
                                        <span class="apple-date-caption">Delivery:</span>
                                        @if($order->delivery_date)
                                            @if($order->delivery_date->isPast() && $order->status !== 'completed')
                                                <span class="apple-date-badge overdue" title="Order delivery is overdue">
                                                    <i class="fas fa-clock me-1"></i>{{ $order->delivery_date->format('M d, Y') }}
                                                </span>
                                            @else
                                                <span class="apple-date-val">{{ $order->delivery_date->format('M d, Y') }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted small">&mdash;</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Item Fulfillment Status -->
                            <td>
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <div class="apple-fulfillment-count">
                                        {{ $order->unique_items_count }} {{ Str::plural('item', $order->unique_items_count) }}
                                        @if($order->items_count > $order->unique_items_count)
                                            <span class="apple-batch-pill" title="Batches split across shipments">
                                                {{ $order->items_count }} batches
                                            </span>
                                        @endif
                                    </div>
                                    @if($order->pending_unique_items_count > 0)
                                        <span class="apple-pill-status pill-warning">
                                            <span class="status-dot dot-warning"></span>
                                            {{ $order->pending_unique_items_count }} pending
                                        </span>
                                    @else
                                        <span class="apple-pill-status pill-success">
                                            <span class="status-dot dot-success"></span>
                                            All processed
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Amount -->
                            <td class="text-end">
                                <div class="apple-amount-box">
                                    <span class="apple-currency">{{ $order->currency }}</span>
                                    <span class="apple-amount-value">{{ number_format($order->total_amount, 2) }}</span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                @php
                                    $st = strtolower($order->status);
                                @endphp
                                <span class="apple-status-capsule capsule-{{ $st }}">
                                    <span class="capsule-dot"></span>
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <a href="{{ route('admin.purchase-order.view', $order->id) }}"
                                       class="apple-action-btn apple-action-view"
                                       title="View and process order">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                    @if($order->items->contains('status', 'completed'))
                                        <button type="button"
                                                class="apple-action-btn apple-action-slip"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quickSlipModal{{ $order->id }}"
                                                title="Generate Packing Slip">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center">
                                <div class="apple-empty-state">
                                    <div class="apple-empty-icon-circle mb-3">
                                        <i class="fas fa-inbox fa-2x"></i>
                                    </div>
                                    <h4 class="apple-empty-title">No Recent Purchase Orders</h4>
                                    <p class="apple-empty-desc">
                                        There are currently no recent purchase orders to display.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Generate Modals -->
@foreach($recentOrders as $order)
    @if($order->items->contains('status', 'completed'))
        <div class="modal fade" id="quickSlipModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content apple-modal">
                    <form action="{{ route('admin.quick-packing-slip', $order->id) }}" method="POST" target="_blank">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <div>
                                <span class="apple-section-eyebrow text-success">Quick Action</span>
                                <h5 class="apple-modal-title mb-0">Generate Packing Slip</h5>
                            </div>
                            <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="apple-modal-order-box mb-3">
                                <div class="small text-muted">Purchase Order</div>
                                <div class="fw-bold fs-6 text-dark">{{ $order->po_number }}</div>
                                <div class="small text-muted mt-1">
                                    {{ $order->vendor->name ?? 'Vendor' }} &bull; {{ $order->items->where('status', 'completed')->count() }} processed item(s)
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="apple-field-label">Delivery Note Number <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="delivery_note"
                                       class="form-control apple-input"
                                       placeholder="e.g. DN-2026-0081"
                                       required
                                       oninput="this.form.querySelector('.apple-modal-submit').disabled = this.value.trim() === ''">
                                <div class="form-text text-muted small mt-1">
                                    Required before generating the official PDF packing slip.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="apple-btn-success apple-modal-submit" disabled>
                                <i class="fas fa-file-pdf me-1"></i> Generate Slip
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">PO number copied</span>
</div>

@include('partials.apple-theme')
@endsection