@extends('layouts.app')

@section('title', 'Preliminary Contracts')

@section('content')
@include('admin.pc.partials._tokens')

<div class="container-fluid apple-po-wrapper px-3 px-md-4 py-3">
    <!-- Header with Eyebrow -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="apple-section-eyebrow">Fabric Supplier Portal</span>
                <span class="apple-bullet">&bull;</span>
                <span class="apple-section-eyebrow text-muted">Legal &amp; Procurement</span>
            </div>
            <h1 class="apple-page-title mb-1">Preliminary Contracts</h1>
            <p class="text-muted mb-0 small">Official fabric purchase contracts awaiting your review, confirmation, or questions.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('vendor.pc.guidebook.pdf') }}" class="apple-btn-secondary" target="_blank" title="Download Supplier Operating & Workflow Guidebook PDF">
                <i class="fas fa-file-pdf me-1 text-danger"></i>Supplier Guidebook (PDF)
            </a>
            <span class="apple-count-pill" style="background: #ffffff; border: 1px solid rgba(0, 0, 0, 0.08); padding: 6px 14px; font-size: 0.85rem;">
                <i class="fas fa-file-contract text-primary me-2"></i>
                <span class="fw-semibold text-dark">Supplier Contract Desk</span>
            </span>
        </div>
    </div>

    <!-- Segmented Status Bar -->
    <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
        <div class="apple-segmented-bar">
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'new']) }}"
               class="apple-segment-item {{ $tab === 'new' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-pending"></span>
                <span>Needs Action</span>
                @if(($counts['new'] ?? 0) > 0)
                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.72rem; border-radius: 9999px;">
                        {{ $counts['new'] }}
                    </span>
                @endif
            </a>
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'confirmed']) }}"
               class="apple-segment-item {{ $tab === 'confirmed' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-completed"></span>
                <span>Confirmed</span>
                @if(($counts['confirmed'] ?? 0) > 0)
                    <span class="badge bg-light text-dark ms-1" style="font-size: 0.72rem; border-radius: 9999px;">
                        {{ $counts['confirmed'] }}
                    </span>
                @endif
            </a>
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'queried']) }}"
               class="apple-segment-item {{ $tab === 'queried' ? 'active' : '' }}">
                <span class="apple-segment-dot" style="background-color: #ff3b30;"></span>
                <span>Queried</span>
                @if(($counts['queried'] ?? 0) > 0)
                    <span class="badge bg-light text-dark ms-1" style="font-size: 0.72rem; border-radius: 9999px;">
                        {{ $counts['queried'] }}
                    </span>
                @endif
            </a>
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'history']) }}"
               class="apple-segment-item {{ $tab === 'history' ? 'active' : '' }}">
                <span class="apple-segment-dot dot-cancelled"></span>
                <span>History</span>
            </a>
            <a href="{{ route('vendor.pc.contracts.index', ['tab' => 'all']) }}"
               class="apple-segment-item {{ $tab === 'all' ? 'active' : '' }}">
                <i class="fas fa-list me-1 opacity-50" style="font-size: 0.75rem;"></i>
                <span>All Contracts</span>
            </a>
        </div>
    </div>

    <!-- Contracts Table Card -->
    <div class="apple-card apple-table-container mb-4">
        <div class="apple-card-header">
            <div>
                <h5 class="apple-card-title mb-1">
                    <i class="fas fa-file-contract text-primary"></i>
                    <span>Contracts Register</span>
                </h5>
                <span class="small text-muted">Showing {{ $contracts->total() }} {{ Str::plural('contract', $contracts->total()) }} in this view</span>
            </div>
            @if($tab === 'new' && ($counts['new'] ?? 0) > 0)
                <span class="apple-pill-status pill-warning">
                    <span class="status-dot dot-warning"></span>
                    {{ $counts['new'] }} requires confirmation
                </span>
            @endif
        </div>

        {{-- Desktop Table --}}
        <div class="d-none d-md-block table-responsive">
            <table class="table apple-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 28%;">Contract Details</th>
                        <th style="width: 14%;">Season</th>
                        <th style="width: 12%;">Lines</th>
                        <th style="width: 18%; text-align: right;">Total Amount</th>
                        <th style="width: 14%; text-align: center;">Status</th>
                        <th style="width: 8%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr class="apple-table-row">
                            <!-- Contract Details -->
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('vendor.pc.contracts.show', $contract) }}"
                                           class="apple-po-number"
                                           title="Review contract {{ $contract->contract_number }}">
                                            {{ $contract->contract_number }}
                                        </a>
                                        <button type="button"
                                                class="apple-copy-btn"
                                                onclick="copyPoNumber('{{ $contract->contract_number }}', this)"
                                                title="Copy Contract Number">
                                            <i class="far fa-copy"></i>
                                        </button>
                                        @if($contract->revision_no > 0)
                                            <span class="badge bg-secondary-subtle text-secondary small px-2 py-0" style="font-size: 0.72rem; border-radius: 4px;">
                                                REV {{ $contract->revision_no }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="apple-vendor-info text-truncate">
                                        <i class="far fa-building me-1 opacity-50"></i>
                                        <span class="fw-medium text-dark">PT Mega Putra Garment</span>
                                        <span class="opacity-75">&bull; Updated {{ $contract->updated_at->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Season -->
                            <td>
                                @if($contract->season?->label)
                                    <span class="apple-sub-tag pc-tag">
                                        <i class="far fa-calendar-alt me-1 opacity-75"></i>{{ $contract->season->label }}
                                    </span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            <!-- Lines -->
                            <td>
                                <span class="apple-count-pill" style="padding: 3px 10px; font-size: 0.78rem;">
                                    <i class="fas fa-layer-group me-1 opacity-50"></i>{{ $contract->lines->count() }} line(s)
                                </span>
                            </td>

                            <!-- Total Amount -->
                            <td class="text-end">
                                <div class="apple-amount-box">
                                    <span class="apple-currency">{{ $contract->currency?->code ?? 'IDR' }}</span>
                                    <span class="apple-amount-value">{{ number_format($contract->lines->sum('line_total'), 2) }}</span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                                    <span class="apple-status-capsule capsule-pending">
                                        <span class="capsule-dot"></span>
                                        Action Required
                                    </span>
                                @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
                                    <span class="apple-status-capsule capsule-completed">
                                        <span class="capsule-dot"></span>
                                        Accepted
                                    </span>
                                @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
                                    <span class="apple-status-capsule capsule-cancelled" style="background: rgba(255, 59, 48, 0.1); color: #ff3b30;">
                                        <span class="capsule-dot" style="background: #ff3b30;"></span>
                                        Queried
                                    </span>
                                @else
                                    @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="text-center">
                                <a href="{{ route('vendor.pc.contracts.show', $contract) }}"
                                   class="apple-action-btn apple-action-view"
                                   title="Review Contract">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
                                <div class="apple-empty-state">
                                    <div class="apple-empty-icon-circle mb-3">
                                        <i class="fas fa-file-contract fa-2x"></i>
                                    </div>
                                    <h4 class="apple-empty-title">No Preliminary Contracts Found</h4>
                                    <p class="apple-empty-desc">
                                        Contracts sent to your supplier account will appear in this register.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards View --}}
        <div class="d-md-none p-3">
            @forelse ($contracts as $contract)
                <div class="apple-card mb-3 p-3 {{ $contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER ? 'border-warning' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('vendor.pc.contracts.show', $contract) }}" class="apple-po-number">
                                    {{ $contract->contract_number }}
                                </a>
                                @if($contract->revision_no > 0)
                                    <span class="badge bg-secondary-subtle text-secondary small px-2 py-0">REV {{ $contract->revision_no }}</span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">{{ $contract->season?->label ?? '' }}</div>
                        </div>
                        @if($contract->status === \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER)
                            <span class="apple-status-capsule capsule-pending">
                                <span class="capsule-dot"></span>
                                Action
                            </span>
                        @elseif($contract->status === \App\Models\PcContract::STATUS_CONFIRMED)
                            <span class="apple-status-capsule capsule-completed">
                                <span class="capsule-dot"></span>
                                Accepted
                            </span>
                        @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
                            <span class="apple-status-capsule capsule-cancelled">
                                <span class="capsule-dot"></span>
                                Queried
                            </span>
                        @else
                            @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" style="border-color: #ebebf0 !important;">
                        <div>
                            <div class="apple-amount-box text-start">
                                <span class="apple-currency">{{ $contract->currency?->code ?? 'IDR' }}</span>
                                <span class="apple-amount-value">{{ number_format($contract->lines->sum('line_total'), 2) }}</span>
                            </div>
                            <span class="small text-muted">{{ $contract->lines->count() }} line(s)</span>
                        </div>
                        <a href="{{ route('vendor.pc.contracts.show', $contract) }}" class="apple-btn-primary py-1 px-3" style="font-size: 0.82rem;">
                            <span>Review</span>
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-muted">No Preliminary Contracts found in this view.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $contracts->links('custom-pagination') }}</div>
</div>

<!-- Apple Toast for Copy Action -->
<div id="appleToast" class="apple-toast">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span id="appleToastText">Contract number copied</span>
</div>

@include('partials.apple-theme')
@endsection
