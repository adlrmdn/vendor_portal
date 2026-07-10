@extends('layouts.app')

@php $styleName = ($subcon ?? null) && $subcon->title ? $subcon->title : null; @endphp

@section('title', 'Final Approval'.($styleName ? ' - '.$styleName : ''))
@section('bare', '1')

@section('content')
<div class="container-fluid py-5 px-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill text-bg-warning">Final Approval</span>
                    </div>
                    <h4 class="fw-bold mb-1">Final Approval @if($styleName)<span class="text-muted fw-normal">- {{ $styleName }}</span>@endif</h4>
                    <p class="text-muted mb-4">Review this packaging inspection and sign off. Fabric consumption is prefilled from the cutting-report approval — <strong>revise it here if needed</strong> and it will be recalculated and saved on approval. Record any deductions below, then <strong>Approve</strong> or <strong>Reject</strong>.</p>

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
                        <dt class="col-5 col-sm-3 text-muted fw-normal">Total Qty Cut</dt>
                        <dd class="col-7 col-sm-9">{{ number_format($totalCut) }} pcs</dd>
                    </dl>

                    @include('subcon.partials.remarks', ['remarks' => $subcon->remarks ?? null])

                    @include('subcon.partials.production-detail', [
                        'productionGroups' => $productionGroups ?? [],
                        'cuttingReports' => $cuttingReports ?? collect(),
                        'mode' => 'view',
                    ])

                    <form method="POST" action="{{ route('qc.ho-approve.submit', ['token' => $token]) }}"
                          onsubmit="return confirm('Submit Head Office approval? The entered consumption will be recalculated and saved, and the inspection signed off.');">
                        @csrf

                        {{-- Consumption inputs (calculation+approval), same engine as the cutting gate --}}
                        @if($subcon)
                            @include('subcon.partials.consumption-input', [
                                'order' => $subcon,
                                'fabricLines' => $fabricLines,
                                'totalCut' => $totalCut,
                                'editable' => true,
                            ])
                        @endif

                        {{-- Optional deductions --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold mb-0">Deductions <span class="text-muted fw-normal small">(optional)</span></label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-deduction"><i class="fas fa-plus me-1"></i> Add row</button>
                            </div>
                            <div id="deduction-rows"></div>
                            <div class="form-text">Any cost deducted from the vendor (e.g. label reprint). Leave empty if none.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('qc.ho-decline', ['token' => $token]) }}"
                               class="btn btn-outline-danger px-4 fw-semibold"
                               onclick="return confirm('Reject this inspection at the Head Office stage? This records a rejection and cannot be undone.');">
                                <i class="fas fa-circle-xmark me-1"></i> Reject
                            </a>
                            <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                                <i class="fas fa-circle-check me-1"></i> Approve &amp; Sign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">This link is unique to this inspection.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dRows = document.getElementById('deduction-rows');
    let dIdx = 0;
    document.getElementById('add-deduction').addEventListener('click', function () {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-center';
        div.innerHTML =
            '<div class="col-7"><input type="text" class="form-control form-control-sm" name="deductions[' + dIdx + '][description]" placeholder="Description (e.g. Label reprint cost)"></div>' +
            '<div class="col-4"><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input type="number" step="0.01" min="0" class="form-control" name="deductions[' + dIdx + '][amount]" placeholder="Amount"></div></div>' +
            '<div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-deduction" title="Remove"><i class="fas fa-times"></i></button></div>';
        dRows.appendChild(div);
        dIdx++;
    });
    dRows.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-deduction');
        if (btn) btn.closest('.row').remove();
    });
});
</script>
@endsection
