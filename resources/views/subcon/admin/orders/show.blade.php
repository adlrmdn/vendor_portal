@extends('layouts.app')

@section('title', 'Work Order — ' . $order->order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ $summary['style'] ?? $order->title }}</h2>
        <small class="text-muted">{{ $order->order_number }}@if(!empty($order->production_group)) <span class="mx-1">·</span> PRG: {{ $order->production_group }}@endif</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <a href="{{ route('subcon.admin.orders.export-cutting', $order->id) }}" class="btn btn-outline-success">
            <i class="fas fa-file-excel me-1"></i> Export Cutting Report
        </a>
        {{-- Cutting Plan temporarily dormant — routes/controller/service kept intact for re-enable
        <a href="{{ route('subcon.admin.orders.cutting-plan', $order->id) }}" class="btn btn-outline-primary">
            <i class="fas fa-ruler-combined me-1"></i> Cutting Plan
        </a>
        --}}
        @if($order->gramasi_approved_at)
            {{-- On-demand generate/recalculate: allowed any time after gramasi &
                 blister capacity are approved. Once labels already exist this is
                 a Coli/Blister-only resync against the current capacity (see
                 GenerateSubconLabels::recalculateColiBlister()) — no DTT re-run. --}}
            @if($order->isGeneratingLabels())
                <button type="button" class="btn btn-secondary" disabled>
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating…
                </button>
            @else
                <form method="POST" action="{{ route('subcon.admin.orders.generate-labels-manual', $order->id) }}" class="d-inline"
                      onsubmit="return confirm('{{ $order->canPrintLabels() ? 'Recalculate Coli/Blister with the current blister/sack capacity?' : 'Trigger label generation? This calls the DTT/RPA service and can take up to a minute.' }}');">
                    @csrf
                    <button type="submit" class="btn {{ $order->labelGenFailed() ? 'btn-warning' : 'btn-outline-primary' }}">
                        <i class="fas {{ $order->labelGenFailed() ? 'fa-rotate-right' : ($order->canPrintLabels() ? 'fa-rotate' : 'fa-cog') }} me-1"></i>
                        {{ $order->labelGenFailed() ? 'Retry Generation' : ($order->canPrintLabels() ? 'Recalculate Labels' : 'Generate Labels') }}
                    </button>
                </form>
            @endif
        @endif
        <a href="{{ route('subcon.admin.orders.print-labels', ['id' => $order->id, 'scope' => 'store']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm" style="min-width: 210px;">
            <i class="fas fa-print"></i> <span class="text-center">Print Store Labels</span>
        </a>
        <a href="{{ route('subcon.admin.orders.print-labels', ['id' => $order->id, 'scope' => 'warehouse']) }}" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2 lh-sm" style="min-width: 210px;">
            <i class="fas fa-warehouse"></i> <span class="text-center">Print WH Labels</span>
        </a>
    </div>
</div>

