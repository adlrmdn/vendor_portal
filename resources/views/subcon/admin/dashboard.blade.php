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
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
        border-radius: 18px;
        padding: 28px 32px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(30, 58, 138, 0.12);
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
            <h1 class="premium-header-title mb-1">{{ __('Welcome back') }}, {{ auth()->user()->name }}</h1>
            @if(auth()->user()->email)
                <p class="mb-0 text-white text-opacity-75 small">
                    <i class="far fa-envelope me-1.5 opacity-75"></i>{{ auth()->user()->email }}
                </p>
            @endif
        </div>
        <div class="text-end d-flex flex-column align-items-end gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="premium-date-badge d-none d-md-inline-flex"><i class="far fa-calendar-alt me-2"></i>{{ date('d M Y') }}</span>
                <button type="button" id="syncPosBtn" class="btn btn-light btn-sm rounded-pill px-3 fw-semibold text-dark shadow-sm"
                        data-bs-toggle="tooltip" data-bs-placement="left" title="{{ __('Synchronizes active CMT purchase orders and items from Dynamics 365.') }}">
                    <i class="fas fa-rotate me-1 text-primary"></i> <span class="sync-label">{{ __('Sync POs') }}</span>
                </button>
            </div>
            <small id="syncStatusText" class="text-white text-opacity-75" style="max-width: 320px;"></small>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-4 mb-4">
        <!-- Pending Approvals -->
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="{{ route('subcon.admin.approvals') }}" class="text-decoration-none">
                <div class="card premium-card" style="height: 100%;">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label d-flex align-items-center gap-1">
                                <span>{{ __('Pending Approvals') }}</span>
                                <i class="fas fa-info-circle text-primary opacity-50 ms-1" style="font-size: 0.8rem; cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Orders currently waiting at Cutting Review, Gramasi Review, or Final Approval gates.') }}"></i>
                            </div>
                            <div class="d-flex align-items-baseline gap-2 mt-1">
                                <div class="stat-number {{ ($stats['pending_approvals'] ?? 0) > 0 ? 'text-danger' : 'text-dark' }}">{{ $stats['pending_approvals'] ?? 0 }}</div>
                                @if(($stats['pending_approvals'] ?? 0) > 0)
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5 small fw-semibold" style="font-size: 0.72rem;">{{ __('Action Required') }}</span>
                                @endif
                            </div>
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
                        <div class="stat-label d-flex align-items-center gap-1">
                            <span>{{ __('Active Orders') }}</span>
                            <i class="fas fa-info-circle text-primary opacity-50 ms-1" style="font-size: 0.8rem; cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('All CMT work orders currently in production across assigned subcontractors.') }}"></i>
                        </div>
                        <div class="stat-number mt-1 text-dark">{{ $stats['active_orders'] }}</div>
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
                        <div class="stat-label d-flex align-items-center gap-1">
                            <span>{{ __('Completed Orders') }}</span>
                            <i class="fas fa-info-circle text-primary opacity-50 ms-1" style="font-size: 0.8rem; cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Work orders that have completed label generation and packaging sign-off.') }}"></i>
                        </div>
                        <div class="stat-number mt-1 text-dark">{{ $stats['completed'] }}</div>
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
                        <div class="stat-label d-flex align-items-center gap-1">
                            <span>{{ __('Active Vendors') }}</span>
                            <i class="fas fa-info-circle text-primary opacity-50 ms-1" style="font-size: 0.8rem; cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Subcontractor vendors with active portal access accounts.') }}"></i>
                        </div>
                        <div class="stat-number mt-1 text-dark">{{ $stats['total_vendors'] }}</div>
                    </div>
                    <div class="icon-box icon-box-info">
                        <i class="fas fa-users fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guidebook Spotlight Card -->
    <div class="card premium-card mb-4" style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 5px solid #2563eb;">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: #eff6ff; color: #2563eb; font-size: 1.35rem;">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">{{ __('Admin Guidebook & SOP Workflow') }}</h6>
                        <span class="badge bg-primary-subtle text-primary rounded-pill small fw-normal">{{ __('SOP') }}</span>
                    </div>
                    <p class="mb-0 text-muted small">
                        {{ app()->getLocale() === 'id' 
                            ? 'Panduan terstruktur verifikasi Cutting, persetujuan Gramasi, formula Cutt Plan, integrasi D365 RPA label, dan Add Process.' 
                            : 'Standard operating manual for cutting reviews, gramasi approvals, D365 RPA labels, and Add Process reconciliation.' }}
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <a href="{{ route('subcon.admin.guidebook') }}" class="btn btn-primary rounded-pill px-3.5 btn-sm fw-semibold shadow-sm">
                    <i class="fas fa-book-open me-1"></i> {{ __('Admin Guidebook') }}
                </a>
                <a href="{{ route('subcon.admin.user-guide') }}" target="_blank" class="btn btn-outline-secondary rounded-pill px-3 btn-sm fw-semibold">
                    <i class="fas fa-file-pdf me-1 text-danger"></i> PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Work Orders Table -->
    <div class="card premium-card mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="premium-title mb-0" style="font-size: 1.1rem;"><i class="fas fa-history text-muted me-2"></i>{{ __('Recent Work Orders') }}</h5>
            <a href="{{ route('subcon.admin.orders') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">{{ __('View All') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Order #') }}</th>
                            <th>{{ __('Production Group') }}</th>
                            <th>{{ __('Vendor') }}</th>
                            <th>{{ __('Style Name') }}</th>
                            <th>{{ __('Due Date') }}</th>
                            <th>{{ __('Workflow Stage') }}</th>
                            <th>{{ __('Status') }}</th>
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
                                    <i class="fas fa-eye me-1"></i> {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">{{ __('No work orders assigned yet.') }}</p>
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
