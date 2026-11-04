@extends('layouts.app')

@section('title', 'Preliminary Contract — ' . ($contract->contract_number ?? 'Draft'))

@section('content')
@include('admin.pc.partials._tokens')
@php
    $s = $contract->status;
    $snap = $contract->header_snapshot ?? [];
@endphp

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'show'])

    <!-- Back Navigation -->
    <div class="mb-3">
        <a href="{{ route('admin.pc.contracts.index') }}" class="apple-back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Contracts</span>
        </a>
    </div>

    {{-- Header & Action Bar Hero Card --}}
    <div class="apple-card p-3 p-md-4 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="apple-section-eyebrow">Preliminary Contract</span>
                    <span class="apple-bullet">&bull;</span>
                    <span class="apple-section-eyebrow text-muted">Legal &amp; Procurement</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="apple-page-title mb-0">{{ $contract->contract_number ?? 'Draft Contract' }}</h1>
                    @if($contract->contract_number)
                        <button type="button"
                                class="apple-copy-btn"
                                onclick="copyPoNumber('{{ $contract->contract_number }}', this)"
                                title="Copy Contract Number">
                            <i class="far fa-copy"></i>
                        </button>
                    @endif
                    @if($contract->revision_no > 0)
                        <span class="badge bg-secondary-subtle text-secondary small px-2 py-1" style="font-size: 0.72rem; border-radius: 6px; border: 1px solid var(--apple-border);">
                            REV {{ $contract->revision_no }}
                        </span>
                    @endif
                    @include('admin.pc.partials.status-pill', ['status' => $s])
                </div>
            </div>

            {{-- Action Buttons Toolbar --}}
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if($contract->isEditableByPreparer())
                    <a href="{{ route('admin.pc.contracts.edit', $contract) }}" class="apple-btn-secondary">
                        <i class="fas fa-edit"></i> <span>Edit Contract</span>
                    </a>
                    <form action="{{ route('admin.pc.contracts.submit', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="apple-btn-primary" onclick="return confirm('Submit this contract for internal approval? An official contract number will be assigned.')">
                            <i class="fas fa-paper-plane"></i> <span>Submit for Approval</span>
                        </button>
                    </form>
                @endif

                @if($contract->isAwaitingApproval() && $isApprover)
                    <button class="apple-btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="fas fa-check-circle"></i> <span>Approve</span>
                    </button>
                    <button class="apple-btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="fas fa-times-circle"></i> <span>Reject…</span>
                    </button>
                @endif

                @if($contract->isApproved())
                    <form action="{{ route('admin.pc.contracts.send-to-supplier', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="apple-btn-primary" onclick="return confirm('Send this approved contract to the supplier via Vendor Portal?')">
                            <i class="fas fa-envelope"></i> <span>Send to Supplier</span>
                        </button>
                    </form>
                @endif

                @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                    <form action="{{ route('admin.pc.contracts.resend-email', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="apple-btn-secondary" title="Resend notification email & PDF to supplier" onclick="return confirm('Resend contract review email and PDF attachment to the supplier?')">
                            <i class="fas fa-paper-plane"></i> <span>Resend Email</span>
                        </button>
                    </form>
                @endif

                <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="apple-btn-pdf">
                    <i class="fas fa-file-pdf"></i>
                    <span>{{ $contract->files()->where('file_type', 'contract_pdf')->exists() ? 'Official PDF' : 'Preview PDF' }}</span>
                </a>

                @if($contract->files()->where('file_type', 'contract_pdf')->exists() && (auth()->user()->isFabricAdmin() || auth()->user()->isAdmin()))
                    <form action="{{ route('admin.pc.contracts.regenerate-pdf', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="apple-btn-secondary" title="Regenerate PDF with latest layout" onclick="return confirm('Regenerate stored official PDF for this contract using current layout?')">
                            <i class="fas fa-sync-alt"></i> <span>Regenerate PDF</span>
                        </button>
                    </form>
                @endif

                @if($contract->canBeRevised())
                    <button class="apple-btn-secondary" data-bs-toggle="modal" data-bs-target="#reviseModal">
                        <i class="fas fa-history"></i> <span>Revise</span>
                    </button>
                @endif

                @if($contract->canBeCancelledBy(auth()->user()))
                    <button class="apple-btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="fas fa-ban"></i> <span>Cancel</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Stage Tracker Card --}}
    <div class="apple-card px-3 py-2 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="apple-section-eyebrow text-muted" style="font-size: 0.72rem;">Workflow Pipeline</span>
            <div>
                @include('admin.pc.partials.stage-tracker', ['status' => $s])
            </div>
        </div>
    </div>

    {{-- Supplier Query Alert (if queried) --}}
    @if($contract->isQueried())
        <div class="apple-card p-3 mb-4" style="background: #fff8e6; border-color: rgba(255, 159, 10, 0.3);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-1">
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i> Supplier Query Received:
                    </h6>
                    <p class="mb-0 small text-dark">{{ $contract->vendor_decision_reason }}</p>
                </div>
                <div>
                    <button class="apple-btn-primary" data-bs-toggle="modal" data-bs-target="#reviseModal">
                        <i class="fas fa-code-branch me-1"></i> Create Revision to Resolve
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Header Details & Workflow Cards --}}
    <div class="row g-3 mb-4">
        {{-- Contract Terms --}}
        <div class="col-lg-6">
            <div class="apple-card h-100">
                <div class="apple-card-header">
                    <div class="apple-card-title">
                        <i class="fas fa-file-contract text-primary"></i>
                        <span>Contract Terms</span>
                    </div>
                    @php
                        $types = $contract->lines->pluck('fabricItem.weave')->filter()->unique()->values();
                    @endphp
                    @if($types->isNotEmpty())
                        <div class="d-flex gap-1">
                            @foreach($types as $t)
                                <span class="apple-sub-tag pc-tag">{{ strtoupper($t) }}</span>
                            @endforeach
                        </div>
                    @elseif($contract->fabric_type)
                        <span class="apple-sub-tag pc-tag">{{ $contract->fabric_type }}</span>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="apple-kv-table">
                        <tbody>
                            <tr>
                                <td class="apple-kv-label">Vendor</td>
                                <td class="apple-kv-value">
                                    {{ $contract->vendor->name ?? '—' }}
                                    <span class="text-muted fw-normal">({{ $contract->vendor->code ?? '' }})</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Currency</td>
                                <td class="apple-kv-value">
                                    <span class="apple-count-pill" style="padding: 2px 8px; font-size: 0.75rem;">{{ $contract->currency?->code }}</span>
                                    <span class="text-dark ms-1 small">{{ $contract->currency?->label }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Contract Date</td>
                                <td class="apple-kv-value">
                                    {{ $contract->contract_date ? $contract->contract_date->format('d M Y') : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Season</td>
                                <td class="apple-kv-value">
                                    <span class="apple-sub-tag pc-tag">
                                        {{ $contract->season?->label ?? $contract->season?->name ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Payment Terms</td>
                                <td class="apple-kv-value">{{ $contract->paymentTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Delivery Terms</td>
                                <td class="apple-kv-value">{{ $contract->deliveryTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Transport Mode</td>
                                <td class="apple-kv-value">{{ $contract->transportMode?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">VAT / PPN</td>
                                <td class="apple-kv-value">
                                    {{ $contract->vatTreatment?->label ?? 'EXCLUDE' }}
                                    @if(($contract->vatTreatment?->code ?? '') === 'INCLUDE')
                                        <span class="badge bg-info-subtle text-info ms-1">PPN {{ $contract->vat_rate }}%</span>
                                    @endif
                                </td>
                            </tr>
                            @if($contract->internal_remarks)
                                <tr>
                                    <td class="apple-kv-label">Remarks</td>
                                    <td class="apple-kv-value text-muted fst-italic small">{{ $contract->internal_remarks }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Workflow & Milestones --}}
        <div class="col-lg-6">
            <div class="apple-card h-100 d-flex flex-column">
                <div class="apple-card-header">
                    <div class="apple-card-title">
                        <i class="fas fa-history text-info"></i>
                        <span>Milestones &amp; Audit Trail</span>
                    </div>
                </div>

                <div class="table-responsive flex-grow-1">
                    <table class="apple-kv-table">
                        <tbody>
                            <tr>
                                <td class="apple-kv-label">Prepared by</td>
                                <td class="apple-kv-value">
                                    <span>{{ $contract->creator?->name ?? 'System' }}</span>
                                    <span class="text-muted small">({{ $contract->created_at ? $contract->created_at->format('d M Y, H:i') : '—' }} WIB)</span>
                                </td>
                            </tr>
                            @if($contract->submitted_at)
                                <tr>
                                    <td class="apple-kv-label">Submitted</td>
                                    <td class="apple-kv-value">{{ $contract->submitted_at->format('d M Y, H:i') }} WIB</td>
                                </tr>
                            @endif
                            @if($contract->approval_decision)
                                <tr>
                                    <td class="apple-kv-label">Internal Approval</td>
                                    <td class="apple-kv-value">
                                        <span class="apple-pill-status {{ $contract->approval_decision === 'approved' ? 'pill-success' : 'pill-danger' }}">
                                            <span class="status-dot {{ $contract->approval_decision === 'approved' ? 'dot-success' : 'dot-cancelled' }}"></span>
                                            {{ ucfirst($contract->approval_decision) }}
                                        </span>
                                        <span class="text-dark ms-1 small">by {{ $contract->approver?->name ?? 'Approver' }}</span>
                                        <span class="text-muted small">({{ $contract->approval_decision_at ? $contract->approval_decision_at->format('d M Y, H:i') : '' }})</span>
                                        @if($contract->approval_decision_reason)
                                            <div class="small text-danger mt-1">Reason: {{ $contract->approval_decision_reason }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            @if($contract->vendor_decision)
                                <tr>
                                    <td class="apple-kv-label">Vendor Response</td>
                                    <td class="apple-kv-value">
                                        <span class="apple-pill-status {{ $contract->vendor_decision === 'accepted' ? 'pill-success' : 'pill-warning' }}">
                                            <span class="status-dot {{ $contract->vendor_decision === 'accepted' ? 'dot-success' : 'dot-warning' }}"></span>
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
                                    <td class="apple-kv-label">Confirmed at</td>
                                    <td class="apple-kv-value text-success">
                                        <i class="fas fa-check-circle me-1"></i> {{ $contract->confirmed_at->format('d M Y, H:i') }} WIB
                                    </td>
                                </tr>
                            @endif
                            @if($contract->cancelled_at)
                                <tr>
                                    <td class="apple-kv-label">Cancelled</td>
                                    <td class="apple-kv-value text-danger">
                                        {{ $contract->cancelled_at->format('d M Y, H:i') }} — "{{ $contract->cancel_reason }}"
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- Fabric Delivery Schedule Summary --}}
                @if($contract->deliveries->isNotEmpty())
                    <div class="p-3 border-top" style="background: #fafafc; border-color: var(--apple-border-subtle) !important;">
                        <div class="small fw-bold text-dark mb-2"><i class="fas fa-truck text-muted me-1"></i>Fabric Delivery Schedule</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($contract->deliveries as $del)
                                <span class="apple-count-pill" style="background: #ffffff; border: 1px solid var(--apple-border); padding: 5px 12px; font-size: 0.78rem;">
                                    <strong>Lot {{ $del->lot?->label ?? $del->lot?->code ?? $loop->iteration }}:</strong>
                                    <span class="text-primary ms-1">{{ $del->eta_date ? $del->eta_date->format('d M Y') : '—' }}</span>
                                    @if($del->delivery_qty > 0) <span class="text-muted ms-1">({{ number_format($del->delivery_qty) }})</span> @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Line Items Card --}}
    <div class="apple-card apple-table-container mb-4">
        <div class="apple-card-header">
            <div class="apple-card-title">
                <i class="fas fa-list-ol text-primary"></i>
                <span>Ordered Lines ({{ $contract->lines->count() }})</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="apple-count-pill" style="font-size: 0.8rem;">
                    Total Qty: <strong class="text-primary ms-1">{{ number_format($contract->lines->sum('qty'), 2) }}</strong>
                </span>
                <span class="apple-count-pill" style="font-size: 0.8rem; background: #eafaf1; color: #1b7a42; border: 1px solid rgba(52, 199, 89, 0.3);">
                    Total Value: <strong class="ms-1">{{ $contract->currency?->code ?? 'USD' }} {{ number_format($contract->lines->sum('line_total'), 2) }}</strong>
                </span>
            </div>
        </div>

        {{-- Desktop Table --}}
        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table apple-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 40px">#</th>
                            <th style="width: 80px">Lot</th>
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
                            <tr class="apple-table-row">
                                <td class="ps-3 text-muted">{{ $line->line_no }}</td>
                                <td>
                                    <span class="apple-count-pill" style="padding: 2px 8px; font-size: 0.72rem;">
                                        {{ $line->lot_snapshot ?? ($line->lot?->label ? 'Lot '.$line->lot->label : ($line->lot?->code ? 'Lot '.$line->lot->code : '—')) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $line->item_name_snapshot }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">{{ $line->item_number_snapshot }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $line->plm_article_name_snapshot ?? '—' }}</div>
                                    <div class="small text-muted">{{ $line->plm_id_snapshot }}</div>
                                </td>
                                <td>
                                    <div class="small text-dark">{{ $line->fabricItem?->composition }}</div>
                                    <div class="small text-muted">{{ $line->fabricItem?->construction }} · {{ $line->fabricItem?->weight_gsm ? $line->fabricItem->weight_gsm . ' GSM' : '' }}</div>
                                </td>
                                <td><span class="apple-sub-tag pc-tag" style="font-size: 0.72rem;">{{ $line->formatted_hs_code ?? '—' }}</span></td>
                                <td class="text-end fw-bold font-monospace">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }}</td>
                                <td class="text-end font-monospace">{{ number_format($line->unit_price, 4) }}</td>
                                <td class="pe-3 text-end fw-bold text-dark font-monospace">{{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile Cards --}}
        <div class="d-md-none p-2">
            @foreach ($contract->lines as $line)
                <div class="apple-card p-3 mb-2">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-1">
                            <span class="apple-count-pill" style="padding: 1px 6px; font-size: 0.7rem;">#{{ $line->line_no }}</span>
                            @if($line->lot_snapshot || $line->lot)
                                <span class="apple-count-pill" style="padding: 1px 6px; font-size: 0.7rem;">
                                    {{ $line->lot_snapshot ?? ($line->lot?->label ? 'Lot '.$line->lot->label : 'Lot '.$line->lot?->code) }}
                                </span>
                            @endif
                        </div>
                        <span class="fw-bold text-success font-monospace">{{ number_format($line->line_total, 2) }} {{ $contract->currency?->code }}</span>
                    </div>
                    <div class="fw-bold text-dark mt-1">{{ $line->item_name_snapshot }}</div>
                    <div class="small text-muted">{{ $line->item_number_snapshot }}</div>
                    <div class="small mt-1"><strong>Style:</strong> {{ $line->plm_article_name_snapshot }} ({{ $line->plm_id_snapshot }})</div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top small" style="border-color: #ebebf0 !important;">
                        <div>HS: {{ $line->formatted_hs_code ?? '—' }}</div>
                        <div class="fw-semibold font-monospace">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }} × {{ number_format($line->unit_price, 2) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Clauses & Additional Notes --}}
    @if($contract->notes->isNotEmpty() || $contract->additional_notes)
        <div class="apple-card p-3 p-md-4 mb-4">
            <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom" style="border-color: #ebebf0 !important;">
                <span class="fw-bold text-dark fs-6"><i class="fas fa-file-signature text-secondary me-2"></i>Additional Notes &amp; Standard Clauses</span>
                @if($contract->isConfirmed() || $contract->isSentToSupplier() || $contract->isApproved())
                    <span class="text-muted small ms-1">(frozen at approval)</span>
                @else
                    <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;">Draft Clauses</span>
                @endif
            </div>
            <div>
                @if($contract->notes->isNotEmpty())
                    @foreach ($contract->notes->sortBy('clause_number') as $note)
                        <div class="mb-2 p-2 rounded {{ $note->emphasis || in_array($note->clause_number, [7, 8, 9]) ? 'bg-danger-subtle text-danger fw-bold' : 'bg-light text-dark' }}" style="font-size: 0.85rem; line-height: 1.45;">
                            {{ $note->clause_number }}. {{ $note->body }}
                        </div>
                    @endforeach
                    @if($contract->additional_notes && !$contract->notes->contains('body', trim($contract->additional_notes)))
                        <div class="mb-2 p-2 rounded bg-light text-dark" style="font-size: 0.85rem; line-height: 1.45;">
                            {{ ($contract->notes->max('clause_number') ?? 9) + 1 }}. {{ trim($contract->additional_notes) }}
                        </div>
                    @endif
                @else
                    {{-- Draft preview --}}
                    @for($c = 1; $c <= 9; $c++)
                        <div class="mb-2 p-2 rounded {{ in_array($c, [7, 8, 9]) ? 'bg-danger-subtle text-danger fw-bold' : 'text-muted' }}">
                            @if($c === 1) 1. THIS CONTRACT IS FOR BOOKING YARN ONLY. DO NOT DYE YARN UNLESS YOU HAVE THE PO IN HAND FROM PURCHASING WITH COLOR BREAKDOWN
                            @elseif($c === 2) 2. PRODUCTION OF FINISH FABRIC SHOULD PROCEED BASED ON QUANTITY ,COLOR BREAKDOWN AND APPROVAL IN PURCHASE ORDER.
                            @elseif($c === 3) 3. MILL HAS TO PROVIDE FOLLOWING DOCS PRIOR TO SHIPMENT : *INTERNAL FABRIC TEST REPORT *SHADEBAND/SHADELOT *INTERNAL 100% FABRIC INSPECTION REPORT
                            @elseif($c === 4) 4. For Import & overseas shipment it is mandatory for all goods to go through a SGS Inspection at factory before shipment cost of which is borne by the Buyer.
                            @elseif($c === 5) 5. The Specification and HS code of the fabric in this contract are stated as declared to us by the supplier.
                            @elseif($c === 6) 6. If SGS inspection finds non-conformity with the quantity , specification and HS of the goods the non-conformity will result in failure of shipment and extra cost will be incurred for shipment for which these costs are to be borne by the Supplier.
                            @elseif($c === 7) 7. ANY LATE TIMELINE/DELIVERY FROM COMMITMENT - AIR SHIPMENT MAY APPLY
                            @elseif($c === 8) 8. SHIPMENT TOLERANCE TO BE FOLLOWED. SHORTAGE MORE THAN DECLARED % - ACCESSORIES AND OR OTHER RELATED TRIMMING CLAIM WILL APPLY
                            @elseif($c === 9) 9. THIS CONTRACT IS ONLY VALID AFTER QUALITY IS APPROVED ONLY
                            @endif
                        </div>
                    @endfor
                    @if($contract->additional_notes)
                        <div class="mb-2 p-2 rounded bg-light border fw-semibold text-dark">
                            10. {{ trim($contract->additional_notes) }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>

{{-- Sticky Bottom Action Bar for Mobile Approver --}}
@if($contract->isAwaitingApproval() && $isApprover)
    <div class="d-md-none position-fixed bottom-0 start-0 end-0 bg-white border-top p-2 shadow-lg" style="z-index: 1040">
        <div class="d-flex gap-2">
            <button class="apple-btn-secondary text-danger flex-fill py-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                Reject…
            </button>
            <button class="apple-btn-success flex-fill py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#approveModal">
                Approve
            </button>
        </div>
    </div>
@endif

{{-- Modals --}}

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form action="{{ route('admin.pc.contracts.approve', $contract) }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-success">Internal Verification</span>
                        <h5 class="apple-modal-title mb-0">Approve Contract {{ $contract->contract_number }}</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="mb-0 text-dark small">Confirm your internal approval to freeze clauses, tolerances, line snapshots, and generate the final contract PDF.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="apple-btn-success px-4">
                        <i class="fas fa-check me-1"></i> Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form action="{{ route('admin.pc.contracts.reject', $contract) }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-danger">Rejection</span>
                        <h5 class="apple-modal-title mb-0 text-danger">Reject Contract back to Draft</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="apple-field-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control apple-input" style="height: auto;" rows="3" minlength="10" placeholder="State clearly what needs to be changed…" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="apple-btn-primary px-4" style="background: #ff3b30;">
                        <i class="fas fa-times me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Revise Modal -->
<div class="modal fade" id="reviseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form action="{{ route('admin.pc.contracts.revise', $contract) }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-primary">Revision Draft</span>
                        <h5 class="apple-modal-title mb-0">Create New Revision</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="apple-modal-order-box mb-3">
                        <div class="small text-muted">Upcoming Revision</div>
                        <div class="fw-bold fs-6 text-dark">REV {{ $contract->revision_no + 1 }}</div>
                        <div class="small text-muted mt-1">Preserving contract number <code>{{ $contract->contract_number }}</code> as an editable draft.</div>
                    </div>
                    <div class="mb-3">
                        <label class="apple-field-label">Revision Reason</label>
                        <textarea name="reason" class="form-control apple-input" style="height: auto;" rows="2" placeholder="e.g. Price adjustment, supplier requested lot change…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="apple-btn-primary">Create Revision Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content apple-modal">
            <form action="{{ route('admin.pc.contracts.cancel', $contract) }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="apple-section-eyebrow text-danger">Void Contract</span>
                        <h5 class="apple-modal-title mb-0 text-danger">Cancel Contract</h5>
                    </div>
                    <button type="button" class="btn-close apple-btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="alert alert-danger small mb-3">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        Cancellation is final and cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="apple-field-label">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control apple-input" style="height: auto;" rows="2" minlength="5" required placeholder="Reason for cancellation…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="apple-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="apple-btn-primary" style="background: #ff3b30;">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">Contract number copied</span>
</div>

@include('partials.apple-theme')
@endsection
