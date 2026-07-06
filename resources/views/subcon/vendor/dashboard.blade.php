@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
    }
    .premium-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        border-radius: 20px;
        padding: 30px;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
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
    }
    .premium-header-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2rem;
        letter-spacing: -0.02em;
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
    .step-card {
        background-color: #f8fafc;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .step-number {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1.1rem;
        color: #3b82f6;
        margin-bottom: 4px;
    }
    .step-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1e293b;
        margin-bottom: 6px;
    }
    .step-desc {
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.4;
        margin-bottom: 0;
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
</style>

<div class="premium-container">
    <!-- Header banner -->
    <div class="premium-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="premium-header-title mb-1">Welcome back,</h1>
            <p class="mb-0 text-white text-opacity-75"><i class="fas fa-industry me-2"></i>{{ auth()->user()->vendor?->name ?? 'Subcontractor Vendor' }}</p>
        </div>
        <div class="d-none d-md-block text-end">
            <span class="premium-date-badge"><i class="far fa-calendar-alt me-2"></i>{{ date('d M Y') }}</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Total Work Orders</div>
                        <div class="stat-number mt-1">{{ $stats['total_orders'] }}</div>
                    </div>
                    <div class="icon-box icon-box-primary">
                        <i class="fas fa-clipboard-list fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-number mt-1">{{ $stats['active_orders'] }}</div>
                    </div>
                    <div class="icon-box icon-box-warning">
                        <i class="fas fa-tasks fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card premium-card">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Completed Orders</div>
                        <div class="stat-number mt-1">{{ $stats['completed'] }}</div>
                    </div>
                    <div class="icon-box icon-box-success">
                        <i class="fas fa-check-double fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step-by-Step Workflow Guide -->
    <div class="mb-5">
        <h4 class="premium-title mb-4"><i class="fas fa-route me-2 text-primary"></i>Your 6-Step Production Workflow</h4>
        <div class="row g-3">
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <div class="step-title">Cutting Report</div>
                    <p class="step-desc">Enter and submit cutting quantities.</p>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">02</div>
                    <div class="step-title">Cutting Approval</div>
                    <p class="step-desc">Awaiting admin review &amp; approval.</p>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">03</div>
                    <div class="step-title">Gramasi &amp; Blister</div>
                    <p class="step-desc">Enter gramasi weights and blister capacity.</p>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">04</div>
                    <div class="step-title">Gramasi Approval</div>
                    <p class="step-desc">Awaiting admin review &amp; approval.</p>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">05</div>
                    <div class="step-title">Waiting Distribution</div>
                    <p class="step-desc">Awaiting packing label generation.</p>
                </div>
            </div>
            <div class="col-12 col-sm-4 col-lg-2">
                <div class="step-card">
                    <div class="step-number">06</div>
                    <div class="step-title">Print &amp; Complete</div>
                    <p class="step-desc">Print barcode labels and complete order.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Work Orders Table -->
    <div class="card premium-card mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="premium-title mb-0" style="font-size: 1.1rem;"><i class="fas fa-history me-2 text-muted"></i>Recent Work Orders</h5>
            <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All Orders</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Production Group</th>
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
                            <td class="fw-semibold">{{ $order->title }}</td>
                            <td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>@include('subcon.partials.stage-badge', ['order' => $order])</td>
                            <td>
                                @php 
                                    $badge = match($order->status) { 
                                        'pending' => 'secondary', 
                                        'in_progress' => 'warning text-dark', 
                                        'completed' => 'success', 
                                        'cancelled' => 'danger', 
                                        default => 'secondary' 
                                    }; 
                                @endphp
                                <span class="badge-workflow bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('subcon.vendor.orders.view', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
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
@endsection
