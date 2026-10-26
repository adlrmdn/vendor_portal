@extends('layouts.app')

@section('title', 'Central Approvals Hub')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-0 fw-bold">Approvals Hub</h2>
        <small class="text-muted">Consolidated decision center for Preliminary Fabric Contracts &amp; PO Tolerance Amendments</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.workflow') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-sitemap me-1"></i> Workflow Settings
        </a>
        <a href="{{ route('admin.pc.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-file-contract me-1"></i> PC Module
        </a>
        <a href="{{ route('admin.purchase-orders') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-file-invoice me-1"></i> Purchase Orders
        </a>
    </div>
</div>

{{-- Top Decision Tabs --}}
<ul class="nav nav-pills gap-2 mb-4 border-bottom pb-3" id="approvalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.approvals', ['tab' => 'pc', 'q' => $search]) }}" 
           class="nav-link px-3 py-2 d-flex align-items-center gap-2 {{ ($tab ?? 'pc') === 'pc' ? 'active bg-primary' : 'text-secondary bg-light' }}">
            <i class="fas fa-file-contract"></i>
            <span>Preliminary Contracts</span>
            @if($pendingPcCount > 0)
                <span class="badge {{ ($tab ?? 'pc') === 'pc' ? 'bg-white text-primary' : 'bg-warning text-dark' }} rounded-pill">
                    {{ $pendingPcCount }}
                </span>
            @endif
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="{{ route('admin.approvals', ['tab' => 'amendments', 'q' => $search]) }}" 
           class="nav-link px-3 py-2 d-flex align-items-center gap-2 {{ ($tab ?? 'pc') === 'amendments' ? 'active bg-primary' : 'text-secondary bg-light' }}">
            <i class="fas fa-truck-loading"></i>
            <span>Tolerance &amp; Partial Shipments</span>
            @if($pendingAmendmentCount > 0)
                <span class="badge {{ ($tab ?? 'pc') === 'amendments' ? 'bg-white text-primary' : 'bg-danger text-white' }} rounded-pill">
                    {{ $pendingAmendmentCount }}
                </span>
            @endif
        </a>
    </li>
</ul>

{{-- Search Bar --}}
<form method="GET" action="{{ route('admin.approvals') }}" class="mb-4">
    <input type="hidden" name="tab" value="{{ $tab ?? 'pc' }}">
    <div class="input-group" style="max-width: 480px;">
        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
        <input type="text" name="q" value="{{ $search }}" class="form-control border-start-0 ps-0" 
               placeholder="{{ ($tab ?? 'pc') === 'pc' ? 'Search contract number or vendor…' : 'Search PO, item or vendor…' }}">
        @if($search)
            <a href="{{ route('admin.approvals', ['tab' => $tab ?? 'pc']) }}" class="btn btn-outline-secondary" title="Clear search">
                <i class="fas fa-times"></i>
            </a>
        @endif
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
</form>

