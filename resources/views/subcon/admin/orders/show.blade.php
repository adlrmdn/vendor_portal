@extends('layouts.app')

@section('title', 'Work Order — ' . $order->order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ $summary['style'] ?? $order->title }}</h2>
        <small class="text-muted">{{ $order->order_number }}@if(!empty($order->production_group)) <span class="mx-1">·</span> PRG: {{ $order->production_group }}@endif</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <a href="{{ route('subcon.admin.orders.export-cutting', $order->id) }}" class="btn btn-outline-success">
            <i class="fas fa-file-excel me-1"></i> Export Cutting Report
        </a>
        <a href="{{ route('subcon.admin.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm" style="min-width: 210px;">
            <i class="fas fa-print"></i> <span class="text-center">Print Store Labels</span>
        </a>
        <a href="{{ route('subcon.admin.orders.print-labels', ['id' => $order->id, 'scope' => 'warehouse']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm" style="min-width: 210px;">
            <i class="fas fa-warehouse"></i> <span class="text-center">Print WH Labels</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Items --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Order Items</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item #</th>
                                <th>Description</th>
                                <th>Sizes</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr>
                                <td><code>{{ $item->item_number }}</code></td>
                                <td>{{ $summary['style'] ?? $item->description }}</td>
                                <td>{{ $summary['size_count'] > 0 ? $summary['size_count'] . ' ' . Str::plural('size', $summary['size_count']) : '—' }}</td>
                                <td>{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td>
                                    @php $badge = match($item->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', default => 'secondary' }; @endphp
                                    <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports])

        @if($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW)
            {{-- Consumption + approve are one action: this form's inputs are
                 submitted by the "Approve" button in the sidebar approval card. --}}
            <form id="cutting-approve-form" method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}">
                @csrf
                <input type="hidden" name="gate" value="cutting">
                @include('subcon.partials.consumption-input', [
                    'order' => $order,
                    'fabricLines' => $fabricLines,
                    'totalCut' => $totalCut,
                    'editable' => true,
                ])
            </form>
        @else
            @include('subcon.partials.consumption-input', [
                'order' => $order,
                'fabricLines' => $fabricLines,
                'totalCut' => $totalCut,
                'editable' => false,
            ])
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Info Card --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Order Info</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Vendor</dt>
                    <dd class="col-7">{{ $order->vendor?->name ?? '—' }}</dd>
                    <dt class="col-5">Order Date</dt>
                    <dd class="col-7">{{ $order->order_date->format('d M Y') }}</dd>
                    <dt class="col-5">Due Date</dt>
                    <dd class="col-7">{{ $order->due_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7">
                        @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
                        <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                    </dd>
                    <dt class="col-5">Workflow</dt>
                    <dd class="col-7"><span class="badge bg-info-subtle text-info border border-info border-opacity-25">{{ $order->stageLabel() }}</span></dd>
                    <dt class="col-5">Blister Cap.</dt>
                    <dd class="col-7">{{ $order->blister_capacity ? number_format($order->blister_capacity) . ' pcs' : '—' }}</dd>
                    <dt class="col-5">Sack (Karung) Capacity</dt>
                    <dd class="col-7">{{ number_format($order->sack_capacity ?? 50) }} pcs</dd>
                    @if($order->cutting_approved_at)
                        <dt class="col-5">Cutting OK</dt>
                        <dd class="col-7 small text-muted">{{ $order->cutting_approved_at->format('d M Y H:i') }}<br>by {{ $order->cutting_approved_by }}</dd>
                    @endif
                    @if($order->gramasi_approved_at)
                        <dt class="col-5">Gramasi OK</dt>
                        <dd class="col-7 small text-muted">{{ $order->gramasi_approved_at->format('d M Y H:i') }}<br>by {{ $order->gramasi_approved_by }}</dd>
                    @endif
                </dl>
                @if($order->description)
                    <hr><p class="small mb-0">{{ $order->description }}</p>
                @endif
            </div>
        </div>

        @include('subcon.partials.remarks', ['remarks' => $order->remarks ?? null])

        {{-- Stage approval --}}
        @if($order->isAwaitingApproval())
            @php $gate = $order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW ? 'cutting' : 'gramasi'; @endphp
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning-subtle">
                    <h6 class="mb-0"><i class="fas fa-gavel me-1"></i> Approval Required</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-3">
                        The vendor submitted the
                        <strong>{{ $gate === 'gramasi' ? 'gramasi & blister capacity' : 'cutting report' }}</strong>
                        for this work order.
                        @if($gate === 'gramasi' && $order->blister_capacity)
                            <br>Blister capacity: <strong>{{ number_format($order->blister_capacity) }}</strong> pcs/blister.
                        @endif
                        @if($gate === 'cutting')
                            <br><span class="text-muted">Enter the fabric consumption above; it is saved when you approve.</span>
                        @endif
                    </p>
                    <div class="d-flex gap-2">
                        @if($gate === 'cutting')
                            {{-- Submits the consumption form in the left column (one atomic action). --}}
                            <button type="submit" form="cutting-approve-form" class="btn btn-success flex-fill"
                                    onclick="return confirm('Approve the cutting report and save the entered consumption?');">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        @else
                            <form method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}" class="flex-fill m-0"
                                  onsubmit="return confirm('Approve the gramasi & blister capacity?');">
                                @csrf
                                <input type="hidden" name="gate" value="{{ $gate }}">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i> Approve</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('subcon.admin.orders.decline', $order->id) }}" class="flex-fill m-0"
                              onsubmit="return confirm('Return this to the vendor for changes?');">
                            @csrf
                            <input type="hidden" name="gate" value="{{ $gate }}">
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="fas fa-times me-1"></i> Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Status control --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Status</h6></div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    <i class="fas fa-robot me-1"></i> Status follows the workflow stage automatically. Cancelling is the only manual override.
                </p>
                @if($order->status === 'cancelled')
                    <form method="POST" action="{{ route('subcon.admin.orders.update-status', $order->id) }}">
                        @csrf
                        <input type="hidden" name="action" value="reactivate">
                        <button type="submit" class="btn btn-outline-success w-100">
                            <i class="fas fa-rotate-right me-1"></i> Reactivate Order
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('subcon.admin.orders.update-status', $order->id) }}"
                          onsubmit="return confirm('Cancel this work order?');">
                        @csrf
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-ban me-1"></i> Cancel Order
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
