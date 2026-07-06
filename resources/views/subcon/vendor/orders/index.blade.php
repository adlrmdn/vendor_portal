@extends('layouts.app')

@section('title', 'My Work Orders')

@section('content')
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
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
        transition: transform 0.2s, box-shadow 0.2s;
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
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="premium-title mb-0">My Work Orders</h2>
</div>

<div class="card premium-card mb-4">
    <div class="card-body p-4">
        <form method="GET" action="{{ route('subcon.vendor.orders') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-8 col-lg-6">
                <label for="style" class="form-label small fw-semibold text-muted mb-2">Search Work Orders</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="style" name="style" value="{{ $style }}"
                           class="form-control border-start-0 ps-0" placeholder="Search by style, order number, or PRG...">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-filter me-2"></i>Filter</button>
                @if($style !== '')
                    <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-outline-secondary px-4 ms-2">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card premium-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table premium-table mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Production Group</th>
                        <th>Style Name</th>
                        <th>Sizes</th>
                        <th>Order Date</th>
                        <th>Due Date</th>
                        <th>Workflow Stage</th>
                        <th>Status</th>
                        <th></th>
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
                        <td class="fw-semibold">{{ $order->title }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $order->sizes_count ?? 0 }} sizes</span></td>
                        <td>{{ $order->order_date->format('d M Y') }}</td>
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
                        <td colspan="9" class="text-center text-muted py-5">
                            @if($style !== '')
                                <i class="fas fa-search fa-2x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">No work orders match your search “<strong>{{ $style }}</strong>”.</p>
                            @else
                                <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">No work orders assigned yet.</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($orders->hasPages())
    <div class="card-footer bg-white border-top-0 py-3">{{ $orders->links('custom-pagination') }}</div>
    @endif
</div>
@endsection
