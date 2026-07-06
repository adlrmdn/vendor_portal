@extends('layouts.app')

@section('title', 'Label Generation')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    .premium-container {
        font-family: 'Inter', sans-serif;
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
    .code-pill {
        background-color: #f1f5f9;
        color: #475569;
        font-family: var(--bs-font-monospace);
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
    }
    .btn-generate {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        color: white;
        transition: all 0.2s;
    }
    .btn-generate:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
</style>

<div class="premium-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('subcon.admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Label Generation</li>
                </ol>
            </nav>
            <h2 class="premium-title mb-0">Manual Label Generation</h2>
        </div>
        <span class="text-muted small"><i class="fas fa-print me-1"></i>Waiting Distribution Details</span>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center" style="background-color: #eff6ff; color: #1e3a8a;">
        <div class="me-3 fs-4 text-primary">
            <i class="fas fa-info-circle"></i>
        </div>
        <div>
            <strong class="d-block mb-1">Manual Packing Label Generation</strong>
            These orders have finished Gramasi approval and are waiting for distribution/packing label generation. In case the email link is lost, you can trigger generation directly from here.
        </div>
    </div>

    <!-- Orders List Card -->
    <div class="card premium-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Production Group</th>
                            <th>Vendor</th>
                            <th>Style Name</th>
                            <th>Current Stage</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
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
                            <td>@include('subcon.partials.stage-badge', ['order' => $order])</td>
                            <td class="text-end">
                                <form action="{{ route('subcon.admin.orders.generate-labels-manual', $order->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Trigger label generation for {{ $order->order_number }}? This calls the idempotent DTT service.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-generate rounded-pill px-3 fw-semibold">
                                        <i class="fas fa-cog fa-spin-pulse me-1"></i> Generate Labels
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-check-circle fa-2x mb-3 text-success opacity-50"></i>
                                <p class="mb-0 fw-semibold">No work orders currently waiting for distribution details.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>
@endsection
