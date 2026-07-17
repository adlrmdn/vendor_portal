@extends('layouts.app')

@section('title', 'Requested Approvals')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Requested Approvals</h2>
        <small class="text-muted">Cutting reports, gramasi &amp; final submissions awaiting your decision</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.approval-logs') }}" class="btn btn-outline-secondary">
            <i class="fas fa-clock-rotate-left me-1"></i> Approval Log
        </a>
        <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary">
            <i class="fas fa-clipboard-list me-1"></i> All Work Orders
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Work Order</th>
                        <th>Vendor</th>
                        <th>Awaiting</th>
                        <th>Submitted</th>
                        <th class="text-end pe-4" style="width: 260px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        @php $gate = $order->workflow_stage === \App\Models\SubconOrder::STAGE_CUTTING_REVIEW ? 'cutting' : 'gramasi'; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="fw-semibold text-decoration-none font-monospace">{{ $order->order_number }}</a>
                                <div class="text-muted small">{{ $order->title }}</div>
                            </td>
                            <td>{{ $order->vendor?->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $gate === 'gramasi' ? 'info' : 'warning' }}-subtle text-{{ $gate === 'gramasi' ? 'info' : 'warning' }} border border-{{ $gate === 'gramasi' ? 'info' : 'warning' }} border-opacity-25">
                                    {{ $gate === 'gramasi' ? 'Gramasi & Blister' : 'Cutting Report' }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $order->updated_at?->diffForHumans() }}</td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    @if($gate === 'cutting')
                                        {{-- Cutting approval needs fabric-consumption entry, so route to the
                                             order page (or use the no-login email link) rather than one-click. --}}
                                        <a href="{{ route('subcon.admin.orders.view', $order->id) }}" class="btn btn-sm btn-success">
                                            <i class="fas fa-check me-1"></i> Review &amp; Approve
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('subcon.admin.orders.approve', $order->id) }}" class="m-0"
                                              onsubmit="return confirm('Approve the gramasi & blister capacity for {{ $order->order_number }}?');">
                                            @csrf
                                            <input type="hidden" name="gate" value="{{ $gate }}">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('subcon.admin.orders.decline', $order->id) }}" class="m-0"
                                          onsubmit="return confirm('Reject the {{ $gate === 'gramasi' ? 'gramasi & blister capacity' : 'cutting report' }} for {{ $order->order_number }}?\n\nThis returns the work order to the vendor for changes. Press OK only if you are sure.');">
                                        @csrf
                                        <input type="hidden" name="gate" value="{{ $gate }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i> Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    {{-- Final (QC-console) approvals: stage-1 done, awaiting Final sign-off. --}}
                    @foreach($finalApprovals as $fa)
                        <tr>
                            <td>
                                @if($fa['order_id'])
                                    <a href="{{ route('subcon.admin.orders.view', $fa['order_id']) }}" class="fw-semibold text-decoration-none font-monospace">{{ $fa['order_number'] }}</a>
                                @else
                                    <span class="fw-semibold font-monospace">{{ $fa['order_number'] }}</span>
                                @endif
                                @if($fa['style'])<div class="text-muted small">{{ $fa['style'] }}</div>@endif
                            </td>
                            <td>{{ $fa['vendor'] }}</td>
                            <td>
                                @if(($fa['step'] ?? 'approve') === 'send')
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25">Final Approval — Validate &amp; Send</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25">Final Approval</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $fa['approved_at'] ? \Illuminate\Support\Carbon::parse($fa['approved_at'])->diffForHumans() : '—' }}</td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    @if(($fa['step'] ?? 'approve') === 'send')
                                        {{-- Step 2: approved + RAF queued — the form renders read-only
                                             with the Validate & Send Approval button. --}}
                                        <a href="{{ route('qc.ho-approve', ['token' => $fa['token']]) }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
                                            <i class="fas fa-paper-plane me-1"></i> Validate &amp; Send
                                        </a>
                                    @else
                                        {{-- Final approval needs fabric-consumption review, so route to the form
                                             (no one-click) — opened in a new tab so the approvals list stays put. --}}
                                        <a href="{{ route('qc.ho-approve', ['token' => $fa['token']]) }}" class="btn btn-sm btn-success" target="_blank" rel="noopener">
                                            <i class="fas fa-check me-1"></i> Review &amp; Approve
                                        </a>
                                        <a href="{{ route('qc.ho-decline', ['token' => $fa['token']]) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener"
                                           onclick="return confirm('Reject this inspection at the Final approval stage? This records a rejection and cannot be undone.');">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @if($orders->isEmpty() && empty($finalApprovals))
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-check-double fa-2x mb-3 text-success opacity-50 d-block"></i>
                                No approvals pending. You're all caught up.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($orders->hasPages())
    <div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection
