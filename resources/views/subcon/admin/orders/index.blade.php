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

@php
    $addProcessMode = $addProcessMode ?? false;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="premium-title mb-0">Work Orders</h2>
</div>

{{-- View mode: a genuinely different row source (SubconOrder vs vsm's
     job_transaction_headers), not a narrowing filter — kept as its own
     segmented control instead of living inside the filter form below, which
     is idiomatic Bootstrap for "which dataset am I browsing" (as distinct
     from "how am I narrowing it"). Plain links so the existing search/vendor
     query params carry across the switch. --}}
<div class="btn-group mb-3" role="group" aria-label="Work order view mode">
    <a href="{{ request()->fullUrlWithQuery(['add_process' => null, 'workflow_stage' => null, 'page' => null]) }}"
       class="btn btn-sm {{ $addProcessMode ? 'btn-outline-primary' : 'btn-primary' }}">
        All Work Orders
    </a>
    <a href="{{ request()->fullUrlWithQuery(['add_process' => 1, 'workflow_stage' => null, 'page' => null]) }}"
       class="btn btn-sm {{ $addProcessMode ? 'btn-primary' : 'btn-outline-primary' }}">
        Add Process Only
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="card premium-card filter-card mb-4">
    <div class="card-body p-4">
        @if($addProcessMode)
            <input type="hidden" name="add_process" value="1">
        @endif
        <div class="row g-3">
            <div class="col-12 col-md-{{ $addProcessMode ? 6 : 4 }}">
                <label class="form-label small fw-semibold text-muted mb-2">Search Orders</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by style, order, PRG..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted mb-2">Vendor</label>
                <select name="vendor_id" class="form-select searchable" data-no-clear>
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            @unless($addProcessMode)
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-2">Workflow Stage</label>
                <select name="workflow_stage" class="form-select searchable" data-no-clear>
                    <option value="">All Stages</option>
                    @foreach(\App\Models\SubconOrder::workflowStages() as $value => $label)
                        <option value="{{ $value }}" {{ request('workflow_stage') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
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
                        <th>{{ ($addProcessMode ?? false) ? 'Operation' : 'Workflow Stage' }}</th>
                        <th>{{ ($addProcessMode ?? false) ? 'Add Process Status' : 'Status' }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    @if($addProcessMode ?? false)
                        {{-- $order here is a plain ['production_group','operation','reported','addpro_pos']
                             array from vsm.job_transaction_headers, NOT a SubconOrder — this
                             row can exist with no work order at all (in-house production). --}}
                        @php
                            $linkedOrder = $ordersByGroup->get($order['production_group']);
                            // The PO matched to THIS row's own operation (not just any
                            // PO the group happens to have) — see
                            // SubconAdminController::addProcessOrdersList(). Every row
                            // reaching this table already has one: rows without a
                            // confirmed match are filtered out before pagination.
                            $addproPo = $order['matched_po'] ?? null;
                            $extraPos = max(0, count($order['addpro_pos'] ?? []) - 1);
                        @endphp
                        <tr>
                            <td>
                                {{-- Always label what's shown here — a bare unlabeled PO
                                     pill is indistinguishable from an "Add Process PO"
                                     pill at a glance, but the two can be genuinely
                                     different POs, or (for a standalone/non-PLM group
                                     with only one PO on record) the very same PO number
                                     showing up two ways: once as the vendor's synced
                                     work order, once tagged AddPro-pool in D365. --}}
                                @if($addproPo)
                                    <span class="code-pill" style="background-color: #fff4e6; border-color: #ffe0b3;">{{ $addproPo['po_number'] }}</span>
                                    <div class="text-muted small mt-1">Add Process PO{{ $extraPos > 0 ? ' (+'.$extraPos.' more)' : '' }}</div>
                                @elseif($linkedOrder)
                                    <span class="code-pill">{{ $linkedOrder->order_number }}</span>
                                    <div class="text-muted small mt-1">Work Order</div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td><span class="code-pill text-primary" style="background-color: #eff6ff; border-color: #dbeafe;">{{ $order['production_group'] }}</span></td>
                            <td class="fw-semibold">
                                @if($addproPo)
                                    {{ $addproPo['vendor_name'] ?? $addproPo['vendor_code'] ?? '—' }}
                                @else
                                    {{ $linkedOrder?->vendor?->name ?? '— (in-house / unregistered)' }}
                                @endif
                            </td>
                            <td>{{ $order['style_name'] ?? $linkedOrder?->title ?? '—' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $order['sizes_count'] ?? 0 }} sizes</span></td>
                            <td>
                                @php
                                    // The matched PO's own D365 RequestedDeliveryDate first
                                    // (this row's actual Add Process PO) — falls back to the
                                    // linked CMT work order's due_date when the PO doesn't
                                    // carry one.
                                    $addProcessDueDate = $addproPo['due_date'] ?? null;
                                    $addProcessDueDate = $addProcessDueDate ? \Illuminate\Support\Carbon::parse($addProcessDueDate) : $linkedOrder?->due_date;
                                @endphp
                                {{ $addProcessDueDate?->format('d M Y') ?? '—' }}
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary border">{{ $order['operation'] }}</span></td>
                            <td><span class="badge-workflow bg-{{ $order['reported'] ? 'success' : 'secondary' }}">{{ $order['reported'] ? 'Reported' : 'Not reported' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('subcon.admin.add-process.index', ['prg' => $order['production_group']]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                    @else
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
                            {{-- Once the local workflow says "Completed" it stops saying
                                 anything further — but the QC-console inspection → approval
                                 → debit-note pipeline is often still running behind it. This
                                 shows admins where each completed order actually stands
                                 without opening it (see SubconAdminController::
                                 qcPipelineStatusForOrders()). --}}
                            @if(isset($qcPipelineByOrder[$order->id]))
                                <div class="mt-1">
                                    <span class="badge bg-{{ $qcPipelineByOrder[$order->id]['badge'] }}" style="font-size:.68rem;" title="QC / Finance pipeline status">
                                        {{ $qcPipelineByOrder[$order->id]['label'] }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="fas fa-eye me-1"></i> View
                            </a>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-2x mb-3 text-muted opacity-50"></i>
                            <p class="mb-0">No {{ ($addProcessMode ?? false) ? 'add-process rows' : 'orders' }} found matching the filter criteria.</p>
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
