@extends('layouts.app')

@section('title', 'Purchase Order Details — ' . $purchaseOrder->po_number)

@section('content')
@php
    $poItems = $purchaseOrder->items;
@endphp

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    <!-- Back Navigation & Eyebrow -->
    <div class="mb-3">
        <a href="{{ route('vendor.purchase-orders') }}" class="apple-btn-secondary py-1 px-3 d-inline-flex align-items-center" style="font-size: 0.82rem;">
            <i class="fas fa-arrow-left me-2"></i>
            <span>Back to My Orders</span>
        </a>
    </div>

    @if($purchaseOrder->vendor_id !== ($vendorId ?? null))
        <div class="apple-card p-3 mb-4" style="background: #f0f7ff; border-color: rgba(0, 113, 227, 0.25);">
            <div class="d-flex align-items-center gap-3">
                <div class="apple-stat-icon-wrap icon-wrap-blue" style="width: 40px; height: 40px; flex-shrink: 0;">
                    <i class="fas fa-link"></i>
                </div>
                <div class="small">
                    This PO belongs to <strong class="text-dark">{{ $purchaseOrder->vendor?->name }} ({{ $purchaseOrder->vendor?->vendor_code }})</strong>,
                    linked to your account via shared PC reference <strong class="text-dark">{{ $purchaseOrder->reference }}</strong>.
                </div>
            </div>
        </div>
    @endif

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Purchase Order</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Order Details</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="apple-page-title mb-0">{{ $purchaseOrder->po_number }}</h1>
                <button type="button"
                        class="apple-copy-btn"
                        onclick="copyPoNumber('{{ $purchaseOrder->po_number }}', this)"
                        title="Copy PO Number">
                    <i class="far fa-copy"></i>
                </button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            @php $st = strtolower($purchaseOrder->status); @endphp
            <span class="apple-status-capsule capsule-{{ $st }} fs-6 px-3 py-2">
                <span class="capsule-dot"></span>
                {{ ucfirst($purchaseOrder->status) }}
            </span>
            @if($poItems->contains('status', 'completed'))
                <button class="apple-btn-success py-2 px-3" data-bs-toggle="modal" data-bs-target="#generateSlipModal">
                    <i class="fas fa-file-pdf me-2"></i>Generate Packing Slip
                </button>
            @endif
        </div>
    </div>

    <!-- PO Overview Card -->
    <div class="apple-card p-4 mb-4">
        <h5 class="fw-bold fs-6 text-dark mb-3">Order Overview</h5>
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <div class="apple-field-label">Vendor</div>
                <div class="fw-semibold text-dark">{{ $purchaseOrder->vendor->name ?? 'N/A' }}</div>
                <div class="small text-muted">{{ $purchaseOrder->vendor->vendor_code ?? '' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="apple-field-label">Order Date</div>
                <div class="fw-semibold text-dark">
                    {{ $purchaseOrder->order_date ? $purchaseOrder->order_date->format('M d, Y') : '—' }}
                </div>
                <div class="small text-muted">{{ $purchaseOrder->order_date ? $purchaseOrder->order_date->format('l') : '' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="apple-field-label">Delivery Date</div>
                @if($purchaseOrder->delivery_date)
                    @if($purchaseOrder->delivery_date->isPast() && $purchaseOrder->status !== 'completed')
                        <div>
                            <span class="apple-date-badge overdue">
                                <i class="fas fa-clock me-1"></i>{{ $purchaseOrder->delivery_date->format('M d, Y') }} (Overdue)
                            </span>
                        </div>
                    @else
                        <div class="fw-semibold text-dark">{{ $purchaseOrder->delivery_date->format('M d, Y') }}</div>
                    @endif
                @else
                    <span class="text-muted small">Not set</span>
                @endif
            </div>
            <div class="col-12 col-md-3">
                <div class="apple-field-label">Total Amount</div>
                <div class="apple-amount-box text-start">
                    <span class="apple-currency">{{ $purchaseOrder->currency }}</span>
                    <span class="apple-amount-value fs-5">{{ number_format($purchaseOrder->total_amount, 2) }}</span>
                </div>
            </div>
        </div>
        @if($purchaseOrder->reference)
            <div class="mt-3 pt-3 border-top" style="border-color: #ebebf0 !important;">
                <span class="apple-sub-tag pc-tag">
                    <i class="far fa-file-lines me-1 opacity-75"></i>Preliminary Contract Reference: <strong>{{ $purchaseOrder->reference }}</strong>
                </span>
            </div>
        @endif
    </div>

    <!-- Items Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold fs-6 text-dark mb-0">Order Line Items</h5>
            <span class="small text-muted">{{ $purchaseOrder->items->count() }} line items in this purchase order</span>
        </div>
    </div>

    @foreach($purchaseOrder->items as $item)
        <div class="apple-card mb-3 p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 pb-3 mb-3 border-bottom" style="border-color: #ebebf0 !important;">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-bold fs-6 text-dark">Item {{ $item->item_number }}</span>
                        @php $ist = strtolower($item->status); @endphp
                        <span class="apple-status-capsule capsule-{{ $ist }}">
                            <span class="capsule-dot"></span>
                            {{ ucfirst($item->status) }}
                        </span>
                    </div>
                    @if($item->description)
                        <div class="small text-muted">{{ $item->description }}</div>
                    @endif
                </div>

                <!-- Item Actions -->
                <div class="d-flex align-items-center gap-2">
                    @if($item->status == 'pending')
                        <a href="{{ route('vendor.item.process', $item->id) }}" class="apple-btn-primary py-1 px-3" style="font-size: 0.82rem;">
                            <i class="fas fa-play me-2"></i>Process Item
                        </a>
                    @elseif($item->status == 'processing')
                        <a href="{{ route('vendor.item.process', $item->id) }}" class="apple-btn-secondary py-1 px-3" style="font-size: 0.82rem;">
                            <i class="fas fa-edit me-1"></i>Edit Rolls
                        </a>
                        <form action="{{ route('vendor.item.mark-processed', $item->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="apple-btn-success py-1 px-3" style="font-size: 0.82rem;"
                                    onclick="return confirm('Mark this item as processed?')">
                                <i class="fas fa-check me-1"></i>Done
                            </button>
                        </form>
                    @elseif($item->status == 'completed')
                        <span class="apple-pill-status pill-success py-1 px-3" style="font-size: 0.82rem;">
                            <span class="status-dot dot-success"></span>
                            Processed
                        </span>
                        <button type="button" class="apple-btn-secondary py-1 px-2" style="font-size: 0.82rem;"
                                data-bs-toggle="modal" data-bs-target="#revertModal"
                                data-action="{{ route('vendor.item.revert-processing', $item->id) }}">
                            <i class="fas fa-undo me-1"></i>Revert
                        </button>
                    @endif
                </div>
            </div>

            <!-- Item Specs Grid -->
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="apple-field-label">Batch / Style</div>
                    <div class="fw-semibold text-dark">{{ $item->batch ?? 'N/A' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-field-label">Quantity</div>
                    <div class="fw-semibold text-dark font-monospace">
                        {{ number_format($item->quantity, 2) }} {{ ucfirst($item->unit) }}
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-field-label">Unit Price</div>
                    <div class="fw-semibold text-dark font-monospace">
                        {{ $purchaseOrder->currency }} {{ number_format($item->unit_price, 2) }}
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-field-label">Total Price</div>
                    <div class="apple-amount-box text-start">
                        <span class="apple-currency">{{ $purchaseOrder->currency }}</span>
                        <span class="apple-amount-value">{{ number_format($item->total_price, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Rolls Section -->
            @php $rollCount = $item->rolls()->count(); @endphp
            <div class="mt-3 pt-3 border-top" style="border-color: #ebebf0 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="apple-field-label mb-0">Rolls Created:</span>
                        @if($rollCount > 0)
                            <span class="apple-count-pill" style="padding: 2px 10px; font-size: 0.78rem;">
                                <i class="fas fa-layer-group me-1 opacity-50"></i>{{ $rollCount }} rolls
                            </span>
                        @else
                            <span class="apple-pill-status pill-warning" style="padding: 2px 8px; font-size: 0.75rem;">
                                <span class="status-dot dot-warning"></span>No rolls added
                            </span>
                        @endif
                    </div>
                    @if($rollCount > 0)
                        <button class="apple-btn-secondary py-1 px-3" type="button"
                                data-bs-toggle="collapse" data-bs-target="#rolls{{ $item->id }}"
                                style="font-size: 0.78rem;">
                            <span>View Rolls</span>
                            <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    @endif
                </div>

                @if($rollCount > 0)
                    <div class="collapse mt-3" id="rolls{{ $item->id }}">
                        @php
                            $sortedRolls = $item->rolls->sortBy('sequence');
                            $totalQuantity = 0;
                            foreach ($sortedRolls as $roll) {
                                if ($roll->unit == 'YD')
                                    $totalQuantity += $roll->length_yd;
                                elseif ($roll->unit == 'M')
                                    $totalQuantity += $roll->length_m;
                                else
                                    $totalQuantity += $roll->weight;
                            }
                        @endphp

                        <div class="apple-card p-0 apple-table-container">
                            <div class="d-flex justify-content-between align-items-center p-2 px-3 border-bottom bg-light">
                                <span class="small fw-bold text-dark">Roll Details ({{ $sortedRolls->count() }} rolls)</span>
                                <span class="small fw-semibold text-dark">
                                    Total Qty: {{ number_format($totalQuantity, 2) }} {{ $sortedRolls->first()->unit ?? '' }}
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="table apple-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th style="width: 20%;">Sequence Order</th>
                                            <th style="width: 25%; text-align: right;">Quantity</th>
                                            <th style="width: 15%; text-align: center;">Unit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($sortedRolls as $roll)
                                            @php
                                                $displayQty = 0;
                                                if ($roll->unit == 'YD')
                                                    $displayQty = $roll->length_yd;
                                                elseif ($roll->unit == 'M')
                                                    $displayQty = $roll->length_m;
                                                else
                                                    $displayQty = $roll->weight;
                                            @endphp
                                            <tr class="apple-table-row">
                                                <td class="fw-semibold text-dark">{{ $roll->roll_number }}</td>
                                                <td class="text-muted">{{ $roll->sequence }}</td>
                                                <td class="text-end font-monospace fw-bold text-dark">{{ number_format($displayQty, 2) }}</td>
                                                <td class="text-center">
                                                    <span class="apple-count-pill" style="padding: 1px 8px; font-size: 0.72rem;">{{ $roll->unit }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if($purchaseOrder->items->isEmpty())
        <div class="apple-card p-5 text-center">
            <div class="apple-empty-state">
                <div class="apple-empty-icon-circle mb-3">
                    <i class="fas fa-box-open fa-2x"></i>
                </div>
                <h4 class="apple-empty-title">No Items Found</h4>
                <p class="apple-empty-desc">There are no line items recorded for this purchase order.</p>
            </div>
        </div>
    @endif
</div>

<!-- Generate Packing Slip Modal -->
@if($poItems->contains('status', 'completed'))
    <div class="modal fade" id="generateSlipModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content apple-modal">
                <form action="{{ route('vendor.generate-packing-slip', $purchaseOrder->id) }}" method="POST" target="_blank">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <span class="apple-section-eyebrow text-success">Official Documentation</span>
                            <h5 class="apple-modal-title mb-0">Generate Packing Slip</h5>
                        </div>
                        <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="apple-modal-order-box mb-3">
                            <div class="small text-muted">Purchase Order</div>
                            <div class="fw-bold fs-6 text-dark">{{ $purchaseOrder->po_number }}</div>
                            <div class="small text-muted mt-1">This will include all selected processed items with their rolls.</div>
                        </div>

                        <div class="apple-field-label mb-2">Select Items to Include:</div>
                        <div class="d-flex flex-column gap-2 mb-3">
                            @foreach($purchaseOrder->items->where('status', 'completed') as $item)
                                <div class="form-check p-2 border rounded" style="background: #fafafc;">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="items[]" value="{{ $item->id }}"
                                           id="item{{ $item->id }}" checked>
                                    <label class="form-check-label small" for="item{{ $item->id }}">
                                        <strong>Item {{ $item->item_number }}</strong>: {{ $item->description }}
                                        <span class="text-muted">({{ $item->rolls()->count() }} rolls)</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <label class="apple-field-label" for="deliveryNoteInput">Delivery Note Number <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_note" id="deliveryNoteInput" class="form-control apple-input"
                                   placeholder="e.g. DN-2026-0081" required oninput="validateGenerateSlip()">
                            <div class="form-text text-muted small mt-1">Required before generating the packing slip.</div>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="show_secondary" value="1" id="showSecondary" checked>
                            <label class="form-check-label small fw-semibold" for="showSecondary">
                                Show secondary metric (e.g. Yard and Meter)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="reverse_units" value="1" id="reverseUnits">
                            <label class="form-check-label small fw-semibold" for="reverseUnits">
                                Reverse units (swap primary and secondary metrics)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="apple-btn-success" id="generateSlipBtn" disabled>
                            <i class="fas fa-file-pdf me-1"></i> Generate Slip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Revert Confirmation Modal -->
<div class="modal fade" id="revertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form id="revertForm" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-warning">Action Reversion</span>
                        <h5 class="apple-modal-title mb-0">Confirm Reversion</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="mb-2">Are you sure you want to revert this item to <strong>Processing</strong> status?</p>
                    <ul class="text-muted small mb-0">
                        <li>This will reopen the item for editing (adding/removing rolls).</li>
                        <li>The item will be marked as incomplete.</li>
                    </ul>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="apple-btn-warning">
                        <i class="fas fa-undo me-1"></i>Confirm Revert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">PO number copied</span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var revertModal = document.getElementById('revertModal');
        if (revertModal) {
            revertModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var actionUrl = button.getAttribute('data-action');
                var form = document.getElementById('revertForm');
                form.action = actionUrl;
            });
        }
    });

    function validateGenerateSlip() {
        var input = document.getElementById('deliveryNoteInput');
        var btn = document.getElementById('generateSlipBtn');
        if (input && btn) {
            btn.disabled = input.value.trim() === '';
        }
    }
</script>

@include('partials.apple-theme')
@endsection