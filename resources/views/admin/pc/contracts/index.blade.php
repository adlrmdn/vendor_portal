@extends('layouts.app')

@section('title', 'Preliminary Contracts')

@section('content')
@include('admin.pc.partials._tokens')
@include('partials.searchable-select')

<div class="container-fluid px-0">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'contracts'])

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Preliminary Contracts</h1>
            <p class="text-muted small mb-0">Search, filter, review and manage fabric preliminary contracts</p>
        </div>
        <a href="{{ route('admin.pc.contracts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Contract
        </a>
    </div>

    {{-- Filter Card --}}
    <div class="card mb-4 border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.pc.contracts.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small fw-semibold text-muted">Search Contract or Vendor</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" 
                                   placeholder="e.g. MPG/PC/PDV/... or vendor name" value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-semibold text-muted">Contract Status</label>
                        <select name="status" class="form-select">
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
                        <label class="form-label small fw-semibold text-muted">Vendor</label>
                        <select name="vendor_id" class="form-select searchable">
                            <option value="">All Fabric Vendors</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}" {{ $vendorId === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        @if ($search || $status || $vendorId)
                            <a href="{{ route('admin.pc.contracts.index') }}" class="btn btn-outline-secondary" title="Clear all filters">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Results Table Card --}}
    <div class="card border">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold text-dark">
                Showing {{ $contracts->total() }} {{ Str::plural('contract', $contracts->total()) }}
            </span>
            <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                @foreach(request()->except(['per_page','page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label class="small text-muted mb-0">Per page:</label>
                <select name="per_page" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                    @foreach([10, 25, 50, 100] as $pp)
                        <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 25%;">Contract Number</th>
                            <th style="width: 25%;">Vendor</th>
                            <th style="width: 10%;" class="text-center">Lines</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 15%;">Created</th>
                            <th class="pe-3 text-end" style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">
                                        {{ $contract->contract_number ?? 'Draft' }}
                                        @if($contract->revision_no > 0)
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">REV {{ $contract->revision_no }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ $contract->season->name ?? $contract->season->code ?? '' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $contract->vendor->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $contract->vendor->code ?? '' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill">{{ $contract->lines_count }}</span>
                                </td>
                                <td>
                                    @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                                </td>
                                <td class="small text-muted">
                                    {{ $contract->created_at ? $contract->created_at->format('d M Y, H:i') : '—' }}
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary">
                                        View <i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 text-secondary opacity-50"></i>
                                    <div>No preliminary contracts match your filter criteria.</div>
                                    @if ($search || $status || $vendorId)
                                        <a href="{{ route('admin.pc.contracts.index') }}" class="btn btn-sm btn-outline-secondary mt-2">
                                            Clear Filters
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($contracts->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-end mb-0">
                    {{ $contracts->links('custom-pagination') }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
