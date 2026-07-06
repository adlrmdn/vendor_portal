@extends('layouts.app')

@section('title', 'Packing Slip')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('vendor.purchase-order.view', $packingSlip->po_id) }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Purchase Order
            </a>
            <h1 class="h3">Packing Slip</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Packing Slip</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('vendor.packing-slip.print', $packingSlip->id) }}" class="btn btn-success" target="_blank">
                <i class="fas fa-print me-2"></i>Print PDF
            </a>
        </div>
    </div>
    
    <!-- Packing Slip Details -->
    <div class="card">
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h4>Packing Slip</h4>
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Slip Number:</th>
                            <td>{{ $packingSlip->slip_number }}</td>
                        </tr>
                        <tr>
                            <th>PO Number:</th>
                            <td>{{ $packingSlip->purchaseOrder->po_number }}</td>
                        </tr>
                        <tr>
                            <th>Vendor:</th>
                            <td>{{ $packingSlip->vendor->name }}</td>
                        </tr>
                        <tr>
                            <th>Printed Count:</th>
                            <td>{{ $packingSlip->printed_count }} times</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6 text-end">
                    <div class="mb-3">
                        <div class="fs-1 text-primary fw-bold">PACKING SLIP</div>
                        <div class="text-muted">Generated: {{ $packingSlip->created_at->format('F d, Y H:i') }}</div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <!-- Items List -->
            <h5 class="mb-3">Items Included in this Slip</h5>
            @if($packingSlip->items && is_array($packingSlip->items))
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Item #</th>
                                <th>Description</th>
                                <th>Fabric Type</th>
                                <th>Color</th>
                                <th>Rolls</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($packingSlip->items as $itemId)
                                @php
                                    // You would need to fetch item details here
                                    // This is simplified for the view
                                @endphp
                                <tr>
                                    <td>ITEM-{{ $loop->iteration }}</td>
                                    <td>Sample Item Description</td>
                                    <td>Cotton</td>
                                    <td>White</td>
                                    <td>
                                        <span class="badge bg-info">3 rolls</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">Ready for shipment</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No items included in this packing slip.
                </div>
            @endif
            
            <!-- QR Codes Section -->
            <div class="mt-4">
                <h5>QR Codes for Rolls</h5>
                <div class="row">
                    @for($i = 1; $i <= 6; $i++)
                        <div class="col-md-2 col-sm-4 col-6 mb-3 text-center">
                            <div class="border p-2">
                                <div class="text-muted small mb-2">ROLL-{{ $i }}</div>
                                <div class="bg-light p-2 d-flex justify-content-center align-items-center" style="height: 100px;">
                                    <i class="fas fa-qrcode fa-2x text-secondary"></i>
                                </div>
                                <div class="small text-muted mt-2">PO-{{ $packingSlip->purchaseOrder->po_number }}</div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            
            <!-- Signature Section -->
            <div class="mt-5 pt-4 border-top">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Prepared By:</h6>
                            <div class="signature-line" style="border-bottom: 1px solid #000; width: 200px; height: 30px;"></div>
                            <div class="text-muted small mt-1">Name & Signature</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Received By:</h6>
                            <div class="signature-line" style="border-bottom: 1px solid #000; width: 200px; height: 30px;"></div>
                            <div class="text-muted small mt-1">Name & Signature</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Print Notes -->
            <div class="alert alert-light mt-4">
                <h6><i class="fas fa-info-circle me-2"></i>Printing Instructions:</h6>
                <ul class="mb-0">
                    <li>Click "Print PDF" button to generate printable version</li>
                    <li>Attach one QR code to each roll mentioned above</li>
                    <li>Keep one copy for your records</li>
                    <li>Include this slip with the shipment</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        .navbar, .sidebar, .btn, .breadcrumb, .alert {
            display: none !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }
</style>
@endsection