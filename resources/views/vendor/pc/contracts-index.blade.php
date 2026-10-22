@extends('layouts.app')

@section('title', 'Preliminary Contracts')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    <h1 class="pc-h1 mb-4">Preliminary Contracts</h1>

    <div class="pc-card">
        <table class="pc-table mb-0">
            <thead>
                <tr><th>Contract</th><th>Lines</th><th>Status</th><th>Received</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    <tr>
                        <td class="pc-mono">{{ $contract->contract_number ?? 'Pending number' }}@if($contract->revision_no > 0)<span class="pc-meta"> rev {{ $contract->revision_no }}</span>@endif</td>
                        <td class="pc-meta">{{ $contract->lines->count() }}</td>
                        <td>@include('admin.pc.partials.status-pill', ['status' => $contract->status])</td>
                        <td class="pc-meta">{{ $contract->submitted_at?->format('d M Y') }}</td>
                        <td class="text-end"><a href="{{ route('vendor.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                @endforeach
                @if ($contracts->isEmpty())
                    <tr><td colspan="5" class="text-center pc-meta py-4">No Preliminary Contracts yet.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $contracts->links('custom-pagination') }}</div>
</div>
@endsection
