@extends('layouts.app')

@section('title', 'Preliminary Contracts')

@section('content')
@include('admin.pc.partials._tokens')
@include('partials.searchable-select')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'contracts'])

    <!-- Header with Eyebrow -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Fabric Administration</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Legal &amp; Procurement</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <h1 class="apple-page-title mb-0">Preliminary Contracts</h1>
                <span class="apple-count-pill">
                    <span class="apple-count-num">{{ $contracts->total() }}</span>
                    <span>contracts</span>
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.pc.guidebook.pdf') }}" class="apple-btn-secondary" target="_blank" title="Download Admin Operating & Workflow Guidebook PDF">
                <i class="fas fa-file-pdf me-1 text-danger"></i>Admin Guidebook (PDF)
            </a>
            <a href="{{ route('admin.pc.contracts.create') }}" class="apple-btn-primary">
                <i class="fas fa-plus me-2"></i>New Contract
            </a>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="apple-card apple-filter-panel mb-4">
        <form method="GET" action="{{ route('admin.pc.contracts.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="apple-field-label">Search Contract or Vendor</label>
                    <div class="apple-search-container">
                        <i class="fas fa-search apple-search-icon" style="left: 1rem;"></i>
                        <input type="text"
                               name="search"
                               class="apple-search-input"
                               style="height: 38px; padding-left: 2.6rem; padding-right: 1rem;"
                               placeholder="e.g. MPG/PC/PDV/... or vendor name"
                               value="{{ $search }}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="apple-field-label">Contract Status</label>
                    <select name="status" class="form-select apple-select">
                        <option value="">All Statuses</option>
                        @php
                            $statuses = [
                                \App\Models\PcContract::STATUS_DRAFT => 'Draft',
                                \App\Models\PcContract::STATUS_PENDING_APPROVAL => 'Awaiting Approval',
                                \App\Models\PcContract::STATUS_APPROVED => 'Approved (Ready to Send)',
                                \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER => 'With Vendor',
                                \App\Models\PcContract::STATUS_CONFIRMED => 'Confirmed',
                                \App\Models\PcContract::STATUS_SUPPLIER_QUERIED => 'Vendor Queried',
                                \App\Models\PcContract::STATUS_CANCELLED => 'Cancelled',
                                \App\Models\PcContract::STATUS_SUPERSEDED => 'Superseded (Revised)',
                            ];
                        @endphp
                        @foreach ($statuses as $val => $lbl)
                            <option value="{{ $val }}" {{ $status === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="apple-field-label">Vendor</label>
                    <select name="vendor_id" class="form-select searchable">
                        <option value="">All Fabric Vendors</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" {{ $vendorId === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="apple-btn-primary flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    @if ($search || $status || $vendorId)
                        <a href="{{ route('admin.pc.contracts.index') }}" class="apple-btn-secondary" title="Clear all filters">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Results Table Card --}}
    <div class="apple-card apple-table-container mb-4">
        <div class="apple-card-header">
            <div>
                <h5 class="apple-card-title mb-1">
                    <i class="fas fa-file-contract text-primary"></i>
                    <span>Contracts Register</span>
                </h5>
                <span class="small text-muted">Showing {{ $contracts->firstItem() ?? 0 }} – {{ $contracts->lastItem() ?? 0 }} of {{ $contracts->total() }} contracts</span>
            </div>
            <form method="GET" class="d-flex align-items-center mb-0">
                @foreach(request()->except(['per_page','page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <div class="apple-per-page-container">
                    <span class="apple-per-page-label">Per page:</span>
                    <select name="per_page" class="apple-per-page-select" onchange="this.form.submit()">
                        @foreach([10, 25, 50, 100] as $pp)
                            <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 25%;">Contract Details</th>
                        <th style="width: 25%;">Vendor</th>
                        <th style="width: 12%;" class="text-center">Lines</th>
                        <th style="width: 15%; text-align: center;">Status</th>
                        <th style="width: 15%;">Created</th>
                        <th class="pe-3 text-end" style="width: 8%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr class="apple-table-row">
                            <!-- Contract Details -->
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('admin.pc.contracts.show', $contract) }}"
                                           class="apple-po-number"
                                           title="View contract details">
                                            {{ $contract->contract_number ?? 'Draft' }}
                                        </a>
                                        <button type="button"
                                                class="apple-copy-btn"
                                                onclick="copyPoNumber('{{ $contract->contract_number }}', this)"
                                                title="Copy Contract Number">
                                            <i class="far fa-copy"></i>
                                        </button>
                                        @if($contract->revision_no > 0)
                                            <span class="badge bg-secondary-subtle text-secondary small px-2 py-0" style="font-size: 0.72rem; border-radius: 4px;">
                                                REV {{ $contract->revision_no }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ $contract->season->name ?? $contract->season->code ?? '—' }}
                                    </div>
                                </div>
                            </td>

                            <!-- Vendor -->
                            <td>
                                <div class="fw-semibold text-dark">{{ $contract->vendor->name ?? '—' }}</div>
                                <div class="small text-muted">{{ $contract->vendor->code ?? '' }}</div>
                            </td>

                            <!-- Lines -->
                            <td class="text-center">
                                <span class="apple-count-pill" style="padding: 2px 8px; font-size: 0.75rem;">
                                    {{ $contract->lines_count }} lines
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                            </td>

                            <!-- Created -->
                            <td>
                                <div class="small fw-semibold text-dark">{{ $contract->created_at ? $contract->created_at->format('d M Y') : '—' }}</div>
                                <div class="small text-muted" style="font-size: 0.75rem;">{{ $contract->created_at ? $contract->created_at->format('H:i') : '' }}</div>
                            </td>

                            <!-- Action -->
                            <td class="pe-3 text-end">
                                <a href="{{ route('admin.pc.contracts.show', $contract) }}"
                                   class="apple-action-btn apple-action-view"
                                   title="View Contract">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
                                <div class="apple-empty-state">
                                    <div class="apple-empty-icon-circle mb-3">
                                        <i class="fas fa-search fa-2x"></i>
                                    </div>
                                    <h4 class="apple-empty-title">No Contracts Found</h4>
                                    <p class="apple-empty-desc">
                                        No preliminary contracts match your filter criteria.
                                    </p>
                                    @if ($search || $status || $vendorId)
                                        <a href="{{ route('admin.pc.contracts.index') }}" class="apple-btn-secondary mt-2">
                                            Clear Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
            <div class="p-3 border-top d-flex justify-content-end" style="border-color: #ebebf0 !important;">
                {{ $contracts->links('custom-pagination') }}
            </div>
        @endif
    </div>
</div>

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">Contract number copied</span>
</div>

@include('partials.apple-theme')
@endsection
