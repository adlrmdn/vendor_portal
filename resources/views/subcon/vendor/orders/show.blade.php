@extends('layouts.app')

@section('title', 'Work Order — ' . $order->order_number)

@section('content')
<style>
    .subcon-header {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
    }
    .modern-card {
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        background: #ffffff;
        overflow: hidden;
    }
    .modern-card-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f3f5;
        padding: 18px 24px;
    }
    .modern-card-body {
        padding: 24px;
    }
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
    }
    .status-indicator::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .status-indicator-warning {
        background: #fff9db;
        color: #f08c00;
    }
    .status-indicator-warning::before {
        background: #f08c00;
    }
    .status-indicator-success {
        background: #ebfbee;
        color: #2b8a3e;
    }
    .status-indicator-success::before {
        background: #2b8a3e;
    }
    .status-indicator-info {
        background: #e7f5ff;
        color: #1c7ed6;
    }
    .status-indicator-info::before {
        background: #1c7ed6;
    }
    .status-indicator-secondary {
        background: #f1f3f5;
        color: #868e96;
    }
    .status-indicator-secondary::before {
        background: #868e96;
    }
</style>

@php
    $g = !empty($productionGroups) ? $productionGroups[0] : null;
    $prodBadge = fn ($s) => match ($s) {
        'Completed', 'ReportedFinished' => 'success',
        'StartedUp'                     => 'warning',
        'Released'                      => 'info',
        'CostEstimated', 'Created'      => 'secondary',
        default                         => 'secondary',
    };
@endphp

