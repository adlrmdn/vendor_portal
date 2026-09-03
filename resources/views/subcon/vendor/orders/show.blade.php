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
            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 mb-2 px-2.5 py-1 fw-semibold small">Subcontractor Portal</span>
            <h2 class="mb-0 fw-bold text-dark">{{ $g['article_name'] ?? $summary['style'] ?? $order->title }}</h2>
            <small class="text-muted font-monospace">{{ $order->order_number }}@if(!empty($order->production_group)) <span class="mx-1">·</span> PRG: {{ $order->production_group }}@endif</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('subcon.vendor.orders') }}" class="btn btn-outline-secondary px-3 shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            @if($order->canPrintLabels())
                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm" style="min-width: 210px;">
                    <i class="fas fa-print"></i> <span class="text-center">Print Store Labels</span>
                </a>
                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'warehouse']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm" style="min-width: 210px;">
                    <i class="fas fa-warehouse"></i> <span class="text-center">Print WH Labels</span>
                </a>
            @else
                <span class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm disabled" style="min-width: 210px;" tabindex="-1" aria-disabled="true"
                      title="Unlocks after gramasi &amp; blister approval">
                    <i class="fas fa-lock"></i> <span class="text-center">Print Store Labels</span>
                </span>
                <span class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 lh-sm shadow-sm disabled" style="min-width: 210px;" tabindex="-1" aria-disabled="true"
                      title="Unlocks after gramasi &amp; blister approval">
                    <i class="fas fa-lock"></i> <span class="text-center">Print WH Labels</span>
                </span>
            @endif
        </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2 pt-3 mt-1 border-top border-light-subtle">
        @foreach($order->progressSteps() as $i => $s)
            @php $cls = $s['done'] ? 'success' : ($s['active'] ? 'primary' : 'secondary'); @endphp
            <span class="badge bg-{{ $cls }}-subtle text-{{ $cls }} border border-{{ $cls }} border-opacity-25 px-2.5 py-1 fw-semibold small">
                @if($s['done'])<i class="fas fa-check-circle me-1"></i>@elseif($s['active'])<i class="fas fa-dot-circle me-1"></i>@endif
                {{ $i + 1 }}. {{ $s['label'] }}
            </span>
            @if(!$loop->last)<i class="fas fa-chevron-right text-muted small"></i>@endif
        @endforeach
    </div>
    
    @if($g)
        <!-- Style Specifications Badges -->
        <div class="d-flex align-items-center gap-2 mb-3 mt-3 pt-3 flex-wrap border-top border-light-subtle">
            <span class="badge bg-light text-primary border border-primary border-opacity-10 px-2.5 py-1 fw-semibold small">Season: {{ $g['season'] ?: '—' }}</span>
            <span class="badge bg-light text-secondary border border-secondary border-opacity-10 px-2.5 py-1 fw-semibold small">World: {{ $g['world'] ?: '—' }}</span>
            <span class="badge bg-light text-success border border-success border-opacity-10 px-2.5 py-1 fw-semibold small">Dept: {{ $g['department'] ?: '—' }}</span>
            <span class="badge bg-light text-dark border border-dark border-opacity-10 px-2.5 py-1 fw-semibold small">Category: {{ $g['category'] ?: '—' }} / {{ $g['subcategory'] ?: '—' }}</span>
            @if($g['colour'])
                <span class="badge bg-light text-danger border border-danger border-opacity-10 px-2.5 py-1 fw-semibold small">Color: {{ $g['colour'] }}</span>
            @endif
        </div>
    @endif
    
    <!-- Cohesive Order Info Row -->
    <div class="d-flex align-items-center flex-wrap gap-4 pt-3 border-top border-light-subtle small text-muted">
        <div>
            <i class="fas fa-calendar-alt text-secondary me-1.5"></i>
            <span class="fw-semibold text-secondary">Order Date:</span>
            <span class="text-dark fw-semibold ms-1">{{ $order->order_date->format('d M Y') }}</span>
        </div>
        <div>
            <i class="fas fa-calendar-check text-secondary me-1.5"></i>
            <span class="fw-semibold text-secondary">Due Date:</span>
            <span class="text-dark fw-semibold ms-1">{{ $order->due_date?->format('d M Y') ?? '—' }}</span>
        </div>
        @if($g)
            <div>
                <i class="fas fa-boxes text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">Total Order Qty:</span>
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
            <span class="fw-semibold text-secondary">Status:</span>
            @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
            <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} border border-{{ $badge }} border-opacity-10 px-2.5 py-0.5 fw-semibold small ms-1" style="font-size: 0.75rem;">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
        </div>
        @if($order->description)
            <div class="w-100 mt-2 text-secondary">
                <i class="fas fa-align-left text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">Description:</span>
                <span class="text-dark ms-1">{{ $order->description }}</span>
            </div>
        @endif
        @if($order->notes)
            <div class="w-100 mt-1 text-muted">
                <i class="fas fa-sticky-note text-secondary me-1.5"></i>
                <span class="fw-semibold text-secondary">Notes:</span>
                <span class="ms-1">{{ $order->notes }}</span>
            </div>
        @endif
    </div>
