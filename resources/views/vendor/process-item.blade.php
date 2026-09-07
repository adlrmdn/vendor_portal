@extends('layouts.app')

@section('title', 'Process Item')

@section('content')
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">Process Item</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('vendor.purchase-orders') }}">My Orders</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('vendor.purchase-order.view', $item->purchaseOrder->id) }}">PO:
                                {{ $item->purchaseOrder->po_number }}</a></li>
                        <li class="breadcrumb-item active">Item {{ $item->item_number }}</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="{{ route('vendor.purchase-order.view', $item->purchaseOrder->id) }}" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to PO
                </a>
                <span class="badge badge-{{ $item->status }} fs-6 px-3 py-2">
                    {{ ucfirst($item->status) }}
                </span>
            </div>
        </div>

        <!-- Display Success/Error Messages -->
        <!-- Flash messages are handled in the layout file -->

        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Item Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="text-muted fw-bold small">Item Description</div>
                    <div>
                        @if($item->status == 'processing')
                            <form action="{{ route('vendor.item.mark-processed', $item->id) }}" method="POST" class="d-inline"
                                id="markProcessedForm">
                                @csrf
                                <button type="button" class="btn btn-success btn-sm" onclick="showConfirmationModal()">
                                    <i class="fas fa-check me-2"></i>Mark as Processed
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="fw-semibold" style="font-size: 0.85rem; line-height: 1.4;">
                    {{ $item->description }}
                </div>

                <hr class="my-3">

                <div class="row">
                    <div class="col-md-2">
                        <label class="text-muted small fw-bold">Item Number</label>
                        <p class="mb-0 fw-bold text-dark">{{ $item->item_number }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small fw-bold">Batch</label>
                        <p class="mb-0">{{ $item->batch ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-2">
                        <label class="text-muted small fw-bold">Order Qty</label>
                        <p class="mb-0 fw-bold">{{ number_format($item->quantity, 2) }} {{ ucfirst($item->unit) }}</p>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-1">
                            <span class="text-muted small fw-bold me-2">Unit Price:</span>
                            <span class="fw-bold">{{ $item->purchaseOrder->currency }}
                                {{ number_format($item->unit_price, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-muted small fw-bold me-2">Total Price:</span>
                            <span class="fw-bold text-primary">{{ $item->purchaseOrder->currency }}
                                {{ number_format($item->total_price, 2) }}</span>
                        </div>
                    </div>
                    <div class="col-md-12 mt-2">
                        <div class="alert alert-info alert-permanent py-2 px-3 small border-0 mb-0 d-flex align-items-center justify-content-between" style="background-color: #eef6ff; color: #0056b3;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>
                                    <strong>Delivery Tolerance:</strong> 
                                    Under: <span class="fw-bold">{{ number_format($item->getEffectiveUnderdelivery(), 2) }}%</span> (Min: {{ number_format($item->getMinQuantityLimit(), 2) }}) | 
                                    Over: <span class="fw-bold">{{ number_format($item->getEffectiveOverdelivery(), 2) }}%</span> (Max: {{ number_format($item->getMaxQuantityLimit(), 2) }})
                                </div>
                            </div>
                            @if($item->status != 'completed')
                                @php
                                    $pendingTolerance = \App\Models\ToleranceAmendmentRequest::where('po_item_id', $item->id)
                                        ->where('type', 'tolerance')
                                        ->where('status', 'pending')
                                        ->first();
                                @endphp
                                @if($pendingTolerance)
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-warning text-dark" title="Requested {{ $pendingTolerance->created_at->format('M d, Y H:i') }}">
                                            <i class="fas fa-clock me-1"></i>Pending: {{ number_format($pendingTolerance->new_underdelivery, 2) }}% / {{ number_format($pendingTolerance->new_overdelivery, 2) }}%
                                        </span>
                                        <form action="{{ route('vendor.tolerance.cancel', $pendingTolerance->id) }}" method="POST"
                                              onsubmit="return confirm('Recall this pending request so you can submit a new one?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-bold" title="Recall Request">
                                                <i class="fas fa-undo me-1"></i>Recall
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <button class="btn btn-sm btn-primary py-0 px-2 fw-bold" title="Amend Tolerance"
                                            data-bs-toggle="modal" data-bs-target="#amendToleranceModal"
                                            data-item-id="{{ $item->id }}"
                                            data-item-number="{{ $item->item_number }}"
                                            data-under="{{ $item->getEffectiveUnderdelivery() }}"
                                            data-over="{{ $item->getEffectiveOverdelivery() }}"
                                            data-target-qty="{{ $item->getGlobalOrderedQuantity() }}"
                                            data-other-delivered="{{ $item->getOtherCompletedSiblingsDeliveredQuantity() }}"
                                            data-unit="{{ strtoupper($item->unit) }}">
                                        <i class="fas fa-edit me-1"></i>Amend
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rolls Management Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 d-flex align-items-center">
                            Manage Rolls
                            <button type="button" class="btn btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#instructionsModal" title="Show Instructions">
                                <i class="fas fa-info-circle fa-lg"></i>
                            </button>
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2" id="totalRollsBadge">
                                Total Rolls: {{ $item->rolls->where('deleted', false)->count() }}
                            </span>
                            @php
                                $activeRolls = $item->rolls->where('deleted', false);
                                $totalQty = 0;
                                $unit = $activeRolls->first()->unit ?? $item->unit;
                                foreach ($activeRolls as $r) {
                                    if ($r->unit == 'YD')
                                        $totalQty += $r->length_yd;
                                    elseif ($r->unit == 'M')
                                        $totalQty += $r->length_m;
                                    else
                                        $totalQty += $r->weight;
                                }
                            @endphp
                            <span class="badge bg-success" id="totalQtyBadge">
                                Total Qty: {{ number_format($totalQty, 2) }} {{ ucfirst($unit) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            // All metric columns (YD / M / KG) are shown on every roll; only the
                            // original order metric is mandatory. Non-metric order units (PCS/UNIT)
                            // keep the legacy convention of storing their qty in the weight column.
                            $orderUnit = strtoupper($item->unit);
                            $isMetricItem = in_array($orderUnit, ['YD', 'M', 'KG']);
                        @endphp
                        <form action="{{ route('vendor.item.save-rolls', $item->id) }}" method="POST" id="rollsForm">
                            @csrf

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Original Order Metric</label>
                                    <input type="text" class="form-control fw-bold" value="{{ $orderUnit }}" disabled>
                                    <small class="text-muted">Only the {{ $orderUnit }} quantity is mandatory per roll — other metrics are optional. YD and M fill each other automatically.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Number of Rolls</label>
                                    <div class="input-group">
                                        <input type="number" min="1" max="500" class="form-control" id="rollCount"
                                            value="{{ old('roll_count', max($item->rolls->where('deleted', false)->count(), 1)) }}">
                                        <button type="button" class="btn btn-primary" onclick="generateRollFields()">
                                            Generate Roll Inputs
                                        </button>
                                    </div>
                                    <small class="text-muted">Enter total number of rolls and click Generate</small>
                                </div>
                            </div>

                            <!-- Rolls Input Container -->
                            <div id="rollsContainer">
                                @php
                                    $existingRolls = $item->rolls->where('deleted', false);
                                @endphp

                                @if($existingRolls->count() > 0)
                                    <!-- Show existing rolls -->
                                    @foreach($existingRolls as $index => $roll)
                                        <div class="roll-input card mb-3" id="roll-{{ $roll->id }}" data-roll-id="{{ $roll->id }}">
                                            <div class="card-body">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-1">
                                                        @php
                                                            // roll_number = {PO}-{item#}-{seq}, where {PO} itself
                                                            // is now hyphen-separated (e.g. "PO-2607-01046") — so
                                                            // the last two segments are peeled off first and
                                                            // everything left over is the PO chunk, keeping the
                                                            // badge to its original 3 stacked lines.
                                                            $rollNumParts = explode('-', $roll->roll_number);
                                                            $rollSeq = array_pop($rollNumParts);
                                                            $rollItemNo = array_pop($rollNumParts);
                                                            $rollPoChunk = implode('-', $rollNumParts);
                                                        @endphp
                                                        <div
                                                            class="roll-number small font-monospace text-secondary lh-sm user-select-all">
                                                            <div>{{ $rollPoChunk }}</div>
                                                            <div>{{ $rollItemNo }}</div>
                                                            <div>{{ $rollSeq }}</div>
                                                        </div>
                                                        <small>
                                                            <a href="{{ route('vendor.roll.qr', $roll) }}" target="_blank"
                                                                class="text-primary">
                                                                <i class="fas fa-qrcode"></i> View QR
                                                            </a>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Roll No.</label>
                                                        <input type="text" class="form-control" name="rolls[{{ $roll->id }}][vendor_roll_no]" value="{{ old('rolls.'.$roll->id.'.vendor_roll_no', $roll->vendor_roll_no) }}" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Bale No.</label>
                                                        <input type="text" class="form-control" name="rolls[{{ $roll->id }}][bale_no]" value="{{ old('rolls.'.$roll->id.'.bale_no', $roll->bale_no) }}" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Lot-ID</label>
                                                        <input type="text" class="form-control" name="rolls[{{ $roll->id }}][internal_id]" value="{{ old('rolls.'.$roll->id.'.internal_id', $roll->internal_id) }}" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Color</label>
                                                        <input type="text" class="form-control" name="rolls[{{ $roll->id }}][color]" value="{{ old('rolls.'.$roll->id.'.color', $roll->color) }}" placeholder="Optional">
                                                    </div>
                                                    @if(!$isMetricItem)
                                                        <div class="col-md-2">
                                                            <label class="form-label">Qty ({{ $orderUnit }}) <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" class="form-control roll-weight roll-primary"
                                                                name="rolls[{{ $roll->id }}][weight]"
                                                                value="{{ old('rolls.'.$roll->id.'.weight', (float) $roll->weight ?: '') }}"
                                                                placeholder="Required" oninput="updateTotalQtyBadge();" required>
                                                        </div>
                                                    @endif
                                                    <div class="col-md-2">
                                                        <label class="form-label">Length (YD){!! $orderUnit == 'YD' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-yd {{ $orderUnit == 'YD' ? 'roll-primary' : '' }}"
                                                            name="rolls[{{ $roll->id }}][length_yd]"
                                                            value="{{ old('rolls.'.$roll->id.'.length_yd', (float) $roll->length_yd ?: '') }}"
                                                            placeholder="{{ $orderUnit == 'YD' ? 'Required' : 'Optional' }}"
                                                            oninput="syncLength(this, 'yd');" {{ $orderUnit == 'YD' ? 'required' : '' }}>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Length (M){!! $orderUnit == 'M' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-m {{ $orderUnit == 'M' ? 'roll-primary' : '' }}"
                                                            name="rolls[{{ $roll->id }}][length_m]"
                                                            value="{{ old('rolls.'.$roll->id.'.length_m', (float) $roll->length_m ?: '') }}"
                                                            placeholder="{{ $orderUnit == 'M' ? 'Required' : 'Optional' }}"
                                                            oninput="syncLength(this, 'm');" {{ $orderUnit == 'M' ? 'required' : '' }}>
                                                    </div>
                                                    @if($isMetricItem)
                                                        <div class="col-md-2">
                                                            <label class="form-label">Weight (KG){!! $orderUnit == 'KG' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                            <input type="number" step="0.01" class="form-control roll-weight {{ $orderUnit == 'KG' ? 'roll-primary' : '' }}"
                                                                name="rolls[{{ $roll->id }}][weight]"
                                                                value="{{ old('rolls.'.$roll->id.'.weight', (float) $roll->weight ?: '') }}"
                                                                placeholder="{{ $orderUnit == 'KG' ? 'Required' : 'Optional' }}"
                                                                oninput="updateTotalQtyBadge();" {{ $orderUnit == 'KG' ? 'required' : '' }}>
                                                        </div>
                                                    @endif
                                                    <div class="col-md-1 text-center">
                                                        <button type="button" class="btn btn-danger btn-sm remove-roll-btn"
                                                            onclick="removeExistingRoll('{{ $roll->id }}')" title="Remove Roll"
                                                            tabindex="-1">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <!-- Hidden fields for roll data -->
                                                <input type="hidden" name="rolls[{{ $roll->id }}][id]" value="{{ $roll->id }}">
                                                <input type="hidden" name="rolls[{{ $roll->id }}][delete]" class="delete-flag"
                                                    value="0" id="delete-{{ $roll->id }}">
                                            </div>
                                        </div>
                                    @endforeach
                                @endif

                                <!-- New Rolls Container -->
                                <div id="newRollsContainer">
                                    <!-- New rolls will be added here -->
                                    <!-- Default new roll if no existing rolls -->
                                    @if($existingRolls->count() == 0)
                                        <div class="roll-input card mb-3 new-roll" id="new-roll-0">
                                            <div class="card-body">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-1">
                                                        <h6 class="mb-0 roll-number">Roll 1</h6>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Roll No.</label>
                                                        <input type="text" class="form-control" name="new_rolls[0][vendor_roll_no]" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Bale No.</label>
                                                        <input type="text" class="form-control" name="new_rolls[0][bale_no]" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Lot-ID</label>
                                                        <input type="text" class="form-control" name="new_rolls[0][internal_id]" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Color</label>
                                                        <input type="text" class="form-control" name="new_rolls[0][color]" placeholder="Optional">
                                                    </div>
                                                    @if(!$isMetricItem)
                                                        <div class="col-md-2">
                                                            <label class="form-label">Qty ({{ $orderUnit }}) <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" class="form-control roll-weight roll-primary"
                                                                name="new_rolls[0][weight]" placeholder="Required"
                                                                oninput="updateTotalQtyBadge();" required>
                                                        </div>
                                                    @endif
                                                    <div class="col-md-2">
                                                        <label class="form-label">Length (YD){!! $orderUnit == 'YD' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-yd {{ $orderUnit == 'YD' ? 'roll-primary' : '' }}"
                                                            name="new_rolls[0][length_yd]"
                                                            placeholder="{{ $orderUnit == 'YD' ? 'Required' : 'Optional' }}"
                                                            oninput="syncLength(this, 'yd');" {{ $orderUnit == 'YD' ? 'required' : '' }}>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Length (M){!! $orderUnit == 'M' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-m {{ $orderUnit == 'M' ? 'roll-primary' : '' }}"
                                                            name="new_rolls[0][length_m]"
                                                            placeholder="{{ $orderUnit == 'M' ? 'Required' : 'Optional' }}"
                                                            oninput="syncLength(this, 'm');" {{ $orderUnit == 'M' ? 'required' : '' }}>
                                                    </div>
                                                    @if($isMetricItem)
                                                        <div class="col-md-2">
                                                            <label class="form-label">Weight (KG){!! $orderUnit == 'KG' ? ' <span class="text-danger">*</span>' : '' !!}</label>
                                                            <input type="number" step="0.01" class="form-control roll-weight {{ $orderUnit == 'KG' ? 'roll-primary' : '' }}"
                                                                name="new_rolls[0][weight]"
                                                                placeholder="{{ $orderUnit == 'KG' ? 'Required' : 'Optional' }}"
                                                                oninput="updateTotalQtyBadge();" {{ $orderUnit == 'KG' ? 'required' : '' }}>
                                                        </div>
                                                    @endif
                                                    <div class="col-md-1 text-center">
                                                        <button type="button" class="btn btn-danger btn-sm"
                                                            onclick="removeNewRoll(0)" title="Remove Roll" tabindex="-1">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-between mt-4">
                                <div>
                                    <button type="button" class="btn btn-outline-primary" onclick="addSingleRoll()">
                                        <i class="fas fa-plus me-2"></i>Add Single Roll
                                    </button>
                                    <button type="button" class="btn btn-outline-info ms-2" onclick="document.getElementById('importFile').click()">
                                        <i class="fas fa-file-import me-2"></i>Import (Excel/PDF)
                                    </button>
                                    <a href="{{ route('vendor.item.rolls-template', $item->id) }}" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-file-download me-2"></i>Download Template
                                    </a>
                                    <input type="file" id="importFile" class="d-none" accept=".xlsx,.xls,.csv,.pdf" onchange="handleFileUpload(this)">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-danger me-2" onclick="clearAllRolls()">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Rolls
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>Save Rolls
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Instructions Sidebar Removed (moved to popup) -->
        </div>

        <!-- Confirmation Modal -->
        <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="confirmationModalLabel"><i class="fas fa-shipping-fast me-2"></i>Finalize Item Processing</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="standardConfirmation">
                            <p class="fs-5">Are you sure you want to mark this item as processed? This will finalize your current rolls and submit them.</p>
                        </div>
                        
                        <div id="partialShipmentWarning" class="d-none">
                            <div class="alert alert-warning border-0 alert-permanent">
                                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Under-Delivery Detected</h4>
                                <p class="mb-0">The total quantity processed (<span id="modalDeliveredQty" class="fw-bold"></span>) is below the minimum allowed tolerance (<span id="modalMinQty" class="fw-bold"></span>).</p>
                            </div>
                            
                            @php
                                $pendingRequest = \App\Models\ToleranceAmendmentRequest::where('po_item_id', $item->id)
                                    ->where('type', 'partial_shipment')
                                    ->where('status', 'pending')
                                    ->first();
                                $isApproved = $item->hasApprovedPartialShipment();
                            @endphp

                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold"><i class="fas fa-layer-group me-2"></i>Partial Shipment Option:</h6>
                                    <p class="small text-muted mb-3">Choosing "Partial Shipment" will complete the <strong>CURRENT</strong> batch and automatically create a new item for the <strong>remaining</strong> balance (<span id="modalRemainingQty" class="fw-bold text-primary"></span>) in this PO.</p>
                                    
                                    @if($pendingRequest)
                                        <div class="alert alert-info border-0 mb-0 d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="fas fa-clock me-2"></i><strong>Request Pending Approval</strong><br>
                                                <small>Submitted on: {{ $pendingRequest->created_at->format('M d, Y H:i') }}</small>
                                            </div>
                                            <form action="{{ route('vendor.tolerance.cancel', $pendingRequest->id) }}" method="POST"
                                                  onsubmit="return confirm('Recall this pending request so you can submit a new one?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                    <i class="fas fa-undo me-1"></i>Recall
                                                </button>
                                            </form>
                                        </div>
                                    @elseif(!$isApproved)
                                        <div class="p-3 border rounded bg-white">
                                            <label class="form-label small fw-bold">Note for Approval Request:</label>
                                            <textarea id="partialRequestNote" class="form-control form-control-sm mb-2" rows="2" placeholder="Explain why a partial shipment is needed..."></textarea>
                                            <p class="small text-danger mb-0 d-none" id="partialNoteError">Please provide a reason for the request.</p>
                                        </div>
                                    @else
                                        <div class="alert alert-success border-0 mb-0">
                                            <i class="fas fa-check-circle me-2"></i><strong>Partial Shipment Approved</strong><br>
                                            <small>Management has authorized this split.</small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <div id="modalButtons">
                            <button type="button" class="btn btn-primary" id="confirmActionBtn">Yes, Mark as Processed</button>
                            
                            @if($isApproved)
                                <button type="button" class="btn btn-warning d-none" id="partialShipmentBtn" onclick="submitPartialShipment()">
                                    <i class="fas fa-layer-group me-1"></i>Execute Partial Shipment
                                </button>
                                <form id="partialShipmentForm" action="{{ route('vendor.item.mark-partial', $item->id) }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            @elseif(!$pendingRequest)
                                <button type="button" class="btn btn-primary d-none" id="requestPartialBtn" onclick="submitPartialRequest()">
                                    <i class="fas fa-paper-plane me-1"></i>Request Partial Shipment
                                </button>
                                <form id="partialRequestForm" action="{{ route('vendor.partial.request') }}" method="POST" class="d-none">
                                    @csrf
                                    <input type="hidden" name="po_item_id" value="{{ $item->id }}">
                                    <input type="hidden" name="requested_qty" id="formRequestedQty">
                                    <input type="hidden" name="reason" id="formPartialReason">
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        .roll-input {
            transition: all 0.3s ease;
            position: relative;
        }

        .roll-input:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .roll-input.marked-for-deletion {
            opacity: 0.5;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        .roll-input.marked-for-deletion .card-body {
            color: #721c24;
        }

        .roll-input.marked-for-deletion input,
        .roll-input.marked-for-deletion select {
            text-decoration: line-through;
        }
    </style>

    <script>
        let newRollCounter = {{ $existingRolls->count() == 0 ? 1 : 0 }};
        let removedRolls = [];

        // The order metric is fixed per item: it is the only mandatory quantity.
        const ORDER_UNIT = @json($orderUnit);
        const IS_METRIC_ITEM = @json($isMetricItem);

        // Generate roll input fields based on count
        function generateRollFields() {
            const countInput = document.getElementById('rollCount');
            const count = parseInt(countInput.value) || 1;

            if (count < 1 || count > 500) {
                alert('Please enter a number between 1 and 500');
                return;
            }

            const newContainer = document.getElementById('newRollsContainer');

            // Clear new rolls container
            newContainer.innerHTML = '';
            newRollCounter = 0;

            // Count existing non-deleted rolls (including those with data-roll-id attribute)
            const existingRolls = document.querySelectorAll('.roll-input[data-roll-id]:not(.marked-for-deletion)');
            const existingCount = existingRolls.length;

            // Calculate how many new rolls we need
            const newRollsNeeded = Math.max(0, count - existingCount);

            if (newRollsNeeded === 0) {
                alert('The requested total roll count (' + count + ') is equal to or less than the current number of rolls (' + existingCount + '). No new rolls added.');
                return;
            }

            // Add new rolls
            for (let i = 0; i < newRollsNeeded; i++) {
                addNewRoll();
            }

            updateRollNumbers();
            updateTotalRollsBadge();
            calculateTotal();
        }

        // Add a single new roll
        function addSingleRoll() {
            addNewRoll();
            updateRollNumbers();

            // Update count input
            const totalRolls = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
            document.getElementById('rollCount').value = totalRolls;

            // Scroll to new roll
            const newRoll = document.getElementById(`new-roll-${newRollCounter - 1}`);
            if (newRoll) {
                newRoll.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Add new roll to container. Imported rows pass {internal_id, quantity,
        // vendor_roll_no, bale_no, color}; quantity is in the order metric.
        function addNewRoll(data = {}) {
            const internalId = data.internal_id ?? '';
            const vendorRollNo = data.vendor_roll_no ?? '';
            const baleNo = data.bale_no ?? '';
            const color = data.color ?? '';
            const quantity = data.quantity ?? null;
            const container = document.getElementById('newRollsContainer');
            if (!container) {
                container = document.getElementById('rollsContainer');
            }

            const rollDiv = document.createElement('div');
            rollDiv.className = 'roll-input card mb-3 new-roll';
            rollDiv.id = `new-roll-${newRollCounter}`;

            // Prefill metrics: explicit per-metric values win; a bare `quantity`
            // goes to the order-metric column; the missing YD/M is derived.
            let lengthYd = data.length_yd ?? '';
            let lengthM = data.length_m ?? '';
            let weight = data.weight ?? '';

            if (quantity !== null && quantity !== '') {
                if (ORDER_UNIT === 'YD' && lengthYd === '') {
                    lengthYd = quantity;
                } else if (ORDER_UNIT === 'M' && lengthM === '') {
                    lengthM = quantity;
                } else if (ORDER_UNIT !== 'YD' && ORDER_UNIT !== 'M' && weight === '') {
                    weight = quantity;
                }
            }
            if (lengthYd !== '' && lengthM === '') {
                lengthM = (parseFloat(lengthYd) * 0.9144).toFixed(2);
            } else if (lengthM !== '' && lengthYd === '') {
                lengthYd = (parseFloat(lengthM) / 0.9144).toFixed(2);
            }

            const req = ' <span class="text-danger">*</span>';

            const pcsCol = !IS_METRIC_ITEM ? `
                         <div class="col-md-2">
                             <label class="form-label">Qty (${ORDER_UNIT})${req}</label>
                             <input type="number" step="0.01" class="form-control roll-weight roll-primary"
                                    name="new_rolls[${newRollCounter}][weight]"
                                    placeholder="Required"
                                    oninput="updateTotalQtyBadge();"
                                    value="${weight}"
                                    required>
                         </div>` : '';

            const kgCol = IS_METRIC_ITEM ? `
                         <div class="col-md-2">
                             <label class="form-label">Weight (KG)${ORDER_UNIT === 'KG' ? req : ''}</label>
                             <input type="number" step="0.01" class="form-control roll-weight ${ORDER_UNIT === 'KG' ? 'roll-primary' : ''}"
                                    name="new_rolls[${newRollCounter}][weight]"
                                    placeholder="${ORDER_UNIT === 'KG' ? 'Required' : 'Optional'}"
                                    oninput="updateTotalQtyBadge();"
                                    value="${weight}"
                                    ${ORDER_UNIT === 'KG' ? 'required' : ''}>
                         </div>` : '';

            rollDiv.innerHTML = `
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                         <div class="col-md-1">
                             <h6 class="mb-0 roll-number">New Roll</h6>
                         </div>
                         <div class="col-md-1">
                             <label class="form-label">Roll No.</label>
                             <input type="text" class="form-control"
                                    name="new_rolls[${newRollCounter}][vendor_roll_no]"
                                    placeholder="Optional"
                                    value="${vendorRollNo}">
                         </div>
                         <div class="col-md-1">
                             <label class="form-label">Bale No.</label>
                             <input type="text" class="form-control"
                                    name="new_rolls[${newRollCounter}][bale_no]"
                                    placeholder="Optional"
                                    value="${baleNo}">
                         </div>
                         <div class="col-md-1">
                             <label class="form-label">Lot-ID</label>
                             <input type="text" class="form-control"
                                    name="new_rolls[${newRollCounter}][internal_id]"
                                    placeholder="Optional"
                                    value="${internalId}">
                         </div>
                         <div class="col-md-1">
                             <label class="form-label">Color</label>
                             <input type="text" class="form-control"
                                    name="new_rolls[${newRollCounter}][color]"
                                    placeholder="Optional"
                                    value="${color}">
                         </div>
                         ${pcsCol}
                         <div class="col-md-2">
                             <label class="form-label">Length (YD)${ORDER_UNIT === 'YD' ? req : ''}</label>
                             <input type="number" step="0.01" class="form-control roll-length-yd ${ORDER_UNIT === 'YD' ? 'roll-primary' : ''}"
                                    name="new_rolls[${newRollCounter}][length_yd]"
                                    placeholder="${ORDER_UNIT === 'YD' ? 'Required' : 'Optional'}"
                                    oninput="syncLength(this, 'yd');"
                                    value="${lengthYd}"
                                    ${ORDER_UNIT === 'YD' ? 'required' : ''}>
                         </div>
                         <div class="col-md-2">
                             <label class="form-label">Length (M)${ORDER_UNIT === 'M' ? req : ''}</label>
                             <input type="number" step="0.01" class="form-control roll-length-m ${ORDER_UNIT === 'M' ? 'roll-primary' : ''}"
                                    name="new_rolls[${newRollCounter}][length_m]"
                                    placeholder="${ORDER_UNIT === 'M' ? 'Required' : 'Optional'}"
                                    oninput="syncLength(this, 'm');"
                                    value="${lengthM}"
                                    ${ORDER_UNIT === 'M' ? 'required' : ''}>
                         </div>
                         ${kgCol}
                         <div class="col-md-1 text-center">
                             <button type="button" class="btn btn-danger btn-sm"
                                     onclick="removeNewRoll(${newRollCounter})"
                                     title="Remove Roll" tabindex="-1">
                                 <i class="fas fa-times"></i>
                             </button>
                         </div>
                    </div>
                </div>
            `;

            if (container.id === 'newRollsContainer') {
                container.appendChild(rollDiv);
            } else {
                // If no newRollsContainer yet, create it
                const newContainer = document.createElement('div');
                newContainer.id = 'newRollsContainer';
                newContainer.appendChild(rollDiv);
                container.appendChild(newContainer);
            }

            newRollCounter++;
            updateTotalRollsBadge();
        }

        // Remove a new roll (not saved yet)
        function removeNewRoll(rollCounter) {
            const rollElement = document.getElementById(`new-roll-${rollCounter}`);
            if (rollElement) {
                rollElement.remove();
                updateRollNumbers();
                const totalRolls = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
                document.getElementById('rollCount').value = totalRolls;
                updateTotalRollsBadge();
                updateTotalQtyBadge();
            }
        }

        // Remove an existing saved roll
        function removeExistingRoll(rollId) {
            const rollElement = document.getElementById(`roll-${rollId}`);
            if (!rollElement) return;

            // Mark for deletion
            rollElement.classList.add('marked-for-deletion');

            // Make inputs read-only/non-interactive instead of disabled
            // This ensures they are still submitted so validation passes and ID is available
            const inputs = rollElement.querySelectorAll('input:not(.delete-flag)');
            inputs.forEach(input => {
                input.readOnly = true;
            });

            // Mark delete flag
            const deleteFlag = document.getElementById(`delete-${rollId}`);
            if (deleteFlag) {
                deleteFlag.value = "1";
            }

            // Change button to "Undo"
            const button = rollElement.querySelector('.remove-roll-btn');
            if (button) {
                button.innerHTML = '<i class="fas fa-undo"></i>';
                button.classList.remove('btn-danger');
                button.classList.add('btn-warning');
                button.onclick = function () { undoRemoveRoll(rollId); };
                button.title = "Undo Remove";
            }



            updateRollNumbers();
            const totalRolls = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
            document.getElementById('rollCount').value = totalRolls;
            updateTotalRollsBadge();
            calculateTotal();
        }

        // Undo removal of an existing roll
        function undoRemoveRoll(rollId) {
            const rollElement = document.getElementById(`roll-${rollId}`);
            if (!rollElement) return;

            // Remove deletion marking
            rollElement.classList.remove('marked-for-deletion');

            // Re-enable inputs
            const inputs = rollElement.querySelectorAll('input:not(.delete-flag)');
            inputs.forEach(input => {
                input.readOnly = false;
            });

            // Reset delete flag
            const deleteFlag = document.getElementById(`delete-${rollId}`);
            if (deleteFlag) {
                deleteFlag.value = "0";
            }

            // Change button back to "Remove"
            const button = rollElement.querySelector('.remove-roll-btn');
            if (button) {
                button.innerHTML = '<i class="fas fa-times"></i>';
                button.classList.remove('btn-warning');
                button.classList.add('btn-danger');
                button.onclick = function () { removeExistingRoll(rollId); };
                button.title = "Remove Roll";
            }



            updateRollNumbers();
            const totalRolls = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
            document.getElementById('rollCount').value = totalRolls;
            updateTotalRollsBadge();
            calculateTotal();
        }

        // Clear all rolls at once
        function clearAllRolls() {
            if (confirm('Are you sure you want to clear all rolls? This will remove all new rolls and mark all existing rolls for deletion.')) {
                // 1. Clear new rolls container
                const newContainer = document.getElementById('newRollsContainer');
                if (newContainer) {
                    newContainer.innerHTML = '';
                }
                newRollCounter = 0;

                // 2. Mark all existing rolls for deletion
                const existingRollsElements = document.querySelectorAll('.roll-input[data-roll-id]');
                existingRollsElements.forEach(rollElement => {
                    const rollId = rollElement.getAttribute('data-roll-id');
                    if (rollId && !rollElement.classList.contains('marked-for-deletion')) {
                        rollElement.classList.add('marked-for-deletion');
                        const inputs = rollElement.querySelectorAll('input:not(.delete-flag)');
                        inputs.forEach(input => {
                            input.readOnly = true;
                        });
                        const deleteFlag = document.getElementById(`delete-${rollId}`);
                        if (deleteFlag) {
                            deleteFlag.value = "1";
                        }
                        const button = rollElement.querySelector('.remove-roll-btn');
                        if (button) {
                            button.innerHTML = '<i class="fas fa-undo"></i>';
                            button.classList.remove('btn-danger');
                            button.classList.add('btn-warning');
                            button.onclick = function () { undoRemoveRoll(rollId); };
                            button.title = "Undo Remove";
                        }
                    }
                });

                // 3. Update totals once at the end
                updateRollNumbers();
                const totalRolls = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
                document.getElementById('rollCount').value = totalRolls;
                updateTotalRollsBadge();
                updateTotalQtyBadge();
                calculateTotal();
            }
        }

        // Update roll numbers after changes
        function updateRollNumbers() {
            const rollElements = document.querySelectorAll('.roll-input:not(.marked-for-deletion)');
            let rollNumber = 1;

            rollElements.forEach((element) => {
                const rollNumberElement = element.querySelector('.roll-number');
                if (rollNumberElement) {
                    const currentText = rollNumberElement.textContent.trim();
                    // Only rename if it's a generic name "New Roll" or "Roll X", OR if we are re-indexing everything.
                    // But we want to preserve things like "PO-XXXX".
                    // Logic: If it starts with "Roll " followed by a digit, or is "New Roll" (with optional number), update it.
                    // If it has a complex ID format, leave it alone.

                    if (currentText === 'New Roll' || currentText.match(/^(Roll|New Roll)\s*\d*$/)) {
                        rollNumberElement.textContent = `Roll ${rollNumber}`;
                    }
                    // Otherwise preserve existing name
                }
                rollNumber++;
            });
        }

        // YD and M are interchangeable: editing one always recomputes the other.
        function syncLength(input, which) {
            const container = input.closest('.roll-input');
            const ydInput = container.querySelector('.roll-length-yd');
            const mInput = container.querySelector('.roll-length-m');
            const val = parseFloat(input.value);

            if (which === 'yd' && mInput) {
                mInput.value = isNaN(val) ? '' : (val * 0.9144).toFixed(2);
            } else if (which === 'm' && ydInput) {
                ydInput.value = isNaN(val) ? '' : (val / 0.9144).toFixed(2);
            }

            updateTotalQtyBadge();
        }

        // Calculate total quantity in the order metric (only returns value)
        function calculateTotal() {
            const inputs = document.querySelectorAll('.roll-primary:not([disabled])');
            let total = 0;

            inputs.forEach(input => {
                if (!input.closest('.roll-input.marked-for-deletion')) {
                    total += parseFloat(input.value) || 0;
                }
            });

            return total;
        }

        // Update total quantity badge
        function updateTotalQtyBadge() {
            const total = calculateTotal();
            document.getElementById('totalQtyBadge').textContent = `Total Qty: ${total.toFixed(2)} ${ORDER_UNIT}`;
        }

        // Update total rolls badge
        function updateTotalRollsBadge() {
            const rollInputs = document.querySelectorAll('.roll-input:not(.marked-for-deletion)');
            document.getElementById('totalRollsBadge').textContent = `Total Rolls: ${rollInputs.length}`;
        }

        // Show Confirmation Modal
        function showConfirmationModal() {
            const rawText = document.getElementById('totalQtyBadge').innerText;
            const totalQty = parseFloat(rawText.replace(/[^0-9.]/g, '')) || 0;
            const minQty = {{ (float)$item->getMinQuantityLimit() }};
            const originalQty = {{ (float)$item->quantity }};
            
            const standardDiv = document.getElementById('standardConfirmation');
            const warningDiv = document.getElementById('partialShipmentWarning');
            const confirmBtn = document.getElementById('confirmActionBtn');
            const partialBtn = document.getElementById('partialShipmentBtn');
            const requestBtn = document.getElementById('requestPartialBtn');
            
            document.getElementById('modalDeliveredQty').innerText = formatNumber(totalQty);
            document.getElementById('modalMinQty').innerText = formatNumber(minQty);
            
            if (totalQty < minQty) {
                // Under delivery
                standardDiv.classList.add('d-none');
                warningDiv.classList.remove('d-none');
                confirmBtn.classList.add('d-none');
                
                if (partialBtn) partialBtn.classList.remove('d-none');
                if (requestBtn) requestBtn.classList.remove('d-none');
                
                const remaining = originalQty - totalQty;
                document.getElementById('modalRemainingQty').innerText = formatNumber(remaining);
            } else {
                // Within tolerance or Over
                standardDiv.classList.remove('d-none');
                warningDiv.classList.add('d-none');
                confirmBtn.classList.remove('d-none');
                
                if (partialBtn) partialBtn.classList.add('d-none');
                if (requestBtn) requestBtn.classList.add('d-none');
            }

            var myModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            myModal.show();
        }

        function submitPartialShipment() {
            const btn = document.getElementById('partialShipmentBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            btn.disabled = true;
            document.getElementById('partialShipmentForm').submit();
        }

        function submitPartialRequest() {
            const rawText = document.getElementById('totalQtyBadge').innerText;
            const totalQty = parseFloat(rawText.replace(/[^0-9.]/g, '')) || 0;
            const noteArea = document.getElementById('partialRequestNote');
            const note = noteArea.value;
            const errorElement = document.getElementById('partialNoteError');

            if (!note || note.trim().length < 5) {
                errorElement.classList.remove('d-none');
                noteArea.classList.add('is-invalid');
                return;
            }

            errorElement.classList.add('d-none');
            noteArea.classList.remove('is-invalid');
            
            document.getElementById('formRequestedQty').value = totalQty;
            document.getElementById('formPartialReason').value = note;
            
            const btn = document.getElementById('requestPartialBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending Request...';
            btn.disabled = true;

            document.getElementById('partialRequestForm').submit();
        }

        // Handle Confirm Action
        document.getElementById('confirmActionBtn').addEventListener('click', function () {
            const form = document.getElementById('markProcessedForm');
            const button = this;

            // Add loading state
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            button.disabled = true;

            form.submit();
        });

        // Form validation
        document.getElementById('rollsForm').addEventListener('submit', function (e) {
            const quantityInputs = this.querySelectorAll('.roll-primary:not([disabled])');
            let isValid = true;
            let activeRolls = 0;

            // Only the order-metric quantity is mandatory (non-deleting, non-disabled rolls)
            quantityInputs.forEach(input => {
                const rollInput = input.closest('.roll-input');
                if (rollInput && !rollInput.classList.contains('marked-for-deletion')) {
                    activeRolls++;
                    if (!input.value || parseFloat(input.value) <= 0) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                }
            });

            // Check if we have at least one roll
            if (activeRolls === 0) {
                alert('You must have at least one roll.');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert(`Please enter a valid ${ORDER_UNIT} quantity for all rolls (greater than 0)`);
                return false;
            }

            // Rename new_rolls to rolls for the controller
            const newRollsInputs = this.querySelectorAll('[name^="new_rolls"]');
            newRollsInputs.forEach(input => {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace('new_rolls', 'rolls');
                input.setAttribute('name', newName);
            });

            // Ensure delete flags are properly set for marked-for-deletion rolls
            const markedForDeletion = document.querySelectorAll('.roll-input.marked-for-deletion');
            markedForDeletion.forEach(element => {
                const deleteFlag = element.querySelector('.delete-flag');
                if (deleteFlag) {
                    deleteFlag.value = "1";
                }
            });

            return true;
        });

        // Handle File Upload
        function handleFileUpload(input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            // Show loading state
            const importBtn = document.querySelector('button[onclick*="importFile"]');
            const originalBtnHtml = importBtn.innerHTML;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';
            importBtn.disabled = true;

            fetch('{{ route('vendor.item.upload-rolls', $item->id) }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const data = result.data;
                    const container = document.getElementById('newRollsContainer');

                    // Clear existing new rolls
                    container.innerHTML = '';
                    newRollCounter = 0;

                    // Populate with new data (imported quantities are in the order metric)
                    data.forEach(roll => {
                        addNewRoll(roll);
                    });

                    updateRollNumbers();
                    updateTotalRollsBadge();
                    updateTotalQtyBadge();
                    
                    document.getElementById('rollCount').value = document.querySelectorAll('.roll-input:not(.marked-for-deletion)').length;
                    
                    alert(result.message);
                } else {
                    alert('Import failed: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading the file.');
            })
            .finally(() => {
                importBtn.innerHTML = originalBtnHtml;
                importBtn.disabled = false;
                input.value = ''; // Reset file input
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Backfill a missing order-metric quantity from the other length metric
            // (legacy rolls saved in M for a YD order, or vice versa).
            document.querySelectorAll('.roll-input').forEach(row => {
                const primary = row.querySelector('.roll-primary');
                if (!primary || primary.value) return;

                const yd = row.querySelector('.roll-length-yd');
                const m = row.querySelector('.roll-length-m');

                if (ORDER_UNIT === 'YD' && m && m.value) {
                    primary.value = (parseFloat(m.value) / 0.9144).toFixed(2);
                } else if (ORDER_UNIT === 'M' && yd && yd.value) {
                    primary.value = (parseFloat(yd.value) * 0.9144).toFixed(2);
                }
            });

            // Initialize badges
            updateTotalRollsBadge();
            updateTotalQtyBadge();
        });
    </script>
    
    <!-- Amend Tolerance Modal -->
    <div class="modal fade" id="amendToleranceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content text-start">
                <form action="{{ route('vendor.tolerance.amend') }}" method="POST">
                    @csrf
                    <input type="hidden" name="po_item_id" id="amend_item_id">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">Amend Delivery Tolerance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-dark">
                        <p class="mb-3">Request to change tolerance for <strong id="amend_item_label"></strong>. This request will be sent for approval.</p>
                        
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Underdelivery (%)</label>
                                <div class="input-group text-dark">
                                    <input type="number" step="0.01" name="new_underdelivery" id="new_underdelivery" class="form-control" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Overdelivery (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="new_overdelivery" id="new_overdelivery" class="form-control" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 px-3 small mb-3" id="amend_qty_helper">
                            Allowed delivery quantity at these percentages:
                            <strong>Min <span id="amend_min_qty">-</span></strong> &ndash;
                            <strong>Max <span id="amend_max_qty">-</span></strong>
                            <span id="amend_qty_unit"></span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason for Amendment</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Explain why you need to change the tolerance..." required></textarea>
                            <div class="form-text text-muted small">Please provide a clear justification for the approval process.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white">
                            <i class="fas fa-paper-plane me-1"></i>Send Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var amendModal = document.getElementById('amendToleranceModal');
            if (amendModal) {
                // Set by show.bs.modal from the triggering button's data-* attrs;
                // read by recalcAmendQtyHelper() on every %-field input.
                var targetQty = 0;
                var otherDelivered = 0;
                var qtyUnit = '';

                function recalcAmendQtyHelper() {
                    var under = parseFloat(amendModal.querySelector('#new_underdelivery').value);
                    var over = parseFloat(amendModal.querySelector('#new_overdelivery').value);
                    var minEl = amendModal.querySelector('#amend_min_qty');
                    var maxEl = amendModal.querySelector('#amend_max_qty');

                    if (isNaN(under) || isNaN(over)) {
                        minEl.textContent = '-';
                        maxEl.textContent = '-';
                        return;
                    }

                    // Mirrors PoItem::getQuantityLimitsForTolerance() server-side.
                    var min = Math.max(0, targetQty * (1 - under / 100) - otherDelivered);
                    var max = Math.max(0, targetQty * (1 + over / 100) - otherDelivered);

                    minEl.textContent = min.toFixed(2);
                    maxEl.textContent = max.toFixed(2);
                }

                amendModal.querySelector('#new_underdelivery').addEventListener('input', recalcAmendQtyHelper);
                amendModal.querySelector('#new_overdelivery').addEventListener('input', recalcAmendQtyHelper);

                amendModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var itemId = button.getAttribute('data-item-id');
                    var itemNumber = button.getAttribute('data-item-number');
                    var under = button.getAttribute('data-under');
                    var over = button.getAttribute('data-over');

                    targetQty = parseFloat(button.getAttribute('data-target-qty')) || 0;
                    otherDelivered = parseFloat(button.getAttribute('data-other-delivered')) || 0;
                    qtyUnit = button.getAttribute('data-unit') || '';

                    amendModal.querySelector('#amend_item_id').value = itemId;
                    amendModal.querySelector('#amend_item_label').textContent = 'Item ' + itemNumber;
                    amendModal.querySelector('#new_underdelivery').value = under;
                    amendModal.querySelector('#new_overdelivery').value = over;
                    amendModal.querySelector('#amend_qty_unit').textContent = qtyUnit;

                    recalcAmendQtyHelper();
                });
            }
        });
    </script>

    <!-- Instructions Modal -->
    <div class="modal fade text-dark" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="instructionsModalLabel"><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark">
                    <ol class="mb-0">
                        <li class="mb-2"><strong>Enter Roll Count:</strong> Enter total number of rolls and click Generate</li>
                        <li class="mb-2"><strong>Fill Quantities:</strong> Only the original order metric quantity is mandatory per roll; the other metrics are optional. Yard and Meter fill each other automatically — type whichever one you have.</li>
                        <li class="mb-2"><strong>Extra Details:</strong> Roll No. (your own roll number), Bale No., Lot-ID and Color are optional per roll</li>
                        <li class="mb-2"><strong>Add/Remove:</strong> Use buttons to add or remove individual rolls</li>
                        <li class="mb-2"><strong>Save:</strong> Click Save Rolls to store data</li>
                        <li class="mb-2"><strong>Mark Processed:</strong> When done, mark item as processed (redirects to PO)</li>
                        <li><strong>QR Codes:</strong> Generate QR codes for selected item</li>
                    </ol>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection