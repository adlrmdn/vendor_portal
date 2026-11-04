{{-- PC Audit Log tab-pane content --}}
<div class="apple-card mb-4">
    <div class="apple-card-header">
        <div class="apple-card-title">
            <i class="fas fa-clipboard-list text-info"></i>
            <span>PC Audit Log</span>
        </div>
        <span class="small text-muted">Immutable trail of contract submissions, approvals, revisions, and cancellations</span>
    </div>
    <div class="table-responsive">
        <table class="table apple-table mb-0">
            <thead>
                <tr>
                    <th class="ps-3">When</th>
                    <th>Actor</th>
                    <th>Role</th>
                    <th>Event</th>
                    <th>Entity</th>
                    <th class="pe-3">Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="apple-table-row">
                        <td class="ps-3 fw-semibold">{{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                        <td class="text-dark">{{ $log->actor }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $log->actor_role }}</span></td>
                        <td><span class="apple-pill-status pill-neutral">{{ $log->event_type }}</span></td>
                        <td class="fw-medium text-dark">{{ $log->entity_label }}</td>
                        <td class="pe-3 text-muted small">{{ $log->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $logs->links() }}
</div>
