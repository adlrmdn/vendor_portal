@extends('layouts.app')

@section('title', 'Report Validations')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Report Validations</h2>
        <small class="text-muted">Inspections approved by MD Production (RAF run queued) awaiting Validate &amp; Send to the Director</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.approval-logs', ['gate' => 'final']) }}" class="btn btn-outline-secondary">
            <i class="fas fa-clock-rotate-left me-1"></i> Decision Log
        </a>
    </div>
</div>

@include('subcon.partials.table-search', [
    'route' => route('subcon.admin.report-validations'),
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
                        <th>RAF Run</th>
                        <th>MD Production</th>
                        <th>Deduction</th>
                        <th>Director</th>
                        <th class="text-end pe-4" style="width: 320px;">Action</th>
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
                            <td>
                                @php $raf = strtolower((string) ($p['raf_status'] ?? '')); @endphp
                                @if($raf === 'completed')
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25"><i class="fas fa-robot me-1"></i>Completed</span>
                                @elseif(in_array($raf, ['pending', 'processing'], true))
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25"><i class="fas fa-robot me-1"></i>{{ ucfirst($raf) }}</span>
                                @elseif($raf === 'failed')
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25"><i class="fas fa-robot me-1"></i>Failed</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25">Unknown</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $p['ho_signature'] ?: '—' }}
                                @if(($p['material_return_status'] ?? null) === 'pending')
                                    <div><span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25"><i class="fas fa-truck-ramp-box me-1"></i>Material Flow pending</span></div>
                                @elseif(($p['material_return_status'] ?? null) === 'checked')
                                    <div><span class="badge bg-success-subtle text-success border border-success border-opacity-25"><i class="fas fa-truck-ramp-box me-1"></i>Material Flow checked</span></div>
                                @endif
                            </td>
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
                            <td>
                                @if(!empty($p['director_rejected']))
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">
                                        <i class="fas fa-triangle-exclamation me-1"></i> Rejected
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    {{-- The inspection report as it currently stands — rendered
                                         fresh from current data on every open. --}}
                                    <a href="{{ route('qc.document', ['token' => $p['token']]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf me-1"></i> Report
                                    </a>
                                    {{-- Opens the token-based form in validate mode (read-only
                                         numbers + Validate & Send Approval) — same as the email link. --}}
                                    <a href="{{ route('qc.ho-approve', ['token' => $p['token']]) }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
                                        <i class="fas fa-paper-plane me-1"></i> Validate &amp; Send
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if(empty($pending))
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                {{ $search !== '' ? 'No pending validations match "'.$search.'".' : "Nothing awaiting validation. You're all caught up." }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
