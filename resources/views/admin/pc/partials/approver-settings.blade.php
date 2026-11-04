{{-- PC Approver Settings tab-pane content. Data from PcController::index().
     Sub-tabs: Approver List (any admin/fabric_admin can edit) and My
     Authenticator Code (only rendered for the current user when they're a
     configured Approver or root admin — see $isApprover). --}}
<div class="mb-3">
    <ul class="nav apple-segmented-bar" id="approverSubTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="apple-segment-item active" id="approver-list-subtab" data-bs-toggle="tab" data-bs-target="#approver-list-content" type="button" role="tab">
                <i class="fas fa-users-cog"></i> Approver List
            </button>
        </li>
        @if ($isApprover)
            <li class="nav-item" role="presentation">
                <button class="apple-segment-item" id="approver-totp-subtab" data-bs-toggle="tab" data-bs-target="#approver-totp-content" type="button" role="tab">
                    <i class="fas fa-key"></i> My Authenticator Code
                </button>
            </li>
        @endif
    </ul>
</div>

<div class="tab-content">
    <div class="tab-pane fade show active" id="approver-list-content" role="tabpanel">
        <div class="apple-card">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-shield-halved text-primary"></i>
                    <span>Approver Pool</span>
                </div>
            </div>
            <div class="p-3 p-md-4">
                <form action="{{ route('admin.pc.workflow.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="pc_approver_emails" class="form-label fw-bold">PC Approver Email Address(es)</label>
                        <input type="text" name="pc_approver_emails" id="pc_approver_emails"
                               class="form-control @error('pc_approver_emails') is-invalid @enderror"
                               value="{{ old('pc_approver_emails', $pcApproverEmails) }}"
                               placeholder="aslam@megaperintis.co.id">
                        @error('pc_approver_emails')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Any existing <code>fabric_admin</code> or <code>admin</code> account whose email is listed here is authorized to approve contracts. Separate multiple recipients with commas.
                        </div>
                    </div>
                    <div class="d-flex justify-content-end border-top pt-3" style="border-color: var(--apple-border-subtle) !important;">
                        <button type="submit" class="apple-btn-primary">
                            <i class="fas fa-save me-1"></i> Save Approvers
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($isApprover)
        <div class="tab-pane fade" id="approver-totp-content" role="tabpanel">
            <div class="pc-card">
                <div class="pc-card-head d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-key me-2"></i>Authenticator Key (TOTP)
                    </div>
                    @if ($totpConfirmed)
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending Setup</span>
                    @endif
                </div>
                <div class="pc-card-body">
                    @if ($totpConfirmed)
                        <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <div>
                                <i class="fas fa-circle-check fa-lg me-2"></i>
                                <span>Your authenticator code is active and confirmed for signing PC approvals.</span>
                            </div>
                            <form action="{{ route('admin.pc.totp.reset-self') }}" method="POST" onsubmit="return confirm('Reset and reconfigure your authenticator app? You will need to scan a new QR code or enter a new secret key.');" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-rotate me-1"></i> Reset / Change Authenticator
                                </button>
                            </form>
                        </div>
                        <p class="text-muted small mb-0">
                            If you lost access to your authenticator app, got a new phone, or want to re-sync your 6-digit code, click <strong>Reset / Change Authenticator</strong> above to generate a new key and QR code.
                        </p>
                    @else
                        <p class="mb-3">Scan this QR code with Google Authenticator, Microsoft Authenticator, Authy, or any TOTP app, then enter the 6-digit code below to confirm setup.</p>
                        
                        <div class="row align-items-center g-4 my-2">
                            <div class="col-md-5 text-center">
                                <div class="p-3 bg-white border rounded d-inline-block shadow-sm">
                                    {!! $totpQrSvg !!}
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Can't scan the QR code? Enter this secret key manually:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control font-monospace fw-bold" id="totpSecretInput" value="{{ $totpSecret }}" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $totpSecret }}'); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 2000);">
                                            <i class="fas fa-copy me-1"></i> Copy
                                        </button>
                                    </div>
                                    <div class="form-text">Account: <code>{{ auth()->user()->email }}</code> | Type: Time-based (TOTP)</div>
                                </div>

                                <form action="{{ route('admin.pc.totp.confirm') }}" method="POST" class="mt-4">
                                    @csrf
                                    <label class="form-label fw-bold">Enter 6-digit verification code</label>
                                    <div class="input-group mb-3" style="max-width: 320px;">
                                        <input type="text" name="code" class="form-control form-control-lg text-center font-monospace" placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                            <i class="fas fa-check me-1"></i> Confirm
                                        </button>
                                    </div>
                                    <div class="form-text">Open your authenticator app and enter the current 6-digit number shown for this account.</div>
                                </form>

                                <form action="{{ route('admin.pc.totp.reset-self') }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-link text-muted p-0 text-decoration-none">
                                        <i class="fas fa-arrows-rotate me-1"></i> Generate a different secret key
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