<!-- Header Action Panel -->
<div class="subcon-header p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 mb-2 px-2.5 py-1 fw-semibold small">{{ __('Subcontractor Portal') }}</span>
            <h2 class="mb-0 fw-bold text-dark">{{ $g['article_name'] ?? $summary['style'] ?? $order->title }}</h2>
            <small class="text-muted font-monospace">{{ $order->order_number }}@if(!empty($order->production_group)) <span class="mx-1">·</span> PRG: {{ $order->production_group }}@endif</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-outline-secondary px-3 shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
            </a>
            @if($order->canPrintLabels())
                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm" style="min-width: 210px;">
                    <i class="fas fa-print"></i> <span class="text-center">{{ __('Print Store Labels') }}</span>
                </a>
                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'warehouse']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm" style="min-width: 210px;">
                    <i class="fas fa-warehouse"></i> <span class="text-center">{{ __('Print WH Labels') }}</span>
                </a>
            @else
                <span class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm disabled" style="min-width: 210px;" tabindex="-1" aria-disabled="true"
                      title="{{ __('Unlocks after gramasi & blister approval') }}">
                    <i class="fas fa-lock"></i> <span class="text-center">{{ __('Print Store Labels') }}</span>
                </span>
                <span class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm disabled" style="min-width: 210px;" tabindex="-1" aria-disabled="true"
                      title="{{ __('Unlocks after gramasi & blister approval') }}">
                    <i class="fas fa-lock"></i> <span class="text-center">{{ __('Print WH Labels') }}</span>
                </span>
            @endif
        </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2 pt-3 mt-1 border-top border-light-subtle">
        @foreach($order->progressSteps() as $i => $s)
            @php $cls = $s['done'] ? 'success' : ($s['active'] ? 'primary' : 'secondary'); @endphp
            <span class="badge bg-{{ $cls }}-subtle text-{{ $cls }} border border-{{ $cls }} border-opacity-25 px-2.5 py-1 fw-semibold small">
                @if($s['done'])<i class="fas fa-check-circle me-1"></i>@elseif($s['active'])<i class="fas fa-dot-circle me-1"></i>@endif
                {{ $i + 1 }}. {{ __($s['label']) }}
            </span>
            @if(!$loop->last)<i class="fas fa-chevron-right text-muted small"></i>@endif
        @endforeach
    </div>
    
    @if($g)
        <!-- Style Specifications Badges -->
        <div class="d-flex align-items-center gap-2 mb-3 mt-3 pt-3 flex-wrap border-top border-light-subtle">
            <span class="badge bg-light text-primary border border-primary border-opacity-10 px-2.5 py-1 fw-semibold small">{{ __('Season') }}: {{ $g['season'] ?? '—' }}</span>
            <span class="badge bg-light text-secondary border border-secondary border-opacity-10 px-2.5 py-1 fw-semibold small">{{ __('World') }}: {{ $g['world'] ?? '—' }}</span>
            <span class="badge bg-light text-success border border-success border-opacity-10 px-2.5 py-1 fw-semibold small">{{ __('Dept') }}: {{ $g['department'] ?? '—' }}</span>
            <span class="badge bg-light text-dark border border-dark border-opacity-10 px-2.5 py-1 fw-semibold small">{{ __('Category') }}: {{ $g['category'] ?? '—' }} / {{ $g['subcategory'] ?? '—' }}</span>
            @if(!empty($g['colour']))
                <span class="badge bg-light text-danger border border-danger border-opacity-10 px-2.5 py-1 fw-semibold small">{{ __('Color') }}: {{ $g['colour'] }}</span>
            @endif
        </div>
    @endif
    
    <!-- Cohesive Order Info Row -->
    <div class="d-flex align-items-center flex-wrap gap-4 pt-3 border-top border-light-subtle small text-muted">
        <div>
            <i class="fas fa-calendar-alt text-secondary me-1.5"></i>
            <span class="fw-semibold text-secondary">{{ __('Order Date') }}:</span>
            <span class="text-dark fw-semibold ms-1">{{ $order->order_date->format('d M Y') }}</span>
        </div>
        <div>
            <i class="fas fa-calendar-check text-secondary me-1.5"></i>
            <span class="fw-semibold text-secondary">{{ __('Due Date') }}:</span>
            <span class="text-dark fw-semibold ms-1">{{ $order->due_date?->format('d M Y') ?? '—' }}</span>
        </div>
        @if($g)
            <div>
                <i class="fas fa-boxes text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">{{ __('Total Order Qty') }}:</span>
                <span class="text-dark fw-semibold ms-1">{{ number_format($g['total_qty']) }} pcs</span>
            </div>
            <div>
                <i class="fas fa-tasks text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">PLM Status:</span>
                <span class="status-indicator status-indicator-{{ $prodBadge($g['plm_status']) }} ms-1">{{ $g['plm_status'] }}</span>
            </div>
        @endif
        <div>
            <i class="fas fa-info-circle text-secondary me-1.5"></i>
            <span class="fw-semibold text-secondary">{{ __('Status') }}:</span>
            @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
            <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} border border-{{ $badge }} border-opacity-10 px-2.5 py-0.5 fw-semibold small ms-1" style="font-size: 0.75rem;">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
        </div>
        @if($order->description)
            <div class="w-100 mt-2 text-secondary">
                <i class="fas fa-align-left text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">{{ __('Description') }}:</span>
                <span class="text-dark ms-1">{{ $order->description }}</span>
            </div>
        @endif
        @if($order->notes)
            <div class="w-100 mt-1 text-muted">
                <i class="fas fa-sticky-note text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">{{ __('Notes') }}:</span>
                <span class="ms-1">{{ $order->notes }}</span>
            </div>
        @endif
    </div>
</div>

@if($order->reject_reason)
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" style="border-radius:12px;">
        <i class="fas fa-triangle-exclamation mt-1"></i>
        <div>
            <strong>{{ $order->reject_gate === 'gramasi' ? __('Gramasi & blister capacity') : __('Cutting report') }} rejected{{ $order->rejected_at ? ' '.$order->rejected_at->diffForHumans() : '' }}.</strong>
            <div class="mt-1" style="white-space:pre-wrap;">{{ $order->reject_reason }}</div>
        </div>
    </div>
@endif

@php
    $mode = $order->canEditCutting() ? 'cutting' : ($order->canEditGramasi() ? 'gramasi' : 'view');
@endphp

