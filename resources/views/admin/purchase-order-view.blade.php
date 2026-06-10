@extends('layouts.app')

@section('title', 'Purchase Order Details')

@section('content')
    @php
        $poItems = $purchaseOrder->items;
    @endphp
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">Purchase Order Details</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders') }}">Purchase Orders</a></li>
                        <li class="breadcrumb-item active">{{ $purchaseOrder->po_number }}</li>
                    </ol>
                </nav>
            </div>
            <div>
                <span class="badge badge-{{ $purchaseOrder->status }} fs-6">
                    {{ ucfirst($purchaseOrder->status) }}
                </span>
            </div>
        </div>

        <!-- PO Header -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5>Order Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">PO Number:</th>
                                <td>{{ $purchaseOrder->po_number }}</td>
                            </tr>
                            <tr>
                                <th>Vendor:</th>
                                <td>{{ $purchaseOrder->vendor->vendor_code }} - {{ $purchaseOrder->vendor->name }}</td>
                            </tr>
                            <tr>
                                <th>Order Date:</th>
                                <td>{{ $purchaseOrder->order_date->format('F d, Y') }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Date:</th>
                                <td>
                                    @if($purchaseOrder->delivery_date)
                                        {{ $purchaseOrder->delivery_date->format('F d, Y') }}
                                        @if($purchaseOrder->delivery_date->isPast() && $purchaseOrder->status !== 'completed')
                                            <span class="badge bg-danger ms-2">Overdue</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td class="fs-5 fw-bold text-primary">
                                    {{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->total_amount, 2) }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4 text-end">
                        @if(
                                $poItems->contains(function ($item) {
                                    return $item->status == 'completed';
                                })
                            )
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateSlipModal">
                                <i class="fas fa-file-pdf me-2"></i>Generate Packing Slip
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Items List</h5>
            </div>
            <div class="card-body">
                @foreach($purchaseOrder->items as $item)
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center">
                                    <strong class="me-2">Item {{ $item->item_number }}</strong>
                                    <span class="badge badge-{{ $item->status }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </div>
                                <div>
                                    @if($item->status == 'pending')
                                        <a href="{{ route('admin.item.process', $item->id) }}"
                                            class="btn btn-sm btn-primary d-flex align-items-center">
                                            <i class="fas fa-play me-2"></i>
                                            <span class="text-start">Process Item</span>
                                        </a>
                                    @elseif($item->status == 'processing')
                                        <div class="btn-group">
                                            <a href="{{ route('admin.item.process', $item->id) }}"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i>Edit Rolls
                                            </a>
                                            <form action="{{ route('admin.item.mark-processed', $item->id) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"
                                                    onclick="return confirm('Mark this item as processed?')">
                                                    <i class="fas fa-check me-1"></i>Done
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($item->status == 'completed')
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-success" disabled style="min-width: 100px;">
                                                <i class="fas fa-check me-1"></i>Processed
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning" style="min-width: 100px;"
                                                data-bs-toggle="modal" data-bs-target="#revertModal"
                                                data-action="{{ route('admin.item.revert-processing', $item->id) }}">
                                                <i class="fas fa-undo me-1"></i>Revert
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-muted fw-semibold" style="font-size: 0.75rem;">
                                {{ $item->description }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                        <tr>
                                            <th style="width: 140px;">
                                                <div class="d-flex justify-content-between">
                                                    <span>Batch</span>
                                                    <span>:</span>
                                                </div>
                                            </th>
                                            <td>{{ $item->batch ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <div class="d-flex justify-content-between">
                                                    <span>Quantity</span>
                                                    <span>:</span>
                                                </div>
                                            </th>
                                            <td>{{ number_format($item->quantity, 2) }} {{ ucfirst($item->unit) }}</td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <div class="d-flex justify-content-between">
                                                    <span>Unit Price</span>
                                                    <span>:</span>
                                                </div>
                                            </th>
                                            <td>{{ $item->purchaseOrder->currency }} {{ number_format($item->unit_price, 2) }}
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th style="width: 140px;">
                                                <div class="d-flex justify-content-between">
                                                    <span>Total Price</span>
                                                    <span>:</span>
                                                </div>
                                            </th>
                                            <td class="fw-bold">{{ $item->purchaseOrder->currency }}
                                                {{ number_format($item->total_price, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <div class="d-flex justify-content-between">
                                                    <span>Rolls Created</span>
                                                    <span>:</span>
                                                </div>
                                            </th>
                                            <td>
                                                @php
                                                    // Force load the relationship count
                                                    $rollCount = $item->rolls()->count();
                                                @endphp
                                                @if($rollCount > 0)
                                                    <span class="badge bg-success">{{ $rollCount }} rolls</span>
                                                    <button class="btn btn-sm btn-outline-info ms-2" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#rolls{{ $item->id }}">
                                                        View Details
                                                    </button>
                                                @else
                                                    <span class="badge bg-warning">No rolls added</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Rolls Collapse -->
                            @php
                                $rollCount = $item->rolls()->count();
                            @endphp
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

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Rolls Details:</h6>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2">Total Rolls: {{ $sortedRolls->count() }}</span>
                                            <span class="badge bg-success">Total Qty: {{ number_format($totalQuantity, 2) }}
                                                {{ $sortedRolls->first()->unit ?? '' }}</span>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Roll Number</th>
                                                    <th>Sequence Order</th>
                                                    <th>Quantity</th>
                                                    <th>Unit</th>
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
                                                    <tr>
                                                        <td>{{ $roll->roll_number }}</td>
                                                        <td>{{ $roll->sequence }}</td>
                                                        <td>{{ number_format($displayQty, 2) }}</td>
                                                        <td>{{ $roll->unit }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($purchaseOrder->items->isEmpty())
                    <div class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-box-open fa-2x mb-3"></i>
                            <p>No items found in this purchase order</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Generate Packing Slip Modal -->
    @if(
            $poItems->contains(function ($item) {
                return $item->status == 'completed';
            })
        )
        <div class="modal fade" id="generateSlipModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.generate-packing-slip', $purchaseOrder->id) }}" method="POST"
                        target="_blank">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Generate Packing Slip</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Generate packing slip for PO: {{ $purchaseOrder->po_number }}</p>
                            <p class="text-muted">This will include all processed items with their rolls.</p>

                            <h6>Select Items to Include:</h6>
                            @foreach($purchaseOrder->items->where('status', 'completed') as $item)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="items[]" value="{{ $item->id }}"
                                        id="item{{ $item->id }}" checked>
                                    <label class="form-check-label" for="item{{ $item->id }}">
                                        Item {{ $item->item_number }}: {{ $item->description }}
                                        ({{ $item->rolls()->count() }} rolls)
                                    </label>
                                </div>
                            @endforeach

                            @if($purchaseOrder->items->where('status', 'completed')->count() == 0)
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No items have been processed yet. Please mark items as processed first.
                                </div>
                            @else
                                <div class="mt-4 mb-2">
                                    <label class="form-label fw-bold" for="deliveryNoteInput">Delivery Note Number <span class="text-danger">*</span></label>
                                    <input type="text" name="delivery_note" id="deliveryNoteInput" class="form-control" 
                                           placeholder="Enter Delivery Note Number" required oninput="validateGenerateSlip()">
                                    <div class="form-text text-muted">Required before generating the packing slip.</div>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="generateSlipBtn" disabled>
                                Generate Slip
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Revert Confirmation Modal -->
    <div class="modal fade" id="revertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="revertForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Reversion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Are you sure you want to revert this item to <strong>Processing</strong> status?</p>
                        <ul class="text-muted small mb-0">
                            <li>This will reopen the item for editing (adding/removing rolls).</li>
                            <li>The item will be marked as incomplete.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo me-1"></i>Confirm Revert
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

        .btn-group .btn {
            border-radius: 0.25rem !important;
            margin: 0 2px;
        }

        .btn-group .btn:first-child:not(:last-child) {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }

        .btn-group .btn:last-child:not(:first-child) {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
    </style>

    <script>
        // Auto-dismiss alerts
        setTimeout(function () {
            $('.alert:not(.alert-permanent)').fadeOut('slow');
        }, 5000);
    </script>
@endsection