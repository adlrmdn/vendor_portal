@extends('layouts.app')

@section('title', 'Preliminary Contracts')

@section('content')
@include('admin.pc.partials._tokens')

<div class="pc-shell">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="pc-h1">Preliminary Contracts</h1>
        <span class="pc-meta small">Official fabric contracts awaiting your confirmation</span>
    </div>

    {{-- Filter Tabs (Spec P1) --}}
    <ul class="nav pc-ledger-tabs mb-3">
        <li class="nav-item">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'new']) }}" class="nav-link {{ $tab === 'new' ? 'active' : '' }}">
                Needs Action @if(($counts['new'] ?? 0) > 0)<span class="badge bg-warning text-dark ms-1">{{ $counts['new'] }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'confirmed']) }}" class="nav-link {{ $tab === 'confirmed' ? 'active' : '' }}">
                Confirmed @if(($counts['confirmed'] ?? 0) > 0)<span class="badge bg-light text-dark ms-1">{{ $counts['confirmed'] }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'queried']) }}" class="nav-link {{ $tab === 'queried' ? 'active' : '' }}">
                Queried @if(($counts['queried'] ?? 0) > 0)<span class="badge bg-light text-dark ms-1">{{ $counts['queried'] }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'history']) }}" class="nav-link {{ $tab === 'history' ? 'active' : '' }}">
                History
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'all']) }}" class="nav-link {{ $tab === 'all' ? 'active' : '' }}">
                All Contracts
            </a>
        </li>
    </ul>

    <div class="pc-card">
        {{-- Desktop Table --}}
        <div class="d-none d-md-block">
            <table class="pc-table mb-0">
                <thead>
                    <tr>
                        <th>Contract No.</th>
                        <th>Season</th>
                        <th>Lines</th>
                        <th class="text-end">Total Amount</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr>
                            <td class="pc-mono fw-bold">
                                {{ $contract->contract_number }}
                                @if($contract->revision_no > 0)
                                    <span class="badge bg-secondary ms-1">REV {{ $contract->revision_no }}</span>
                                @endif
                            </td>
                            <td>{{ $contract->season?->label ?? '—' }}</td>
                            <td class="pc-meta">{{ $contract->lines->count() }} line(s)</td>
                            <td class="text-end pc-mono fw-bold">
                                {{ number_format($contract->lines->sum('line_total'), 2) }} {{ $contract->currency?->code }}
                            </td>
                            <td>
                                @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                                    <span class="pc-pill pc-pill-waiting fw-bold">Action Required</span>
                                @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
                                    <span class="pc-pill pc-pill-confirmed">Accepted</span>
                                @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
                                    <span class="pc-pill pc-pill-waiting">Queried</span>
                                @else
                                    @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                                @endif
                            </td>
                            <td class="pc-meta small">{{ $contract->updated_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('vendor.pc.contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center pc-meta py-4">No Preliminary Contracts found in this view.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards View (Spec P1) --}}
        <div class="d-md-none p-2">
            @forelse ($contracts as $contract)
                <div class="border rounded p-3 mb-2 bg-white {{ $contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER ? 'border-primary border-2' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <span class="fw-bold pc-mono">{{ $contract->contract_number }}</span>
                            @if($contract->revision_no > 0)
                                <span class="badge bg-secondary ms-1">REV {{ $contract->revision_no }}</span>
                            @endif
                        </div>
                        @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                            <span class="badge bg-warning text-dark">Action Required</span>
                        @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
                            <span class="badge bg-success">Accepted</span>
                        @else
                            @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                        @endif
                    </div>
                    <div class="small text-muted">{{ $contract->season?->label ?? '' }} · PT Mega Putra Garment</div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <div class="fw-bold pc-mono">
                            {{ number_format($contract->lines->sum('line_total'), 2) }} {{ $contract->currency?->code }}
                            <span class="small text-muted fw-normal">({{ $contract->lines->count() }} lines)</span>
                        </div>
                        <a href="{{ route('vendor.pc.contracts.show', $contract) }}" class="btn btn-sm pc-btn-primary">
                            Review
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center pc-meta py-4">No Preliminary Contracts found in this view.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $contracts->links('custom-pagination') }}</div>
</div>
@endsection
