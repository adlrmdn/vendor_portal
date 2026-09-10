@extends('layouts.app')

@section('title', 'Workflow Settings')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
        color: #334155;
    }
    .premium-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        border-radius: 20px;
        padding: 35px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        position: relative;
        overflow: hidden;
        margin-bottom: 30px;
    }
    .premium-header::after {
        content: '';
        position: absolute;
        right: -50px;
        bottom: -50px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
    }
    .premium-header-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
        margin-bottom: 25px;
    }
    .card-header-premium {
        background-color: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        padding: 20px 24px;
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 1.15rem;
        color: #0f172a;
        display: flex;
        align-items: center;
    }
    .card-body-premium {
        padding: 24px;
    }
    .form-label-premium {
        font-weight: 500;
        color: #475569;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    .form-control-premium {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 11px 16px;
        font-size: 0.95rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control-premium:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    .btn-premium {
        background: #2563eb;
        color: white;
        border-radius: 10px;
        padding: 11px 24px;
        font-weight: 600;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1), 0 2px 4px -1px rgba(37, 99, 235, 0.06);
        transition: all 0.2s;
    }
    .btn-premium:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
    }
</style>

<div class="premium-container container-fluid py-4">
    <!-- Header Banner -->
    <div class="premium-header">
        <div class="d-flex align-items-center">
            <div class="me-4 rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; backdrop-filter: blur(8px);">
                <i class="fas fa-sitemap fs-2 text-white"></i>
            </div>
            <div>
                <h1 class="premium-header-title mb-1">Workflow Mail Settings</h1>
                <p class="mb-0 text-white text-opacity-75">Configure static email recipients for subcon portal transaction pipelines</p>
            </div>
        </div>
    </div>

    <!-- Error Alert List -->
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex">
                <i class="fas fa-exclamation-circle fs-4 me-3 mt-1"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Please correct the following errors:</h5>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="premium-card card">
                <div class="card-header-premium">
                    <i class="fas fa-envelope-open-text me-2 text-primary"></i> Workflow Email Routing Configuration
                </div>
                <div class="card-body-premium">
                    <form action="{{ route('subcon.admin.workflow.update') }}" method="POST">
                        @csrf

                        <!-- Cutting Approver Email -->
                        <div class="mb-4">
                            <label for="subcon_cutting_approver_email" class="form-label-premium">Cutting Approver Email Address(es)</label>
                            <input type="text" name="subcon_cutting_approver_email" id="subcon_cutting_approver_email" class="form-control form-control-premium @error('subcon_cutting_approver_email') is-invalid @enderror" value="{{ old('subcon_cutting_approver_email', $cuttingApproverEmail) }}" placeholder="a@mail.com, b@mail.com" required>
                            @error('subcon_cutting_approver_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Receives all subcon vendor cutting report approval requests containing signed links. Separate multiple recipients with commas.
                            </div>
                        </div>

                        <!-- Gramasi Approver Email -->
                        <div class="mb-4">
                            <label for="subcon_gramasi_approver_email" class="form-label-premium">Gramasi Approver Email Address(es)</label>
                            <input type="text" name="subcon_gramasi_approver_email" id="subcon_gramasi_approver_email" class="form-control form-control-premium @error('subcon_gramasi_approver_email') is-invalid @enderror" value="{{ old('subcon_gramasi_approver_email', $gramasiApproverEmail) }}" placeholder="a@mail.com, b@mail.com" required>
                            @error('subcon_gramasi_approver_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Receives all subcon vendor gramasi report approval requests containing signed links. Separate multiple recipients with commas.
                            </div>
                        </div>

                        <!-- Generate Labels Email -->
                        <div class="mb-4">
                            <label for="subcon_label_generator_email" class="form-label-premium">Generate Labels Email Address(es)</label>
                            <input type="text" name="subcon_label_generator_email" id="subcon_label_generator_email" class="form-control form-control-premium @error('subcon_label_generator_email') is-invalid @enderror" value="{{ old('subcon_label_generator_email', $labelGeneratorEmail) }}" placeholder="a@mail.com, b@mail.com" required>
                            @error('subcon_label_generator_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Receives the signed link to generate packing labels when work orders enter the Distribution stage — the vendor cannot print until labels are generated here. Separate multiple recipients with commas.
                            </div>
                        </div>

                        <!-- Final (Head Office) Approver Email -->
                        <div class="mb-4">
                            <label for="qc_ho_approver_email" class="form-label-premium">Final Email Address(es)</label>
                            <input type="text" name="qc_ho_approver_email" id="qc_ho_approver_email" class="form-control form-control-premium @error('qc_ho_approver_email') is-invalid @enderror" value="{{ old('qc_ho_approver_email', $finalApproverEmail) }}" placeholder="a@mail.com, b@mail.com">
                            @error('qc_ho_approver_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Receives the second-stage (Head Office) consumption approval link after the packaging inspection is confirmed. Separate multiple recipients with commas. Leave blank to reuse the Cutting Approver list.
                            </div>
                        </div>

                        <!-- QC Head — notification only, no approval action -->
                        <div class="mb-4">
                            <label for="qc_head_notification_email" class="form-label-premium">QC Head Email Address(es)</label>
                            <input type="text" name="qc_head_notification_email" id="qc_head_notification_email" class="form-control form-control-premium @error('qc_head_notification_email') is-invalid @enderror" value="{{ old('qc_head_notification_email', $qcHeadNotificationEmail) }}" placeholder="a@mail.com, b@mail.com">
                            @error('qc_head_notification_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Notified when the Director authorizes and the project completes. No approval action — notification only. Separate multiple recipients with commas. Leave blank to skip.
                            </div>
                        </div>

                        <!-- Director (Authorization) Email -->
                        <div class="mb-4">
                            <label for="qc_director_approver_email" class="form-label-premium">Director Email Address(es)</label>
                            <input type="text" name="qc_director_approver_email" id="qc_director_approver_email" class="form-control form-control-premium @error('qc_director_approver_email') is-invalid @enderror" value="{{ old('qc_director_approver_email', $directorApproverEmail) }}" placeholder="a@mail.com, b@mail.com">
                            @error('qc_director_approver_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Receives the third-stage (Director) authorization link after MD Production approves. Approval queues the Invoice &amp; Deduction RPA jobs and completes the project. Separate multiple recipients with commas. Leave blank to reuse the Final Email list.
                            </div>
                        </div>

                        <!-- Director (Authorization) Phone — WhatsApp, alongside the email -->
                        <div class="mb-4">
                            <label for="qc_director_approver_phone" class="form-label-premium">Director Phone Number(s) <span class="text-muted fw-normal">(WhatsApp)</span></label>
                            <input type="text" name="qc_director_approver_phone" id="qc_director_approver_phone" class="form-control form-control-premium @error('qc_director_approver_phone') is-invalid @enderror" value="{{ old('qc_director_approver_phone', $directorApproverPhone) }}" placeholder="08123456789, 08987654321">
                            @error('qc_director_approver_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted mt-2">
                                <i class="fab fa-whatsapp me-1 text-success"></i>
                                Sends the same third-stage authorization link as a WhatsApp message, on top of the Director email above. Separate multiple numbers with commas. Optional — leave blank to notify by email only.
                            </div>
                        </div>

                        <!-- Material Flow auto-approve override — bypasses the "inventory must
                             check returned material" gate for EVERY order while on. See
                             MaterialReturnService::autoApproveActive()/isAutoApproved(). -->
                        <div class="mb-4 border-top pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="subcon_material_flow_auto_approve" id="subcon_material_flow_auto_approve" value="1" {{ old('subcon_material_flow_auto_approve', $materialFlowAutoApprove) ? 'checked' : '' }}>
                                <label class="form-check-label form-label-premium" for="subcon_material_flow_auto_approve">Auto-approve Material Flow check (all orders)</label>
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-triangle-exclamation me-1 text-warning"></i>
                                While on, Report Validation is <strong>never</strong> blocked waiting on inventory's Material Flow check — for every order, regardless of whether it was actually dispatched or checked. This does not fake a real check: the badge shows "Auto-Approved (not checked)" instead of "Checked", and every send that only went through because of this is noted in the decision log. Turn off to restore the real inventory-check gate.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end border-top pt-3">
                            <button type="submit" class="btn btn-premium">
                                <i class="fas fa-save me-2"></i> Save Workflow Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
