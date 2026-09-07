@extends('layouts.app')

@section('title', 'Invoices')

@php
    $statusColors = ['waiting' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
    $hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['checked'] !== '';
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Invoices</h4>
    <span class="text-muted small">As of {{ now('Asia/Jakarta')->format('d M Y H:i') }} WIB &mdash; {{ $rows->total() }} total</span>
</div>

<form method="GET" action="{{ route('subcon.admin.invoices') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="PO or vendor…">
            </div>
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="waiting" @selected($filters['status'] === 'waiting')>Waiting</option>
                    <option value="processing" @selected($filters['status'] === 'processing')>Processing RPA</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Checked</label>
                <select name="checked" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" @selected($filters['checked'] === '1')>Checked</option>
                    <option value="0" @selected($filters['checked'] === '0')>Unchecked</option>
                </select>
            </div>
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                @if($hasFilters)
                    <a href="{{ route('subcon.admin.invoices') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
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
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Updated</th>
                    <th>Report</th>
                    <th>Checked</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @if ($row->po && $row->work_order_id)
                                <a href="{{ route('subcon.admin.orders.view', $row->work_order_id) }}">{{ $row->po }}</a>
                            @else
                                {{ $row->po ?: '-' }}
                            @endif
                        </td>
                        <td>{{ $row->vendor_name ?: '-' }}</td>
                        <td>{{ $row->style ?: '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $statusColors[$row->status] ?? 'secondary' }}">
                                {{ $row->status === 'processing' ? 'Processing RPA' : ucfirst($row->status) }}
                            </span>
                        </td>
                        <td class="small text-danger" style="max-width: 320px;">
                            @if ($row->reason)
                                <span title="{{ $row->reason }}">{{ \Illuminate\Support\Str::limit($row->reason, 100) }}</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                        <td>
                            @if ($row->has_report)
                                <a href="{{ route('subcon.admin.invoices.report', $row->id) }}"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-pdf me-1"></i>View
                                </a>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            @if ($row->checked)
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Checked</span>
                            @else
                                <span class="badge bg-light text-muted border">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">No invoices match{{ $hasFilters ? ' these filters' : '' }}.</td>
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
