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

        @if(session('success') && ! session('new_vendor_credentials'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        <!-- Search Bar -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.vendors') }}" class="row g-2 align-items-center">
                    <div class="col-md-9 col-lg-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by vendor name, code, group, or login email..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
                        @if(request('search'))
                            <a href="{{ route('admin.vendors') }}" class="btn btn-outline-secondary" title="Clear Search">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Vendors List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%">Vendor Details</th>
                                <th style="width: 10%">Group</th>
                                <th style="width: 18%">Contact Info</th>
                                <th style="width: 15%">Login Account</th>
                                <th style="width: 10%" class="text-center">PO Stats</th>
                                <th style="width: 8%" class="text-center">Status</th>
                                <th style="width: 19%" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vendors as $vendor)
                                @php $vendorUser = $vendor->users->first(); @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $vendor->vendor_code }}</div>
                                        <div class="text-muted small">{{ $vendor->name }}</div>
                                    </td>
                                    <td>{{ $vendor->group ?? '—' }}</td>
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
                                    <td>
                                        @if($vendorUser)
                                            <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1">
                                                <code class="text-dark me-2 small">{{ $vendorUser->email }}</code>
                                                <button type="button" class="btn btn-link text-secondary p-0 border-0"
                                                    title="Copy Login Email"
                                                    onclick="copyText('{{ $vendorUser->email }}', this)">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        @else
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <span class="badge bg-warning text-dark">No Login</span>
                                                <form action="{{ route('admin.vendor.create-account', $vendor->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @if(request('search'))
                                                        <input type="hidden" name="search" value="{{ request('search') }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2 small">
                                                        Create Account
                                                    </button>
                                                </form>
                                            </div>
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
                                        <div class="d-flex flex-wrap justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-info credentials-vendor-btn"
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
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <form action="{{ route('admin.vendor.toggle-status', $vendor->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-{{ $vendor->is_active ? 'warning' : 'success' }}"
                                                    title="{{ $vendor->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>

                                            <form action="{{ route('admin.vendor.destroy', $vendor->id) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this vendor? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
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
                                <label class="form-label">Group</label>
                                <input type="text" class="form-control" name="group">
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
                            <div class="alert alert-light border small text-secondary mb-0">
                                <i class="fas fa-info-circle me-1 text-info"></i> A portal login is created automatically with default password <code>password</code>, shown once after saving.
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

        <!-- View / Reset Credentials Modal -->
        <div class="modal fade" id="credentialsVendorModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-key me-2 text-info"></i> Vendor Login Credentials</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-0">Vendor</label>
                            <div class="fw-semibold text-dark" id="cred_modal_vendor_name">—</div>
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

                            <hr class="my-3">

                            <form method="POST" id="resetPasswordForm" action="">
                                @csrf
                                @if(request('search'))
                                    <input type="hidden" name="search" value="{{ request('search') }}">
                                @endif
                                <h6 class="fw-semibold mb-2 text-dark"><i class="fas fa-shield-alt me-1 text-primary"></i> Reset Password</h6>
                                <p class="small text-muted mb-2">Provide a new custom password or leave empty to reset to default <code>password</code>.</p>
                                <div class="mb-3">
                                    <input type="text" name="password" class="form-control font-monospace" placeholder="password">
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning px-3">
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
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-user-plus me-1"></i> Create Login Account
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        @if(session('new_vendor_credentials'))
        @php $cred = session('new_vendor_credentials'); @endphp
        <div class="modal fade" id="vendorCredentialsModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-key me-2 text-success"></i> {{ !empty($cred['is_reset']) ? 'Vendor Password Reset' : 'Vendor Login Credentials' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
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
                    <div class="modal-footer">
                        <button class="btn btn-outline-primary" type="button" onclick="copyCred('__both__', this)"><i class="fas fa-copy me-1"></i> Copy both</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

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
@endsection
