@extends('layouts.app')

@section('title', 'Fabric Delivery')

@php
    $hasFilters = $filters['q'] !== '' || $filters['type'] !== '';
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-truck-ramp-box me-2"></i>Fabric Delivery</h4>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">As of {{ now('Asia/Jakarta')->format('d M Y H:i') }} WIB &mdash; {{ $rows->total() }} total</span>
        <a href="{{ route('finance.admin.fabric-delivery.export', array_filter($filters)) }}" class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-excel me-1"></i>Export Excel
        </a>
    </div>
</div>

<div class="alert alert-info small">
    <i class="fas fa-circle-info me-1"></i>
    Completed fabric PO lines that shipped outside their <strong>original</strong> delivery tolerance (the
    under/over-delivery % in effect before any approved tolerance amendment). A PO line shipped across multiple
    partial shipments is counted once, as a whole — a line still mid-shipment isn't shown yet.
</div>

<form method="GET" action="{{ route('finance.admin.fabric-delivery') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="PO, vendor or style…">
            </div>
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="UNDER" @selected($filters['type'] === 'UNDER')>Under-delivered</option>
                    <option value="OVER" @selected($filters['type'] === 'OVER')>Over-delivered</option>
                </select>
            </div>
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                @if($hasFilters)
                    <a href="{{ route('finance.admin.fabric-delivery') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>PO</th>
                    <th>Vendor</th>
                    <th>Style</th>
                    <th>Item #</th>
                    <th class="text-end">Ordered</th>
                    <th class="text-end">Delivered</th>
                    <th class="text-end">Diff %</th>
                    <th class="text-end">Tolerance</th>
                    <th>Type</th>
                    <th class="text-center">Split?</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->po_number ?: '-' }}</td>
                        <td>{{ $row->vendor_name ?: '-' }}</td>
                        <td>{{ $row->style ?: '-' }}</td>
                        <td>{{ $row->item_number ?: '-' }}</td>
                        <td class="text-end">{{ number_format($row->ordered, 2) }} {{ $row->unit }}</td>
                        <td class="text-end">{{ number_format($row->delivered, 2) }} {{ $row->unit }}</td>
                        <td class="text-end {{ $row->type === 'OVER' ? 'text-success' : 'text-danger' }}">
                            {{ $row->pct_vs_ordered !== null ? ($row->pct_vs_ordered > 0 ? '+' : '').number_format($row->pct_vs_ordered, 2).'%' : '-' }}
                        </td>
                        <td class="text-end small text-muted" title="Min {{ number_format($row->min_allowed, 2) }} / Max {{ number_format($row->max_allowed, 2) }}">
                            -{{ number_format($row->orig_underdelivery_pct, 2) }}% / +{{ number_format($row->orig_overdelivery_pct, 2) }}%
                            @if($row->tolerance_was_amended)
                                <i class="fas fa-triangle-exclamation text-warning ms-1" title="Tolerance was later amended — this line's current tolerance may differ."></i>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $row->type === 'OVER' ? 'success' : 'danger' }}-subtle text-{{ $row->type === 'OVER' ? 'success' : 'danger' }} border">
                                {{ ucfirst(strtolower($row->type)) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($row->was_split_shipment)
                                <i class="fas fa-check text-muted" title="{{ $row->shipments }} shipments"></i>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">No fabric PO lines fall outside their original tolerance{{ $hasFilters ? ' matching these filters' : '' }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())
        <div class="card-footer bg-white border-top-0 py-3">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
