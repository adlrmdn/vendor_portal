@extends('layouts.app')

@section('title', 'Edit Vendor')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.vendors') }}" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Vendors
                </a>
                <h1 class="h3">Edit Vendor: {{ $vendor->name }}</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.vendors') }}">Vendors</a></li>
                        <li class="breadcrumb-item active">{{ $vendor->name }}</li>
                    </ol>
                </nav>
            </div>
            <div>
                @if($vendor->is_active)
                    <span class="badge bg-success fs-6">Active</span>
                @else
                    <span class="badge bg-danger fs-6">Inactive</span>
                @endif
            </div>
        </div>

        <!-- Vendor Edit Form -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vendor Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.vendor.update', $vendor->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" value="{{ $vendor->name }}"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="vendor_code"
                                        value="{{ $vendor->vendor_code }}" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contact_person"
                                        value="{{ $vendor->contact_info['contact_person'] ?? '' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="{{ $vendor->contact_info['email'] ?? '' }}">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone"
                                        value="{{ $vendor->contact_info['phone'] ?? '' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                            value="1" {{ $vendor->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active Vendor
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address"
                                    rows="3">{{ $vendor->contact_info['address'] ?? '' }}</textarea>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('admin.vendors') }}" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Vendor</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Stats Sidebar -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Statistics</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total POs
                                <span class="badge bg-primary rounded-pill">{{ $vendor->purchaseOrders()->count() }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Active POs
                                <span
                                    class="badge bg-warning rounded-pill">{{ $vendor->activePurchaseOrders()->count() }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Completed POs
                                <span
                                    class="badge bg-success rounded-pill">{{ $vendor->purchaseOrders()->where('status', 'completed')->count() }}</span>
                            </li>

                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent POs -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Purchase Orders</h5>
            </div>
            <div class="card-body">
                @if($vendor->purchaseOrders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Date</th>

                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vendor->purchaseOrders as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.purchase-order.view', $order->id) }}"
                                                class="text-decoration-none fw-bold">
                                                {{ $order->po_number }}
                                            </a>
                                        </td>
                                        <td>{{ $order->order_date->format('M d, Y') }}</td>

                                        <td>
                                            <span class="badge badge-{{ $order->status }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.purchase-order.view', $order->id) }}"
                                                class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-file-invoice fa-2x mb-3"></i>
                        <p>No purchase orders found</p>
                    </div>
                @endif
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

        .badge-cancelled {
            background-color: #dc3545;
        }
    </style>
@endsection