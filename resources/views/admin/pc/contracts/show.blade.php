@extends('layouts.app')

@section('title', 'Preliminary Contract — ' . ($contract->contract_number ?? 'Draft'))

@section('content')
@include('admin.pc.partials._tokens')
@php
    $s = $contract->status;
    $snap = $contract->header_snapshot ?? [];
@endphp

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'show'])

    {{-- Header & Action Bar --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.pc.contracts.index') }}">Contracts</a></li>
                    <li class="breadcrumb-item active">{{ $contract->contract_number ?? 'Draft Contract' }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 fw-bold text-dark mb-0">{{ $contract->contract_number ?? 'Draft Contract' }}</h1>
                @if($contract->revision_no > 0)
                    <span class="badge bg-secondary-subtle text-secondary fs-6">REV {{ $contract->revision_no }}</span>
                @endif
                <div>@include('admin.pc.partials.status-pill', ['status' => $s])</div>
            </div>
            <div class="mt-2" style="max-width: 480px;">
                @include('admin.pc.partials.stage-tracker', ['status' => $s])
            </div>
        </div>

        {{-- Action Buttons Toolbar --}}
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($contract->isEditableByPreparer())
                <a href="{{ route('admin.pc.contracts.edit', $contract) }}" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Edit Contract
                </a>
                <form action="{{ route('admin.pc.contracts.submit', $contract) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Submit this contract for internal approval? An official contract number will be assigned.')">
                        <i class="fas fa-paper-plane me-1"></i> Submit for Approval
                    </button>
                </form>
            @endif

            @if($contract->isAwaitingApproval() && $isApprover)
                <button class="btn btn-success px-3" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fas fa-check-circle me-1"></i> Approve (TOTP)
                </button>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times-circle me-1"></i> Reject…
                </button>
            @endif

            @if($contract->isApproved())
                <form action="{{ route('admin.pc.contracts.send-to-supplier', $contract) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Send this approved contract to the supplier via Vendor Portal?')">
                        <i class="fas fa-envelope me-1"></i> Send to Supplier
                    </button>
                </form>
            @endif

            <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-danger">
                <i class="fas fa-file-pdf me-1"></i>{{ $contract->files()->where('file_type', 'contract_pdf')->exists() ? 'Official PDF' : 'Preview PDF' }}
            </a>

            @if($contract->canBeRevised())
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reviseModal">
                    <i class="fas fa-history me-1"></i> Revise
                </button>
            @endif

            @if($contract->canBeCancelledBy(auth()->user()))
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="fas fa-ban me-1"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    {{-- Supplier Query Alert (if queried) --}}
    @if($contract->isQueried())
        <div class="alert alert-warning border-warning shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-1">
                <i class="fas fa-exclamation-triangle me-1"></i> Supplier Query Received:
            </h6>
            <p class="mb-2">{{ $contract->vendor_decision_reason }}</p>
            <div>
                <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#reviseModal">
                    <i class="fas fa-code-branch me-1"></i> Create Revision to Resolve
                </button>
            </div>
        </div>
    @endif

    {{-- Header Details & Workflow Cards --}}
    <div class="row g-4 mb-4">
        {{-- Contract Terms --}}
        <div class="col-lg-6">
            <div class="card h-100 border">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold"><i class="fas fa-file-contract text-primary me-2"></i>Contract Terms</span>
                    <span class="badge bg-light text-dark border">{{ $contract->fabric_type ?? 'WOVEN' }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 text-muted" style="width: 35%">Vendor</td>
                                <td class="pe-3 fw-bold text-dark">
                                    {{ $contract->vendor->name ?? '—' }}
                                    <span class="text-muted fw-normal">({{ $contract->vendor->code ?? '' }})</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Currency</td>
                                <td class="pe-3">
                                    <span class="badge bg-light text-dark border">{{ $contract->currency?->code }} 🔒</span>
                                    <span class="text-dark ms-1">{{ $contract->currency?->label }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Contract Date</td>
                                <td class="pe-3 text-dark fw-semibold">
                                    {{ $contract->contract_date ? $contract->contract_date->format('d M Y') : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Season</td>
                                <td class="pe-3">
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        {{ $contract->season?->label ?? $contract->season?->name ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Payment Terms</td>
                                <td class="pe-3 text-dark fw-semibold">{{ $contract->paymentTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Delivery Terms</td>
                                <td class="pe-3 text-dark fw-semibold">{{ $contract->deliveryTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">Transport Mode</td>
                                <td class="pe-3 text-dark fw-semibold">{{ $contract->transportMode?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-muted">VAT / PPN</td>
                                <td class="pe-3 text-dark">
                                    {{ $contract->vatTreatment?->label ?? 'EXCLUDE' }}
                                    @if(($contract->vatTreatment?->code ?? '') === 'INCLUDE')
                                        <span class="badge bg-info-subtle text-info ms-1">PPN {{ $contract->vat_rate }}%</span>
                                    @endif
                                </td>
                            </tr>
                            @if($contract->internal_remarks)
                                <tr>
                                    <td class="ps-3 text-muted">Remarks</td>
                                    <td class="pe-3 text-muted fst-italic">{{ $contract->internal_remarks }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Workflow & Milestones --}}
        <div class="col-lg-6">
            <div class="card h-100 border">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold"><i class="fas fa-history text-info me-2"></i>Milestones & Audit Trail</span>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3 text-muted" style="width: 35%">Prepared by</td>
                                <td class="pe-3">
                                    <span class="fw-bold text-dark">{{ $contract->creator?->name ?? 'Leon Oscar' }}</span>
                                    <span class="text-muted small">({{ $contract->created_at ? $contract->created_at->format('d M Y, H:i') : '—' }} WIB)</span>
                                </td>
                            </tr>
                            @if($contract->submitted_at)
                                <tr>
                                    <td class="ps-3 text-muted">Submitted</td>
                                    <td class="pe-3 text-dark">{{ $contract->submitted_at->format('d M Y, H:i') }} WIB</td>
                                </tr>
                            @endif
                            @if($contract->approval_decision)
                                <tr>
                                    <td class="ps-3 text-muted">Internal Approval</td>
                                    <td class="pe-3">
                                        <span class="badge {{ $contract->approval_decision === 'approved' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                            {{ ucfirst($contract->approval_decision) }}
                                        </span>
                                        <span class="text-dark ms-1">by {{ $contract->approver?->name ?? 'Aslam Akbany' }}</span>
                                        <span class="text-muted small">({{ $contract->approval_decision_at ? $contract->approval_decision_at->format('d M Y, H:i') : '' }})</span>
                                        @if($contract->approval_decision_reason)
                                            <div class="small text-danger mt-1">Reason: {{ $contract->approval_decision_reason }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            @if($contract->vendor_decision)
                                <tr>
                                    <td class="ps-3 text-muted">Vendor Response</td>
                                    <td class="pe-3">
                                        <span class="badge {{ $contract->vendor_decision === 'accepted' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                            {{ ucfirst($contract->vendor_decision) }}
                                        </span>
                                        <span class="text-muted small ms-1">({{ $contract->vendor_decision_at ? $contract->vendor_decision_at->format('d M Y, H:i') : '' }})</span>
                                        @if($contract->vendor_decision_reason)
                                            <div class="small text-muted mt-1">{{ $contract->vendor_decision_reason }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            @if($contract->confirmed_at)
                                <tr>
                                    <td class="ps-3 text-muted">Confirmed at</td>
                                    <td class="pe-3 fw-bold text-success">
                                        <i class="fas fa-check-circle me-1"></i> {{ $contract->confirmed_at->format('d M Y, H:i') }} WIB
                                    </td>
                                </tr>
                            @endif
                            @if($contract->cancelled_at)
                                <tr>
                                    <td class="ps-3 text-muted">Cancelled</td>
                                    <td class="pe-3 text-danger fw-bold">
                                        {{ $contract->cancelled_at->format('d M Y, H:i') }} — "{{ $contract->cancel_reason }}"
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>

                    {{-- Fabric Delivery Schedule Summary --}}
                    @if($contract->deliveries->isNotEmpty())
                        <div class="p-3 border-top bg-light">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-truck me-1"></i>Fabric Delivery Schedule</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($contract->deliveries as $del)
                                    <span class="badge bg-white text-dark border p-2">
                                        <strong>Lot {{ $del->lot?->label ?? $del->lot?->code ?? $loop->iteration }}:</strong>
                                        {{ $del->eta_date ? $del->eta_date->format('d M Y') : '—' }}
                                        @if($del->delivery_qty > 0) ({{ number_format($del->delivery_qty) }}) @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Card --}}
    <div class="card mb-4 border">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <h5 class="mb-0 fw-bold fs-6">
                <i class="fas fa-list-ol text-primary me-2"></i>Ordered Lines ({{ $contract->lines->count() }})
            </h5>
            <div class="fw-bold text-dark">
                Total Quantity: <span class="text-primary">{{ number_format($contract->lines->sum('qty'), 2) }}</span> &nbsp;|&nbsp; 
                Total Value: <span class="text-success">{{ $contract->currency?->code ?? 'USD' }} {{ number_format($contract->lines->sum('line_total'), 2) }}</span>
            </div>
        </div>

        {{-- Desktop Table --}}
        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 40px">#</th>
                            <th>Item Details</th>
                            <th>Style / PLM</th>
                            <th>Specifications</th>
                            <th>HS Code</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="pe-3 text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contract->lines as $line)
                            <tr>
                                <td class="ps-3 text-muted">{{ $line->line_no }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $line->item_name_snapshot }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $line->item_number_snapshot }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $line->plm_article_name_snapshot ?? '—' }}</div>
                                    <div class="small text-muted">{{ $line->plm_id_snapshot }}</div>
                                </td>
                                <td>
                                    <div class="small text-dark">{{ $line->fabricItem?->composition }}</div>
                                    <div class="small text-muted">{{ $line->fabricItem?->construction }} · {{ $line->fabricItem?->weight_gsm ? $line->fabricItem->weight_gsm . ' GSM' : '' }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $line->hs_code_snapshot }}</span></td>
                                <td class="text-end fw-bold">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }}</td>
                                <td class="text-end">{{ number_format($line->unit_price, 4) }}</td>
                                <td class="pe-3 text-end fw-bold text-dark">{{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile Cards --}}
        <div class="d-md-none p-2">
            @foreach ($contract->lines as $line)
                <div class="border rounded p-3 mb-2 bg-white shadow-sm">
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-light text-dark border">#{{ $line->line_no }}</span>
                        <span class="fw-bold text-success">{{ number_format($line->line_total, 2) }} {{ $contract->currency?->code }}</span>
                    </div>
                    <div class="fw-bold mt-1">{{ $line->item_name_snapshot }}</div>
                    <div class="small text-muted">{{ $line->item_number_snapshot }}</div>
                    <div class="small mt-1"><strong>Style:</strong> {{ $line->plm_article_name_snapshot }} ({{ $line->plm_id_snapshot }})</div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top small">
                        <div>HS: {{ $line->hs_code_snapshot }}</div>
                        <div class="fw-semibold">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }} × {{ number_format($line->unit_price, 2) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Frozen Clauses & Tolerances --}}
    @if($contract->notes->isNotEmpty())
        <div class="card mb-4 border">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="fas fa-file-alt text-secondary me-2"></i>Standard Clauses</span>
                <span class="text-muted small ms-1">(frozen at approval)</span>
            </div>
            <div class="card-body p-3 small">
                @foreach ($contract->notes as $note)
                    <div class="mb-2 p-2 rounded {{ $note->emphasis || in_array($note->clause_number, [7, 8, 9]) ? 'bg-danger-subtle text-danger fw-bold' : '' }}">
                        {{ $note->clause_number }}. {{ $note->body }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Sticky Bottom Action Bar for Mobile Approver --}}
@if($contract->isAwaitingApproval() && $isApprover)
    <div class="d-md-none position-fixed bottom-0 start-0 end-0 bg-white border-top p-2 shadow-lg" style="z-index: 1040">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-danger flex-fill py-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                Reject…
            </button>
            <button class="btn btn-success flex-fill py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#approveModal">
                Approve (TOTP)
            </button>
        </div>
    </div>
@endif

{{-- Modals --}}

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.approve', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Approve Contract {{ $contract->contract_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-dark">Enter your 6-digit Authenticator code to finalize approval and generate the frozen PDF contract:</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">6-digit Code</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required autofocus>
                    </div>
                    <div class="small text-muted">
                        Approval will freeze clauses, tolerances, item snapshot, and store the legal contract PDF.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.reject', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger fw-bold">Reject Contract back to Draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Rejection (required)</label>
                        <textarea name="reason" class="form-control" rows="3" minlength="10" placeholder="State clearly what needs to be changed…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">6-digit Authenticator Code</label>
                        <input type="text" name="code" class="form-control text-center fw-bold" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Revise Modal -->
<div class="modal fade" id="reviseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.revise', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New Revision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This will create <strong>REV {{ $contract->revision_no + 1 }}</strong> as a new editable draft, preserving contract number <code>{{ $contract->contract_number }}</code>.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Revision Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Price adjustment, supplier requested lot change…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Revision Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.cancel', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger fw-bold">Cancel Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">Cancellation is final and cannot be undone.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Cancellation (required)</label>
                        <textarea name="reason" class="form-control" rows="2" minlength="5" required placeholder="Reason for cancellation…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
