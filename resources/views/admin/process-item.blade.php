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
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders') }}">Purchase Orders</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('admin.purchase-order.view', $item->purchaseOrder->id) }}">PO:
                                {{ $item->purchaseOrder->po_number }}</a></li>
                        <li class="breadcrumb-item active">Item {{ $item->item_number }}</li>
                    </ol>
                </nav>
            </div>
            <div>
                <span class="badge badge-{{ $item->status }} fs-6 px-3 py-2">
                    {{ ucfirst($item->status) }}
                </span>
            </div>
        </div>

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
                    <div class="text-muted fw-bold small">
                        Item Description
                        <span class="text-dark ms-2">|</span>
                        <span class="ms-2 text-primary">Vendor:
                            {{ $item->purchaseOrder->vendor->vendor_code }} -
                            {{ $item->purchaseOrder->vendor->name }}</span>
                    </div>
                    <div>
                        @if($item->status == 'processing')
                            <form action="{{ route('admin.item.mark-processed', $item->id) }}" method="POST" class="d-inline"
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
                        <div class="alert alert-info py-2 px-3 small border-0 mb-0 d-flex align-items-center" style="background-color: #e3f2fd; color: #0d47a1;">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Delivery Tolerance:</strong> 
                                Under: <span class="fw-bold">{{ $item->underdelivery }}%</span> (Min: {{ number_format($item->getMinQuantityLimit(), 2) }}) | 
                                Over: <span class="fw-bold">{{ $item->overdelivery }}%</span> (Max: {{ number_format($item->getMaxQuantityLimit(), 2) }})
                            </div>
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
                                $unit = $activeRolls->first()->unit ?? '';
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
                        <form action="{{ route('admin.item.save-rolls', $item->id) }}" method="POST" id="rollsForm">
                            @csrf

                            <!-- Unit Selection -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Select Unit for Rolls</label>
                                    <select class="form-select" id="rollUnit" name="roll_unit" required
                                        onchange="updateAllUnits()">
                                        <option value="">Select Unit</option>
                                        <option value="YD" {{ old('roll_unit', $item->rolls->where('deleted', false)->first()->unit ?? $item->unit) == 'YD' ? 'selected' : '' }}>Yard (YD)
                                        </option>
                                        <option value="M" {{ old('roll_unit', $item->rolls->where('deleted', false)->first()->unit ?? $item->unit) == 'M' ? 'selected' : '' }}>Meter (M)
                                        </option>
                                         <option value="KG" {{ old('roll_unit', $item->rolls->where('deleted', false)->first()->unit ?? $item->unit) == 'KG' ? 'selected' : '' }}>Kilogram (KG)
                                         </option>
                                         <option value="PCS" {{ old('roll_unit', $item->rolls->where('deleted', false)->first()->unit ?? $item->unit) == 'PCS' ? 'selected' : '' }}>Pieces (PCS)
                                         </option>
                                    </select>
                                    <small class="text-muted">This unit will apply to all rolls</small>
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
                                                <div class="row align-items-center">
                                                    <div class="col-md-2">
                                                        <div
                                                            class="roll-number small font-monospace text-secondary lh-sm user-select-all">
                                                            @foreach(explode('-', $roll->roll_number) as $part)
                                                                <div>{{ $part }}</div>
                                                            @endforeach
                                                        </div>
                                                        @if($roll->qr_code_path)
                                                            <small>
                                                                <a href="{{ asset('storage/' . $roll->qr_code_path) }}" target="_blank"
                                                                    class="text-primary">
                                                                    <i class="fas fa-qrcode"></i> View QR
                                                                </a>
                                                            </small>
                                                        @endif
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Lot-ID</label>
                                                        <input type="text" class="form-control" name="rolls[{{ $roll->id }}][internal_id]" value="{{ $roll->internal_id }}" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label roll-qty-label">Quantity ({{ $roll->unit }})</label>
                                                        <input type="number" step="0.01" class="form-control roll-quantity"
                                                            name="rolls[{{ $roll->id }}][quantity]"
                                                            value="{{ ($roll->unit == 'YD' || $roll->unit == 'M') ? ($roll->unit == 'YD' ? $roll->length_yd : $roll->length_m) : $roll->weight }}"
                                                            placeholder="Enter quantity" oninput="calculateSecondaryMetric(this); updateTotalQtyBadge();" required>
                                                    </div>
                                                    <div class="col-md-2 yd-metric-col" style="{{ $roll->unit == 'YD' ? 'display: none;' : '' }}">
                                                        <label class="form-label yd-qty-label">{{ $roll->unit == 'M' ? 'Secondary Qty (YD)' : 'Length (YD)' }}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-yd"
                                                            name="rolls[{{ $roll->id }}][length_yd]"
                                                            value="{{ $roll->length_yd }}"
                                                            placeholder="Optional" oninput="calculatePrimaryMetric(this);">
                                                    </div>
                                                    <div class="col-md-2 m-metric-col" style="{{ $roll->unit == 'M' ? 'display: none;' : '' }}">
                                                        <label class="form-label m-qty-label">{{ $roll->unit == 'YD' ? 'Secondary Qty (M)' : 'Length (M)' }}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-m"
                                                            name="rolls[{{ $roll->id }}][length_m]"
                                                            value="{{ $roll->length_m }}"
                                                            placeholder="Optional" oninput="calculatePrimaryMetric(this);">
                                                    </div>
                                                    <div class="col-md-1 text-center">
                                                        <button type="button" class="btn btn-danger btn-sm mt-3 remove-roll-btn"
                                                            onclick="removeExistingRoll('{{ $roll->id }}')" title="Remove Roll"
                                                            tabindex="-1">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <input type="hidden" class="roll-unit" name="rolls[{{ $roll->id }}][unit]" value="{{ $roll->unit }}">
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
                                                <div class="row align-items-center">
                                                    <div class="col-md-2">
                                                        <h6 class="mb-0 roll-number">Roll 1</h6>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Lot-ID</label>
                                                        <input type="text" class="form-control" name="new_rolls[0][internal_id]" placeholder="Optional">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label roll-qty-label">Quantity ({{ $item->unit }})</label>
                                                        <input type="number" step="0.01" class="form-control roll-quantity"
                                                            name="new_rolls[0][quantity]" placeholder="Enter qty"
                                                            oninput="calculateSecondaryMetric(this); updateTotalQtyBadge();" required>
                                                    </div>
                                                    <div class="col-md-2 yd-metric-col" style="{{ $item->unit == 'YD' ? 'display: none;' : '' }}">
                                                        <label class="form-label yd-qty-label">{{ $item->unit == 'M' ? 'Secondary Qty (YD)' : 'Length (YD)' }}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-yd"
                                                            name="new_rolls[0][length_yd]" placeholder="Optional"
                                                            oninput="calculatePrimaryMetric(this);">
                                                    </div>
                                                    <div class="col-md-2 m-metric-col" style="{{ $item->unit == 'M' ? 'display: none;' : '' }}">
                                                        <label class="form-label m-qty-label">{{ $item->unit == 'YD' ? 'Secondary Qty (M)' : 'Length (M)' }}</label>
                                                        <input type="number" step="0.01" class="form-control roll-length-m"
                                                            name="new_rolls[0][length_m]" placeholder="Optional"
                                                            oninput="calculatePrimaryMetric(this);">
                                                    </div>
                                                    <div class="col-md-1 text-center">
                                                        <button type="button" class="btn btn-danger btn-sm mt-3"
                                                            onclick="removeNewRoll(0)" title="Remove Roll" tabindex="-1">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <input type="hidden" class="roll-unit" name="new_rolls[0][unit]" value="{{ $item->unit }}">
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
                                    <button type="button" class="btn btn-outline-danger ms-2" onclick="clearAllRolls()">
                                        <i class="fas fa-trash-alt me-2"></i>Clear All Rolls
                                    </button>
                                    <input type="file" id="importFile" class="d-none" accept=".xlsx,.xls,.csv,.pdf" onchange="handleFileUpload(this)">
                                </div>
                                <div>
                                    <a href="{{ route('admin.purchase-order.view', $item->purchaseOrder->id) }}"
                                        class="btn btn-secondary me-2">
                                        <i class="fas fa-arrow-left me-2"></i>Back to PO
                                    </a>
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
                            <p class="fs-5">Are you sure you want to mark this item as processed? This will finalize the current rolls and submit them.</p>
                        </div>
                        
                        <div id="partialShipmentWarning" class="d-none">
                            <div class="alert alert-warning border-0">
                                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Under-Delivery Detected</h4>
                                <p class="mb-0">The total quantity processed (<span id="modalDeliveredQty" class="fw-bold"></span>) is below the minimum allowed tolerance (<span id="modalMinQty" class="fw-bold"></span>).</p>
                            </div>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold"><i class="fas fa-layer-group me-2"></i>Partial Shipment Option:</h6>
                                    <p class="small text-muted mb-0">Choosing "Partial Shipment" will complete the <strong>CURRENT</strong> batch and automatically create a new "Shadow" item for the remaining balance (<span id="modalRemainingQty" class="fw-bold text-primary"></span>) in this PO.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <div id="modalButtons">
                            <button type="button" class="btn btn-primary" id="confirmActionBtn">Yes, Mark as Processed</button>
                            <button type="button" class="btn btn-warning d-none" id="partialShipmentBtn" onclick="submitPartialShipment()">
                                <i class="fas fa-layer-group me-1"></i>Create Partial Shipment
                            </button>
                            <form id="partialShipmentForm" action="{{ route('admin.item.mark-partial', $item->id) }}" method="POST" class="d-none">
                                @csrf
                            </form>
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

        // Generate roll input fields based on count
        function generateRollFields() {
            const countInput = document.getElementById('rollCount');
            const unitSelect = document.getElementById('rollUnit');
            const count = parseInt(countInput.value) || 1;

            if (count < 1 || count > 500) {
                alert('Please enter a number between 1 and 500');
                return;
            }

            if (!unitSelect.value) {
                alert('Please select a unit first');
                unitSelect.focus();
                return;
            }

            const selectedUnit = unitSelect.value;
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
                addNewRoll(selectedUnit);
            }

            updateRollNumbers();
            updateTotalRollsBadge();
            calculateTotal();
        }

        // Add a single new roll
        function addSingleRoll() {
            const unitSelect = document.getElementById('rollUnit');

            if (!unitSelect.value) {
                alert('Please select a unit first');
                unitSelect.focus();
                return;
            }

            const selectedUnit = unitSelect.value;
            addNewRoll(selectedUnit);
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

        // Add new roll to container
        function addNewRoll(selectedUnit, internalId, quantity) {
            const container = document.getElementById('newRollsContainer');
            if (!container) {
                container = document.getElementById('rollsContainer');
            }

            const rollDiv = document.createElement('div');
            rollDiv.className = 'roll-input card mb-3 new-roll';
            rollDiv.id = `new-roll-${newRollCounter}`;
            
            // Default lengths
            let lengthYd = '';
            let lengthM = '';
            let primaryVal = '';
            
            if (typeof quantity !== 'undefined' && quantity !== null) {
                primaryVal = quantity;
                if (selectedUnit === 'YD') {
                    lengthYd = quantity;
                    lengthM = (quantity * 0.9144).toFixed(2);
                } else if (selectedUnit === 'M') {
                    lengthM = quantity;
                    lengthYd = (quantity / 0.9144).toFixed(2);
                }
            }

            const hideYd = selectedUnit === 'YD' ? 'display: none;' : '';
            const hideM = selectedUnit === 'M' ? 'display: none;' : '';
            
            const ydLabelText = selectedUnit === 'M' ? 'Secondary Qty (YD)' : 'Length (YD)';
            const mLabelText = selectedUnit === 'YD' ? 'Secondary Qty (M)' : 'Length (M)';

            rollDiv.innerHTML = `
                <div class="card-body">
                    <div class="row align-items-center">
                         <div class="col-md-2">
                             <h6 class="mb-0 roll-number">New Roll</h6>
                         </div>
                         <div class="col-md-3">
                             <label class="form-label">Lot-ID</label>
                             <input type="text" class="form-control" 
                                    name="new_rolls[${newRollCounter}][internal_id]" 
                                    placeholder="Optional"
                                    value="${typeof internalId !== 'undefined' && internalId !== null ? internalId : ''}">
                         </div>
                         <div class="col-md-2">
                             <label class="form-label roll-qty-label">Quantity (${selectedUnit})</label>
                             <input type="number" step="0.01" class="form-control roll-quantity" 
                                    name="new_rolls[${newRollCounter}][quantity]" 
                                    placeholder="Enter qty" 
                                    oninput="calculateSecondaryMetric(this); updateTotalQtyBadge();"
                                    value="${primaryVal}"
                                    required>
                         </div>
                         <div class="col-md-2 yd-metric-col" style="${hideYd}">
                             <label class="form-label yd-qty-label">${ydLabelText}</label>
                             <input type="number" step="0.01" class="form-control roll-length-yd" 
                                    name="new_rolls[${newRollCounter}][length_yd]" 
                                    placeholder="Optional" 
                                    oninput="calculatePrimaryMetric(this);"
                                    value="${lengthYd}">
                         </div>
                         <div class="col-md-2 m-metric-col" style="${hideM}">
                             <label class="form-label m-qty-label">${mLabelText}</label>
                             <input type="number" step="0.01" class="form-control roll-length-m" 
                                    name="new_rolls[${newRollCounter}][length_m]" 
                                    placeholder="Optional" 
                                    oninput="calculatePrimaryMetric(this);"
                                    value="${lengthM}">
                         </div>
                         <div class="col-md-1 text-center">
                             <button type="button" class="btn btn-danger btn-sm mt-3" 
                                     onclick="removeNewRoll(${newRollCounter})"
                                     title="Remove Roll" tabindex="-1">
                                 <i class="fas fa-times"></i>
                             </button>
                         </div>
                         <input type="hidden" class="roll-unit" name="new_rolls[${newRollCounter}][unit]" value="${selectedUnit}">
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
            const inputs = rollElement.querySelectorAll('input:not(.delete-flag):not(.roll-unit-text)');
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

        // Update all roll units when master unit changes
        function updateAllUnits() {
            const masterUnit = document.getElementById('rollUnit').value;
            const hiddenInputs = document.querySelectorAll('.roll-unit');
            const textDisplays = document.querySelectorAll('.roll-unit-text');

            hiddenInputs.forEach(input => {
                input.value = masterUnit;
            });

            textDisplays.forEach(display => {
                display.value = masterUnit ? masterUnit : '';
            });

            const rollInputs = document.querySelectorAll('.roll-input');
            rollInputs.forEach(rollEl => {
                const ydCol = rollEl.querySelector('.yd-metric-col');
                const mCol = rollEl.querySelector('.m-metric-col');
                
                const qtyLabel = rollEl.querySelector('.roll-qty-label');
                const ydLabel = rollEl.querySelector('.yd-qty-label');
                const mLabel = rollEl.querySelector('.m-qty-label');
                
                if (qtyLabel) {
                    qtyLabel.textContent = masterUnit ? `Quantity (${masterUnit})` : 'Quantity';
                }
                
                if (masterUnit === 'YD') {
                    if (ydCol) ydCol.style.display = 'none';
                    if (mCol) mCol.style.display = '';
                    if (mLabel) mLabel.textContent = 'Secondary Qty (M)';
                } else if (masterUnit === 'M') {
                    if (ydCol) ydCol.style.display = '';
                    if (mCol) mCol.style.display = 'none';
                    if (ydLabel) ydLabel.textContent = 'Secondary Qty (YD)';
                } else if (masterUnit === 'KG' || masterUnit === 'PCS') {
                    if (ydCol) ydCol.style.display = '';
                    if (mCol) mCol.style.display = '';
                    if (ydLabel) ydLabel.textContent = 'Length (YD)';
                    if (mLabel) mLabel.textContent = 'Length (M)';
                } else {
                    if (ydCol) ydCol.style.display = 'none';
                    if (mCol) mCol.style.display = 'none';
                }
            });

            updateTotalQtyBadge();
        }

        function calculateSecondaryMetric(input) {
            const container = input.closest('.roll-input');
            const unitSelect = document.getElementById('rollUnit');
            const unit = unitSelect.value || container.querySelector('.roll-unit').value;
            const ydInput = container.querySelector('.roll-length-yd');
            const mInput = container.querySelector('.roll-length-m');
            
            const val = parseFloat(input.value);
            if (isNaN(val)) {
                if (unit === 'YD' && mInput) mInput.value = '';
                if (unit === 'M' && ydInput) ydInput.value = '';
                return;
            }
            
            if (unit === 'YD' && mInput) {
                mInput.value = (val * 0.9144).toFixed(2);
            } else if (unit === 'M' && ydInput) {
                ydInput.value = (val / 0.9144).toFixed(2);
            }
        }

        function calculatePrimaryMetric(input) {
            const container = input.closest('.roll-input');
            const unitSelect = document.getElementById('rollUnit');
            const unit = unitSelect.value || container.querySelector('.roll-unit').value;
            const primaryInput = container.querySelector('.roll-quantity');
            const ydInput = container.querySelector('.roll-length-yd');
            const mInput = container.querySelector('.roll-length-m');
            
            const val = parseFloat(input.value);
            
            // If editing Yards input
            if (input.classList.contains('roll-length-yd')) {
                if (isNaN(val)) {
                    if (unit === 'M' && primaryInput) primaryInput.value = '';
                    if ((unit === 'KG' || unit === 'PCS') && mInput) mInput.value = '';
                    return;
                }
                
                if (unit === 'M' && primaryInput) {
                    primaryInput.value = (val * 0.9144).toFixed(2);
                } else if ((unit === 'KG' || unit === 'PCS') && mInput) {
                    mInput.value = (val * 0.9144).toFixed(2);
                }
            }
            
            // If editing Meters input
            if (input.classList.contains('roll-length-m')) {
                if (isNaN(val)) {
                    if (unit === 'YD' && primaryInput) primaryInput.value = '';
                    if ((unit === 'KG' || unit === 'PCS') && ydInput) ydInput.value = '';
                    return;
                }
                
                if (unit === 'YD' && primaryInput) {
                    primaryInput.value = (val / 0.9144).toFixed(2);
                } else if ((unit === 'KG' || unit === 'PCS') && ydInput) {
                    ydInput.value = (val / 0.9144).toFixed(2);
                }
            }
            
            updateTotalQtyBadge();
        }

        // Calculate total quantity (only returns value)
        function calculateTotal() {
            const inputs = document.querySelectorAll('.roll-quantity:not([disabled])');
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
            const unitSelect = document.getElementById('rollUnit');

            let unit = unitSelect.value;
            if (!unit) {
                // Fallback to first existing roll unit if available, or just empty
                const firstRollUnit = document.querySelector('.roll-unit');
                if (firstRollUnit) {
                    unit = firstRollUnit.value;
                }
            }

            // Format for display
            const displayUnit = unit ? unit : '';

            document.getElementById('totalQtyBadge').textContent = `Total Qty: ${total.toFixed(2)} ${displayUnit}`;
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
            const maxQty = {{ (float)$item->getMaxQuantityLimit() }};
            const originalQty = {{ (float)$item->quantity }};
            
            const standardDiv = document.getElementById('standardConfirmation');
            const warningDiv = document.getElementById('partialShipmentWarning');
            const confirmBtn = document.getElementById('confirmActionBtn');
            const partialBtn = document.getElementById('partialShipmentBtn');
            
            document.getElementById('modalDeliveredQty').innerText = totalQty.toFixed(2);
            document.getElementById('modalMinQty').innerText = minQty.toFixed(2);
            
            if (totalQty < minQty) {
                // Under delivery — admin may still finalize as-is (tolerance bypassed) OR split into a partial shipment.
                standardDiv.classList.add('d-none');
                warningDiv.classList.remove('d-none');
                confirmBtn.classList.remove('d-none');
                partialBtn.classList.remove('d-none');

                const remaining = originalQty - totalQty;
                document.getElementById('modalRemainingQty').innerText = remaining.toFixed(2);
            } else {
                // Within tolerance or Over
                standardDiv.classList.remove('d-none');
                warningDiv.classList.add('d-none');
                confirmBtn.classList.remove('d-none');
                partialBtn.classList.add('d-none');
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
            const unitSelect = document.getElementById('rollUnit');
            const quantityInputs = this.querySelectorAll('.roll-quantity:not([disabled])');
            let isValid = true;
            let activeRolls = 0;

            // Check unit is selected
            if (!unitSelect.value) {
                alert('Please select a unit for the rolls');
                unitSelect.focus();
                isValid = false;
            }

            // Check all quantities are valid (only for non-deleting, non-disabled rolls)
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
                alert('Please enter valid quantities for all rolls (greater than 0)');
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Set initial values if editing
            @if($existingRolls->count() > 0)
                const firstRoll = @json($existingRolls->first());
                if (firstRoll && document.getElementById('rollUnit').value === '') {
                    document.getElementById('rollUnit').value = firstRoll.unit;
                    document.getElementById('rollCount').value = {{ $existingRolls->count() }};
                }
            @endif

            // Initial update
            updateAllUnits();
            updateTotalRollsBadge();

            // Initialize badges
            updateTotalRollsBadge();
            calculateTotal();
        });

        // Handle File Upload (Excel/PDF import of rolls data)
        function handleFileUpload(input) {
            if (!input.files || !input.files[0]) return;

            const unitSelect = document.getElementById('rollUnit');
            if (!unitSelect.value) {
                alert('Please select a unit first before importing data.');
                input.value = '';
                unitSelect.focus();
                return;
            }

            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            // Show loading state
            const importBtn = document.querySelector('button[onclick*="importFile"]');
            const originalBtnHtml = importBtn.innerHTML;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...';
            importBtn.disabled = true;

            fetch('{{ route('admin.item.upload-rolls', $item->id) }}', {
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
                    const selectedUnit = unitSelect.value;
                    const container = document.getElementById('newRollsContainer');

                    // Clear existing new rolls
                    container.innerHTML = '';
                    newRollCounter = 0;

                    // Populate with new data
                    data.forEach(roll => {
                        addNewRoll(selectedUnit, roll.internal_id, roll.quantity);
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
                        <li class="mb-2"><strong>Select Unit:</strong> Choose the measurement unit for all rolls</li>
                        <li class="mb-2"><strong>Enter Roll Count:</strong> Enter total number of rolls and click Generate</li>
                        <li class="mb-2"><strong>Fill Quantities:</strong> Enter quantity for each roll</li>
                        <li class="mb-2"><strong>Optional Fill Bars:</strong> If the unit is length (Yard or Meter), you can optionally fill the secondary metric or reverse the primary unit.</li>
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