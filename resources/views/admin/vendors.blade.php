@extends('layouts.app')

@section('title', 'Vendors')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Vendors</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                <i class="fas fa-plus me-2"></i>Add New Vendor
            </button>
        </div>

        <!-- Vendors List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 35%">Vendor Details</th>
                                <th style="width: 25%">Contact Info</th>
                                <th style="width: 15%" class="text-center">PO Stats</th>
                                <th style="width: 10%" class="text-center">Status</th>
                                <th style="width: 15%" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vendors as $vendor)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $vendor->vendor_code }}</div>
                                        <div class="text-muted small">{{ $vendor->name }}</div>
                                    </td>
                                    <td>
                                        @if($vendor->contact_info['contact_person'] ?? false)
                                            <div class="small"><i class="fas fa-user-circle me-1 text-muted"></i>
                                                {{ $vendor->contact_info['contact_person'] }}</div>
                                        @endif
                                        @if($vendor->contact_info['phone'] ?? false)
                                            <div class="small text-muted"><i class="fas fa-phone me-1"></i>
                                                {{ $vendor->contact_info['phone'] }}</div>
                                        @endif
                                        @if(empty($vendor->contact_info['contact_person']) && empty($vendor->contact_info['phone']))
                                            <span class="text-muted small">N/A</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <div class="text-center" title="Active POs">
                                                <span
                                                    class="badge bg-warning text-dark">{{ $vendor->active_purchase_orders_count }}</span>
                                                <div style="font-size: 0.65rem;" class="text-muted mt-1">ACTIVE</div>
                                            </div>
                                            <div class="text-center" title="Total POs">
                                                <span class="badge bg-secondary">{{ $vendor->purchase_orders_count }}</span>
                                                <div style="font-size: 0.65rem;" class="text-muted mt-1">TOTAL</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if($vendor->is_active)
                                            <span
                                                class="badge bg-success-subtle text-success border border-success px-2 py-1">Active</span>
                                        @else
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.vendor.detail', $vendor->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-users fa-2x mb-3"></i>
                                            <p>No vendors found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($vendors->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $vendors->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Add Vendor Modal -->
        <div class="modal fade" id="addVendorModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.vendor.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Vendor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="vendor_code" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="new_is_active"
                                        checked value="1">
                                    <label class="form-check-label" for="new_is_active">
                                        Active Vendor
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Vendor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection