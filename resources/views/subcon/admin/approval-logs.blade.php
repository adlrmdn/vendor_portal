@extends('layouts.app')

@section('title', 'Approval Log')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Approval Log</h2>
        <small class="text-muted">Every cutting, gramasi &amp; final (HO) approve / reject decision, newest first</small>
    </div>
    <a href="{{ route('subcon.admin.approvals') }}" class="btn btn-outline-secondary">
        <i class="fas fa-clipboard-check me-1"></i> Pending Approvals
    </a>
</div>

<form method="GET" action="{{ route('subcon.admin.approval-logs') }}" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Work Order</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search order number…">
            </div>
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Gate</label>
                <select name="gate" class="form-select form-select-sm">
                    <option value="">All gates</option>
                    <option value="cutting" @selected($gate === 'cutting')>Cutting</option>
                    <option value="gramasi" @selected($gate === 'gramasi')>Gramasi</option>
                    <option value="final" @selected($gate === 'final')>Final (HO)</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Decision</label>
                <select name="decision" class="form-select form-select-sm">
                    <option value="">All decisions</option>
                    <option value="approved" @selected($decision === 'approved')>Approved</option>
                    <option value="declined" @selected($decision === 'declined')>Rejected</option>
                </select>
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                @if($q || $gate || $decision)
                    <a href="{{ route('subcon.admin.approval-logs') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Work Order</th>
                        <th>Vendor</th>
                        <th>Gate</th>
                        <th>Decision</th>
                        <th>By</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="small text-nowrap">
                                {{ $log->created_at?->format('d M Y H:i') }}
                                <div class="text-muted">{{ $log->created_at?->diffForHumans() }}</div>
                            </td>
                            <td>
                                @if($log->order_id)
                                    <a href="{{ route('subcon.admin.orders.view', $log->order_id) }}" class="fw-semibold text-decoration-none font-monospace">{{ $log->order_number }}</a>
                                @else
                                    <span class="fw-semibold font-monospace">{{ $log->order_number ?? '—' }}</span>
                                @endif
                            </td>
                            <td>{{ $log->vendor_name ?? '—' }}</td>
                            <td>
                                @php $gc = ['cutting' => 'warning', 'gramasi' => 'info', 'final' => 'primary'][$log->gate] ?? 'secondary'; @endphp
                                <span class="badge bg-{{ $gc }}-subtle text-{{ $gc }} border border-{{ $gc }} border-opacity-25">
                                    {{ $log->gate === 'final' ? 'Final (HO)' : ucfirst($log->gate) }}
                                </span>
                            </td>
                            <td>
                                @if($log->decision === 'approved')
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25"><i class="fas fa-check me-1"></i>Approved</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25"><i class="fas fa-times me-1"></i>Rejected</span>
                                @endif
                            </td>
                            <td class="small">{{ $log->actor ?? '—' }}</td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25">
                                    {{ $log->source === 'email' ? 'Email link' : 'In-app' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach

                    @if($logs->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-clock-rotate-left fa-2x mb-3 opacity-50 d-block"></i>
                                No approval decisions logged{{ ($q || $gate || $decision) ? ' for this filter' : ' yet' }}.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($logs->hasPages())
    <div class="mt-3">{{ $logs->links() }}</div>
@endif
@endsection