</div>

@if($order->reject_reason)
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" style="border-radius:12px;">
        <i class="fas fa-triangle-exclamation mt-1"></i>
        <div>
            <strong>{{ $order->reject_gate === 'gramasi' ? 'Gramasi & blister capacity' : 'Cutting report' }} rejected{{ $order->rejected_at ? ' '.$order->rejected_at->diffForHumans() : '' }}.</strong>
            <div class="mt-1" style="white-space:pre-wrap;">{{ $order->reject_reason }}</div>
        </div>
    </div>
@endif

@php
    $mode = $order->canEditCutting() ? 'cutting' : ($order->canEditGramasi() ? 'gramasi' : 'view');
@endphp

<div class="row">
    <div class="col-12">

        @include('subcon.partials.material-return', [
            'order' => $order,
            'materialReturnTask' => $materialReturnTask,
            'dispatchRoute' => null,
        ])

        {{-- Awaiting-approval / completion banners --}}
        @if($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW)
            <div class="alert alert-warning d-flex align-items-center gap-2 border-0 shadow-sm" role="alert" style="border-radius:12px;">
                <i class="fas fa-hourglass-half"></i>
                <div>Your <strong>{{ $order->cutting_partial ? 'partial ' : '' }}cutting report</strong> has been submitted and is awaiting admin approval. Values are locked until a decision is made.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING && $order->cutting_partial && $order->cutting_approved_at)
            <div class="alert alert-info d-flex align-items-center gap-2 border-0 shadow-sm" role="alert" style="border-radius:12px;">
                <i class="fas fa-circle-half-stroke"></i>
                <div>Your <strong>partial cutting report</strong> was approved on {{ $order->cutting_approved_at->format('d M Y') }}. Continue entering the remaining quantities and submit again — untick <strong>Partial report</strong> on the final submission to move on to gramasi.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_GRAMASI_REVIEW)
            <div class="alert alert-warning d-flex align-items-center gap-2 border-0 shadow-sm" role="alert" style="border-radius:12px;">
                <i class="fas fa-hourglass-half"></i>
                <div>Your <strong>gramasi &amp; blister capacity</strong> have been submitted and are awaiting admin approval.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_WAITING_DISTRIBUTION)
            <div class="alert alert-info d-flex align-items-center gap-2 border-0 shadow-sm" role="alert" style="border-radius:12px;">
                <i class="fas fa-info-circle"></i>
                <div>Your gramasi &amp; blister capacity have been approved. <strong>Awaiting packing label generation.</strong> Please check your email for the "Generate Packing Labels" link to unlock printing.</div>
            </div>
        @elseif($order->workflow_stage === \App\Models\SubconOrder::STAGE_COMPLETED)
            <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm" role="alert" style="border-radius:12px;">
                <i class="fas fa-check-circle"></i>
                <div>This work order is <strong>completed</strong>.</div>
            </div>
        @endif

        @if(!empty($productionGroups))

            {{-- STAGE 1: cutting report entry --}}
            @if($mode === 'cutting')
                <form id="stageForm" method="POST" action="{{ route('subcon.vendor.orders.submit-cutting', $order->id) }}">
                    @csrf
                    @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'mode' => 'cutting'])

                    @include('subcon.partials.fabric-reconciliation', ['fabricLines' => $fabricLines, 'fabricRecon' => $fabricRecon, 'accessoryLines' => $accessoryLines, 'accessoryRecon' => $accessoryRecon, 'editable' => true])

                    @include('subcon.partials.blister-input', ['order' => $order, 'editable' => false, 'required' => false])
                </form>

                @include('subcon.partials.delivery-note-attachment', [
                    'order' => $order,
                    'materialReturns' => $materialReturns,
                    'canSubmit' => $order->materialReturnVendorWindowOpen(),
                    'uploadRoute' => route('subcon.vendor.orders.material-return', $order->id),
                ])

                @include('subcon.partials.vendor-remarks-form', ['order' => $order])

                <div class="card mt-3 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <a href="{{ route('subcon.vendor.orders.download-template', $order->id) }}" class="btn btn-outline-success btn-sm px-3 fw-semibold shadow-sm">
                                <i class="fas fa-file-excel me-1"></i> Download Template
                            </a>
                            <div class="vr text-secondary opacity-25 d-none d-sm-block" style="height: 24px;"></div>
                            <form action="{{ route('subcon.vendor.orders.upload-report', $order->id) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                                @csrf
                                <input class="form-control form-control-sm border shadow-none" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf" required style="width: auto; max-width: 200px;">
                                <button type="submit" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm">
                                    <i class="fas fa-upload me-1"></i> Upload Qty Cut
                                </button>
                            </form>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            {{-- Partial toggle (default: not partial). A partial report is
                                 flagged to the approver, and after approval the order stays
                                 at this stage so the remaining quantities can be submitted. --}}
                            <div class="form-check form-switch m-0" title="Mark this submission as partial: after approval you can continue entering the remaining quantities.">
                                <input class="form-check-input" type="checkbox" role="switch" id="cuttingPartialToggle"
                                       name="is_partial" value="1" form="stageForm" @checked($order->cutting_partial)>
                                <label class="form-check-label small fw-semibold" for="cuttingPartialToggle">Partial report</label>
                            </div>
                            <button type="submit" form="stageForm" class="btn btn-success shadow-sm px-4 fw-semibold" onclick="return confirmStageSubmit(this, document.getElementById('cuttingPartialToggle').checked ? 'Submit this PARTIAL cutting report for approval? After approval you can continue entering the remaining quantities.' : 'Submit the cutting report for approval? You will not be able to edit it until a decision is made.');">
                                <i class="fas fa-paper-plane me-1"></i> Submit Cutting Report
                            </button>
                        </div>
                    </div>
                </div>

            {{-- STAGE 2: gramasi + blister entry --}}
            @elseif($mode === 'gramasi')
                <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm mb-3" role="alert" style="border-radius:12px;">
                    <i class="fas fa-check-circle"></i>
                    <div>Cutting report approved. Enter <strong>gramasi (g)</strong> per size and the <strong>blister capacity</strong>, then submit for approval.</div>
                </div>

                <form id="stageForm" method="POST" action="{{ route('subcon.vendor.orders.submit-gramasi', $order->id) }}">
                    @csrf
                    @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'mode' => 'gramasi'])

                    @include('subcon.partials.blister-input', ['order' => $order, 'editable' => true, 'required' => false])
                </form>

                @include('subcon.partials.fabric-reconciliation', [
                    'fabricLines' => $fabricLines, 'fabricRecon' => $fabricRecon,
                    'accessoryLines' => $accessoryLines, 'accessoryRecon' => $accessoryRecon,
                    'editable' => $order->materialReturnVendorWindowOpen(),
                    'standalone' => true,
                    'saveRoute' => route('subcon.vendor.orders.material-reconciliation', $order->id),
                ])

                @include('subcon.partials.delivery-note-attachment', [
                    'order' => $order,
                    'materialReturns' => $materialReturns,
                    'canSubmit' => $order->materialReturnVendorWindowOpen(),
                    'uploadRoute' => route('subcon.vendor.orders.material-return', $order->id),
                ])

                @include('subcon.partials.vendor-remarks-form', ['order' => $order])

                <div class="card mt-4 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <a href="{{ route('subcon.vendor.orders.download-template', $order->id) }}" class="btn btn-outline-success btn-sm px-3 fw-semibold shadow-sm">
                                <i class="fas fa-file-excel me-1"></i> Download Template
                            </a>
                            <div class="vr text-secondary opacity-25 d-none d-sm-block" style="height: 24px;"></div>
                            <form action="{{ route('subcon.vendor.orders.upload-report', $order->id) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 m-0">
                                @csrf
                                <input class="form-control form-control-sm border shadow-none" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf" required style="width: auto; max-width: 200px;">
                                <button type="submit" class="btn btn-outline-primary btn-sm px-3 fw-semibold shadow-sm">
                                    <i class="fas fa-upload me-1"></i> Upload Gramasi
                                </button>
                            </form>
                        </div>
                        <div>
                            <button type="submit" form="stageForm" class="btn btn-success shadow-sm px-4 fw-semibold"
                                    onclick="return confirmStageSubmit(this, 'Submit gramasi &amp; blister capacity for approval?');">
                                <i class="fas fa-paper-plane me-1"></i> Submit Gramasi &amp; Blister
                            </button>
                        </div>
                    </div>
                </div>

            {{-- STAGE 3+: read-only view (review / labels / completed) --}}
            @else
                @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'mode' => 'view'])

                @include('subcon.partials.fabric-reconciliation', [
                    'fabricLines' => $fabricLines, 'fabricRecon' => $fabricRecon,
                    'accessoryLines' => $accessoryLines, 'accessoryRecon' => $accessoryRecon,
                    'editable' => $order->materialReturnVendorWindowOpen(),
                    'standalone' => true,
                    'saveRoute' => route('subcon.vendor.orders.material-reconciliation', $order->id),
                ])

                @if($order->blister_capacity)
                    <div class="text-muted small mb-3"><i class="fas fa-box me-1"></i> Blister capacity: <strong class="text-dark">{{ number_format($order->blister_capacity) }}</strong> pcs / blister</div>
                @endif

                @include('subcon.partials.delivery-note-attachment', [
                    'order' => $order,
                    'materialReturns' => $materialReturns,
                    'canSubmit' => $order->materialReturnVendorWindowOpen(),
                    'uploadRoute' => route('subcon.vendor.orders.material-return', $order->id),
                ])

                @include('subcon.partials.vendor-remarks-form', ['order' => $order])

                @if($order->canComplete())
                    <div class="card mt-2 shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light">
                            <div class="text-secondary small">
                                <i class="fas fa-info-circle me-1"></i> Print your packaging labels, then mark this work order complete.
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm fw-semibold shadow-sm" style="min-width: 210px;">
                                    <i class="fas fa-print"></i> <span class="text-center">Print Store Labels</span>
                                </a>
                                <a href="{{ route('subcon.vendor.orders.print-labels', ['id' => $order->id, 'scope' => 'warehouse']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm fw-semibold shadow-sm" style="min-width: 210px;">
                                    <i class="fas fa-warehouse"></i> <span class="text-center">Print WH Labels</span>
                                </a>
                                <form method="POST" action="{{ route('subcon.vendor.orders.complete', $order->id) }}" class="m-0"
                                      onsubmit="return confirm('Mark this work order as completed?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                                        <i class="fas fa-check me-1"></i> Complete PO
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

        @else
            @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports, 'mode' => 'view'])
        @endif

    </div>
</div>

@push('scripts')
<script>
    function confirmStageSubmit(btn, message) {
        return confirm(message);
    }
</script>
@endpush
@endsection
