@extends('layouts.app')

@php $styleName = ($subcon ?? null) && $subcon->title ? $subcon->title : null; @endphp

@section('title', 'Director Authorization'.($styleName ? ' - '.$styleName : ''))
@section('bare', '1')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format(round((float) ($v ?? 0)), 0, ',', '.');
    $n = fn ($v) => number_format((float) ($v ?? 0), 0, ',', '.');
    $sigs = $report['signatures'] ?? null;
    $totals = $report['totals'] ?? null;
    $ded = $report['deductions'] ?? null;
    $fabricDeductionLines = $report ? $report['fabricLines']->filter(fn ($f) => ($f->deduction ?? 0) > 0)->values() : collect();
    $manualLines = $report ? $report['deductionLines'] : collect();
    $grandTotal = $report
        ? ($ded['rejectProduksiPenalty'] + $ded['barangHilangPenalty'] + $fabricDeductionLines->sum('deduction') + $manualLines->sum('amount'))
        : 0;
    // Fabric-bottleneck cut plan (see subcon.partials.production-detail) — a
    // garment needs ALL its fabrics, so the achievable plan is capped by the
    // scarcest one. Null until at least one fabric's consumption is entered.
    $cuttPlanTotal = collect($fabricLines ?? [])->pluck('cutt_plan')->filter(fn ($v) => $v !== null)->min();