@if(($tab ?? 'pc') === 'pc')
    {{-- =========================================================================
         TAB 1: PRELIMINARY CONTRACTS AWAITING APPROVAL
         ========================================================================= --}}
    <div class="card border">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold fs-6 text-dark">
                <i class="fas fa-file-signature text-warning me-2"></i>Preliminary Contracts Awaiting Approval
            </h5>
            <span class="badge bg-warning text-dark fs-6">{{ $pendingPcCount }} Pending</span>
        </div>
        <div class="card-body p-0">
            {{-- Desktop Table --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 22%;">Contract</th>
                            <th style="width: 22%;">Vendor</th>
                            <th style="width: 14%;">Season / Lines</th>
                            <th style="width: 16%;">Commercial Total</th>
                            <th style="width: 14%;">Submitted</th>
                            <th class="pe-3 text-end" style="width: 12%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingPcContracts as $contract)
                            @php
                                $totalQty = $contract->lines->sum('qty');
                                $totalVal = $contract->lines->sum('line_total');
                                $ccy = $contract->currency?->code ?? 'USD';
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark fs-6">{{ $contract->contract_number }}</div>
                                    @if($contract->revision_no > 0)
                                        <span class="badge bg-secondary-subtle text-secondary">REV {{ $contract->revision_no }}</span>
                                    @endif
                                    <div class="small text-muted">{{ $contract->fabric_type ?? 'WOVEN' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $contract->vendor->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $contract->vendor->code ?? '' }}</div>
                                </td>
                                <td>
                                    <div><span class="badge bg-light text-dark border">{{ $contract->season->name ?? $contract->season->code ?? '—' }}</span></div>
                                    <div class="small text-muted mt-1">{{ $contract->lines->count() }} line items</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success">{{ $ccy }} {{ number_format($totalVal, 2) }}</div>
                                    <div class="small text-muted">{{ number_format($totalQty, 2) }} units</div>
                                </td>
                                <td>
                                    <div class="text-dark small fw-semibold">{{ $contract->creator?->name ?? 'Leon Oscar' }}</div>
                                    <div class="small text-muted">{{ $contract->submitted_at ? $contract->submitted_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : '—' }} WIB</div>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @if($isPcApprover)
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="openApproveModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="openRejectModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary" title="View Contract Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Preview PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-check-circle fa-2x mb-2 text-success opacity-50 d-block"></i>
                                    <div>No preliminary contracts currently awaiting approval.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (Optimized for Phones) --}}
            <div class="d-md-none p-2">
                @forelse($pendingPcContracts as $contract)
                    @php
                        $totalQty = $contract->lines->sum('qty');
                        $totalVal = $contract->lines->sum('line_total');
                        $ccy = $contract->currency?->code ?? 'USD';
                    @endphp
                    <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-dark fs-6">{{ $contract->contract_number }}</span>
                                @if($contract->revision_no > 0)
                                    <span class="badge bg-secondary ms-1">REV {{ $contract->revision_no }}</span>
                                @endif
                                <div class="small text-muted mt-1">{{ $contract->vendor->name ?? '—' }}</div>
                            </div>
                            <span class="badge bg-warning text-dark">Awaiting Approval</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2 small">
                            <div>
                                <span class="text-muted">Total:</span> 
                                <strong class="text-success">{{ $ccy }} {{ number_format($totalVal, 2) }}</strong>
                            </div>
                            <div>
                                <span class="text-muted">Season:</span> 
                                <span class="badge bg-light text-dark border">{{ $contract->season->code ?? '—' }}</span>
                            </div>
                        </div>

                        <div class="small text-muted mb-3">
                            <i class="fas fa-user-edit me-1"></i> Submitted by <strong>{{ $contract->creator?->name ?? 'Leon Oscar' }}</strong> 
                            ({{ $contract->submitted_at ? $contract->submitted_at->timezone('Asia/Jakarta')->format('d M H:i') : '' }} WIB)
                        </div>

                        <div class="d-grid gap-2">
                            @if($isPcApprover)
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success flex-fill py-2 fw-bold" 
                                            onclick="openApproveModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                        <i class="fas fa-check-circle me-1"></i> Approve (TOTP)
                                    </button>
                                    <button type="button" class="btn btn-outline-danger flex-fill py-2" 
                                            onclick="openRejectModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                        <i class="fas fa-times-circle me-1"></i> Reject…
                                    </button>
                                </div>
                            @endif
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-outline-primary flex-fill py-2">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-danger flex-fill py-2">
                                    <i class="fas fa-file-pdf me-1"></i> PDF Preview
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success opacity-50 d-block"></i>
                        <div>No preliminary contracts currently awaiting approval.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

