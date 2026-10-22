@extends('layouts.app')

@section('title', 'PC Contracts')

@section('content')
@include('admin.pc.partials._tokens')
@include('partials.searchable-select')

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'contracts'])

    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-4">
        <h1 class="pc-h1">Preliminary Contracts</h1>
        <a href="{{ route('admin.pc.contracts.create') }}" class="btn pc-btn-primary">New Contract</a>
    </div>

    <form method="GET" action="{{ route('admin.pc.contracts.index') }}" class="row g-3 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Contract number or vendor name" value="{{ $search }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select searchable">
                <option value="">All status</option>
                @foreach (['draft','pending_vendor_review','pending_final_approval','confirmed','cancelled','superseded'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="vendor_id" class="form-select searchable">
                <option value="">All vendors</option>
                @foreach ($vendors as $v)
                    <option value="{{ $v->id }}" {{ $vendorId === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-secondary flex-grow-1">Filter</button>
            @if ($search || $status || $vendorId)
                <a href="{{ route('admin.pc.contracts.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
            @endif
        </div>
    </form>

    <div class="d-flex justify-content-end mb-2">
        <form method="GET" class="d-flex align-items-center gap-2">
            @foreach(request()->except(['per_page','page']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <label class="pc-meta mb-0">Per page</label>
            <select name="per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                @foreach([10,25,50] as $pp)
                    <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="pc-card">
        <table class="pc-table mb-0">
            <thead>
                <tr><th>Contract</th><th>Vendor</th><th>Lines</th><th>Status</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    <tr>
                        <td class="pc-mono">{{ $contract->contract_number ?? 'Draft' }}@if($contract->revision_no > 0)<span class="pc-meta"> rev {{ $contract->revision_no }}</span>@endif</td>
                        <td>{{ $contract->vendor->name ?? '—' }}</td>
                        <td class="pc-meta">{{ $contract->lines_count }}</td>
                        <td>@include('admin.pc.partials.status-pill', ['status' => $contract->status])</td>
                        <td class="pc-meta">{{ $contract->created_at->format('d M Y') }}</td>
                        <td class="text-end"><a href="{{ route('admin.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                @endforeach
                @if ($contracts->isEmpty())
                    <tr><td colspan="6" class="text-center pc-meta py-4">No contracts found.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $contracts->links('custom-pagination') }}</div>
</div>
@endsection
