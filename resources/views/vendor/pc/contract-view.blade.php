@extends('layouts.app')

@section('title', 'Preliminary Contract — ' . ($contract->contract_number ?? 'Review'))

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="pc-h1 pc-mono">{{ $contract->contract_number ?? 'Preliminary Contract' }}</h1>
                @if($contract->revision_no > 0)
                    <span class="badge bg-secondary pc-mono">REV {{ $contract->revision_no }}</span>
                @endif
            </div>
            <div class="mt-2">@include('admin.pc.partials.stage-tracker', ['status' => $contract->status])</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($contract->files()->where('file_type', 'contract_pdf')->exists())
                <a href="{{ route('vendor.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-danger">
                    <i class="fas fa-file-pdf me-1"></i>Download Official PDF
                </a>
            @endif
            @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                <button class="btn pc-btn-forest px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#acceptModal">
                    <i class="fas fa-check me-1"></i>Accept Contract
                </button>
                <button class="btn btn-outline-danger px-3" data-bs-toggle="modal" data-bs-target="#queryModal">
                    <i class="fas fa-question-circle me-1"></i>Query…
                </button>
            @endif
        </div>
    </div>

    @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
        <div class="alert alert-primary mb-3">
            <i class="fas fa-info-circle me-1"></i>
            Please review the terms, quantities, pricing, and delivery schedule below or in the attached PDF. Click <strong>Accept Contract</strong> to confirm agreement, or <strong>Query</strong> if corrections are required.
        </div>
    @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
        <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle me-1"></i>
            This contract was accepted by your company on {{ $contract->vendor_decision_at?->format('d-m-Y H:i') ?? $contract->confirmed_at?->format('d-m-Y H:i') }}.
        </div>
    @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
        <div class="alert alert-warning mb-3">
            <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-comment-dots me-1"></i>Query submitted to PT Mega Perintis:</h6>
            <p class="mb-0">{{ $contract->vendor_decision_reason }}</p>
            <div class="small text-muted mt-1">Submitted on {{ $contract->vendor_decision_at?->format('d-m-Y H:i') }}. PT Mega Perintis will review and issue a revised contract.</div>
        </div>
    @endif

    {{-- Contract Terms --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="pc-card h-100">
                <div class="pc-card-head">Contract Details</div>
                <div class="pc-card-body">
                    <table class="pc-table mb-0">
                        <tr><td class="pc-meta" style="width: 40%">Buyer</td><td class="fw-bold">PT Mega Putra Garment</td></tr>
                        <tr><td class="pc-meta">Contract Date</td><td class="pc-mono">{{ $contract->contract_date?->format('d-m-Y') }}</td></tr>
                        <tr><td class="pc-meta">Season</td><td>{{ $contract->season?->label }}</td></tr>
                        <tr><td class="pc-meta">Fabric Type</td><td>{{ $contract->fabric_type }}</td></tr>
                        <tr><td class="pc-meta">Currency</td><td class="pc-mono fw-bold">{{ $contract->currency?->code }} ({{ $contract->currency?->label }})</td></tr>
                        <tr><td class="pc-meta">VAT / PPN</td><td>{{ $contract->vatTreatment?->label }} @if(($contract->vatTreatment?->code ?? '') === 'INCLUDE')({{ $contract->vat_rate }}%)@endif</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="pc-card h-100">
                <div class="pc-card-head">Commercial & Delivery Terms</div>
                <div class="pc-card-body">
                    <table class="pc-table mb-0">
                        <tr><td class="pc-meta" style="width: 40%">Terms of Delivery</td><td>{{ $contract->deliveryTerm?->label ?? '—' }}</td></tr>
                        <tr><td class="pc-meta">Terms of Payment</td><td>{{ $contract->paymentTerm?->label ?? '—' }}</td></tr>
                        <tr><td class="pc-meta">Mode of Transport</td><td>{{ $contract->transportMode?->label ?? '—' }}</td></tr>
                    </table>

                    {{-- Deliveries --}}
                    @if($contract->deliveries->isNotEmpty())
                        <div class="mt-3 pt-2 border-top">
                            <div class="pc-meta fw-bold mb-2">Delivery Schedule</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($contract->deliveries as $del)
                                    <span class="badge bg-light text-dark border p-2">
                                        Lot {{ $del->lot?->label ?? '—' }}: <strong>{{ $del->eta_date?->format('d-m-Y') }}</strong>
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

    {{-- Line Items --}}
    <div class="pc-card mb-3">
        <div class="pc-card-head d-flex justify-content-between align-items-center">
            <span>Ordered Line Items</span>
            <span class="pc-mono fw-bold">
                Total: {{ number_format($contract->lines->sum('line_total'), 2) }} {{ $contract->currency?->code }}
            </span>
        </div>
        <div class="table-responsive">
            <table class="pc-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px">#</th>
                        <th>Item Description</th>
                        <th>Brand / Style</th>
                        <th>PLM ID</th>
                        <th>HS Code</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contract->lines as $line)
                        <tr>
                            <td>{{ $line->line_no }}</td>
                            <td>
                                <div class="fw-bold">{{ $line->item_name_snapshot }}</div>
                                <span class="pc-meta pc-mono small">{{ $line->item_number_snapshot }}</span>
                            </td>
                            <td>{{ $line->plm_article_name_snapshot ?? '—' }}</td>
                            <td class="pc-mono">{{ $line->plm_id_snapshot }}</td>
                            <td class="pc-mono">{{ $line->hs_code_snapshot }}</td>
                            <td class="text-end pc-mono">{{ number_format($line->qty, 2) }} {{ $line->uom_snapshot }}</td>
                            <td class="text-end pc-mono">{{ number_format($line->unit_price, 4) }}</td>
                            <td class="text-end pc-mono fw-bold">{{ number_format($line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Standard Clauses --}}
    @if($contract->notes->isNotEmpty())
        <div class="pc-card mb-3">
            <div class="pc-card-head">Standard Clauses</div>
            <div class="pc-card-body small">
                @foreach ($contract->notes as $note)
                    <p class="mb-2 {{ $note->emphasis ? 'text-danger fw-bold' : '' }}">
                        {{ $note->clause_number }}. {{ $note->body }}
                    </p>
                @endforeach
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
@endsection