@endphp
<div class="container-fluid py-5 px-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill text-bg-dark">Director Authorization</span>
                    </div>
                    <h4 class="fw-bold mb-1">Director Authorization @if($styleName)<span class="text-muted fw-normal">- {{ $styleName }}</span>@endif</h4>
                    <p class="text-muted mb-4">Final review of this packaging inspection. It has been confirmed by the factory representative and approved by MD Production. <strong>Authorizing completes the project</strong>: the Invoice{{ $grandTotal > 0 ? ' and Deduction' : '' }} RPA jobs are queued and all parties receive the fully-signed report.</p>

                    <dl class="row small mb-4">
                        @if($subcon)
                            <dt class="col-5 col-sm-3 text-muted fw-normal">Work Order</dt>
                            <dd class="col-7 col-sm-9 fw-semibold">{{ $subcon->order_number }}</dd>
                        @endif
                        @if($productionGroup)
                            <dt class="col-5 col-sm-3 text-muted fw-normal">Production Group</dt>
                            <dd class="col-7 col-sm-9">{{ $productionGroup }}</dd>
                        @endif
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Project</dt>
                        <dd class="col-7 col-sm-9">{{ $row->project_id }}</dd>
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Session</dt>
                        <dd class="col-7 col-sm-9">{{ $row->session_id }}</dd>
                    </dl>

                    @include('subcon.partials.remarks', ['remarks' => $subcon->remarks ?? null, 'label' => 'Vendor Remarks'])
                    @include('subcon.partials.remarks', ['remarks' => $row->remarks ?? null, 'label' => 'QC Remarks'])
                    @include('subcon.partials.remarks', ['remarks' => $row->ho_remarks ?? null, 'label' => 'MD Production Remarks'])

                    @if($report)
                        <!-- Nav Tabs -->
                        <ul class="nav nav-tabs mb-3" id="approvalTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-tab-pane" type="button" role="tab" aria-controls="summary-tab-pane" aria-selected="true">
                                    <i class="fas fa-list me-1"></i> Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="pdf-tab" data-bs-toggle="tab" data-bs-target="#pdf-tab-pane" type="button" role="tab" aria-controls="pdf-tab-pane" aria-selected="false">
                                    <i class="fas fa-file-pdf me-1"></i> PDF Document
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="approvalTabContent">
                            <!-- Summary Tab -->
                            <div class="tab-pane fade show active" id="summary-tab-pane" role="tabpanel" aria-labelledby="summary-tab" tabindex="0">
                                {{-- Result + prior signatures --}}
                                <div class="row g-3 mb-4 mt-1">
                                    <div class="col-6 col-md-3">
                                        <div class="border rounded-3 p-3 text-center h-100 d-flex flex-column">
                                            <div class="small text-muted text-uppercase fw-semibold" style="font-size:.68rem;">Result</div>
                                            @php $res = strtoupper((string) ($report['session']->result ?? 'PENDING')) ?: 'PENDING'; @endphp
                                            <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                                                <div class="fw-bold fs-5 {{ $res === 'PASSED' ? 'text-success' : ($res === 'FAILED' ? 'text-danger' : 'text-warning') }}">{{ $res }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @foreach([
                                        ['label' => 'Inspected By', 'sig' => $sigs['inspector'], 'role' => $report['session']->inspector ?: 'Inspector'],
                                        ['label' => 'Confirmed By', 'sig' => $sigs['factory'], 'role' => 'Factory Representative'],
                                        ['label' => 'Approved By', 'sig' => $sigs['ho'], 'role' => 'MPG HO - MD Production'],
                                    ] as $box)
                                        <div class="col-6 col-md-3">
                                            <div class="border rounded-3 p-3 text-center h-100">
                                                <div class="small text-muted text-uppercase fw-semibold" style="font-size:.68rem;">{{ $box['label'] }}</div>
                                                @if($box['sig']['state'] === 'signed')
                                                    <div class="text-success fw-semibold small mt-1"><i class="fas fa-circle-check me-1"></i>Digitally Signed</div>
                                                @elseif($box['sig']['state'] === 'rejected')
                                                    <div class="text-danger fw-semibold small mt-1"><i class="fas fa-circle-xmark me-1"></i>Rejected</div>
                                                @else
                                                    <div class="text-muted fst-italic small mt-1">Awaiting</div>
                                                @endif
                                                <div class="small text-muted mt-1" style="font-size:.7rem;">{{ $box['sig']['name'] ?: $box['role'] }}</div>
                                                @if($box['sig']['date'])<div class="text-muted" style="font-size:.65rem;">{{ $box['sig']['date'] }}</div>@endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Per-size cutting/production detail (read-only) — same data the
                                     HO gate shows, so the Director sees exactly what was approved. --}}
                                <h6 class="fw-semibold mb-2">Cutting Report Detail (per size)</h6>
                                <div class="mb-4">
                                    @include('subcon.partials.production-detail', [
                                        'productionGroups' => $productionGroups ?? [],
                                        'cuttingReports' => $cuttingReports ?? collect(),
                                        'fabricLines' => $fabricLines ?? [],
                                        'mode' => 'view',
                                    ])
                                </div>

                                {{-- Yield summary (read-only) --}}
                                <h6 class="fw-semibold mb-2">Production Yield Summary</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered align-middle small mb-0">
                                        <thead class="table-light">
                                            <tr class="text-center">
                                                <th>Order Qty</th><th>Cut Plan</th><th>Cutting Qty</th><th>Good Qty</th>
                                                <th>Total Reject</th><th>Total Delivery FG</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="text-center">
                                                <td>{{ $n($totals['orderQty']) }}</td>
                                                <td>{{ $cuttPlanTotal !== null ? $n($cuttPlanTotal) : '—' }}</td>
                                                <td>{{ $n($totals['cuttingQty']) }}</td>
                                                <td class="fw-semibold text-success">{{ $n($totals['goodGarments']) }}</td>
                                                <td class="fw-semibold {{ $totals['totalReject'] > 0 ? 'text-danger' : '' }}">{{ $n($totals['totalReject']) }}</td>
                                                <td>{{ $n($totals['totalDeliveryFG']) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Fabric consumption / reconciliation (read-only) --}}
                                @if($subcon)
                                    <h6 class="fw-semibold mb-2">Fabric Consumption</h6>
                                    @include('subcon.partials.consumption-input', [
                                        'order' => $subcon,
                                        'fabricLines' => $fabricLines ?? [],
                                        'totalCut' => $totalCut ?? 0,
                                        'editable' => false,
                                    ])
                                    <div class="form-text mb-4" style="font-size:.72rem;">
                                        Cutt Plan = ROUNDDOWN((Fabric Sent − Retur Kain) ÷ Cons. Plan).
                                        Actual Cons. = (Fabric Sent − Retur Kain) ÷ Total Qty Cut ({{ $n($totalCut ?? 0) }}).
                                        Overconsumption = (Actual Cons. − Cons. Plan) ÷ Cons. Plan.
                                        Deduction = MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price — charged only when Overconsumption exceeds 3%.
                                    </div>
                                @endif

                                {{-- Deductions (read-only) --}}
                                <h6 class="fw-semibold mb-2 mt-4">Deductions</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered align-middle small mb-0">
                                        <thead class="table-light">
                                            <tr><th>Description</th><th class="text-end">Amount</th></tr>
                                        </thead>
                                        <tbody>
                                            @if($ded['exceedingRejectQty'] > 0)
                                                <tr><td>Production Reject Penalty (Exceeding 1% Limit) — {{ $ded['exceedingRejectQty'] }} pcs</td><td class="text-end">{{ $rp($ded['rejectProduksiPenalty']) }}</td></tr>
                                            @endif
                                            @if($ded['sumBarangHilang'] > 0)
                                                <tr><td>Lost Items Penalty (Barang Hilang) — {{ $ded['sumBarangHilang'] }} pcs</td><td class="text-end">{{ $rp($ded['barangHilangPenalty']) }}</td></tr>
                                            @endif
                                            @foreach($fabricDeductionLines as $f)
                                                <tr><td>Fabric Overconsumption{{ $f->label ? ' — '.$f->label : '' }}@if($f->overconsumption !== null) <span class="text-muted">({{ number_format($f->overconsumption * 100, 2) }}%)</span>@endif</td><td class="text-end">{{ $rp($f->deduction) }}</td></tr>
                                            @endforeach
                                            @foreach($manualLines as $line)
                                                <tr><td>{{ $line->description }}@if($line->created_by) <span class="text-muted">(added by {{ $line->created_by }})</span>@endif</td><td class="text-end">{{ $rp($line->amount) }}</td></tr>
                                            @endforeach
                                            @if($grandTotal <= 0)
                                                <tr><td colspan="2" class="text-center text-muted py-3">No deductions for this inspection.</td></tr>
                                            @else
                                                <tr class="table-light fw-bold"><td class="text-end">Total Deductions</td><td class="text-end">{{ $rp($grandTotal) }}</td></tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- PDF Document Tab -->
                            <div class="tab-pane fade" id="pdf-tab-pane" role="tabpanel" aria-labelledby="pdf-tab" tabindex="0">
                                <div class="border rounded-3 overflow-hidden bg-light mb-4" style="height: 600px;">
                                    <iframe src="{{ route('qc.document', ['token' => $token]) }}?t={{ time() }}" style="width: 100%; height: 100%; border: none;"></iframe>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning small">The inspection summary could not be loaded right now — the attached PDF in the email carries the full report. You can still authorize or reject below.</div>
                    @endif

                    <form method="POST" action="{{ route('qc.director-approve.submit', ['token' => $token]) }}"
                          onsubmit="return confirm('Authorize this inspection? This completes the project, queues the Invoice/Deduction RPA jobs, and notifies all parties.');">
                        @csrf
                        {{-- Recipient marker from the per-approver email link — attributes the signature. --}}
                        @if(request('as'))<input type="hidden" name="as" value="{{ request('as') }}">@endif
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <a href="{{ route('qc.director-decline', array_filter(['token' => $token, 'as' => request('as')])) }}" class="btn btn-outline-danger px-4 fw-semibold">
                                <i class="fas fa-circle-xmark me-1"></i> Reject
                            </a>
                            <button type="submit" class="btn btn-dark px-4 fw-semibold shadow-sm">
                                <i class="fas fa-stamp me-1"></i> Authorize &amp; Sign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">This link is unique to this inspection and requires no login. Rejecting sends it back to Report Validation for MD Production to re-validate and send.</p>
        </div>
    </div>
</div>
@endsection
