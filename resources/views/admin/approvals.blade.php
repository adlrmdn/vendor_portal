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
                            <th class="ps-3" style="width: 20%;">Contract</th>
                            <th style="width: 20%;">Vendor</th>
                            <th style="width: 14%;">Season &amp; Lines</th>
                            <th style="width: 16%;">Commercial Total</th>
                            <th style="width: 14%;">Submitted</th>
                            <th class="pe-3 text-end" style="width: 16%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingPcContracts as $contract)
                            @php
                                $totalQty = $contract->lines->sum('qty');
                                $totalVal = $contract->lines->sum('line_total');
                                $ccy = $contract->currency?->code ?? 'USD';
                                $mainUom = $contract->lines->first()?->uom_snapshot ?? $contract->lines->first()?->fabricItem?->uom ?? 'units';
                            @endphp
                            <tr class="border-bottom-0">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="fw-bold text-primary text-decoration-none fs-6">
                                            {{ $contract->contract_number }}
                                        </a>
                                        @if($contract->revision_no > 0)
                                            <span class="badge bg-secondary-subtle text-secondary border">REV {{ $contract->revision_no }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1 d-flex gap-1 align-items-center flex-wrap">
                                        @if($contract->deliveryTerm)
                                            <span class="badge bg-light text-secondary border">{{ $contract->deliveryTerm->code }}</span>
                                        @endif
                                        @if($contract->paymentTerm)
                                            <span class="badge bg-light text-secondary border">{{ $contract->paymentTerm->code }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $contract->vendor->name ?? '—' }}</div>
                                    <div class="small text-muted mt-1">
                                        <span class="badge bg-light text-dark border">{{ $contract->vendor->code ?? '—' }}</span>
                                        @if($contract->vendor?->contact_person)
                                            <span class="ms-1">&bull; {{ $contract->vendor->contact_person }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div><span class="badge bg-light text-dark border fw-semibold">{{ $contract->season->name ?? $contract->season->code ?? '—' }}</span></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1 py-0 px-2 d-inline-flex align-items-center gap-1" 
                                            data-bs-toggle="collapse" data-bs-target="#pc-lines-{{ $contract->id }}">
                                        <i class="fas fa-layer-group"></i> <span>{{ $contract->lines->count() }} line items</span> <i class="fas fa-chevron-down small"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-6">{{ $ccy }} {{ number_format($totalVal, 2) }}</div>
                                    <div class="small text-muted">{{ number_format($totalQty, $totalQty == (int)$totalQty ? 0 : 2) }} {{ $mainUom }}</div>
                                    <div class="mt-1">
                                        @if($contract->vatTreatment?->code === 'INCLUDE')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">Inc. PPN {{ $contract->vat_rate ?? 11 }}%</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.72rem;">Excl. PPN</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark small fw-semibold">{{ $contract->creator?->name ?? 'Fabric Admin' }}</div>
                                    <div class="small text-muted">{{ $contract->submitted_at ? $contract->submitted_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : ($contract->created_at ? $contract->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') : '—') }} WIB</div>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex gap-1 justify-content-end align-items-center">
                                        @if($isPcApprover)
                                            <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm px-2 py-1" 
                                                    onclick="openApproveModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1" 
                                                    onclick="openRejectModal('{{ $contract->id }}', '{{ $contract->contract_number }}')" title="Reject Contract back to Draft">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary px-2 py-1" title="View Contract Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Preview PDF">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            {{-- Expandable Lines Sub-table --}}
                            <tr class="collapse show" id="pc-lines-{{ $contract->id }}">
                                <td colspan="6" class="p-0 border-0">
                                    <div class="px-3 pb-3 pt-1 bg-light border-bottom">
                                        <div class="card border shadow-sm">
                                            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                                                <div class="small fw-bold text-dark">
                                                    <i class="fas fa-list text-primary me-1"></i> Contract Items Breakdown ({{ $contract->lines->count() }} items)
                                                </div>
                                                <div class="small text-muted">
                                                    Contract: <strong class="text-dark">{{ $contract->contract_number }}</strong>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover align-middle mb-0 bg-white" style="font-size: 0.82rem;">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="text-center" style="width: 35px;">#</th>
                                                            <th style="width: 130px;">Item No</th>
                                                            <th style="width: 150px;">Brand / Article</th>
                                                            <th>Item Name &amp; Description</th>
                                                            <th style="width: 140px;">Composition / Construction</th>
                                                            <th class="text-center" style="width: 70px;">Lot</th>
                                                            <th class="text-end" style="width: 100px;">Quantity</th>
                                                            <th class="text-end" style="width: 110px;">Unit Price</th>
                                                            <th class="text-end" style="width: 120px;">Line Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($contract->lines as $line)
                                                            @php
                                                                $lQty = (float) $line->qty;
                                                                $lPrice = (float) $line->unit_price;
                                                                $lTotal = $lQty * $lPrice;
                                                                $lUom = $line->uom_snapshot ?? $line->fabricItem?->uom ?? 'MTR';
                                                            @endphp
                                                            <tr>
                                                                <td class="text-center text-muted fw-bold">{{ $line->line_no }}</td>
                                                                <td class="font-monospace fw-bold text-primary">{{ $line->item_number_snapshot }}</td>
                                                                <td>
                                                                    <div class="fw-semibold text-dark">{{ $line->plmRef?->article_name ?? $line->plm_article_name_snapshot ?? '—' }}</div>
                                                                    @if($line->plm_id_snapshot)
                                                                        <div class="text-muted" style="font-size: 0.72rem;">{{ $line->plm_id_snapshot }}</div>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div class="text-dark">{{ $line->item_name_snapshot }}</div>
                                                                    @if($line->remarks)
                                                                        <div class="mt-1 text-primary small"><i class="fas fa-comment-dots me-1"></i><strong>Remark:</strong> {{ $line->remarks }}</div>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div>{{ $line->resolved_composition ?? $line->fabricItem?->composition ?? '—' }}</div>
                                                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $line->resolved_construction ?? $line->fabricItem?->construction ?? '' }}</div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-light text-dark border">
                                                                        {{ $line->lot_snapshot ? str_replace('Lot ', '', $line->lot_snapshot) : ($line->lot?->label ?? $line->lot?->code ?? ($line->deliveries->first()?->lot?->label ?? $line->deliveries->first()?->lot?->code ?? '—')) }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-end fw-bold text-dark">
                                                                    {{ number_format($lQty, $lQty == (int)$lQty ? 0 : 2) }} {{ $lUom }}
                                                                </td>
                                                                <td class="text-end text-muted">
                                                                    {{ $ccy }} {{ number_format($lPrice, 2) }}
                                                                </td>
                                                                <td class="text-end fw-bold text-success">
                                                                    {{ $ccy }} {{ number_format($lTotal, 2) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
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
                        $mainUom = $contract->lines->first()?->uom_snapshot ?? $contract->lines->first()?->fabricItem?->uom ?? 'units';
                    @endphp
                    <div class="card border rounded-3 mb-3 bg-white shadow-sm overflow-hidden">
                        {{-- Top Header: Status & Contract Info --}}
                        <div class="card-header bg-white border-bottom py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 rounded-pill small fw-semibold">
                                    <i class="fas fa-clock me-1"></i> Awaiting Approval
                                </span>
                                <div class="d-flex align-items-center gap-1">
                                    @if($contract->revision_no > 0)
                                        <span class="badge bg-secondary-subtle text-secondary border">REV {{ $contract->revision_no }}</span>
                                    @endif
                                    <span class="badge bg-light text-dark border fw-bold">{{ $contract->season->name ?? $contract->season->code ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="fw-bold text-primary text-decoration-none fs-6">
                                    {{ $contract->contract_number }}
                                </a>
                                <div class="d-flex gap-1">
                                    @if($contract->deliveryTerm)
                                        <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">{{ $contract->deliveryTerm->code }}</span>
                                    @endif
                                    @if($contract->paymentTerm)
                                        <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">{{ $contract->paymentTerm->code }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                                <i class="fas fa-building text-secondary opacity-75"></i>
                                <strong class="text-dark">{{ $contract->vendor->name ?? '—' }}</strong>
                                @if($contract->vendor?->code)
                                    <span class="badge bg-light text-dark border ms-1" style="font-size: 0.7rem;">{{ $contract->vendor->code }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- KPI Metrics Block: Commercial Total & Submission Meta --}}
                        <div class="p-3 bg-light bg-opacity-50 border-bottom">
                            <div class="row g-2 align-items-center">
                                <div class="col-7">
                                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Commercial Total</div>
                                    <div class="fw-bold text-success fs-5 lh-1 mt-1">{{ $ccy }} {{ number_format($totalVal, 2) }}</div>
                                    <div class="small text-muted mt-1 d-flex align-items-center gap-1 flex-wrap">
                                        <span class="fw-semibold text-dark">{{ number_format($totalQty, $totalQty == (int)$totalQty ? 0 : 2) }} {{ $mainUom }}</span>
                                        <span>&bull;</span>
                                        @if($contract->vatTreatment?->code === 'INCLUDE')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Inc. PPN {{ $contract->vat_rate ?? 11 }}%</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.68rem;">Excl. PPN</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-5 text-end border-start ps-2">
                                    <div class="text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Submitted By</div>
                                    <div class="text-dark fw-semibold small mt-1 text-truncate">{{ $contract->creator?->name ?? 'Fabric Admin' }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $contract->submitted_at ? $contract->submitted_at->timezone('Asia/Jakarta')->format('d M, H:i') : ($contract->created_at ? $contract->created_at->timezone('Asia/Jakarta')->format('d M, H:i') : '—') }} WIB</div>
                                </div>
                            </div>
                        </div>

                        {{-- Line Items Breakdown --}}
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark d-flex align-items-center gap-1">
                                    <i class="fas fa-layer-group text-primary"></i> 
                                    <span>Line Items ({{ $contract->lines->count() }})</span>
                                </span>
                                <button class="btn btn-sm btn-link text-decoration-none p-0 small text-secondary" 
                                        type="button" data-bs-toggle="collapse" data-bs-target="#m-lines-{{ $contract->id }}">
                                    <span class="small">Toggle View</span> <i class="fas fa-chevron-down ms-0.5 small"></i>
                                </button>
                            </div>
                            <div class="collapse show" id="m-lines-{{ $contract->id }}">
                                <div class="d-flex flex-column gap-2">
                                    @foreach($contract->lines as $line)
                                        @php
                                            $lQty = (float) $line->qty;
                                            $lPrice = (float) $line->unit_price;
                                            $lTotal = $lQty * $lPrice;
                                            $lUom = $line->uom_snapshot ?? $line->fabricItem?->uom ?? 'MTR';
                                        @endphp
                                        <div class="border rounded-2 p-2 bg-white shadow-none">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <span class="badge bg-secondary-subtle text-secondary me-1" style="font-size: 0.7rem;">#{{ $line->line_no }}</span>
                                                    <span class="font-monospace fw-bold text-primary small">{{ $line->item_number_snapshot }}</span>
                                                    @if($line->lot_snapshot || $line->lot?->label || $line->lot?->code)
                                                        <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.68rem;">
                                                            LOT {{ $line->lot_snapshot ? str_replace('Lot ', '', $line->lot_snapshot) : ($line->lot?->label ?? $line->lot?->code) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <strong class="text-success small text-nowrap">{{ $ccy }} {{ number_format($lTotal, 2) }}</strong>
                                            </div>
                                            <div class="text-dark small fw-medium mt-1 lh-sm" style="font-size: 0.8rem;">
                                                {{ $line->item_name_snapshot }}
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center text-muted mt-1.5" style="font-size: 0.75rem;">
                                                <span class="text-truncate me-2">
                                                    <i class="fas fa-tag me-1 opacity-50"></i>{{ $line->plmRef?->article_name ?? $line->plm_article_name_snapshot ?? '—' }}
                                                </span>
                                                <span class="text-nowrap fw-semibold text-dark">
                                                    {{ number_format($lQty, $lQty == (int)$lQty ? 0 : 2) }} {{ $lUom }} &times; {{ number_format($lPrice, 2) }}
                                                </span>
                                            </div>
                                            @if($line->resolved_construction || $line->resolved_composition)
                                                <div class="text-muted mt-1 d-flex gap-1 flex-wrap" style="font-size: 0.7rem;">
                                                    @if($line->resolved_composition ?? $line->fabricItem?->composition)
                                                        <span class="badge bg-light text-muted border py-0.5 px-1.5">{{ $line->resolved_composition ?? $line->fabricItem?->composition }}</span>
                                                    @endif
                                                    @if($line->resolved_construction ?? $line->fabricItem?->construction)
                                                        <span class="badge bg-light text-muted border py-0.5 px-1.5">{{ $line->resolved_construction ?? $line->fabricItem?->construction }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($line->remarks)
                                                <div class="text-primary mt-1.5 p-1 px-2 rounded bg-info-subtle border border-info-subtle" style="font-size: 0.72rem;">
                                                    <i class="fas fa-comment-dots me-1"></i><strong>Remark:</strong> {{ $line->remarks }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="p-3 bg-white">
                            <div class="d-flex flex-column gap-2">
                                @if($isPcApprover)
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-success flex-fill py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-1.5" 
                                                onclick="openApproveModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                            <i class="fas fa-check-circle"></i> <span>Approve</span>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger flex-fill py-2 fw-semibold d-flex align-items-center justify-content-center gap-1.5" 
                                                onclick="openRejectModal('{{ $contract->id }}', '{{ $contract->contract_number }}')">
                                            <i class="fas fa-times-circle"></i> <span>Reject…</span>
                                        </button>
                                    </div>
                                @endif
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-outline-primary flex-fill py-1.5 small fw-medium d-flex align-items-center justify-content-center gap-1.5">
                                        <i class="fas fa-eye"></i> <span>View Details</span>
                                    </a>
                                    <a href="{{ route('admin.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-secondary flex-fill py-1.5 small fw-medium d-flex align-items-center justify-content-center gap-1.5">
                                        <i class="fas fa-file-pdf text-danger"></i> <span>PDF Preview</span>
                                    </a>
                                </div>
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
                    <p class="mb-2 text-dark">Confirm your internal approval to finalize the contract, freeze clauses &amp; tolerances, and generate the official PDF:</p>
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
