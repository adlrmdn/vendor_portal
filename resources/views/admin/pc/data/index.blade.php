@extends('layouts.app')

@section('title', 'PC Data Management')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    @include('admin.pc.partials.section-nav', ['pcSection' => 'data'])

    <ul class="nav pc-ledger-tabs" id="pcDataTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="master-data-tab" data-bs-toggle="tab" data-bs-target="#master-data" type="button" role="tab">
                Master Data
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sync-tab" data-bs-toggle="tab" data-bs-target="#sync" type="button" role="tab">
                D365 Sync
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="audit-log-tab" data-bs-toggle="tab" data-bs-target="#audit-log" type="button" role="tab">
                Audit Log
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approver-settings-tab" data-bs-toggle="tab" data-bs-target="#approver-settings" type="button" role="tab">
                Approver Settings
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pcDataTabContent">
        <div class="tab-pane fade show active" id="master-data" role="tabpanel">
            @include('admin.pc.partials.master-data')
        </div>
        <div class="tab-pane fade" id="sync" role="tabpanel">
            @include('admin.pc.partials.sync')
        </div>
        <div class="tab-pane fade" id="audit-log" role="tabpanel">
            @include('admin.pc.partials.audit-log')
        </div>
        <div class="tab-pane fade" id="approver-settings" role="tabpanel">
            @include('admin.pc.partials.approver-settings')
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Restore the active tab from the URL fragment (e.g. after a
        // store/update action redirects back here with #master-data, or a
        // "View all"/"Manage sync" link from the Dashboard), and keep the
        // fragment in sync as the user clicks between tabs.
        function activateTab(id) {
            const btn = document.getElementById(id + '-tab');
            if (btn && window.bootstrap) {
                new bootstrap.Tab(btn).show();
            }
        }

        const initial = (window.location.hash || '').replace('#', '');
        if (initial) {
            activateTab(initial);
        }

        document.querySelectorAll('#pcDataTab button[data-bs-toggle="tab"]').forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('data-bs-target').replace('#', '');
                history.replaceState(null, '', '#' + target);
            });
        });

        // D365 Sync tab: poll + "Refresh now" buttons.
        const pollUrl = @json(route('admin.pc.sync.poll'));
        const csrfToken = @json(csrf_token());

        function refreshUrl(feed) {
            return '{{ url('/admin/pc/sync') }}/' + feed;
        }

        function applySyncState(state) {
            document.querySelectorAll('#pcSyncTable tbody tr').forEach(function (row) {
                const feed = row.dataset.feed;
                const s = state[feed] || { running: false, last: null };
                row.querySelector('.feed-status').innerHTML = s.running
                    ? '<span class="pc-pill pc-pill-waiting">Running…</span>'
                    : '<span class="pc-pill pc-pill-neutral">Idle</span>';
                row.querySelector('.feed-last-result').textContent = (s.last && s.last.result) || '—';
                row.querySelector('.feed-finished-at').textContent = (s.last && s.last.finished_at) || '—';
                row.querySelector('.feed-refresh-btn').disabled = !!s.running;
            });
        }

        function pollSync() {
            fetch(pollUrl).then(r => r.json()).then(applySyncState).catch(function () {});
        }

        document.querySelectorAll('.feed-refresh-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const feed = btn.dataset.feed;
                btn.disabled = true;
                fetch(refreshUrl(feed), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                }).then(pollSync);
            });
        });

        setInterval(pollSync, 5000);
    })();
</script>
@endpush
@endsection
