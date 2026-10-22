{{-- PC Audit Log tab-pane content. Data from PcController::index(). --}}
<h1 class="pc-h1 mb-4"><i class="fas fa-clipboard-list me-2"></i>PC Audit Log</h1>

<div class="pc-card">
    <div class="pc-card-body">
        <table class="pc-table mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Role</th>
                    <th>Event</th>
                    <th>Entity</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                        <td>{{ $log->actor }}</td>
                        <td>{{ $log->actor_role }}</td>
                        <td><span class="pc-pill pc-pill-neutral">{{ $log->event_type }}</span></td>
                        <td>{{ $log->entity_label }}</td>
                        <td>{{ $log->note }}</td>
                    </tr>
                @endforeach
                @if ($logs->isEmpty())
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries yet.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $logs->links() }}
</div>
