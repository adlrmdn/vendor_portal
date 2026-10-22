@extends('layouts.app')

@section('title', 'Preliminary Contract')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div>
            <h1 class="pc-h1 pc-mono">{{ $contract->contract_number ?? 'Preliminary Contract' }}@if($contract->revision_no > 0)<span class="pc-meta"> rev {{ $contract->revision_no }}</span>@endif</h1>
            <div class="mt-2">@include('admin.pc.partials.stage-tracker', ['status' => $contract->status])</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($contract->isConfirmed())
                <a href="{{ route('vendor.pc.contracts.pdf', $contract) }}" target="_blank" class="btn btn-outline-secondary">Download PDF</a>
            @endif
            @if($contract->status === 'pending_vendor_review')
                <button class="btn pc-btn-forest" data-bs-toggle="modal" data-bs-target="#acceptModal">Accept</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#queryModal">Query</button>
            @endif
        </div>
    </div>

    <div class="pc-card mb-3">
        <div class="pc-card-head">Terms</div>
        <table class="pc-table mb-0">
            <tr><td class="pc-meta">Fabric type</td><td>{{ $contract->fabric_type }}</td></tr>
            <tr><td class="pc-meta">Currency</td><td>{{ $contract->currency?->code }}</td></tr>
            <tr><td class="pc-meta">Terms of delivery</td><td>{{ $contract->deliveryTerm?->label }}</td></tr>
            <tr><td class="pc-meta">Terms of payment</td><td>{{ $contract->paymentTerm?->label }}</td></tr>
            <tr><td class="pc-meta">Mode of transport</td><td>{{ $contract->transportMode?->label }}</td></tr>
        </table>
    </div>

    <div class="pc-card mb-3">
        <div class="pc-card-head">Line Items</div>
        <table class="pc-table mb-0">
            <thead><tr><th>#</th><th>Item</th><th>PLM</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @foreach ($contract->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>{{ $line->item_name_snapshot }}</td>
                        <td class="pc-mono">{{ $line->plm_id_snapshot }}</td>
                        <td class="num">{{ $line->qty }} {{ $line->uom_snapshot }}</td>
                        <td class="num">{{ $line->unit_price }}</td>
                        <td class="num">{{ $line->line_total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Accept Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('vendor.pc.contracts.accept', $contract) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Accept contract</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="mb-0">Confirm you accept <strong class="pc-mono">{{ $contract->contract_number ?? 'this contract' }}</strong> with {{ $contract->lines->count() }} line(s), total <strong class="pc-mono">{{ $contract->lines->sum('line_total') }} {{ $contract->currency?->code }}</strong>.</p>
                </div>
                <div class="modal-footer"><button type="submit" class="btn pc-btn-forest">Accept</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Query Modal -->
<div class="modal fade" id="queryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('vendor.pc.contracts.query', $contract) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Query this contract</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">What needs to change?</label>
                    <textarea name="reason" class="form-control" required minlength="10"></textarea>
                </div>
                <div class="modal-footer"><button type="submit" class="btn pc-btn-outline-brick">Send query</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
