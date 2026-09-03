@extends('layouts.app')

@php
    $styleName = ($subcon ?? null) && $subcon->title ? $subcon->title : null;
    $validateMode = $validateMode ?? false;
@endphp

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
                        @if($validateMode)
                            <span class="badge rounded-pill text-bg-success"><i class="fas fa-signature me-1"></i>Approved — awaiting Validate &amp; Send</span>
                        @endif
                    </div>
                    <h4 class="fw-bold mb-1">Final Approval @if($styleName)<span class="text-muted fw-normal">- {{ $styleName }}</span>@endif</h4>
                    @if($validateMode)
                        <p class="text-muted mb-3">This inspection has been <strong>approved and signed</strong> and the RAF production run was queued. Validate the numbers below — <strong>revise them if needed</strong> and they will be recalculated and saved on send — then press <strong>Validate &amp; Send Approval</strong> to notify the Director.</p>
                    @else
                        <p class="text-muted mb-3">Review this packaging inspection and sign off. Fabric consumption is prefilled from the cutting-report approval — <strong>revise it here if needed</strong> and it will be recalculated and saved on approval. Record any deductions below, then <strong>Review &amp; Approve</strong> or <strong>Reject</strong>. Approving queues the RAF production run; a second <strong>Validate &amp; Send Approval</strong> step forwards it to the Director.</p>
                    @endif

                    @if(!empty($directorRejectReason))
                        <div class="alert alert-danger small mb-4"><i class="fas fa-triangle-exclamation me-1"></i> <strong>Rejected by the Director.</strong> Reason: {{ $directorRejectReason }}</div>
                    @endif

                    @if(session('qc_ho_approved'))
                        <div class="alert alert-success small"><i class="fas fa-circle-check me-1"></i> Head Office approval recorded and the RAF production run queued. Review the final numbers, then <strong>Validate &amp; Send Approval</strong>.</div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success small">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger small">{{ session('error') }}</div>
                    @endif

                    @if($validateMode)
                        @php $rafStatus = strtolower((string) ($rafStatus ?? '')); @endphp
                        @if($rafStatus === 'completed')
                            <div class="alert alert-success small mb-4"><i class="fas fa-robot me-1"></i> <strong>RAF production run completed.</strong> The document below reflects the final numbers.</div>
                        @elseif(in_array($rafStatus, ['pending', 'processing'], true))
                            <div class="alert alert-warning small mb-4"><i class="fas fa-robot me-1"></i> <strong>RAF production run is still {{ $rafStatus }}.</strong> Refresh this page to update the status — you can send once you have validated the numbers.</div>
                        @elseif($rafStatus === 'failed')
                            <div class="alert alert-danger small mb-4"><i class="fas fa-robot me-1"></i> <strong>RAF production run FAILED.</strong> Check with the RPA team before sending to the Director.</div>
                        @else
                            <div class="alert alert-secondary small mb-4"><i class="fas fa-robot me-1"></i> RAF production run status is unavailable right now.</div>
                        @endif
                    @endif

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
                        @if($validateMode)
                            <dt class="col-5 col-sm-3 text-muted fw-normal">Approved by</dt>
                            <dd class="col-7 col-sm-9 text-success fw-semibold"><i class="fas fa-circle-check me-1"></i>{{ $row->ho_approval_signature }}</dd>
                        @endif
                    </dl>

                    {{-- Material Flow — material the subcon vendor returns to us (not our
                         returns to a fabric supplier). Available at both Final Approval and
                         Report Validation; dispatching here blocks Validate & Send until
                         value_stream_ops's inventory staff confirm the return arrived. --}}
                    @if($subcon)
                        @include('subcon.partials.delivery-note-attachment', [
                            'order' => $subcon,
                            'materialReturns' => $materialReturns,
                            'canSubmit' => true,
                            'uploadRoute' => route('qc.ho-approve.material-return', ['token' => $token]),
                        ])
                    @endif

                    @include('subcon.partials.remarks', ['remarks' => $subcon->remarks ?? null])
                    @include('subcon.partials.remarks', ['remarks' => $row->remarks ?? null, 'label' => 'QC Remarks'])

                    @if($subcon)
                        @include('subcon.partials.material-return', [
                            'order' => $subcon,
                            'materialReturnTask' => $materialReturnTask,
                            'dispatchRoute' => route('qc.ho-approve.material-return.dispatch', ['token' => $token]),
                        ])
                    @endif

                    {{-- Details / PDF tabs — same pattern as the Director form. The
                         document endpoint renders FRESH on every open, so the PDF
                         always carries the numbers as they stand right now. --}}
                    <ul class="nav nav-tabs mb-3" id="approvalTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="detail-tab" data-bs-toggle="tab" data-bs-target="#detail-tab-pane" type="button" role="tab" aria-controls="detail-tab-pane" aria-selected="true">
                                <i class="fas fa-list me-1"></i> Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="pdf-tab" data-bs-toggle="tab" data-bs-target="#pdf-tab-pane" type="button" role="tab" aria-controls="pdf-tab-pane" aria-selected="false">
                                <i class="fas fa-file-pdf me-1"></i> PDF Document
                            </button>
                        </li>
                    </ul>

                    {{-- One form for both steps: step 1 POSTs Review & Approve
                         (hoApprove), step 2 POSTs Validate & Send (hoSendApproval).
                         Both carry the same editable consumption + deduction
                         inputs — the send step recalculates any revision through
                         the same engine before notifying the Director. --}}
                    <form method="POST"
                          action="{{ $validateMode ? route('qc.ho-send.submit', ['token' => $token]) : route('qc.ho-approve.submit', ['token' => $token]) }}"
                          onsubmit="return confirm('{{ $validateMode
                              ? 'Send this approval to the Director for final authorization? Any revised figures will be recalculated and saved first.'
                              : 'Approve and sign this inspection? The entered consumption will be recalculated and saved, and the RAF production run queued. The Director is notified only after the separate Validate & Send step.' }}');">
                        @csrf
                        {{-- Recipient marker from the per-approver email link — attributes the signature. --}}
                        @if(request('as'))<input type="hidden" name="as" value="{{ request('as') }}">@endif
                        {{-- Marks that this submit carries the FULL deduction list (replace semantics). --}}
                        <input type="hidden" name="deductions_present" value="1">

                        <div class="tab-content" id="approvalTabContent">
                            <div class="tab-pane fade show active" id="detail-tab-pane" role="tabpanel" aria-labelledby="detail-tab" tabindex="0">
                                @include('subcon.partials.production-detail', [
                                    'productionGroups' => $productionGroups ?? [],
                                    'cuttingReports' => $cuttingReports ?? collect(),
                                    'mode' => 'view',
                                ])

                                {{-- Consumption inputs (calculation+approval), same engine at both steps --}}
                                @if($subcon)
                                    @include('subcon.partials.consumption-input', [
                                        'order' => $subcon,
                                        'fabricLines' => $fabricLines,
                                        'totalCut' => $totalCut,
                                        'editable' => true,
                                    ])
                                @endif

                                {{-- Deductions (optional) — prefilled with the saved rows; the submit replaces the full list. --}}
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold mb-0">Deductions <span class="text-muted fw-normal small">(optional)</span></label>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-deduction"><i class="fas fa-plus me-1"></i> Add row</button>
                                    </div>
                                    <div id="deduction-rows">
                                        @foreach(($deductionLines ?? []) as $di => $d)
                                            <div class="row g-2 mb-2 align-items-center">
                                                <div class="col-7"><input type="text" class="form-control form-control-sm" name="deductions[{{ $di }}][description]" value="{{ $d['description'] }}" placeholder="Description (e.g. Label reprint cost)"></div>
                                                <div class="col-4"><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input type="number" step="0.01" min="0" class="form-control" name="deductions[{{ $di }}][amount]" value="{{ $d['amount'] }}" placeholder="Amount"></div></div>
                                                <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-deduction" title="Remove"><i class="fas fa-times"></i></button></div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="form-text">Any cost deducted from the vendor (e.g. label reprint). Leave empty if none.</div>
                                </div>

                                {{-- MD Production's own remarks — distinct from the vendor's
                                     read-only remarks above and the QC inspector's remarks
                                     on the inspection report. Shown on the signed report and
                                     the Report Validation tab. --}}
                                <div class="mb-3">
                                    <label for="ho_remarks" class="form-label fw-semibold">MD Production Remarks <span class="text-danger">*</span></label>
                                    <textarea name="ho_remarks" id="ho_remarks" rows="2" class="form-control form-control-sm" required placeholder="Explain your review — findings, concerns, or justification for this approval.">{{ old('ho_remarks', $row->ho_remarks ?? null) }}</textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    @if($validateMode)
                                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                                            <i class="fas fa-paper-plane me-1"></i> Validate &amp; Send Approval
                                        </button>
                                    @else
                                        <a href="{{ route('qc.ho-decline', array_filter(['token' => $token, 'as' => request('as')])) }}"
                                           class="btn btn-outline-danger px-4 fw-semibold"
                                           onclick="return confirm('Reject this inspection at the Head Office stage? This records a rejection and cannot be undone.');">
                                            <i class="fas fa-circle-xmark me-1"></i> Reject
                                        </a>
                                        <button type="submit" class="btn btn-success px-4 fw-semibold shadow-sm">
                                            <i class="fas fa-circle-check me-1"></i> Review &amp; Approve
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="tab-pane fade" id="pdf-tab-pane" role="tabpanel" aria-labelledby="pdf-tab" tabindex="0">
                                <div class="border rounded-3 overflow-hidden bg-light mb-4" style="height: 600px;">
                                    <iframe src="{{ route('qc.document', ['token' => $token]) }}?t={{ time() }}" style="width: 100%; height: 100%; border: none;"></iframe>
                                </div>
                            </div>
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
    let dIdx = {{ count($deductionLines ?? []) }};
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