@if($order->gramasi_approved_at && $order->labelGenFailed())
    <div class="alert alert-danger py-2 small mb-4">
        <i class="fas fa-triangle-exclamation me-1"></i>
        <strong>Label generation failed:</strong> {{ $order->label_gen_error ?: 'The RPA service could not generate the packing instruction.' }}
        @if($order->label_gen_at)<span class="text-muted">({{ $order->label_gen_at->diffForHumans() }})</span>@endif
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-9">
        {{-- Items --}}
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

        @include('subcon.partials.production-detail', ['productionGroups' => $productionGroups, 'cuttingReports' => $cuttingReports])

        @if($order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW)
            {{-- Consumption + approve are one action: this form's inputs are
                 submitted by the "Approve" button in the sidebar approval card. --}}
            <form id="cutting-approve-form" method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}">
                @csrf
                <input type="hidden" name="gate" value="cutting">
                @include('subcon.partials.consumption-input', [
                    'order' => $order,
                    'fabricLines' => $fabricLines,
                    'totalCut' => $totalCut,
                    'editable' => true,
                ])
            </form>
        @else
            @include('subcon.partials.consumption-input', [
                'order' => $order,
                'fabricLines' => $fabricLines,
                'totalCut' => $totalCut,
                'editable' => false,
            ])
        @endif
    </div>

    <div class="col-lg-3">
        {{-- Info Card --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Order Info</h6></div>
            <div class="card-body">
                <style>
                    .order-info-field { margin-bottom: .85rem; }
                    .order-info-field:last-child { margin-bottom: 0; }
                    .order-info-field dt {
                        font-size: .68rem; font-weight: 600; text-transform: uppercase;
                        letter-spacing: .04em; color: #6c757d; margin-bottom: .15rem;
                    }
                    .order-info-field dd { margin-bottom: 0; font-size: .85rem; word-break: break-word; }
                </style>
                <dl class="mb-0">
                    <div class="order-info-field">
                        <dt>Vendor</dt>
                        <dd>{{ $order->vendor?->name ?? '—' }}</dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Order Date</dt>
                        <dd>{{ $order->order_date->format('d M Y') }}</dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Due Date</dt>
                        <dd>{{ $order->due_date?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Status</dt>
                        <dd>
                            @php $badge = match($order->status) { 'pending' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Workflow</dt>
                        <dd><span class="badge bg-info-subtle text-info border border-info border-opacity-25 d-inline-block" style="white-space:normal; word-break:break-word; max-width:100%; text-align:left;">{{ $order->stageLabel() }}</span></dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Blister Cap.</dt>
                        <dd>
                            <input type="number" form="capacity-form" name="blister_capacity" min="1" step="1" inputmode="numeric"
                                   class="form-control form-control-sm" placeholder="—"
                                   value="{{ old('blister_capacity', $order->blister_capacity) }}">
                        </dd>
                    </div>
                    <div class="order-info-field">
                        <dt>Sack (Karung) Cap.</dt>
                        <dd>
                            <input type="number" form="capacity-form" name="sack_capacity" min="1" step="1" inputmode="numeric"
                                   class="form-control form-control-sm" placeholder="50"
                                   value="{{ old('sack_capacity', $order->sack_capacity) }}">
                        </dd>
                    </div>
                    <div class="order-info-field">
                        <dd>
                            <form id="capacity-form" method="POST" action="{{ route('subcon.admin.orders.update-capacity', $order->id) }}" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-save me-1"></i> Save capacity
                                </button>
                            </form>
                        </dd>
                    </div>
                    @if($order->cutting_approved_at || $order->gramasi_approved_at)
                        <div class="row g-2 order-info-field">
                            @if($order->cutting_approved_at)
                                <div class="col-6">
                                    <dt>Cutting OK</dt>
                                    <dd class="text-muted">{{ $order->cutting_approved_at->format('d M Y H:i') }}<br>by {{ $order->cutting_approved_by }}</dd>
                                </div>
                            @endif
                            @if($order->gramasi_approved_at)
                                <div class="col-6">
                                    <dt>Gramasi OK</dt>
                                    <dd class="text-muted">{{ $order->gramasi_approved_at->format('d M Y H:i') }}<br>by {{ $order->gramasi_approved_by }}</dd>
                                </div>
                            @endif
                        </div>
                    @endif
                </dl>
                @if($order->description)
                    <hr><p class="small mb-0">{{ $order->description }}</p>
                @endif
            </div>
        </div>

        @include('subcon.partials.delivery-note-attachment', [
            'order' => $order,
            'materialReturns' => $materialReturns,
            'canSubmit' => $order->materialReturnAdminWindowOpen(),
            'uploadRoute' => route('subcon.admin.orders.material-return', $order->id),
        ])

        @include('subcon.partials.remarks', ['remarks' => $order->remarks ?? null])

        @include('subcon.partials.material-return', [
            'order' => $order,
            'materialReturnTask' => $materialReturnTask,
            'dispatchRoute' => route('subcon.admin.orders.material-return.dispatch', $order->id),
        ])

        {{-- Stage approval --}}
        @if($order->isAwaitingApproval())
            @php $gate = $order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW ? 'cutting' : 'gramasi'; @endphp
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning-subtle">
                    <h6 class="mb-0"><i class="fas fa-gavel me-1"></i> Approval Required</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-3">
                        The vendor submitted the
                        <strong>{{ $gate === 'gramasi' ? 'gramasi & blister capacity' : 'cutting report' }}</strong>
                        @if($gate === 'cutting' && $order->cutting_partial)
                            <span class="badge text-bg-danger">Partial</span>
                        @endif
                        for this work order.
                        @if($gate === 'gramasi' && $order->blister_capacity)
                            <br>Blister capacity: <strong>{{ number_format($order->blister_capacity) }}</strong> pcs/blister.
                        @endif
                        @if($gate === 'cutting')
                            <br><span class="text-muted">Enter the fabric consumption above; it is saved when you approve.</span>
                            @if($order->cutting_partial)
                                <br><span class="text-danger">Partial report: approving keeps the order at the cutting stage so the vendor can submit the remaining quantities.</span>
                            @endif
                        @endif
                    </p>
                    <div class="d-flex gap-2">
                        @if($gate === 'cutting')
                            {{-- Submits the consumption form in the left column (one atomic action). --}}
                            <button type="submit" form="cutting-approve-form" class="btn btn-success flex-fill"
                                    onclick="return confirm('Approve the cutting report and save the entered consumption?');">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        @else
                            <form method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}" class="flex-fill m-0"
                                  onsubmit="return confirm('Approve the gramasi & blister capacity?');">
                                @csrf
                                <input type="hidden" name="gate" value="{{ $gate }}">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i> Approve</button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-outline-danger flex-fill" data-bs-toggle="modal" data-bs-target="#rejectReasonModal"
                                data-action="{{ route('subcon.admin.orders.decline', $order->id) }}"
                                data-gate="{{ $gate }}"
                                data-order-number="{{ $order->order_number }}"
                                data-gate-label="{{ $gate === 'gramasi' ? 'gramasi & blister capacity' : 'cutting report' }}">
                            <i class="fas fa-times me-1"></i> Reject
                        </button>
                    </div>
                </div>
            </div>

            {{-- Reject-reason modal — the reason is required and shown to the
                 vendor until they resubmit this same gate. --}}
            <div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" id="rejectReasonForm" class="modal-content">
                        @csrf
                        <input type="hidden" name="gate" id="rejectReasonGate">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Reject <span id="rejectReasonOrderNumber"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">This returns the <span id="rejectReasonGateLabel"></span> to the vendor for changes. The reason you enter is shown to the vendor.</p>
                            <label for="rejectReasonText" class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="rejectReasonText" rows="3" maxlength="1000" required class="form-control form-control-sm" placeholder="What needs to change before resubmitting?"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i> Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            document.getElementById('rejectReasonModal').addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                document.getElementById('rejectReasonForm').action = btn.getAttribute('data-action');
                document.getElementById('rejectReasonGate').value = btn.getAttribute('data-gate');
                document.getElementById('rejectReasonOrderNumber').textContent = btn.getAttribute('data-order-number');
                document.getElementById('rejectReasonGateLabel').textContent = btn.getAttribute('data-gate-label');
                document.getElementById('rejectReasonText').value = '';
            });
            </script>
        @endif

        {{-- Status control --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Status</h6></div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    <i class="fas fa-robot me-1"></i> Status follows the workflow stage automatically. Completing or cancelling are the only manual overrides.
                </p>
                @if($order->status === 'cancelled')
                    <form method="POST" action="{{ route('subcon.admin.orders.update-status', $order->id) }}">
                        @csrf
                        <input type="hidden" name="action" value="reactivate">
                        <button type="submit" class="btn btn-outline-success w-100">
                            <i class="fas fa-rotate-right me-1"></i> Reactivate Order
                        </button>
                    </form>
                @else
                    <div class="d-flex flex-column gap-2">
                        @if($order->canComplete())
                            <form method="POST" action="{{ route('subcon.admin.orders.update-status', $order->id) }}"
                                  onsubmit="return confirm('Mark this work order as completed?');">
                                @csrf
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn btn-outline-success w-100">
                                    <i class="fas fa-check me-1"></i> Complete Order
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('subcon.admin.orders.update-status', $order->id) }}"
                              onsubmit="return confirm('Cancel this work order?');">
                            @csrf
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-ban me-1"></i> Cancel Order
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
