@extends('layouts.app')

@section('title', 'Director Authorizations')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Director Authorizations</h2>
        <small class="text-muted">
            @if($isDirector)
                Inspections signed by MD Production awaiting your final authorization
            @else
                Inspections signed by MD Production awaiting the Director's final authorization — view only
            @endif
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.approval-logs', ['gate' => 'director']) }}" class="btn btn-outline-secondary">
            <i class="fas fa-clock-rotate-left me-1"></i> Decision Log
        </a>
    </div>
</div>

@include('subcon.partials.table-search', [
    'route' => route('subcon.admin.director-approvals'),
    'search' => $search,
    'placeholder' => 'Search work order, vendor, style, production group…',
])

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Work Order</th>
                        <th>Vendor</th>
                        <th>Inspection</th>
                        <th>MD Production</th>
                        <th>Deduction</th>
                        <th class="text-end pe-4" style="width: 360px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $p)
                        <tr>
                            <td>
                                @if($p['order_id'])
                                    <a href="{{ route('subcon.admin.orders.view', $p['order_id']) }}" class="fw-semibold text-decoration-none font-monospace">{{ $p['order_number'] }}</a>
                                @else
                                    <span class="fw-semibold font-monospace">{{ $p['order_number'] }}</span>
                                @endif
                                @if($p['style'])<div class="text-muted small">{{ $p['style'] }}</div>@endif
                            </td>
                            <td>{{ $p['vendor'] }}</td>
                            <td>
                                @if($p['version'])
                                    <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25">{{ $p['version'] }}</span>
                                @endif
                                @if($p['result'])
                                    <span class="badge {{ strtoupper($p['result']) === 'PASSED' ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger' }} border border-opacity-25">{{ strtoupper($p['result']) }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $p['ho_signature'] ?: '—' }}</td>
                            <td>
                                @if(($p['deduction_total'] ?? 0) > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">
                                        <i class="fas fa-circle-minus me-1"></i> Rp {{ number_format($p['deduction_total'], 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25">
                                        <i class="fas fa-circle-check me-1"></i> None
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    {{-- Signed inspection report as it currently stands (QC + Factory
                                         Rep + MD Production signatures) — the email attachment. --}}
                                    <a href="{{ route('qc.document', ['token' => $p['token']]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf me-1"></i> Report
                                    </a>
                                    @if($isDirector)
                                        {{-- Opens the existing token-based Director form (read-only summary
                                             + Authorize & Sign) — same workflow as the email link. --}}
                                        <a href="{{ route('qc.director-approve', ['token' => $p['token']]) }}" class="btn btn-sm btn-success" target="_blank" rel="noopener">
                                            <i class="fas fa-stamp me-1"></i> Review &amp; Authorize
                                        </a>
                                        <a href="{{ route('qc.director-decline', ['token' => $p['token']]) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if(empty($pending))
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                {{ $search !== '' ? 'No pending authorizations match "'.$search.'".' : 'Nothing awaiting your authorization.' }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($recentDecisions->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header bg-transparent">
            <span class="fw-semibold"><i class="fas fa-clock-rotate-left me-1"></i> Recent Director decisions</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Work Order</th>
                            <th>Vendor</th>
                            <th>Decision</th>
                            <th>Note</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDecisions as $log)
                            <tr>
                                <td class="font-monospace">{{ $log->order_number ?? '—' }}</td>
                                <td>{{ $log->vendor_name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $log->decision === 'approved' ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger' }} border border-opacity-25">
                                        {{ $log->decision === 'approved' ? 'Authorized' : 'Rejected' }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $log->note ?? '—' }}</td>
                                <td class="small text-muted">{{ $log->created_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