<!-- Structured Tabs Navigation -->
<ul class="nav nav-tabs nav-fill mb-4 border-bottom-0 gap-2" id="orderTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold py-3 px-4 rounded-3 border d-flex align-items-center justify-content-center gap-2" id="stage-tab" data-bs-toggle="tab" data-bs-target="#stage-content" type="button" role="tab" aria-controls="stage-content" aria-selected="true">
            <i class="fas fa-layer-group text-primary"></i>
            <span>{{ __('Production Stage') }}</span>
            <span class="badge bg-primary-subtle text-primary ms-1 px-2 py-0.5 small">{{ ucfirst($mode) }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold py-3 px-4 rounded-3 border d-flex align-items-center justify-content-center gap-2" id="recon-tab" data-bs-toggle="tab" data-bs-target="#recon-content" type="button" role="tab" aria-controls="recon-content" aria-selected="false">
            <i class="fas fa-boxes text-success"></i>
            <span>{{ __('Material Recon & Delivery Notes') }}</span>
            @if($materialReturns && count($materialReturns) > 0)
                <span class="badge bg-success-subtle text-success ms-1 px-2 py-0.5 small">{{ count($materialReturns) }}</span>
            @endif
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold py-3 px-4 rounded-3 border d-flex align-items-center justify-content-center gap-2" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily-content" type="button" role="tab" aria-controls="daily-content" aria-selected="false">
            <i class="fas fa-industry text-warning"></i>
            <span>{{ __('Daily Production Summary') }}</span>
        </button>
    </li>
</ul>

<!-- Tab Contents -->
<div class="tab-content" id="orderTabContent">

    {{-- TAB 1: PRODUCTION STAGE --}}
    <div class="tab-pane fade show active" id="stage-content" role="tabpanel" aria-labelledby="stage-tab">
        
        {{-- Awaiting-approval / completion banners --}}
        @if($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW)
            <div class="alert alert-warning d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                <i class="fas fa-hourglass-half"></i>
                <div>Your <strong>{{ $order->cutting_partial ? 'partial ' : '' }}cutting report</strong> has been submitted and is awaiting admin approval. Values are locked until a decision is made.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING && $order->cutting_partial && $order->cutting_approved_at)
            <div class="alert alert-info d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                <i class="fas fa-circle-half-stroke"></i>
                <div>Your <strong>partial cutting report</strong> was approved on {{ $order->cutting_approved_at->format('d M Y') }}. Continue entering the remaining quantities and submit again — untick <strong>Partial report</strong> on the final submission to move on to gramasi.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_GRAMASI_REVIEW)
            <div class="alert alert-warning d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                <i class="fas fa-hourglass-half"></i>
                <div>Your <strong>gramasi &amp; blister capacity</strong> have been submitted and are awaiting admin approval.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_WAITING_DISTRIBUTION)
            <div class="alert alert-info d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                <i class="fas fa-info-circle"></i>
                <div>Your gramasi &amp; blister capacity have been approved. <strong>Awaiting packing label generation.</strong> Please check your email for the "Generate Packing Labels" link to unlock printing.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_COMPLETED)
            <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                <i class="fas fa-check-circle"></i>
                <div>This work order is <strong>completed</strong>.</div>
            </div>
        @endif

        @if(!empty($productionGroups))

            {{-- STAGE 1: Cutting Report Entry --}}
            @if($mode === 'cutting')
                <div class="alert alert-light border border-primary border-opacity-25 d-flex align-items-center justify-content-between p-3 mb-3 rounded-3 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle text-primary fs-5"></i>
                        <span class="small text-secondary">{{ __('Cutting Stage Guidance') }}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link p-0 text-primary text-decoration-none" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="left" title="{{ __('Panduan Laporan Cutting') }}" data-bs-content="{{ __('Isi jumlah cutting per ukuran. Angka dari Produksi Harian otomatis muncul sebagai acuan awal namun tetap dapat Anda edit bebas. Anda juga dapat mengunduh template Excel atau unggah file.') }}">
                        <i class="fas fa-question-circle"></i> {{ __('Guidance') }}
                    </button>
                </div>

                <form id="stageForm" method="POST" action="{{ route('subcon.vendor.orders.submit-cutting', $order->id) }}">
                    @csrf
                    @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'dailyCuttingTotals' => $dailyCuttingTotals, 'mode' => 'cutting'])
                </form>

                {{-- Catatan (Remarks) before final action card --}}
                @include('subcon.partials.vendor-remarks-form', ['order' => $order, 'remarksId' => 'remarks-stage'])

                <div class="card mt-3 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <a href="{{ route('subcon.vendor.orders.download-template', $order->id) }}" class="btn btn-outline-success btn-sm px-3 fw-semibold shadow-sm">
                                <i class="fas fa-file-excel me-1"></i> {{ __('Download Template') }}
                            </a>
                            <div class="vr text-secondary opacity-25 d-none d-sm-block" style="height: 24px;"></div>
                            <form action="{{ route('subcon.vendor.orders.upload-report', $order->id) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                                @csrf
                                <input class="form-control form-control-sm border shadow-none" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf" required style="width: auto; max-width: 200px;">
                                <button type="submit" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm">
                                    <i class="fas fa-upload me-1"></i> {{ __('Upload Qty Cut') }}
                                </button>
                            </form>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="form-check form-switch m-0" title="Mark this submission as partial: after approval you can continue entering the remaining quantities.">
                                <input class="form-check-input" type="checkbox" role="switch" id="cuttingPartialToggle"
                                       name="is_partial" value="1" form="stageForm" @checked($order->cutting_partial)>
                                <label class="form-check-label small fw-semibold" for="cuttingPartialToggle">{{ __('Partial report') }}</label>
                            </div>
                            <button type="submit" form="stageForm" class="btn btn-success shadow-sm px-4 fw-semibold" onclick="return confirmStageSubmit(this, document.getElementById('cuttingPartialToggle').checked ? 'Submit this PARTIAL cutting report for approval? After approval you can continue entering the remaining quantities.' : 'Submit the cutting report for approval? You will not be able to edit it until a decision is made.');">
                                <i class="fas fa-paper-plane me-1"></i> {{ __('Submit Cutting Report') }}
                            </button>
                        </div>
                    </div>
                </div>

            {{-- STAGE 2: Gramasi + Blister Entry --}}
            @elseif($mode === 'gramasi')
                <div class="alert alert-light border border-success border-opacity-25 d-flex align-items-center justify-content-between p-3 mb-3 rounded-3 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle text-success fs-5"></i>
                        <span class="small text-secondary">{{ __('Gramasi & Blister Guidance') }}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link p-0 text-success text-decoration-none" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="left" title="{{ __('Blister Capacity Guidance') }}" data-bs-content="{{ __('Gramasi & Blister Guidance') }}">
                        <i class="fas fa-question-circle"></i> {{ __('Guidance') }}
                    </button>
                </div>

                <form id="stageForm" method="POST" action="{{ route('subcon.vendor.orders.submit-gramasi', $order->id) }}">
                    @csrf
                    @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'mode' => 'gramasi'])

                    @include('subcon.partials.blister-input', ['order' => $order, 'editable' => true, 'required' => false])
                </form>

                {{-- Catatan (Remarks) before final action card --}}
                @include('subcon.partials.vendor-remarks-form', ['order' => $order, 'remarksId' => 'remarks-stage'])

                <div class="card mt-4 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <a href="{{ route('subcon.vendor.orders.download-template', $order->id) }}" class="btn btn-outline-success btn-sm px-3 fw-semibold shadow-sm">
                                <i class="fas fa-file-excel me-1"></i> {{ __('Download Template') }}
                            </a>
                            <div class="vr text-secondary opacity-25 d-none d-sm-block" style="height: 24px;"></div>
                            <form action="{{ route('subcon.vendor.orders.upload-report', $order->id) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                                @csrf
                                <input class="form-control form-control-sm border shadow-none" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf" required style="width: auto; max-width: 200px;">
                                <button type="submit" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm">
                                    <i class="fas fa-upload me-1"></i> {{ __('Upload Gramasi') }}
                                </button>
                            </form>
                        </div>
                        <div>
                            <button type="submit" form="stageForm" class="btn btn-success shadow-sm px-4 fw-semibold"
                                    onclick="return confirmStageSubmit(this, 'Submit gramasi &amp; blister capacity for approval?');">
                                <i class="fas fa-paper-plane me-1"></i> {{ __('Submit Gramasi & Blister') }}
                            </button>
                        </div>
                    </div>
                </div>

            {{-- STAGE 3+: Read-Only View (Review / Labels / Completed) --}}
            @else
                @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'fabricLines' => $fabricLines, 'qcSizeOrderQty' => $qcSizeOrderQty, 'mode' => 'view'])

                @if($order->blister_capacity || $order->sack_capacity)
                    <div class="text-muted small my-3 d-flex flex-wrap align-items-center justify-content-between p-3 bg-light rounded-3 border">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            @if($order->blister_capacity)
                                <span><i class="fas fa-box text-primary me-1"></i> {{ __('Blister Capacity') }}: <strong class="text-dark">{{ number_format($order->blister_capacity) }}</strong> {{ __('pcs / blister') }}</span>
                            @endif
                            @if($order->sack_capacity)
                                <span><i class="fas fa-gift text-success me-1"></i> {{ __('Sack (Karung) Capacity') }}: <strong class="text-dark">{{ number_format($order->sack_capacity) }}</strong> {{ __('pcs / sack') }}</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-link p-0 text-secondary text-decoration-none" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="left" title="{{ __('Packaging Capacity') }}" data-bs-content="{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}">
                                <i class="fas fa-info-circle text-primary fs-5"></i>
                            </button>
                            <span class="small text-muted d-none d-lg-inline">{{ __('Capacity of finished goods that can be loaded into blister pack or sack pack.') }}</span>
                        </div>
                    </div>
                @endif

                {{-- Catatan (Remarks) before final action card --}}
                @include('subcon.partials.vendor-remarks-form', ['order' => $order, 'remarksId' => 'remarks-stage'])

                @if($order->canComplete())
                    <div class="card mt-2 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                            <div class="text-secondary small">
                                <i class="fas fa-info-circle me-1"></i> {{ __('Print your packaging labels, then mark this work order complete.') }}
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm fw-semibold shadow-sm" style="min-width: 210px;">
                                    <i class="fas fa-print"></i> <span class="text-center">{{ __('Print Store Labels') }}</span>
                                </a>
                                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'online']) }}" target="_blank" class="btn btn-outline-success d-inline-flex align-items-center justify-content-center gap-2 lh-sm fw-semibold shadow-sm" style="min-width: 210px;">
                                    <i class="fas fa-print"></i> <span class="text-center">{{ __('Print Online Labels') }}</span>
                                </a>
                                <form method="POST" action="{{ route('subcon.vendor.orders.complete', $order->id) }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2 lh-sm fw-semibold shadow-sm px-4"
                                            onclick="return confirmStageSubmit(this, 'Mark this work order as complete? This finishes your production workflow.');">
                                        <i class="fas fa-check-circle"></i> {{ __('Complete Work Order') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            @if($order->canReviseCutting())
                <div class="card border-warning border-opacity-50 shadow-sm mt-4 mb-3 rounded-3" style="overflow: hidden;">
                    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-edit text-warning fs-5"></i>
                            <div>
                                <h6 class="mb-0 text-dark fw-bold">{{ __('Need to revise cutting quantities?') }}</h6>
                                <div class="small text-muted">
                                    {{ __('Cutting was approved on :date. You can submit a revision if wrong data was sent.', ['date' => $order->cutting_approved_at ? $order->cutting_approved_at->format('d M Y H:i') : '—']) }}
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-outline-warning text-dark btn-sm fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#cuttingRevisionCollapse" aria-expanded="false" aria-controls="cuttingRevisionCollapse">
                            <i class="fas fa-pencil-alt me-1"></i> {{ __('Revise Cutting Report') }}
                        </button>
                    </div>
                    <div class="collapse" id="cuttingRevisionCollapse">
                        <div class="card-body p-4 bg-white border-top">
                            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                                <i class="fas fa-exclamation-triangle fs-5"></i>
                                <span class="small">{{ __('Note: Submitting this revision will return the work order to admin cutting review.') }}</span>
                            </div>
                            <form id="cuttingRevisionForm" method="POST" action="{{ route('subcon.vendor.orders.submit-cutting', $order->id) }}">
                                @csrf
                                @include('subcon.partials.production-detail', [
                                    'productionGroups' => $productionGroups,
                                    'cuttingReports' => $cuttingReports,
                                    'dailyCuttingTotals' => $dailyCuttingTotals ?? [],
                                    'mode' => 'cutting'
                                ])

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-warning shadow-sm px-4 fw-semibold" onclick="return confirmStageSubmit(this, '{{ __('Submit this cutting revision for approval? The work order will return to cutting review.') }}');">
                                        <i class="fas fa-paper-plane me-1"></i> {{ __('Submit Cutting Revision for Approval') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

        @else
            @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'fabricLines' => $fabricLines, 'qcSizeOrderQty' => $qcSizeOrderQty, 'mode' => 'view'])
            @include('subcon.partials.vendor-remarks-form', ['order' => $order, 'remarksId' => 'remarks-stage'])
        @endif
    </div>

    {{-- TAB 2: MATERIAL RECONCILIATION & DELIVERY NOTES --}}
    <div class="tab-pane fade" id="recon-content" role="tabpanel" aria-labelledby="recon-tab">
        <div class="alert alert-light border border-info border-opacity-25 d-flex align-items-center justify-content-between p-3 mb-3 rounded-3 shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-info-circle text-info fs-5"></i>
                <span class="small text-secondary">{{ __('Material Recon Independent Guidance') }}</span>
            </div>
        </div>

        @include('subcon.partials.fabric-reconciliation', [
            'fabricLines' => $fabricLines,
            'fabricRecon' => $fabricRecon,
            'accessoryLines' => $accessoryLines,
            'accessoryRecon' => $accessoryRecon,
            'accessoryClaimByLabel' => $accessoryClaimByLabel ?? [],
            'editable' => $order->materialReturnVendorWindowOpen(),
            'standalone' => true,
            'saveRoute' => route('subcon.vendor.orders.material-reconciliation', $order->id),
        ])

        {{-- Catatan (Remarks) before the last element (Delivery Notes) --}}
        @include('subcon.partials.vendor-remarks-form', ['order' => $order, 'remarksId' => 'remarks-recon'])

        @include('subcon.partials.delivery-note-attachment', [
            'order' => $order,
            'materialReturns' => $materialReturns,
            'canSubmit' => $order->materialReturnVendorWindowOpen(),
            'uploadRoute' => route('subcon.vendor.orders.material-return', $order->id),
            'viewerRole' => 'vendor',
            'deleteRouteName' => 'subcon.vendor.orders.material-return.delete',
            'deleteRouteParam' => $order->id,
        ])
    </div>

    {{-- TAB 3: DAILY PRODUCTION SUMMARY --}}
    <div class="tab-pane fade" id="daily-content" role="tabpanel" aria-labelledby="daily-tab">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 mb-3 bg-light rounded-3 border shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-info-circle text-warning fs-5"></i>
                <span class="small text-secondary">{{ __('Daily Production Sync Guidance') }}</span>
            </div>
            <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-sm btn-primary shadow-sm fw-semibold">
                <i class="fas fa-external-link-alt me-1"></i> {{ __('Open Daily Production Board') }}
            </a>
        </div>

        @if(!empty($dailyProductionSummary) && !empty($dailyProductionSummary['totals']) && array_sum(array_map('array_sum', $dailyProductionSummary['totals'])) > 0)
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-bar text-primary me-2"></i>{{ __('Total Logged Output') }}</h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 px-2.5 py-1">
                        {{ $dailyProductionSummary['style']?->status === 'closed' ? __('Closed') : __('Active') }}
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-3">{{ __('Stage') }}</th>
                                    @foreach($dailyProductionSummary['sizes'] as $prodId => $sizeName)
                                        <th class="text-center">{{ $sizeName }}</th>
                                    @endforeach
                                    <th class="text-end pe-3">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyProductionSummary['totals'] as $stageKey => $sizeMap)
                                    @php $stageTotal = array_sum($sizeMap); @endphp
                                    <tr>
                                        <td class="ps-3 fw-semibold text-dark">
                                            {{ ucfirst(str_replace('_', ' ', $stageKey)) }}
                                        </td>
                                        @foreach($dailyProductionSummary['sizes'] as $prodId => $sizeName)
                                            <td class="text-center">{{ number_format($sizeMap[$prodId] ?? 0) }}</td>
                                        @endforeach
                                        <td class="text-end pe-3 fw-bold text-primary">{{ number_format($stageTotal) }} pcs</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm p-4 text-center rounded-3 bg-light my-3">
                <div class="py-4">
                    <i class="fas fa-industry fa-3x text-muted opacity-50 mb-3"></i>
                    <h5 class="fw-bold text-dark">{{ __('No daily production logs recorded yet for this work order.') }}</h5>
                    <p class="text-muted small mb-4">Papan Produksi Harian memungkinkan Anda mencatat pergerakan produksi per hari secara terpisah dan bertahap.</p>
                    <a href="{{ route('subcon.vendor.daily-production.index') }}" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="fas fa-plus me-1"></i> {{ __('Start Daily Production Logging') }}
                    </a>
                </div>
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
    function confirmStageSubmit(btn, message) {
        return confirm(message);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, { html: true, trigger: 'hover focus' })
        })
    });
</script>
@endpush
@endsection
