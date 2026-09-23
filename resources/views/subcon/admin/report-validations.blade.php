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
    'deductionOnly' => $deductionOnly,
])

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 22%;">Work Order</th>
                        <th style="width: 18%;">MD Production</th>
                        <th style="width: 12%;">Deduction</th>
                        <th style="width: 24%;">Status</th>
                        <th class="text-end pe-4" style="width: 300px;">Action</th>
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
                                    {{-- Deferred — see SubconAdminController::pendingValidateSends()'s
                                         docblock: this column alone was confirmed live to cost
                                         ~160ms/row of qms/D365 round trips, so the list renders
                                         without it and report-validations-scripts.blade.php below
                                         fills it in via one background fetch right after. --}}
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
                                    @if(!empty($p['director_rejected']))
                                        <div class="mt-1">
                                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">
                                                <i class="fas fa-triangle-exclamation me-1"></i> Director Rejected
                                            </span>
                                        </div>
                                    @endif
                                @endunless
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    @if($isOfficialReport)
                                        {{-- Opens the signed confirm page (hoApproveSigned) where
                                             MD Production can actually edit additional_deduction/
                                             remarks before resending — same signed-link pattern as
                                             the email flow, just generated in-app instead of mailed. --}}
                                        <a href="{{ $p['action_url'] }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
                                            <i class="fas fa-rotate me-1"></i> Revise &amp; Resend
                                        </a>
                                    @else
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
                                    No pending validations match "{{ $search }}".
                                @elseif($deductionOnly)
                                    No pending validations carry a deduction right now.
                                @else
                                    Nothing awaiting validation. You're all caught up.
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@unless($deductionOnly)
    {{-- Deduction cells were left as placeholders above (see
         SubconAdminController::pendingValidateSends()'s docblock) — one
         background fetch fills them all in right after the list itself has
         already rendered. Same "server renders, JS swaps a fragment" shape
         as daily-production-scripts.blade.php's dpFetchAndSwapStyleCard(). --}}
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
