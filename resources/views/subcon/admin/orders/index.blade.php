@extends('layouts.app')

@section('title', 'Work Orders')

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
    }
    /* The filter card holds the Tom Select dropdowns — it must NOT clip them
       (overflow:hidden) and must stack above the table card below it. */
    .filter-card {
        overflow: visible;
        position: relative;
        z-index: 3;
    }
    /* Float the open dropdown above any following card. */
    .filter-card .ts-dropdown {
        z-index: 1050;
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
    <h2 class="premium-title mb-0">Work Orders</h2>
</div>

{{-- Filters --}}
<form method="GET" class="card premium-card filter-card mb-4">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted mb-2">Search Orders</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by style, order, PRG..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-2">Vendor</label>
                <select name="vendor_id" class="form-select searchable">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-2">Workflow Stage</label>
                <select name="workflow_stage" class="form-select searchable">
                    <option value="">All Stages</option>
                    @foreach(\App\Models\SubconOrder::workflowStages() as $value => $label)
                        <option value="{{ $value }}" {{ request('workflow_stage') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 align-self-end">
                <button type="submit" class="btn btn-outline-primary w-100 py-2"><i class="fas fa-filter me-2"></i>Filter</button>
            </div>
        </div>
    </div>
</form>

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
                        <th>Sizes</th>
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
                        <td class="fw-semibold">{{ $order->vendor?->name ?? '—' }}</td>
                        <td>{{ $order->title }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $order->sizes_count ?? 0 }} sizes</span></td>
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
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                            <p class="mb-0">No orders found matching the filter criteria.</p>
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

@include('partials.searchable-select')
@endsection
