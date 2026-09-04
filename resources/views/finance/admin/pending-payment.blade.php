@extends('layouts.app')

@section('title', 'Pending Payment')

@php
    $hasFilters = $filters['q'] !== '';
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-hourglass-half me-2"></i>Pending Payment</h4>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">As of {{ now('Asia/Jakarta')->format('d M Y H:i') }} WIB &mdash; {{ $rows->total() }} total</span>
        <a href="{{ route('finance.admin.pending-payment.export', $filters['q'] !== '' ? ['q' => $filters['q']] : []) }}" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-file-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="alert alert-info small">
    <i class="fas fa-circle-info me-1"></i>
    Subcon CMT purchase orders D365 shows as <strong>Received</strong> that have never had an invoice RPA job
    queued — not a status on an existing job, these simply aren't in the invoice queue at all yet.
</div>

<form method="GET" action="{{ route('finance.admin.pending-payment') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="PO or vendor…">
            </div>
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                @if($hasFilters)
                    <a href="{{ route('finance.admin.pending-payment') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
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
                    <th>Item Batch</th>
                    <th>PLM ID</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Amount</th>
                    <th>Received (D365)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->po ?: '-' }}</td>
                        <td>{{ $row->vendor_name ?: '-' }}</td>
                        <td>{{ $row->item_batch ?: '-' }}</td>
                        <td>{{ $row->plm_id ?: '-' }}</td>
                        <td class="text-end">{{ is_numeric($row->qty) ? number_format((float) $row->qty, 0) : '-' }}</td>
                        <td class="text-end">{{ is_numeric($row->amount) ? number_format((float) $row->amount, 2) : '-' }}</td>
                        <td>{{ $row->received_at ? \Illuminate\Support\Carbon::parse($row->received_at)->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No received CMT POs are missing an invoice{{ $hasFilters ? ' matching these filters' : '' }}.</td>
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
