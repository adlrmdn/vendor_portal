@extends('layouts.app')

@section('title', 'Subcon Vendors')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
        color: #334155;
    }
    .premium-header {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
    }
    .table-premium th {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        color: #475569;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px 24px;
    }
    .table-premium td {
        padding: 16px 24px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .badge-active {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.8rem;
    }
    .badge-inactive {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.8rem;
    }
    .btn-action {
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.85rem;
        padding: 6px 12px;
        transition: all 0.15s ease-in-out;
    }
    .modal-premium-content {
        border-radius: 18px;
        border: none;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .modal-premium-header {
        background-color: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        border-top-left-radius: 18px;
        border-top-right-radius: 18px;
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        padding: 20px 24px;
    }
    .modal-premium-body {
        padding: 24px;
    }
    .modal-premium-footer {
        border-top: 1px solid #f1f5f9;
        background-color: #f8fafc;
        padding: 16px 24px;
        border-bottom-left-radius: 18px;
        border-bottom-right-radius: 18px;
    }
</style>

<div class="premium-container container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="premium-header mb-1">Subcon Vendors</h2>
            <p class="text-muted small mb-0">Manage vendor accounts, portal login access, and credentials.</p>
        </div>
        <button class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600;" data-bs-toggle="modal" data-bs-target="#addVendorModal">
            <i class="fas fa-plus me-2"></i> Add Vendor
        </button>
    </div>

    <!-- Search Bar Card -->
    <div class="premium-card card mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('subcon.admin.vendors') }}" class="row g-2 align-items-center">
                <div class="col-md-9 col-lg-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by vendor name, code, group, or login email..." value="{{ request('search') }}" style="box-shadow: none;">
                    </div>
                </div>
                <div class="col-md-3 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" style="border-radius: 8px; font-weight: 600;">
                        Search
                    </button>
                    @if(request('search'))
                    <a href="{{ route('subcon.admin.vendors') }}" class="btn btn-outline-secondary" style="border-radius: 8px;" title="Clear Search">
                        <i class="fas fa-times"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="premium-card card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-premium mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Group</th>
                            <th>Login Account</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendors as $vendor)
                        @php $vendorUser = $vendor->users->first(); @endphp
                        <tr>
                            <td class="fw-semibold text-dark">{{ $vendor->name }}</td>
                            <td><code>{{ $vendor->vendor_code }}</code></td>
                            <td>{{ $vendor->group ?? '—' }}</td>
                            <td>
                                @if($vendorUser)
                                    <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1">
                                        <i class="fas fa-envelope text-muted me-1.5 small"></i>
                                        <code class="text-dark me-2 small">{{ $vendorUser->email }}</code>
                                        <button type="button" class="btn btn-link text-secondary p-0 border-0"
                                            title="Copy Login Email"
                                            onclick="copyText('{{ $vendorUser->email }}', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> No Login</span>
                                        <form action="{{ route('subcon.admin.vendors.create-account', $vendor->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @if(request('search'))
                                                <input type="hidden" name="search" value="{{ request('search') }}">
                                            @endif
                                            <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2 small" style="border-radius: 6px; font-size: 0.75rem;">
                                                Create Account
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary rounded-pill px-2.5 py-1">{{ $vendor->subcon_orders_count }}</span></td>
                            <td>
                                @if($vendor->is_active)
                                    <span class="badge-active"><i class="fas fa-check-circle me-1"></i> Active</span>
                                @else
                                    <span class="badge-inactive"><i class="fas fa-ban me-1"></i> Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-action btn-outline-info credentials-vendor-btn me-1"
                                    data-id="{{ $vendor->id }}"
                                    data-name="{{ $vendor->name }}"
                                    data-code="{{ $vendor->vendor_code }}"
                                    data-email="{{ $vendorUser ? $vendorUser->email : '' }}"
                                    data-has-user="{{ $vendorUser ? '1' : '0' }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#credentialsVendorModal"
                                    title="View credentials & reset password">
                                    <i class="fas fa-key me-1"></i> Credentials
                                </button>

                                <button type="button" class="btn btn-action btn-outline-primary edit-vendor-btn me-1"
                                    data-id="{{ $vendor->id }}"
                                    data-name="{{ $vendor->name }}"
                                    data-code="{{ $vendor->vendor_code }}"
                                    data-group="{{ $vendor->group }}"
                                    data-phone="{{ $vendor->contact_info['phone'] ?? '' }}"
                                    data-email="{{ $vendor->contact_info['email'] ?? '' }}"
                                    data-address="{{ $vendor->contact_info['address'] ?? '' }}"
                                    data-active="{{ $vendor->is_active ? '1' : '0' }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editVendorModal">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>

                                <form action="{{ route('subcon.admin.vendors.toggle-status', $vendor->id) }}" method="POST" class="d-inline me-1">
                                    @csrf
                                    <button type="submit" class="btn btn-action btn-outline-{{ $vendor->is_active ? 'warning' : 'success' }}">
                                        <i class="fas fa-power-off me-1"></i> {{ $vendor->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form action="{{ route('subcon.admin.vendors.destroy', $vendor->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this vendor? This will delete all associated orders, reports, and login users. This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-action btn-outline-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-users fs-2 mb-3 text-secondary d-block"></i>
                                No subcon vendors found matching your criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($vendors->hasPages())
        <div class="card-footer bg-white border-top-0 py-3">{{ $vendors->links() }}</div>
        @endif
    </div>
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('subcon.admin.vendor.store') }}">
            @csrf
            <div class="modal-content modal-premium-content">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i> Add Subcon Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-premium-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" style="border-radius: 8px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Vendor Code <span class="text-danger">*</span></label>
                        <input type="text" name="vendor_code" class="form-control" style="border-radius: 8px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Group</label>
                        <input type="text" name="group" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Phone</label>
                        <input type="text" name="contact_info[phone]" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Email</label>
                        <input type="email" name="contact_info[email]" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Address</label>
                        <textarea name="contact_info[address]" class="form-control" style="border-radius: 8px;" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-premium-footer modal-footer">
                    <button type="button" class="btn btn-secondary px-3" style="border-radius: 8px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px;">Save Vendor</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Vendor Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editVendorForm" action="">
            @csrf
            @method('PUT')
            <div class="modal-content modal-premium-content">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i> Edit Subcon Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-premium-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" style="border-radius: 8px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Vendor Code <span class="text-danger">*</span></label>
                        <input type="text" name="vendor_code" id="edit_vendor_code" class="form-control" style="border-radius: 8px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Group</label>
                        <input type="text" name="group" id="edit_group" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Phone</label>
                        <input type="text" name="contact_info[phone]" id="edit_phone" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Email</label>
                        <input type="email" name="contact_info[email]" id="edit_email" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary">Address</label>
                        <textarea name="contact_info[address]" id="edit_address" class="form-control" style="border-radius: 8px;" rows="2"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label fw-medium text-secondary" for="edit_is_active">Vendor Active Status</label>
                    </div>
                </div>
                <div class="modal-premium-footer modal-footer">
                    <button type="button" class="btn btn-secondary px-3" style="border-radius: 8px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px;">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- View / Reset Credentials Modal -->
<div class="modal fade" id="credentialsVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-premium-content">
            <div class="modal-header modal-premium-header">
                <h5 class="modal-title"><i class="fas fa-key me-2 text-info"></i> Vendor Login Credentials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-premium-body">
                <div class="mb-3">
                    <label class="form-label small text-muted mb-0">Vendor Details</label>
                    <div class="fw-semibold text-dark fs-6" id="cred_modal_vendor_name">—</div>
                    <div class="small text-secondary font-monospace" id="cred_modal_vendor_code">—</div>
                </div>

                <div id="cred_modal_has_account_sec">
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Portal Login Email</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light font-monospace" id="cred_modal_email" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyCred('cred_modal_email', this)"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-3 text-secondary">
                        <i class="fas fa-info-circle me-1 text-info"></i> Standard default password for new or reset vendor accounts is <code>password</code>.
                    </div>

                    <hr class="my-3 text-muted opacity-25">

                    <form method="POST" id="resetPasswordForm" action="">
                        @csrf
                        @if(request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        <h6 class="fw-semibold mb-2 text-dark"><i class="fas fa-shield-alt me-1 text-primary"></i> Reset Password</h6>
                        <p class="small text-muted mb-2">Provide a new custom password or leave empty to reset to default <code>password</code>.</p>
                        <div class="mb-3">
                            <input type="text" name="password" class="form-control font-monospace" placeholder="password" style="border-radius: 8px;">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning px-3" style="border-radius: 8px; font-weight: 500;">
                                <i class="fas fa-sync-alt me-1"></i> Reset Password
                            </button>
                        </div>
                    </form>
                </div>

                <div id="cred_modal_no_account_sec" class="text-center py-4 d-none">
                    <i class="fas fa-user-slash fs-2 text-warning mb-2 d-block"></i>
                    <p class="text-secondary mb-3">This vendor does not have a portal login account yet.</p>
                    <form method="POST" id="createAccountModalForm" action="">
                        @csrf
                        @if(request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px; font-weight: 600;">
                            <i class="fas fa-user-plus me-1"></i> Create Login Account
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-premium-footer modal-footer">
                <button type="button" class="btn btn-secondary px-4" style="border-radius: 8px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if(session('new_vendor_credentials'))
@php $cred = session('new_vendor_credentials'); @endphp
<div class="modal fade" id="vendorCredentialsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content modal-premium-content">
            <div class="modal-header modal-premium-header">
                <h5 class="modal-title"><i class="fas fa-key me-2 text-success"></i> {{ !empty($cred['is_reset']) ? 'Vendor Password Reset' : 'Vendor Login Credentials' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-premium-body">
                <div class="alert alert-warning small mb-3">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Copy these credentials now — password details are shown <strong>only once</strong>.
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted mb-1">Vendor</label>
                    <div class="fw-semibold">{{ $cred['name'] }}</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted mb-1">Login Email</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="credEmail" value="{{ $cred['login_email'] }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyCred('credEmail', this)"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label small text-muted mb-1">Password</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="credPassword" value="{{ $cred['password'] }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyCred('credPassword', this)"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-premium-footer modal-footer">
                <button class="btn btn-outline-primary px-3" style="border-radius:8px;" type="button" onclick="copyCred('__both__', this)"><i class="fas fa-copy me-1"></i> Copy both</button>
                <button type="button" class="btn btn-primary px-3" style="border-radius:8px;" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
    function copyText(text, btn) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
            if (btn) {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(function () { btn.innerHTML = original; }, 1200);
            }
        });
    }

    function copyCred(id, btn) {
        let text;
        if (id === '__both__') {
            const e = document.getElementById('credEmail');
            const p = document.getElementById('credPassword');
            text = 'Login Email: ' + e.value + '\nPassword: ' + p.value;
        } else {
            text = document.getElementById(id).value;
        }
        navigator.clipboard.writeText(text).then(function () {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function () { btn.innerHTML = original; }, 1200);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const credModal = document.getElementById('vendorCredentialsModal');
        if (credModal && window.bootstrap) {
            new bootstrap.Modal(credModal).show();
        }

        const editVendorModal = document.getElementById('editVendorModal');
        if (editVendorModal) {
            editVendorModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const code = button.getAttribute('data-code');
                const group = button.getAttribute('data-group');
                const phone = button.getAttribute('data-phone');
                const email = button.getAttribute('data-email');
                const address = button.getAttribute('data-address');
                const active = button.getAttribute('data-active') === '1';

                const form = document.getElementById('editVendorForm');
                form.action = `/subcon/admin/vendors/${id}`;

                document.getElementById('edit_name').value = name;
                document.getElementById('edit_vendor_code').value = code;
                document.getElementById('edit_group').value = group || '';
                document.getElementById('edit_phone').value = phone || '';
                document.getElementById('edit_email').value = email || '';
                document.getElementById('edit_address').value = address || '';
                document.getElementById('edit_is_active').checked = active;
            });
        }

        const credentialsVendorModal = document.getElementById('credentialsVendorModal');
        if (credentialsVendorModal) {
            credentialsVendorModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const code = button.getAttribute('data-code');
                const email = button.getAttribute('data-email');
                const hasUser = button.getAttribute('data-has-user') === '1';

                document.getElementById('cred_modal_vendor_name').textContent = name;
                document.getElementById('cred_modal_vendor_code').textContent = 'Code: ' + code;

                const hasAccountSec = document.getElementById('cred_modal_has_account_sec');
                const noAccountSec = document.getElementById('cred_modal_no_account_sec');

                if (hasUser) {
                    hasAccountSec.classList.remove('d-none');
                    noAccountSec.classList.add('d-none');
                    document.getElementById('cred_modal_email').value = email;
                    document.getElementById('resetPasswordForm').action = `/subcon/admin/vendors/${id}/reset-password`;
                } else {
                    hasAccountSec.classList.add('d-none');
                    noAccountSec.classList.remove('d-none');
                    document.getElementById('createAccountModalForm').action = `/subcon/admin/vendors/${id}/create-account`;
                }
            });
        }
    });
</script>
@endpush
@endsection