@else
    {{-- =========================================================================
         TAB 2: TOLERANCE AMENDMENTS & PARTIAL SHIPMENT REQUESTS
         ========================================================================= --}}
    <div class="card border">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold fs-6 text-dark">
                <i class="fas fa-gavel text-info me-2"></i>Tolerance Amendments &amp; Partial Shipments
            </h5>
            <span class="badge bg-danger fs-6">{{ $requests->total() }} Pending</span>
        </div>
        <div class="card-body p-0">
            {{-- Desktop Table --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Type</th>
                            <th>PO / Item</th>
                            <th>Vendor</th>
                            <th>Details</th>
                            <th>Reason</th>
                            <th>Submitted</th>
                            <th class="text-end pe-3" style="width: 200px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            @php $item = $req->poItem; $po = $item?->purchaseOrder; @endphp
                            <tr>
                                <td class="ps-3">
                                    @if($req->type === 'partial_shipment')
                                        <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-25">Partial Shipment</span>
                                    @else
                                        <span class="badge bg-info-subtle text-info border border-info border-opacity-25">Tolerance Amendment</span>
                                    @endif
                                </td>
                                <td>
                                    @if($po)
                                        <a href="{{ route('admin.purchase-order.view', $po->id) }}" class="fw-semibold text-decoration-none font-monospace">{{ $po->po_number }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    <div class="text-muted small">Item {{ $item?->item_number ?? '—' }}</div>
                                </td>
                                <td>{{ $po?->vendor?->name ?? '—' }}</td>
                                <td class="small">
                                    @if($req->type === 'partial_shipment')
                                        Requested qty: <strong>{{ number_format((float) $req->requested_qty, 2) }} {{ $item?->unit }}</strong>
                                    @else
                                        Underdelivery: {{ number_format((float) $req->old_underdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_underdelivery, 2) }}%</strong><br>
                                        Overdelivery: {{ number_format((float) $req->old_overdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_overdelivery, 2) }}%</strong>
                                    @endif
                                </td>
                                <td class="small text-muted" style="max-width: 240px;">{{ \Illuminate\Support\Str::limit($req->reason, 100) }}</td>
                                <td class="small text-muted">{{ $req->created_at?->diffForHumans() }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <form method="POST" action="{{ route('admin.approvals.approve', $req->id) }}" class="m-0"
                                              onsubmit="return confirm('Approve this {{ $req->type === 'partial_shipment' ? 'partial shipment' : 'tolerance amendment' }} request?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.approvals.decline', $req->id) }}" class="m-0"
                                              onsubmit="return confirm('Decline this request?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i> Decline</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                    {{ $search !== '' ? 'No pending approvals match "'.$search.'".' : "No approvals pending. You're all caught up." }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View for Tolerance Requests --}}
            <div class="d-md-none p-2">
                @forelse($requests as $req)
                    @php $item = $req->poItem; $po = $item?->purchaseOrder; @endphp
                    <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-dark font-monospace">{{ $po?->po_number ?? 'PO' }}</span>
                                <div class="small text-muted">Item {{ $item?->item_number ?? '—' }} · {{ $po?->vendor?->name ?? '—' }}</div>
                            </div>
                            @if($req->type === 'partial_shipment')
                                <span class="badge bg-warning text-dark">Partial</span>
                            @else
                                <span class="badge bg-info text-dark">Tolerance</span>
                            @endif
                        </div>
                        <div class="small bg-light p-2 rounded mb-2">
                            @if($req->type === 'partial_shipment')
                                Requested qty: <strong>{{ number_format((float) $req->requested_qty, 2) }} {{ $item?->unit }}</strong>
                            @else
                                Under: {{ number_format((float) $req->old_underdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_underdelivery, 2) }}%</strong><br>
                                Over: {{ number_format((float) $req->old_overdelivery, 2) }}% &rarr; <strong>{{ number_format((float) $req->new_overdelivery, 2) }}%</strong>
                            @endif
                        </div>
                        @if($req->reason)
                            <div class="small text-muted mb-3 fst-italic">"{{ $req->reason }}"</div>
                        @endif
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.approvals.approve', $req->id) }}" class="flex-fill m-0"
                                  onsubmit="return confirm('Approve this request?');">
                                @csrf
                                <button type="submit" class="btn btn-success w-100 py-2 fw-bold"><i class="fas fa-check me-1"></i> Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.approvals.decline', $req->id) }}" class="flex-fill m-0"
                                  onsubmit="return confirm('Decline this request?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100 py-2"><i class="fas fa-times me-1"></i> Decline</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                        {{ $search !== '' ? 'No pending approvals match "'.$search.'".' : "No approvals pending. You're all caught up." }}
                    </div>
                @endforelse
            </div>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-end mb-0">
                    {{ $requests->links('custom-pagination') }}
                </div>
            </div>
        @endif
    </div>
@endif

{{-- Dynamic Approval Modals for Preliminary Contracts --}}
<div class="modal fade" id="pcApproveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="pcApproveForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="pcApproveModalTitle">Approve Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-dark">Enter your 6-digit Authenticator code to finalize approval and generate the frozen PDF contract:</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">6-digit Code</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold font-monospace" 
                               maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required autofocus>
                    </div>
                    <div class="small text-muted">
                        Approval locks the contract, creates an immutable PDF, and readies it for transmission to the supplier.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pcRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="pcRejectForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger fw-bold" id="pcRejectModalTitle">Reject Contract back to Draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Rejection (required)</label>
                        <textarea name="reason" class="form-control" rows="3" minlength="10" placeholder="State clearly what needs to be changed…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">6-digit Authenticator Code</label>
                        <input type="text" name="code" class="form-control text-center fw-bold font-monospace" 
                               maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openApproveModal(contractId, contractNumber) {
        const form = document.getElementById('pcApproveForm');
        form.action = '/admin/pc/contracts/' + contractId + '/approve';
        document.getElementById('pcApproveModalTitle').innerText = 'Approve Contract ' + contractNumber;
        const modal = new bootstrap.Modal(document.getElementById('pcApproveModal'));
        modal.show();
    }

    function openRejectModal(contractId, contractNumber) {
        const form = document.getElementById('pcRejectForm');
        form.action = '/admin/pc/contracts/' + contractId + '/reject';
        document.getElementById('pcRejectModalTitle').innerText = 'Reject Contract ' + contractNumber;
        const modal = new bootstrap.Modal(document.getElementById('pcRejectModal'));
        modal.show();
    }
</script>
@endpush
@endsection
