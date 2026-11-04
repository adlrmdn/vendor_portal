@extends('layouts.app')

@section('title', 'Preliminary Contract — ' . ($contract->contract_number ?? 'Review'))

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell apple-po-wrapper">
    <!-- Back Navigation -->
    <div class="mb-3">
        <a href="{{ route('vendor.pc.contracts.index') }}" class="apple-back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Contracts</span>
        </a>
    </div>

    {{-- Header & Top Actions Hero Card --}}
    <div class="apple-card p-3 p-md-4 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="apple-section-eyebrow">Preliminary Contract</span>
                    <span class="apple-bullet">&bull;</span>
                    <span class="apple-section-eyebrow text-muted">Vendor Review</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="apple-page-title mb-0">{{ $contract->contract_number ?? 'Preliminary Contract' }}</h1>
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
                    @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('vendor.pc.guidebook.pdf') }}" target="_blank" class="apple-btn-secondary" title="View Supplier Workflow Guidebook PDF">
                    <i class="fas fa-file-pdf me-1 text-danger"></i>
                    <span>Guidebook</span>
                </a>
                @if($contract->files()->where('file_type', 'contract_pdf')->exists())
                    <a href="{{ route('vendor.pc.contracts.pdf', $contract) }}" target="_blank" class="apple-btn-pdf">
                        <i class="fas fa-file-pdf"></i>
                        <span>Download Official PDF</span>
                    </a>
                @endif
                @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                    <button class="apple-btn-success" data-bs-toggle="modal" data-bs-target="#acceptModal">
                        <i class="fas fa-check"></i>
                        <span>Accept Contract</span>
                    </button>
                    <button class="apple-btn-danger" data-bs-toggle="modal" data-bs-target="#queryModal">
                        <i class="fas fa-question-circle"></i>
                        <span>Query…</span>
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
                @include('admin.pc.partials.stage-tracker', ['status' => $contract->status])
            </div>
        </div>
    </div>

    @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
        <div class="alert alert-primary d-flex align-items-center border-0 shadow-sm mb-4" style="background: #e7f1ff;">
            <i class="fas fa-info-circle fa-lg text-primary me-3"></i>
            <div>
                Please review the terms, quantities, pricing, and delivery schedule below or in the attached PDF. Click <strong>Accept Contract</strong> to confirm agreement, or <strong>Query</strong> if corrections are required.
            </div>
        </div>
    @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
        <div class="alert alert-success d-flex align-items-center border-0 shadow-sm mb-4" style="background: #e6f7ec;">
            <i class="fas fa-check-circle fa-lg text-success me-3"></i>
            <div>
                <span class="fw-bold">Contract Confirmed:</span> This contract was officially accepted by your company on {{ $contract->vendor_decision_at?->format('d-m-Y H:i') ?? $contract->confirmed_at?->format('d-m-Y H:i') }}.
            </div>
        </div>
    @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
        <div class="alert alert-warning border-0 shadow-sm mb-4" style="background: #fff8e6;">
            <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-comment-dots text-warning me-1"></i> Query submitted to PT Mega Perintis:</h6>
            <p class="mb-1 text-dark">{{ $contract->vendor_decision_reason }}</p>
            <div class="small text-muted">Submitted on {{ $contract->vendor_decision_at?->format('d-m-Y H:i') }}. PT Mega Perintis will review and issue a revised contract.</div>
        </div>
    @endif

    {{-- Contract Terms Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="apple-card h-100">
                <div class="apple-card-header">
                    <div class="apple-card-title">
                        <i class="fas fa-file-invoice text-primary"></i>
                        <span>Contract Details</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="apple-kv-table">
                        <tbody>
                            <tr>
                                <td class="apple-kv-label">Buyer</td>
                                <td class="apple-kv-value">PT Mega Putra Garment</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Contract Date</td>
                                <td class="apple-kv-value">{{ $contract->contract_date?->format('d M Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Season</td>
                                <td class="apple-kv-value">{{ $contract->season?->label ?? '—' }}</td>
                            </tr>
                            @php
                                $types = $contract->lines->pluck('fabricItem.weave')->filter()->unique()->values();
                                $ftDisplay = $types->isNotEmpty() ? $types->map(fn($t) => strtoupper($t))->join(', ') : ($contract->fabric_type ?? null);
                            @endphp
                            @if($ftDisplay)
                                <tr>
                                    <td class="apple-kv-label">Fabric Type</td>
                                    <td class="apple-kv-value"><span class="apple-sub-tag pc-tag">{{ $ftDisplay }}</span></td>
                                </tr>
                            @endif
                            <tr>
                                <td class="apple-kv-label">Currency</td>
                                <td class="apple-kv-value">
                                    <span class="apple-count-pill" style="padding: 2px 8px; font-size: 0.75rem;">{{ $contract->currency?->code }}</span>
                                    <span class="text-dark ms-1 small">{{ $contract->currency?->label }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">VAT / PPN</td>
                                <td class="apple-kv-value">
                                    {{ $contract->vatTreatment?->label }}
                                    @if(($contract->vatTreatment?->code ?? '') === 'INCLUDE')
                                        <span class="badge bg-info-subtle text-info ms-1">({{ $contract->vat_rate }}%)</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="apple-card h-100 d-flex flex-column">
                <div class="apple-card-header">
                    <div class="apple-card-title">
                        <i class="fas fa-truck text-info"></i>
                        <span>Commercial &amp; Delivery Terms</span>
                    </div>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="apple-kv-table">
                        <tbody>
                            <tr>
                                <td class="apple-kv-label">Terms of Delivery</td>
                                <td class="apple-kv-value">{{ $contract->deliveryTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Terms of Payment</td>
                                <td class="apple-kv-value">{{ $contract->paymentTerm?->label ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="apple-kv-label">Mode of Transport</td>
                                <td class="apple-kv-value">{{ $contract->transportMode?->label ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Deliveries Schedule --}}
                @if($contract->deliveries->isNotEmpty())
                    <div class="p-3 border-top" style="background: #fafafc; border-color: var(--apple-border-subtle) !important;">
                        <div class="small fw-bold text-dark mb-2"><i class="fas fa-calendar-alt text-muted me-1"></i> Delivery Schedule</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($contract->deliveries as $del)
                                <span class="apple-count-pill" style="background: #ffffff; border: 1px solid var(--apple-border); padding: 5px 12px; font-size: 0.78rem;">
                                    <strong>Lot {{ $del->lot?->label ?? '—' }}:</strong>
                                    <span class="text-primary ms-1">{{ $del->eta_date?->format('d M Y') ?? '—' }}</span>
                                    @if($del->delivery_qty > 0) <span class="text-muted ms-1">({{ number_format($del->delivery_qty) }})</span> @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Line Items --}}
    <div class="apple-card apple-table-container mb-4">
        <div class="apple-card-header">
            <div class="apple-card-title">
                <i class="fas fa-layer-group text-primary"></i>
                <span>Ordered Line Items ({{ $contract->lines->count() }})</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="apple-count-pill" style="font-size: 0.8rem; background: #eafaf1; color: #1b7a42; border: 1px solid rgba(52, 199, 89, 0.3);">
                    Total Value: <strong class="ms-1">{{ $contract->currency?->code ?? 'IDR' }} {{ number_format($contract->lines->sum('line_total'), 2) }}</strong>
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" style="width: 40px">#</th>
                        <th style="width: 80px">LOT</th>
                        <th>ITEM DESCRIPTION</th>
                        <th>BRAND / STYLE</th>
                        <th>PLM ID</th>
                        <th>HS CODE</th>
                        <th class="text-end">QUANTITY</th>
                        <th class="text-end">UNIT PRICE</th>
                        <th class="text-end pe-3">LINE TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contract->lines as $line)
                        <tr>
                            <td class="ps-3 text-muted">{{ $line->line_no }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $line->lot_snapshot ?? ($line->lot?->label ? 'Lot '.$line->lot->label : ($line->lot?->code ? 'Lot '.$line->lot->code : '—')) }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $line->item_name_snapshot }}</div>
                                <span class="text-muted pc-mono small">{{ $line->item_number_snapshot }}</span>
                            </td>
                            <td>{{ $line->plm_article_name_snapshot ?? '—' }}</td>
                            <td class="pc-mono fw-semibold">{{ $line->plm_id_snapshot }}</td>
                            <td class="pc-mono"><span class="badge bg-light text-dark border">{{ $line->formatted_hs_code ?? '—' }}</span></td>
                            <td class="text-end pc-mono fw-semibold">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }}</td>
                            <td class="text-end pc-mono">{{ number_format($line->unit_price, 4) }}</td>
                            <td class="text-end pc-mono fw-bold pe-3 text-dark">{{ number_format($line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Standard Clauses & Additional Notes --}}
    @if($contract->notes->isNotEmpty() || $contract->additional_notes)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="fas fa-file-contract text-muted me-2"></i>
                <h6 class="mb-0 fw-bold text-dark">Standard Clauses & Additional Notes</h6>
            </div>
            <div class="card-body p-4 small" style="background-color: #fafbfc; line-height: 1.6;">
                @if($contract->notes->isNotEmpty())
                    @foreach ($contract->notes->sortBy('clause_number') as $note)
                        <p class="mb-2 {{ $note->emphasis || in_array($note->clause_number, [7, 8, 9]) ? 'text-danger fw-bold' : 'text-dark' }}">
                            {{ $note->clause_number }}. {{ $note->body }}
                        </p>
                    @endforeach
                    @if($contract->additional_notes && !$contract->notes->contains('body', trim($contract->additional_notes)))
                        <p class="mb-2 text-dark">
                            {{ ($contract->notes->max('clause_number') ?? 9) + 1 }}. {{ trim($contract->additional_notes) }}
                        </p>
                    @endif
                @else
                    <p class="mb-2 text-dark">1. THIS CONTRACT IS FOR BOOKING YARN ONLY. DO NOT DYE YARN UNLESS YOU HAVE THE PO IN HAND FROM PURCHASING WITH COLOR BREAKDOWN</p>
                    <p class="mb-2 text-dark">2. PRODUCTION OF FINISH FABRIC SHOULD PROCEED BASED ON QUANTITY ,COLOR BREAKDOWN AND APPROVAL IN PURCHASE ORDER.</p>
                    <p class="mb-2 text-dark">3. MILL HAS TO PROVIDE FOLLOWING DOCS PRIOR TO SHIPMENT : *INTERNAL FABRIC TEST REPORT *SHADEBAND/SHADELOT *INTERNAL 100% FABRIC INSPECTION REPORT</p>
                    <p class="mb-2 text-dark">4. For Import &amp; overseas shipment it is mandatory for all goods to go through a SGS Inspection at factory before shipment cost of which is borne by the Buyer.</p>
                    <p class="mb-2 text-dark">5. The Specification and HS code of the fabric in this contract are stated as declared to us by the supplier.</p>
                    <p class="mb-2 text-dark">6. If SGS inspection finds non-conformity with the quantity , specification and HS of the goods the non-conformity will result in failure of shipment and extra cost will be incurred for shipment for which these costs are to be borne by the Supplier.</p>
                    <p class="mb-2 text-danger fw-bold">7. ANY LATE TIMELINE/DELIVERY FROM COMMITMENT - AIR SHIPMENT MAY APPLY</p>
                    <p class="mb-2 text-danger fw-bold">8. SHIPMENT TOLERANCE TO BE FOLLOWED. SHORTAGE MORE THAN DECLARED % - ACCESSORIES AND OR OTHER RELATED TRIMMING CLAIM WILL APPLY</p>
                    <p class="mb-2 text-danger fw-bold">9. THIS CONTRACT IS ONLY VALID AFTER QUALITY IS APPROVED ONLY</p>
                    @if($contract->additional_notes)
                        <p class="mb-2 text-dark">
                            10. {{ trim($contract->additional_notes) }}
                        </p>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Accept Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('vendor.pc.contracts.accept', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Accept Contract Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">You are officially accepting Preliminary Contract:</p>
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="fw-bold pc-mono fs-6">{{ $contract->contract_number }} @if($contract->revision_no > 0)(REV {{ $contract->revision_no }})@endif</div>
                        <div>Lines: <strong>{{ $contract->lines->count() }} item(s)</strong></div>
                        <div>Total Value: <strong class="pc-mono">{{ number_format($contract->lines->sum('line_total'), 2) }} {{ $contract->currency?->code }}</strong></div>
                    </div>
                    <div class="small text-muted">
                        Acceptance records your user account, timestamp, and IP address electronically in compliance with contract verification procedures.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pc-btn-forest px-4 fw-bold">Confirm & Accept</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Query Modal -->
<div class="modal fade" id="queryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('vendor.pc.contracts.query', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Submit Query on Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Specify what terms, prices, or delivery schedules need revision before you can accept this contract:</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Query / Requested Changes (required)</label>
                        <textarea name="reason" class="form-control" rows="4" required minlength="10" placeholder="e.g. Price for line 2 cannot be met due to raw material increases, or delivery date for Lot 1-2 needs to be pushed back by two weeks…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Submit Query</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('partials.apple-theme')
@endsection
