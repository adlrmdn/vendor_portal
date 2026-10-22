{{-- PC Approver Settings tab-pane content. Data from PcController::index().
     Sub-tabs: Approver List (any admin/fabric_admin can edit) and My
     Authenticator Code (only rendered for the current user when they're a
     configured Approver or root admin — see $isApprover). --}}
<h1 class="pc-h1 mb-4"><i class="fas fa-user-check me-2"></i>PC Approver Settings</h1>

<ul class="nav nav-tabs mb-3" id="approverSubTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="approver-list-subtab" data-bs-toggle="tab" data-bs-target="#approver-list-content" type="button" role="tab">
            Approver List
        </button>
    </li>
    @if ($isApprover)
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approver-totp-subtab" data-bs-toggle="tab" data-bs-target="#approver-totp-content" type="button" role="tab">
                My Authenticator Code
            </button>
        </li>
    @endif
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="approver-list-content" role="tabpanel">
        <div class="pc-card">
            <div class="pc-card-head">
                <i class="fas fa-shield-halved me-2"></i>Approver Pool
            </div>
            <div class="pc-card-body">
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
                            There is no dedicated "PC Approver" role — any existing <code>fabric_admin</code> or <code>admin</code>
                            account whose email is listed here can give the final TOTP-gated approval on a contract and can set up
                            their authenticator code from the "My Authenticator Code" sub-tab. Separate multiple recipients with commas.
                        </div>
                    </div>
                    <div class="d-flex justify-content-end border-top pt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($isApprover)
        <div class="tab-pane fade" id="approver-totp-content" role="tabpanel">
            <div class="pc-card">
                <div class="pc-card-body">
                    @if ($totpConfirmed)
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-circle-check me-2"></i> Your authenticator code is set up and confirmed.
                        </div>
                    @else
                        <p>Scan this code with Google Authenticator, Authy, or any TOTP app, then enter the 6-digit code it shows below to confirm setup.</p>
                        <div class="text-center my-3">
                            {!! $totpQrSvg !!}
                        </div>
                        <form action="{{ route('admin.pc.totp.confirm') }}" method="POST" class="mt-3">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">6-digit code</label>
                                <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Confirm</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
