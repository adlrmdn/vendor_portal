@extends('layouts.app')

@section('title', 'Debit Notes')

@php
    $statusColors = ['waiting' => 'secondary', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
    $portalColors = ['Fabric' => 'primary', 'Subcon' => 'warning'];
    $hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['portal'] !== '' || $filters['checked'] !== '';
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-file-circle-minus me-2"></i>Debit Notes</h4>
    <span class="text-muted small">As of {{ now('Asia/Jakarta')->format('d M Y H:i') }} WIB &mdash; {{ $rows->total() }} total</span>
</div>

<form method="GET" action="{{ route('finance.admin.debit-notes') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="PO or vendor…">
            </div>
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="processing" @selected($filters['status'] === 'processing')>Processing RPA</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                    <option value="waiting" @selected($filters['status'] === 'waiting')>Waiting</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Portal</label>
                <select name="portal" class="form-select form-select-sm">
                    <option value="">All portals</option>
                    <option value="Fabric" @selected($filters['portal'] === 'Fabric')>Fabric</option>
                    <option value="Subcon" @selected($filters['portal'] === 'Subcon')>Subcon</option>
                </select>
            </div>
            <div class="col-sm-2">
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
                    <a href="{{ route('finance.admin.debit-notes') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="fas fa-times"></i></a>
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
                    <th>Portal</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Journal No</th>
                    <th>Reason</th>
                    <th>Updated</th>
                    <th>Report</th>
                    <th>Checked</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->po ?: '-' }}</td>
                        <td>{{ $row->vendor_name ?: '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $portalColors[$row->portal] ?? 'secondary' }}-subtle text-{{ $portalColors[$row->portal] ?? 'secondary' }} border">
                                {{ $row->portal }}
                            </span>
                        </td>
                        <td class="text-end">{{ is_numeric($row->amount) ? number_format((float) $row->amount, 2) : '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $statusColors[$row->status] ?? 'secondary' }}">
                                {{ $row->status === 'processing' ? 'Processing RPA' : ucfirst($row->status) }}
                            </span>
                        </td>
                        <td class="small">
                            @if ($row->document_no)
                                <span class="font-monospace">{{ $row->document_no }}</span>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
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
                                <a href="{{ route('finance.admin.report', ['rpaType' => $rpaType, 'id' => $row->id]) }}"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-pdf me-1"></i>View
                                </a>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm {{ $row->checked ? 'btn-success' : 'btn-outline-secondary' }} finance-check-toggle"
                                data-rpa-id="{{ $row->id }}" data-rpa-type="{{ $rpaType }}" data-status="{{ $row->status }}">
                                <i class="fas {{ $row->checked ? 'fa-check' : 'fa-circle' }} me-1"></i>
                                <span class="check-btn-label">{{ $row->checked ? 'Checked' : 'Mark Checked' }}</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">No debit notes match{{ $hasFilters ? ' these filters' : '' }}.</td>
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

@push('scripts')
<script>
    (function () {
        const toggleUrlBase = "{{ url('/finance/admin/checks') }}";

        document.querySelectorAll('.finance-check-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = this.dataset.rpaId;
                const rpaType = this.dataset.rpaType;
                const status = this.dataset.status;
                const isForce = status !== 'completed';

                if (isForce && !confirm(
                    'This job hasn\'t completed in D365 yet (still "' + status + '").\n\n' +
                    'Marking it checked will also force its status to Completed, bypassing the RPA automation. ' +
                    'Only do this if you\'ve verified it another way. Continue?'
                )) {
                    return;
                }

                const label = this.querySelector('.check-btn-label');
                const icon = this.querySelector('i');
                this.disabled = true;

                fetch(`${toggleUrlBase}/${id}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ rpa_type: rpaType, force: isForce }),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.forced_complete) {
                            window.location.reload();
                            return;
                        }
                        if (data.checked) {
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-success');
                            icon.classList.remove('fa-circle');
                            icon.classList.add('fa-check');
                            label.textContent = 'Checked';
                        } else {
                            this.classList.remove('btn-success');
                            this.classList.add('btn-outline-secondary');
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-circle');
                            label.textContent = 'Mark Checked';
                        }
                    })
                    .catch(() => alert('Failed to update. Please try again.'))
                    .finally(() => { this.disabled = false; });
            });
        });
    })();
</script>
@endpush
