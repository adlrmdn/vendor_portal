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
                        @php $isOfficialReport = ($p['type'] ?? null) === 'official_report'; @endphp
                        <tr data-token="{{ $p['token'] }}">
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
                                @if($isOfficialReport)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle mt-1">Add Process</span>
                                @endif
                            </td>
                            <td class="small text-muted" style="word-break: break-word;">{{ $p['ho_signature'] ?: '—' }}</td>
                            <td data-deduction-cell>
                                @if($isOfficialReport)
                                    Rp {{ number_format($p['deduction_total'], 0, ',', '.') }}
                                @elseif($p['deduction_total'] === null)
                                    {{-- Deferred — see SubconAdminController::pendingDirectorApprovals()'s
                                         docblock; filled in by the background fetch below. --}}
                                    <span class="placeholder-glow"><span class="placeholder col-8"></span></span>
                                @else
                                    @include('subcon.partials.report-validation-deduction', ['total' => $p['deduction_total'], 'breakdown' => $p['deduction_breakdown']])
                                @endif
                            </td>
                            <td>
                                @if($p['result'])
                                    <div>
                                        <span class="badge {{ strtoupper($p['result']) === 'PASSED' ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger' }} border border-opacity-25">{{ strtoupper($p['result']) }}</span>
                                    </div>
                                @endif
                                @unless($isOfficialReport)
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
                                @endunless
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    @unless($isOfficialReport)
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
                                    @endunless
                                    @if($isDirector)
                                        @if($isOfficialReport)
                                            {{-- Signed GET confirm pages built in Task 9 — Authorize opens
                                                 a read-only review + Authorize button, Reject opens a
                                                 reason-entry form. Same in-app-generated signed-URL
                                                 pattern as Report Validation's "Revise & Resend" link. --}}
                                            <a href="{{ $p['authorize_url'] }}" class="btn btn-sm btn-success" target="_blank" rel="noopener">
                                                <i class="fas fa-stamp me-1"></i> Review &amp; Authorize
                                            </a>
                                            <a href="{{ $p['reject_url'] }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </a>
                                        @else
                                            {{-- Opens the existing token-based Director form (read-only summary
                                                 + Authorize & Sign) — same workflow as the email link. --}}
                                            <a href="{{ route('qc.director-approve', ['token' => $p['token']]) }}" class="btn btn-sm btn-success" target="_blank" rel="noopener">
                                                <i class="fas fa-stamp me-1"></i> Review &amp; Authorize
                                            </a>
                                            <a href="{{ route('qc.director-decline', ['token' => $p['token']]) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if($pending->isEmpty())
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

<div class="mt-3">{{ $pending->links() }}</div>

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

@unless($deductionOnly)
    {{-- Same lazy-load fetch as report-validations.blade.php — see
         SubconAdminController::reportValidationDeductions()'s docblock
         (shared by both tabs). --}}
    <script>
        (function () {
            const cells = document.querySelectorAll('[data-token] [data-deduction-cell]');
            if (! cells.length) {
                return;
            }
            const tokens = Array.from(document.querySelectorAll('tr[data-token]')).map(row => row.dataset.token);
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(@json(route('subcon.admin.report-validations.deductions')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({tokens: tokens}),
            })
                .then(r => r.ok ? r.json() : {})
                .then(html => {
                    Object.keys(html).forEach(token => {
                        const row = document.querySelector('tr[data-token="' + CSS.escape(token) + '"]');
                        const cell = row ? row.querySelector('[data-deduction-cell]') : null;
                        if (cell) {
                            cell.innerHTML = html[token];
                        }
                    });
                })
                .catch(() => { /* placeholders just stay put — a manual reload retries */ });
        })();
    </script>
@endunless
@endsection
