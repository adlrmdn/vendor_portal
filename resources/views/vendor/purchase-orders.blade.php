@extends('layouts.app')

@section('title', 'My Purchase Orders')

@section('content')
<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    <!-- Header with Eyebrow & Summary -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Vendor Portal</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Orders</span>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h1 class="apple-page-title mb-0">My Purchase Orders</h1>
                <span class="apple-count-pill">
                    <span class="apple-count-num">{{ number_format($purchaseOrders->total()) }}</span>
                    <span>total orders</span>
                </span>
                @if(request()->hasAny(['search', 'status', 'item_number', 'style']))
                    <a href="{{ route('vendor.purchase-orders') }}" class="apple-filter-active-pill" title="Clear all active filters">
                        <i class="fas fa-filter"></i>
                        <span>Filtered view</span>
                        <i class="fas fa-times ms-1"></i>
                    </a>
                @endif
            </div>
        </div>

        <!-- Quick Status Segmented Control (Apple-style Tab Bar) -->
        <div class="apple-segmented-bar">
            @php
                $currentStatus = request('status', '');
            @endphp
            <a href="{{ route('vendor.purchase-orders', array_merge(request()->except(['status', 'page']), ['status' => ''])) }}"
               class="apple-segment-item {{ $currentStatus === '' ? 'active' : '' }}">
                All
            </a>
            <a href="{{ route('vendor.purchase-orders', array_merge(request()->except(['status', 'page']), ['status' => 'pending'])) }}"
               class="apple-segment-item {{ $currentStatus === 'pending' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-pending"></span> Pending
            </a>
            <a href="{{ route('vendor.purchase-orders', array_merge(request()->except(['status', 'page']), ['status' => 'processing'])) }}"
               class="apple-segment-item {{ $currentStatus === 'processing' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-processing"></span> Processing
            </a>
            <a href="{{ route('vendor.purchase-orders', array_merge(request()->except(['status', 'page']), ['status' => 'completed'])) }}"
               class="apple-segment-item {{ $currentStatus === 'completed' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-completed"></span> Completed
            </a>
        </div>
    </div>

    @if(!empty($pcUnlocked))
        <div class="apple-card p-3 mb-4" style="background: #fffcf0; border-color: rgba(255, 159, 10, 0.3);">
            <div class="d-flex align-items-center gap-3">
                <div class="apple-stat-icon-wrap icon-wrap-amber" style="width: 40px; height: 40px;">
                    <i class="fas fa-unlock"></i>
                </div>
                <div class="small">
                    <strong class="text-dark">PC reference match unlocked:</strong>
                    @foreach($pcUnlocked as $v)
                        <span class="fw-semibold">{{ $v['name'] }} ({{ $v['vendor_code'] }})</span>@if(!$loop->last), @endif
                    @endforeach
                    for this session. You have full visibility into orders linked to this shared PC.
                </div>
            </div>
        </div>
    @endif

    <!-- Spotlight Search & Filters Card -->
    <div class="apple-card apple-filter-panel mb-4">
        <form method="GET" action="{{ route('vendor.purchase-orders') }}" id="vendorPoFilterForm">
            <!-- Spotlight Search Bar -->
            <div class="apple-search-container mb-3">
                <i class="fas fa-search apple-search-icon"></i>
                <input type="text"
                       class="apple-search-input"
                       name="search"
                       id="searchInput"
                       placeholder="Search by PO number, PC reference, PLM or style name..."
                       value="{{ $search ?? request('search') }}"
                       autocomplete="off">
                @if($search ?? request('search'))
                    <a href="{{ route('vendor.purchase-orders', array_filter(request()->except(['search', 'page']))) }}"
                       class="apple-search-clear"
                       title="Clear search">
                        <i class="fas fa-times-circle"></i>
                    </a>
                @endif
                <button class="apple-btn-primary apple-search-btn" type="submit">
                    Search
                </button>
            </div>

            <!-- Secondary Filters Grid -->
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="item_number" class="apple-field-label">Item Number</label>
                    <input type="text"
                           class="form-control apple-input"
                           id="item_number"
                           name="item_number"
                           placeholder="e.g. FAB-1023"
                           value="{{ $itemNumber ?? request('item_number') }}">
                </div>

                <div class="col-12 col-sm-6 col-md-4">
                    <label for="style" class="apple-field-label">Style / Batch Name</label>
                    <input type="text"
                           class="form-control apple-input"
                           id="style"
                           name="style"
                           placeholder="e.g. MOC Eagle Blue"
                           value="{{ $styleFilter ?? request('style') }}">
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label for="statusSelect" class="apple-field-label">Status Filter</label>
                    <select class="form-select apple-select" id="statusSelect" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ ($status ?? request('status')) == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ ($status ?? request('status')) == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ ($status ?? request('status')) == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="apple-btn-primary flex-grow-1" title="Apply Filters">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="{{ route('vendor.purchase-orders') }}"
                           class="apple-btn-ghost"
                           title="Reset all filters">
                            <i class="fas fa-arrow-rotate-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Table Meta Bar (Showing summary & Per-page selector) -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 px-1">
        <div class="apple-meta-summary">
            @if($purchaseOrders->total() > 0)
                Showing <span class="fw-semibold text-dark">{{ $purchaseOrders->firstItem() }}</span> &ndash;
                <span class="fw-semibold text-dark">{{ $purchaseOrders->lastItem() }}</span> of
                <span class="fw-semibold text-dark">{{ number_format($purchaseOrders->total()) }}</span> purchase orders
            @else
                Showing <span class="fw-semibold text-dark">0</span> orders
            @endif
        </div>

        <form action="{{ route('vendor.purchase-orders') }}" method="GET" class="d-flex align-items-center gap-2">
            @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            @if(request('item_number')) <input type="hidden" name="item_number" value="{{ request('item_number') }}"> @endif
            @if(request('style')) <input type="hidden" name="style" value="{{ request('style') }}"> @endif
            <label class="apple-perpage-label">Show</label>
            <select name="per_page" class="apple-perpage-select" onchange="this.form.submit()">
                <option value="10" {{ ($perPage ?? 25) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>50</option>
            </select>
            <span class="apple-perpage-label">per page</span>
        </form>
    </div>

    <!-- Purchase Orders Table Card -->
    <div class="apple-card apple-table-container">
        <div class="table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 24%;">PO Details</th>
                        <th style="width: 23%;">Items</th>
                        <th style="width: 14%;">Timeline</th>
                        <th style="width: 13%;">Item Fulfillment</th>
                        <th style="width: 12%; text-align: right;">Amount</th>
                        <th style="width: 7%; text-align: center;">Status</th>
                        <th style="width: 7%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $order)
                        <tr class="apple-table-row">
                            <!-- PO Details -->
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('vendor.purchase-order.view', $order->id) }}"
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
                                    @if($order->vendor_id !== ($vendorId ?? null))
                                        <div>
                                            <span class="apple-sub-tag" style="background: rgba(0, 113, 227, 0.08); color: var(--apple-blue);" title="Linked via shared PC reference">
                                                <i class="fas fa-link me-1"></i>{{ $order->vendor?->name ?? 'Other vendor' }} ({{ $order->vendor?->vendor_code }})
                                            </span>
                                        </div>
                                    @endif
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
                                                            id="itemsDropdownVendorPo{{ $order->id }}"
                                                            data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        <i class="fas fa-layer-group me-1"></i>
                                                        +{{ $itemCount - 1 }} more items
                                                    </button>
                                                    <div class="dropdown-menu apple-items-dropdown shadow-lg p-2"
                                                         aria-labelledby="itemsDropdownVendorPo{{ $order->id }}">
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
                                    <a href="{{ route('vendor.purchase-order.view', $order->id) }}"
                                       class="apple-action-btn apple-action-view"
                                       title="View and process order">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                    @if($order->items->contains('status', 'completed'))
                                        <button type="button"
                                                class="apple-action-btn apple-action-slip"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quickSlipModalVendorPo{{ $order->id }}"
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
                                    <h4 class="apple-empty-title">No Purchase Orders Found</h4>
                                    <p class="apple-empty-desc">
                                        No purchase orders matched your search criteria or active filters.
                                    </p>
                                    <a href="{{ route('vendor.purchase-orders') }}"
                                       class="apple-btn-primary d-inline-flex align-items-center gap-2">
                                        <i class="fas fa-arrow-rotate-left"></i> Reset All Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($purchaseOrders->hasPages())
            <div class="apple-pagination-footer d-flex justify-content-between align-items-center flex-wrap gap-3 p-3">
                <div class="apple-meta-summary">
                    Showing {{ $purchaseOrders->firstItem() }} to {{ $purchaseOrders->lastItem() }} of {{ number_format($purchaseOrders->total()) }} results
                </div>
                <div class="apple-pagination-links">
                    {{ $purchaseOrders->links('custom-pagination') }}
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Quick Generate Modals -->
@foreach($purchaseOrders as $order)
    @if($order->items->contains('status', 'completed'))
        <div class="modal fade" id="quickSlipModalVendorPo{{ $order->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content apple-modal">
                    <form action="{{ route('vendor.quick-packing-slip', $order->id) }}" method="POST" target="_blank">
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
                                    {{ $order->items->where('status', 'completed')->count() }} processed item(s) ready for shipment
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