@extends('layouts.app')

@section('title', 'Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')
@php $s = $contract->status; @endphp

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'contracts'])

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div>
            <h1 class="pc-h1 pc-mono">{{ $contract->contract_number ?? 'Draft' }}@if($contract->revision_no > 0)<span class="pc-meta"> rev {{ $contract->revision_no }}</span>@endif</h1>
            <div class="mt-2">@include('admin.pc.partials.stage-tracker', ['status' => $s])</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($contract->isEditableByPreparer())
                <a href="{{ route('admin.pc.contracts.edit', $contract) }}" class="btn btn-outline-secondary">Edit</a>
                <form action="{{ route('admin.pc.contracts.submit', $contract) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn pc-btn-primary" onclick="return confirm('Submit this draft to the vendor for review?')">Submit to Vendor</button>
                </form>
            @endif
            @if($contract->isAwaitingApproval() && $isApprover)
                <button class="btn pc-btn-forest" data-bs-toggle="modal" data-bs-target="#approveModal">Approve</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
            @endif
            @if($contract->isConfirmed())
                <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-secondary">View PDF</a>
                <form action="{{ route('admin.pc.contracts.revise', $contract) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('Create a new revision?')">Revise</button>
                </form>
            @endif
            @if($contract->canBeCancelledBy(auth()->user()))
                <button class="btn pc-btn-outline-brick" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="pc-card h-100">
                <div class="pc-card-head">Header</div>
                <div class="pc-card-body">
                    <table class="pc-table mb-0">
                        <tr><td class="pc-meta">Vendor</td><td>{{ $contract->vendor->name ?? '—' }}</td></tr>
                        <tr><td class="pc-meta">Fabric type</td><td>{{ $contract->fabric_type }}</td></tr>
                        <tr><td class="pc-meta">Contract date</td><td class="pc-mono">{{ $contract->contract_date?->format('d-m-Y') }}</td></tr>
                        <tr><td class="pc-meta">Season</td><td>{{ $contract->season?->label }}</td></tr>
                        <tr><td class="pc-meta">Currency</td><td>{{ $contract->currency?->code }} <span class="pc-meta">(locked from vendor)</span></td></tr>
                        <tr><td class="pc-meta">Payment term</td><td>{{ $contract->paymentTerm?->label }}</td></tr>
                        <tr><td class="pc-meta">Delivery term</td><td>{{ $contract->deliveryTerm?->label }}</td></tr>
                        <tr><td class="pc-meta">Transport</td><td>{{ $contract->transportMode?->label }}</td></tr>
                        <tr><td class="pc-meta">VAT/PPN</td><td>{{ $contract->vatTreatment?->label }} ({{ $contract->vat_rate }}%)</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="pc-card h-100">
                <div class="pc-card-head">Workflow</div>
                <div class="pc-card-body">
                    <table class="pc-table mb-0">
                        <tr><td class="pc-meta">Prepared by</td><td>{{ $contract->creator?->name }} <span class="pc-meta">{{ $contract->created_at->format('d-m-Y H:i') }}</span></td></tr>
                        @if($contract->submitted_at)<tr><td class="pc-meta">Submitted</td><td>{{ $contract->submitted_at->format('d-m-Y H:i') }}</td></tr>@endif
                        @if($contract->vendor_decision)<tr><td class="pc-meta">Vendor</td><td>{{ ucfirst($contract->vendor_decision) }}@if($contract->vendor_decision_reason) — "{{ $contract->vendor_decision_reason }}"@endif</td></tr>@endif
                        @if($contract->approval_decision)<tr><td class="pc-meta">Approver</td><td>{{ ucfirst($contract->approval_decision) }} by {{ $contract->approver?->name }}@if($contract->approval_decision_reason) — "{{ $contract->approval_decision_reason }}"@endif</td></tr>@endif
                        @if($contract->confirmed_at)<tr><td class="pc-meta">Confirmed</td><td>{{ $contract->confirmed_at->format('d-m-Y H:i') }}</td></tr>@endif
                        @if($contract->cancelled_at)<tr><td class="pc-meta">Cancelled</td><td class="pc-text-brick">{{ $contract->cancelled_at->format('d-m-Y H:i') }} — "{{ $contract->cancel_reason }}"</td></tr>@endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="pc-card mb-3">
        <div class="pc-card-head">Line Items</div>
        <table class="pc-table mb-0">
            <thead><tr><th>#</th><th>Item</th><th>PLM</th><th>HS Code</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @foreach ($contract->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>{{ $line->item_name_snapshot }} <span class="pc-meta pc-mono">{{ $line->item_number_snapshot }}</span></td>
                        <td class="pc-mono">{{ $line->plm_id_snapshot }}</td>
                        <td class="pc-mono">{{ $line->hs_code_snapshot }}</td>
                        <td class="num">{{ $line->qty }} {{ $line->uom_snapshot }}</td>
                        <td class="num">{{ $line->unit_price }}</td>
                        <td class="num">{{ $line->line_total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($contract->notes->isNotEmpty())
    <div class="pc-card mb-3">
        <div class="pc-card-head">Standard Clauses <span class="pc-meta fw-normal">frozen at confirm</span></div>
        <div class="pc-card-body">
            @foreach ($contract->notes as $note)
                <p class="mb-1" style="{{ $note->emphasis ? 'color:var(--pc-brick);font-weight:600' : '' }}">{{ $note->clause_number }}. {{ $note->body }}</p>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.approve', $contract) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Approve &amp; confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Approving issues the contract number, freezes the clauses and tolerance table, and generates the PDF. This can't be undone, except by cancelling or revising afterward.</p>
                    <label class="form-label">Authenticator code</label>
                    <input type="text" name="code" class="form-control pc-mono" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                </div>
                <div class="modal-footer"><button type="submit" class="btn pc-btn-forest">Confirm</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.reject', $contract) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Reject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control mb-3" required minlength="10"></textarea>
                    <label class="form-label">Authenticator code</label>
                    <input type="text" name="code" class="form-control pc-mono" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <div class="modal-footer"><button type="submit" class="btn pc-btn-outline-brick">Reject — back to draft</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.pc.contracts.cancel', $contract) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Cancel contract</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" required minlength="5"></textarea>
                </div>
                <div class="modal-footer"><button type="submit" class="btn pc-btn-outline-brick">Cancel contract</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
