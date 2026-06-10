@extends('layouts.app')

@section('title', 'Subcon Vendors')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Subcon Vendors</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVendorModal">
        <i class="fas fa-plus me-1"></i> Add Vendor
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Group</th>
                        <th>Orders</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $vendor)
                    <tr>
                        <td class="fw-semibold">{{ $vendor->name }}</td>
                        <td><code>{{ $vendor->vendor_code }}</code></td>
                        <td>{{ $vendor->group ?? '—' }}</td>
                        <td>{{ $vendor->subcon_orders_count }}</td>
                        <td>
                            <span class="badge bg-{{ $vendor->is_active ? 'success' : 'secondary' }}">
                                {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No subcon vendors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($vendors->hasPages())
    <div class="card-footer">{{ $vendors->links('custom-pagination') }}</div>
    @endif
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('subcon.admin.vendor.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subcon Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Code <span class="text-danger">*</span></label>
                        <input type="text" name="vendor_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group</label>
                        <input type="text" name="group" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="contact_info[phone]" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="contact_info[email]" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="contact_info[address]" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
