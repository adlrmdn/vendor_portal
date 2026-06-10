@extends('layouts.app')

@section('title', 'Work Order — ' . $order->order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ $summary['style'] ?? $order->title }}</h2>
        <small class="text-muted">{{ $order->order_number }}</small>
    </div>
    <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
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

        @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups])
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Order Info</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Order Date</dt>
                    <dd class="col-7">{{ $order->order_date->format('d M Y') }}</dd>
                    <dt class="col-5">Due Date</dt>
                    <dd class="col-7">{{ $order->due_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7">
                        @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
                        <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                    </dd>
                </dl>
                @if($order->description)
                    <hr><p class="small mb-0">{{ $order->description }}</p>
                @endif
                @if($order->notes)
                    <hr><p class="small mb-0 text-muted">{{ $order->notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
