@extends('layouts.app')

@section('title', 'Subcon Admin Dashboard')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
    }
    .premium-header {
        background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        border-radius: 20px;
        padding: 30px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
        position: relative;
        overflow: hidden;
    }
    .premium-header::after {
        content: '';
        position: absolute;
        right: -50px;
        bottom: -50px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        pointer-events: none;
    }
    .premium-header > * {
        position: relative;
        z-index: 1;
    }
    .premium-header-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2rem;
        letter-spacing: -0.02em;
    }
    .premium-date-badge {
        background-color: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        padding: 8px 16px;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .premium-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.02em;
    }
    .premium-card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .premium-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.04);
    }
    .stat-number {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.25rem;
        line-height: 1;
        color: #1e293b;
    }
    .stat-label {
        font-family: 'Outfit', sans-serif;
        font-weight: 500;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .icon-box {
        width: 54px;
        height: 54px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .icon-box-danger {
        background-color: #fef2f2;
        color: #ef4444;
    }
    .icon-box-primary {
        background-color: #eff6ff;
        color: #2563eb;
    }
    .icon-box-warning {
        background-color: #fffbef;
        color: #d97706;
    }
    .icon-box-success {
        background-color: #f0fdf4;
        color: #16a34a;
    }
    .icon-box-info {
        background-color: #f0fdfa;
        color: #0d9488;
    }
    .premium-table th {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748b;
        background-color: #f8fafc;
        border-bottom: 2px solid #edf2f7;
        padding: 16px;
    }
    .premium-table td {
        padding: 16px;
        vertical-align: middle;
        font-family: 'Inter', sans-serif;
        color: #334155;
        font-size: 0.875rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .premium-table tr:hover td {
        background-color: #f8fafc;
    }
    .badge-workflow {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 9999px;
        display: inline-block;
    }
    .code-pill {
        background-color: #f1f5f9;
        color: #475569;
        font-family: var(--bs-font-monospace);
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
    }
    .pulse-danger {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }
</style>

<div class="premium-container">
    <!-- Header banner -->
    <div class="premium-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="premium-header-title mb-1">Welcome back,</h1>
            <p class="mb-0 text-white text-opacity-75"><i class="fas fa-user-shield me-2"></i>{{ auth()->user()->name }} (Subcon Admin)</p>
        </div>
        <div class="text-end d-flex flex-column align-items-end gap-2">
            <span class="premium-date-badge d-none d-md-inline-flex"><i class="far fa-calendar-alt me-2"></i>{{ date('d M Y') }}</span>
            <button type="button" id="syncPosBtn" class="btn btn-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="fas fa-rotate me-1"></i> <span class="sync-label">Sync POs</span>
            </button>
            <small id="syncStatusText" class="text-white text-opacity-75" style="max-width: 320px;"></small>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-4 mb-5">
        <!-- Pending Approvals -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="{{ route('subcon.admin.approvals') }}" class="text-decoration-none">
                <div class="card premium-card {{ ($stats['pending_approvals'] ?? 0) > 0 ? 'pulse-danger border-danger-subtle' : '' }}" style="height: 100%;">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Pending Approvals</div>
                            <div class="stat-number mt-1 text-danger">{{ $stats['pending_approvals'] ?? 0 }}</div>
                        </div>
                        <div class="icon-box icon-box-danger">
                            <i class="fas fa-gavel fa-lg"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <!-- Active Orders -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card premium-card" style="height: 100%;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-number mt-1">{{ $stats['active_orders'] }}</div>
                    </div>
                    <div class="icon-box icon-box-warning">
                        <i class="fas fa-spinner fa-lg fa-spin-pulse"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Completed Orders -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card premium-card" style="height: 100%;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Completed Orders</div>
                        <div class="stat-number mt-1">{{ $stats['completed'] }}</div>
                    </div>
                    <div class="icon-box icon-box-success">
                        <i class="fas fa-check-circle fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Active Vendors -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card premium-card" style="height: 100%;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Active Vendors</div>
                        <div class="stat-number mt-1">{{ $stats['total_vendors'] }}</div>
                    </div>
                    <div class="icon-box icon-box-info">
                        <i class="fas fa-users fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Work Orders Table -->
    <div class="card premium-card mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="premium-title mb-0" style="font-size: 1.1rem;"><i class="fas fa-history text-muted me-2"></i>Recent Work Orders</h5>
            <a href="{{ route('subcon.admin.orders') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Production Group</th>
                            <th>Vendor</th>
                            <th>Style Name</th>
                            <th>Due Date</th>
                            <th>Workflow Stage</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td><span class="code-pill">{{ $order->order_number }}</span></td>
                            <td>
                                @if($order->production_group)
                                    <span class="code-pill text-primary" style="background-color: #eff6ff; border-color: #dbeafe;">{{ $order->production_group }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $order->vendor?->name ?? '—' }}</td>
                            <td>{{ $order->title }}</td>
                            <td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>@include('subcon.partials.stage-badge', ['order' => $order])</td>
                            <td>
                                @php
                                    $badge = match($order->status) {
                                        'pending'     => 'secondary',
                                        'in_progress' => 'warning text-dark',
                                        'completed'   => 'success',
                                        'cancelled'   => 'danger',
                                        default       => 'secondary',
                                    };
                                @endphp
                                <span class="badge-workflow bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">No work orders assigned yet.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('syncPosBtn');
    if (!btn) return;

    const statusText = document.getElementById('syncStatusText');
    const label = btn.querySelector('.sync-label');
    const icon = btn.querySelector('i');
    const triggerUrl = "{{ route('subcon.admin.sync-orders') }}";
    const statusUrl = "{{ route('subcon.admin.sync-status') }}";
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let polling = null;

    function setRunning(running) {
        btn.disabled = running;
        icon.className = running ? 'fas fa-rotate fa-spin me-1' : 'fas fa-rotate me-1';
        label.textContent = running ? 'Syncing…' : 'Sync POs';
        if (running && statusText) statusText.textContent = 'Sync in progress…';
    }

    function showResult(last) {
        if (!statusText || !last) return;
        const ok = last.result === 'success';
        statusText.textContent = (ok ? '✓ ' : '✗ ') + (last.message || (ok ? 'Sync completed.' : 'Sync failed.'));
    }

    function startPolling() {
        if (!polling) polling = setInterval(checkStatus, 4000);
    }
    function stopPolling() {
        if (polling) { clearInterval(polling); polling = null; }
    }

    function checkStatus() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data.running) {
                    setRunning(true);
                    if (statusText && data.stage) statusText.textContent = data.stage;
                    startPolling();
                } else {
                    stopPolling();
                    setRunning(false);
                    if (data.last) {
                        showResult(data.last);
                        // Refresh stats + recent orders after a successful sync.
                        if (data.last.result === 'success') {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    }
                }
            })
            .catch(() => {});
    }

    btn.addEventListener('click', function () {
        setRunning(true);
        fetch(triggerUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(body => {
            if (statusText && body.message) statusText.textContent = body.message;
            startPolling();
        })
        .catch(() => {
            setRunning(false);
            if (statusText) statusText.textContent = 'Could not start sync.';
        });
    });

    @if(!empty($syncRunning))
        setRunning(true);
        startPolling();
    @elseif(!empty($syncLast))
        showResult(@json($syncLast));
    @endif
})();
</script>
@endpush
@endsection
