@extends('layouts.app')

@section('title', 'Director Authorizations')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
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
    'deductionOnly' => $deductionOnly,
])

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 22%;">Work Order</th>
                        <th style="width: 16%;">MD Production</th>
                        <th style="width: 12%;">Deduction</th>
                        <th style="width: 24%;">Status</th>
                        <th class="text-end pe-4" style="width: 320px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $p)
                        <tr>
                            <td style="word-break: break-word;">
                                @if($p['order_id'])
                                    <a href="{{ route('subcon.admin.orders.view', $p['order_id']) }}" class="fw-semibold text-primary font-monospace text-decoration-none">{{ $p['order_number'] }}</a>
                                @else
                                    <span class="fw-semibold text-primary font-monospace">{{ $p['order_number'] }}</span>
                                @endif
                                @if(! empty($p['production_group']))
                                    <div class="small">
                                        @if($p['order_id'])
                                            <a href="{{ route('subcon.admin.orders.view', $p['order_id']) }}" class="fw-semibold text-primary font-monospace text-decoration-none">{{ $p['production_group'] }}</a>
                                        @else
                                            <span class="fw-semibold text-primary font-monospace">{{ $p['production_group'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($p['style'])<div class="text-muted small">{{ $p['style'] }}</div>@endif
                                <div class="text-muted small">{{ $p['vendor'] }}</div>
                            </td>
                            <td class="small text-muted" style="word-break: break-word;">{{ $p['ho_signature'] ?: '—' }}</td>
                            <td>
                                @if(($p['deduction_total'] ?? 0) > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">
                                        <i class="fas fa-circle-minus me-1"></i> Rp {{ number_format($p['deduction_total'], 0, ',', '.') }}
                                    </span>
                                    @php $d = $p['deduction_breakdown'] ?? []; @endphp
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @if(($d['fabric'] ?? 0) > 0)<span class="badge bg-light text-dark border">Fabric Cons</span>@endif
                                        @if(($d['rejectProduksiPenalty'] ?? 0) > 0)<span class="badge bg-light text-dark border">Production</span>@endif
                                        @if(($d['barangHilangPenalty'] ?? 0) > 0)<span class="badge bg-light text-dark border">Barang Hilang</span>@endif
                                        @if(($d['accessoryClaimTotal'] ?? 0) > 0)<span class="badge bg-light text-dark border">Retur Material</span>@endif
                                        @if(($d['manual'] ?? 0) > 0)<span class="badge bg-light text-dark border">Manual</span>@endif
                                    </div>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25">
                                        <i class="fas fa-circle-check me-1"></i> None
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($p['result'])
                                    <div>
                                        <span class="badge {{ strtoupper($p['result']) === 'PASSED' ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger' }} border border-opacity-25">{{ strtoupper($p['result']) }}</span>
                                    </div>
                                @endif
                                @php $raf = strtolower((string) ($p['raf_status'] ?? '')); @endphp
                                <div class="mt-1">
                                    @if($raf === 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25"><i class="fas fa-robot me-1"></i>RAF Completed</span>
                                    @elseif(in_array($raf, ['pending', 'processing'], true))
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25"><i class="fas fa-robot me-1"></i>RAF {{ ucfirst($raf) }}</span>
                                    @elseif($raf === 'failed')
                                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25"><i class="fas fa-robot me-1"></i>RAF Failed</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25">RAF Unknown</span>
                                    @endif
                                </div>
                                <div class="mt-1">
                                    @if(($p['material_return_status'] ?? null) === 'pending')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25"><i class="fas fa-truck-ramp-box me-1"></i>Material Flow Pending</span>
                                    @elseif(($p['material_return_status'] ?? null) === 'auto_approved')
                                        <span class="badge bg-info-subtle text-info border border-info border-opacity-25" title="Admin override — inventory has not actually checked this order."><i class="fas fa-truck-ramp-box me-1"></i>Material Flow Auto-Approved</span>
                                    @elseif(($p['material_return_status'] ?? null) === 'checked')
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25"><i class="fas fa-truck-ramp-box me-1"></i>Material Flow Checked</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    {{-- Signed inspection report as it currently stands (QC + Factory
                                         Rep + MD Production signatures) — the email attachment. --}}
                                    <a href="{{ route('qc.document', ['token' => $p['token']]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf me-1"></i> Report
                                    </a>
                                    {{-- Pulls the row back to Report Validation without a rejection
                                         reason or email — a quiet correction path open to any subcon
                                         admin, not just the Director (see recallToReportValidation). --}}
                                    <form action="{{ route('subcon.admin.director-approvals.recall', ['token' => $p['token']]) }}" method="POST" class="d-inline" onsubmit="return confirm('Recall {{ $p['order_number'] }} back to Report Validation? No email will be sent — this just re-opens Validate &amp; Send.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-rotate-left me-1"></i> Recall
                                        </button>
                                    </form>
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
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                @if($search !== '')
                                    No pending authorizations match "{{ $search }}".
                                @elseif($deductionOnly)
                                    No pending authorizations carry a deduction right now.
                                @else
                                    Nothing awaiting your authorization.
                                @endif
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
                                    @if($log->decision === 'approved')
                                        <span class="badge bg-success-subtle text-success border-success border border-opacity-25">Authorized</span>
                                    @elseif($log->decision === 'recalled')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border-warning border border-opacity-25">Recalled</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border-danger border border-opacity-25">Rejected</span>
                                    @endif
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
