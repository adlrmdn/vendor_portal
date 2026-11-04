@extends('layouts.app')

@section('title', 'Vendors')

@section('content')
<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    <!-- Header with Eyebrow -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Fabric Administration</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Supply Chain Roster</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <h1 class="apple-page-title mb-0">Vendor Directory</h1>
                <span class="apple-count-pill">
                    <span class="apple-count-num">{{ $vendors->total() }}</span>
                    <span>total vendors</span>
                </span>
            </div>
        </div>
        <div>
            <button class="apple-btn-primary" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                <i class="fas fa-plus me-2"></i>Add New Vendor
            </button>
        </div>
    </div>

    @if(session('success') && ! session('new_vendor_credentials'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-check-circle"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-exclamation-circle"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-triangle-exclamation"></i>
            <div>{{ session('warning') }}</div>
        </div>
    @endif

    <!-- Spotlight Search Bar -->
    <div class="apple-card apple-filter-panel mb-4">
        <form method="GET" action="{{ route('admin.vendors') }}">
            <div class="apple-search-container">
                <i class="fas fa-search apple-search-icon"></i>
                <input type="text"
                       name="search"
                       class="apple-search-input"
                       placeholder="Search by vendor name, code, group, or login email…"
                       value="{{ request('search') }}"
                       autocomplete="off">
                @if(request('search'))
                    <a href="{{ route('admin.vendors') }}" class="apple-search-clear" title="Clear search">
                        <i class="fas fa-times-circle"></i>
                    </a>
                @endif
                <button type="submit" class="apple-btn-primary apple-search-btn">
                    <span>Search</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Vendors Table Card -->
    <div class="apple-card apple-table-container mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom" style="border-color: #ebebf0 !important;">
            <div>
                <h5 class="mb-0 fw-bold fs-6 text-dark">Active Suppliers</h5>
                <span class="small text-muted">Showing {{ $vendors->firstItem() ?? 0 }} – {{ $vendors->lastItem() ?? 0 }} of {{ $vendors->total() }} suppliers</span>
            </div>
            @if(request('search'))
                <a href="{{ route('admin.vendors') }}" class="apple-filter-active-pill">
                    <span>Query: "{{ request('search') }}"</span>
                    <i class="fas fa-times"></i>
                </a>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 22%">Vendor Details</th>
                        <th style="width: 10%">Group</th>
                        <th style="width: 18%">Contact Info</th>
                        <th style="width: 18%">Portal Login</th>
                        <th style="width: 10%" class="text-center">PO Activity</th>
                        <th style="width: 8%" class="text-center">Status</th>
                        <th style="width: 14%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $vendor)
                        @php $vendorUser = $vendor->users->first(); @endphp
                        <tr class="apple-table-row">
                            <!-- Vendor Details -->
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('admin.vendor.detail', $vendor->id) }}"
                                           class="apple-po-number"
                                           title="View vendor details">
                                            {{ $vendor->vendor_code }}
                                        </a>
                                        <button type="button"
                                                class="apple-copy-btn"
                                                onclick="copyPoNumber('{{ $vendor->vendor_code }}', this)"
                                                title="Copy Vendor Code">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="{{ $vendor->name }}">
                                        {{ $vendor->name }}
                                    </div>
                                </div>
                            </td>

                            <!-- Group -->
                            <td>
                                @if($vendor->group)
                                    <span class="apple-sub-tag pc-tag">
                                        {{ $vendor->group }}
                                    </span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            <!-- Contact Info -->
                            <td>
                                @if($vendor->contact_info['contact_person'] ?? false)
                                    <div class="small fw-semibold text-dark">
                                        <i class="far fa-user me-1 opacity-50"></i>{{ $vendor->contact_info['contact_person'] }}
                                    </div>
                                @endif
                                @if($vendor->contact_info['phone'] ?? false)
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-phone me-1 opacity-50"></i>{{ $vendor->contact_info['phone'] }}
                                    </div>
                                @endif
                                @if(empty($vendor->contact_info['contact_person']) && empty($vendor->contact_info['phone']))
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            <!-- Portal Login -->
                            <td>
                                @if($vendorUser)
                                    <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded" style="background: #f5f5f7; border: 1px solid rgba(0,0,0,0.06);">
                                        <code class="text-dark small" style="font-size: 0.78rem;">{{ $vendorUser->email }}</code>
                                        <button type="button" class="apple-copy-btn"
                                                title="Copy Login Email"
                                                onclick="copyPoNumber('{{ $vendorUser->email }}', this)">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <span class="apple-pill-status pill-warning" style="font-size: 0.75rem;">
                                            <span class="status-dot dot-warning"></span>No Account
                                        </span>
                                        <form action="{{ route('admin.vendor.create-account', $vendor->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @if(request('search'))
                                                <input type="hidden" name="search" value="{{ request('search') }}">
                                            @endif
                                            <button type="submit" class="apple-btn-secondary py-0 px-2" style="font-size: 0.75rem; height: 26px;">
                                                Create
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>

                            <!-- PO Activity -->
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <a href="{{ route('admin.purchase-orders', ['vendor_id' => $vendor->id, 'status' => 'active']) }}"
                                       class="apple-count-pill"
                                       title="{{ $vendor->active_purchase_orders_count }} Active Purchase Orders"
                                       style="background: #fff8e6; color: #b25e00; text-decoration: none; padding: 2px 8px; font-size: 0.75rem;">
                                        <span class="fw-bold">{{ $vendor->active_purchase_orders_count }}</span> active
                                    </a>
                                    <a href="{{ route('admin.purchase-orders', ['vendor_id' => $vendor->id]) }}"
                                       class="apple-count-pill"
                                       title="{{ $vendor->purchase_orders_count }} Total Purchase Orders"
                                       style="text-decoration: none; padding: 2px 8px; font-size: 0.75rem;">
                                        {{ $vendor->purchase_orders_count }} total
                                    </a>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                @if($vendor->is_active)
                                    <span class="apple-status-capsule capsule-completed">
                                        <span class="capsule-dot"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="apple-status-capsule capsule-cancelled">
                                        <span class="capsule-dot"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center gap-1">
                                    <button type="button"
                                            class="apple-action-btn credentials-vendor-btn"
                                            data-id="{{ $vendor->id }}"
                                            data-name="{{ $vendor->name }}"
                                            data-code="{{ $vendor->vendor_code }}"
                                            data-email="{{ $vendorUser ? $vendorUser->email : '' }}"
                                            data-has-user="{{ $vendorUser ? '1' : '0' }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#credentialsVendorModal"
                                            title="View credentials & reset password">
                                        <i class="fas fa-key"></i>
                                    </button>

                                    <a href="{{ route('admin.vendor.detail', $vendor->id) }}"
                                       class="apple-action-btn apple-action-view"
                                       title="Edit vendor profile">
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    <form action="{{ route('admin.vendor.toggle-status', $vendor->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="apple-action-btn"
                                                title="{{ $vendor->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fas fa-power-off {{ $vendor->is_active ? 'text-warning' : 'text-success' }}"></i>
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.vendor.destroy', $vendor->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this vendor? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="apple-action-btn text-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center">
                                <div class="apple-empty-state">
                                    <div class="apple-empty-icon-circle mb-3">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <h4 class="apple-empty-title">No Vendors Found</h4>
                                    <p class="apple-empty-desc">
                                        No fabric suppliers match your search query. Try clearing your filters.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($vendors->hasPages())
            <div class="p-3 border-top d-flex justify-content-center" style="border-color: #ebebf0 !important;">
                {{ $vendors->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form action="{{ route('admin.vendor.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-primary">Supplier Setup</span>
                        <h5 class="apple-modal-title mb-0">Add New Vendor</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="apple-field-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control apple-input" name="name" required placeholder="e.g. PT Textile Jaya">
                        </div>
                        <div class="col-4">
                            <label class="apple-field-label">Vendor Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control apple-input font-monospace" name="vendor_code" required placeholder="e.g. V0999">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="apple-field-label">Group</label>
                            <input type="text" class="form-control apple-input" name="group" placeholder="e.g. Fabric Mills">
                        </div>
                        <div class="col-6">
                            <label class="apple-field-label">Contact Person</label>
                            <input type="text" class="form-control apple-input" name="contact_person" placeholder="e.g. John Doe">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="apple-field-label">Email</label>
                            <input type="email" class="form-control apple-input" name="email" placeholder="vendor@domain.com">
                        </div>
                        <div class="col-6">
                            <label class="apple-field-label">Phone</label>
                            <input type="text" class="form-control apple-input" name="phone" placeholder="+62 812-...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="apple-field-label">Address</label>
                        <textarea class="form-control apple-input" style="height: auto;" name="address" rows="2" placeholder="Factory / office address"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="new_is_active" checked value="1">
                            <label class="form-check-label small fw-semibold" for="new_is_active">
                                Active Vendor Account
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-light border small text-secondary mb-0">
                        <i class="fas fa-info-circle me-1 text-primary"></i> A portal login is generated automatically with default password <code>password</code>.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="apple-btn-primary">Create Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View / Reset Credentials Modal -->
<div class="modal fade" id="credentialsVendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="apple-section-eyebrow text-info">Security &amp; Access</span>
                    <h5 class="apple-modal-title mb-0">Vendor Login Credentials</h5>
                </div>
                <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="apple-modal-order-box mb-3">
                    <div class="small text-muted">Vendor Account</div>
                    <div class="fw-bold fs-6 text-dark" id="cred_modal_vendor_name">—</div>
                    <div class="small text-muted font-monospace" id="cred_modal_vendor_code">—</div>
                </div>

                <div id="cred_modal_has_account_sec">
                    <div class="mb-3">
                        <label class="apple-field-label">Portal Login Email</label>
                        <div class="input-group">
                            <input type="text" class="form-control apple-input bg-light font-monospace" id="cred_modal_email" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyCred('cred_modal_email', this)"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-3 text-secondary">
                        <i class="fas fa-info-circle me-1 text-info"></i> Standard default password for new or reset vendor accounts is <code>password</code>.
                    </div>

                    <form method="POST" id="resetPasswordForm" action="">
                        @csrf
                        @if(request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        <h6 class="fw-semibold mb-2 text-dark small"><i class="fas fa-shield-alt me-1 text-primary"></i> Reset Password</h6>
                        <p class="small text-muted mb-2">Provide a custom password or leave empty to reset to default <code>password</code>.</p>
                        <div class="mb-3">
                            <input type="text" name="password" class="form-control apple-input font-monospace" placeholder="password">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="apple-btn-warning px-3">
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
                        <button type="submit" class="apple-btn-primary px-4">
                            <i class="fas fa-user-plus me-1"></i> Create Login Account
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if(session('new_vendor_credentials'))
@php $cred = session('new_vendor_credentials'); @endphp
<div class="modal fade" id="vendorCredentialsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="apple-section-eyebrow text-success">Credentials Created</span>
                    <h5 class="apple-modal-title mb-0">{{ !empty($cred['is_reset']) ? 'Vendor Password Reset' : 'Vendor Login Credentials' }}</h5>
                </div>
                <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="alert alert-warning small mb-3">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Copy these credentials now — password details are shown <strong>only once</strong>.
                </div>
                <div class="mb-2">
                    <label class="apple-field-label">Vendor</label>
                    <div class="fw-semibold text-dark">{{ $cred['name'] }}</div>
                </div>
                <div class="mb-2">
                    <label class="apple-field-label">Login Email</label>
                    <div class="input-group">
                        <input type="text" class="form-control apple-input" id="credEmail" value="{{ $cred['login_email'] }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyCred('credEmail', this)"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="apple-field-label">Password</label>
                    <div class="input-group">
                        <input type="text" class="form-control apple-input font-monospace" id="credPassword" value="{{ $cred['password'] }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyCred('credPassword', this)"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="apple-btn-secondary" type="button" onclick="copyCred('__both__', this)"><i class="fas fa-copy me-1"></i> Copy both</button>
                <button type="button" class="apple-btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">Copied to clipboard</span>
</div>

@push('scripts')
<script>
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
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(function () { btn.innerHTML = original; }, 1200);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const credModal = document.getElementById('vendorCredentialsModal');
        if (credModal && window.bootstrap) {
            new bootstrap.Modal(credModal).show();
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
                    document.getElementById('resetPasswordForm').action = `/admin/vendor/${id}/reset-password`;
                } else {
                    hasAccountSec.classList.add('d-none');
                    noAccountSec.classList.remove('d-none');
                    document.getElementById('createAccountModalForm').action = `/admin/vendor/${id}/create-account`;
                }
            });
        }
    });
</script>
@endpush

@include('partials.apple-theme')
@endsection
