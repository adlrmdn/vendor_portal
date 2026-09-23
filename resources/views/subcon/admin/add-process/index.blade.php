@extends('layouts.app')

@section('title', 'Add Process' . ($prg ? " — {$prg}" : ''))

@section('content')
<style>
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
    .modern-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    .modern-table thead {
        background-color: #f8f9fa;
    }
    .modern-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #495057;
        font-weight: 700;
        border-bottom: 2px solid #dee2e6;
        padding: 14px 16px !important;
    }
    .modern-table td {
        padding: 14px 16px !important;
        border-bottom: 1px solid #dee2e6;
        color: #212529;
    }
    .size-pill {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #e9ecef;
        color: #212529;
        font-weight: 700;
        font-size: 0.85rem;
        border: 1px solid #ced4da;
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
    .status-indicator-warning { background: #fff9db; color: #f08c00; }
    .status-indicator-warning::before { background: #f08c00; }
    .status-indicator-success { background: #ebfbee; color: #2b8a3e; }
    .status-indicator-success::before { background: #2b8a3e; }
    .status-indicator-info { background: #e7f5ff; color: #1c7ed6; }
    .status-indicator-info::before { background: #1c7ed6; }
    .status-indicator-secondary { background: #f1f3f5; color: #868e96; }
    .status-indicator-secondary::before { background: #868e96; }
    .modern-input {
        background-color: #ffffff !important;
        border: 1px solid #ced4da !important;
        border-radius: 6px !important;
        padding: 0.4rem 0.6rem !important;
        font-size: 0.85rem !important;
        color: #212529 !important;
        text-align: right;
        font-weight: 500;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Add Process</h2>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Search: works for BOTH CMT subcon production groups and in-house ones —
     it's a plain ProductionGroup lookup against D365, not a work-order lookup. --}}
<form method="GET" class="modern-card p-4 mb-4">
    <label class="form-label small fw-semibold text-muted mb-2">Production Group</label>
    <div class="input-group">
        <input type="text" name="prg" class="form-control" placeholder="e.g. MPG/PRG/2601/000070" value="{{ $prg }}">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Search</button>
    </div>
</form>

@if($prg !== '')
    @php
        $lines = collect($productionGroups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        $notFound = $lines->isEmpty() && empty($operations) && ! $cmtOrder && empty($addProcessPos);
        $prodBadge = fn ($s) => match ($s) {
            'Completed', 'ReportedFinished' => 'success',
            'StartedUp'                     => 'warning',
            'Released'                      => 'info',
            'CostEstimated', 'Created'      => 'secondary',
            default                         => 'secondary',
        };
        $pcsSuffix = ' <span class="text-muted small fw-normal">pcs</span>';
    @endphp

    @if($notFound)
        <div class="modern-card p-4 text-center text-muted">
            Production group <code>{{ $prg }}</code> was not found.
        </div>
    @else
        {{-- CMT flow: shown only when this PRG happens to belong to a subcon
             work order. Absent for in-house production groups — that's
             expected, not an error. --}}
        <div class="modern-card mb-4">
            <div class="modern-card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Original (CMT / In-House) Flow</h5>
            </div>
            <div class="p-4">
                @if($cmtOrder)
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="fw-semibold">{{ $cmtOrder->order_number }}</div>
                            <div class="text-muted small">{{ $cmtOrder->vendor?->name ?? '—' }} &middot; {{ $cmtOrder->stageLabel() }}</div>
                        </div>
                        <a href="{{ route('subcon.admin.orders.view', $cmtOrder->id) }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-arrow-up-right-from-square me-1"></i> Open Work Order
                        </a>
                    </div>
                @else
                    <p class="text-muted mb-0">No CMT work order for this production group — likely in-house production.</p>
                @endif
            </div>
        </div>

        {{-- Add Process PO: a genuinely SEPARATE commercial PO/vendor from the
             one above — D365's "AddPro" purchase pool (Item Jasa Add Process
             Washing/Embro/Sablon/Pleating), confirmed to differ from the CMT
             PO's own vendor. Informational only: that vendor is never
             registered as a portal account and no work order is created for
             it — the admin reports quantities directly below regardless. --}}
        @if(! empty($addProcessPos))
            <div class="modern-card mb-4">
                <div class="modern-card-header">
                    <h5 class="mb-0">Add Process Purchase Order</h5>
                </div>
                <div class="p-4">
                    @foreach($addProcessPos as $po)
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 {{ ! $loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                            <div>
                                <div class="fw-semibold">{{ $po['po_number'] }}</div>
                                <div class="text-muted small">{{ $po['description'] ?? '—' }} &middot; Vendor {{ $po['vendor_code'] ?? '—' }} &middot; {{ $po['status'] ?? '—' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(empty($operations))
            <div class="modern-card p-4 text-center text-muted">
                <i class="fas fa-circle-info me-1"></i>
                No add-process step (ADD-Was / ADD-Embro / ADD-Sab / ADD-Pleat) found on this production group's D365 route.
            </div>
        @else
            {{-- Each operation is entirely independent — its own form, its own
                 submit, its own D365 sync. A PRG with two ADD-* steps reports
                 and syncs them as two separate actions, never combined. --}}
            @foreach($operations as $operation)
                @php
                    // Captured before the inner @foreach($lines) shadows $loop —
                    // needed to keep row element ids unique across operations
                    // (each operation is its own <form>/table on the same page).
                    $opIndex = $loop->index;
                    $opReports = $existingReports->get($operation, collect())->keyBy('prod_id');
                    $totalOrderQty = $lines->sum('Qty');
                    $totalCutQty = $lines->sum(fn ($l) => $cutQuantities[$l->Size]['good'] ?? 0);
                    $anyCutData = $lines->contains(fn ($l) => isset($cutQuantities[$l->Size]));
                    $totalGood = $lines->sum(fn ($l) => $opReports->get($l->ProdId)->qty ?? 0);
                    $totalReject = $lines->sum(fn ($l) => $opReports->get($l->ProdId)->reject_qty ?? 0);
                    $anyProcessed = ($totalGood + $totalReject) > 0;
                    $totalBalance = $totalCutQty - ($totalGood + $totalReject);
                @endphp
                <form method="POST" action="{{ route('subcon.admin.add-process.submit') }}" class="modern-card mb-4 add-process-form" data-op-index="{{ $opIndex }}">
                    @csrf
                    <input type="hidden" name="production_group" value="{{ $prg }}">
                    <input type="hidden" name="operation" value="{{ $operation }}">
                    <div class="modern-card-header">
                        <h5 class="mb-0">{{ $operation }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 modern-table">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 90px;">Size</th>
                                    <th style="width: 150px;">PRD ID</th>
                                    <th style="width: 130px;">Status</th>
                                    <th class="text-end" style="width: 110px;">Order Qty</th>
                                    <th class="text-end" style="width: 110px;">Cut Qty</th>
                                    <th class="text-end" style="width: 130px;">Good Qty</th>
                                    <th class="text-end" style="width: 130px;">Reject Qty</th>
                                    <th class="text-end pe-4" style="width: 110px;">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    @php
                                        $existing = $opReports->get($line->ProdId);
                                        $cutInfo = $cutQuantities[$line->Size] ?? null;
                                        $cutQty = $cutInfo['good'] ?? null;
                                        $good = (int) ($existing->qty ?? 0);
                                        $reject = (int) ($existing->reject_qty ?? 0);
                                        $hasEntry = $good > 0 || $reject > 0;
                                        $rowBalance = $cutQty !== null ? ((int) $cutQty - ($good + $reject)) : null;
                                        $balClass = ! $hasEntry ? 'text-muted' : ($rowBalance === 0 ? 'text-success' : 'text-danger');
                                        $balText = $rowBalance === null ? '—' : (! $hasEntry ? number_format($rowBalance) : ($rowBalance > 0 ? '+'.number_format($rowBalance) : number_format($rowBalance)));
                                    @endphp
                                    <tr>
                                        <td class="ps-4"><span class="size-pill">{{ $line->Size }}</span></td>
                                        <td><code class="text-secondary small font-monospace">{{ $line->ProdId }}</code></td>
                                        <td><span class="status-indicator status-indicator-{{ $prodBadge($line->ProdStatus) }}">{{ $line->ProdStatus ?: '—' }}</span></td>
                                        <td class="text-end fw-semibold text-dark">{{ number_format($line->Qty) }}{!! $pcsSuffix !!}</td>
                                        <td class="text-end {{ $cutQty !== null ? 'fw-semibold text-primary' : 'text-muted' }}">
                                            {{ $cutQty !== null ? number_format($cutQty) : '—' }}
                                        </td>
                                        <td class="text-end">
                                            <input type="hidden" name="reports[{{ $loop->index }}][prod_id]" value="{{ $line->ProdId }}">
                                            <input type="hidden" name="reports[{{ $loop->index }}][size]" value="{{ $line->Size }}">
                                            <input type="number" min="0" class="form-control form-control-sm modern-input good-qty-input" style="width: 90px;"
                                                   name="reports[{{ $loop->index }}][qty]"
                                                   data-cut-qty="{{ $cutQty ?? '' }}"
                                                   data-balance-target="ap_bal_{{ $opIndex }}_{{ $loop->index }}"
                                                   value="{{ old('reports.'.$loop->index.'.qty', $existing->qty ?? '') }}">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" min="0" class="form-control form-control-sm modern-input reject-qty-input" style="width: 90px;"
                                                   name="reports[{{ $loop->index }}][reject_qty]"
                                                   data-cut-qty="{{ $cutQty ?? '' }}"
                                                   data-balance-target="ap_bal_{{ $opIndex }}_{{ $loop->index }}"
                                                   value="{{ old('reports.'.$loop->index.'.reject_qty', $existing->reject_qty ?? '') }}">
                                        </td>
                                        <td class="text-end pe-4">
                                            <span id="ap_bal_{{ $opIndex }}_{{ $loop->index }}" class="fw-semibold {{ $balClass }}">{{ $balText }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold border-top border-secondary border-opacity-20">
                                    <td class="ps-4 text-secondary">TOTAL</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end text-dark">{{ number_format($totalOrderQty) }}{!! $pcsSuffix !!}</td>
                                    <td class="text-end {{ $anyCutData ? 'text-primary' : 'text-muted' }}">{{ $anyCutData ? number_format($totalCutQty) : '—' }}</td>
                                    <td class="text-end text-dark"><span id="ap_total_good_{{ $opIndex }}">{{ number_format($totalGood) }}</span></td>
                                    <td class="text-end text-dark"><span id="ap_total_reject_{{ $opIndex }}">{{ number_format($totalReject) }}</span></td>
                                    <td class="text-end pe-4 {{ ! $anyCutData ? 'text-muted' : (! $anyProcessed ? 'text-muted' : ($totalBalance === 0 ? 'text-success' : 'text-danger')) }}"
                                        id="ap_total_balance_{{ $opIndex }}" data-cut-total="{{ $anyCutData ? $totalCutQty : '' }}">
                                        {{ $anyCutData ? ($totalBalance > 0 ? '+'.number_format($totalBalance) : number_format($totalBalance)) : '—' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="p-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Submit {{ $operation }} &amp; Sync to D365
                        </button>
                    </div>
                </form>

                @php
                    $existingOfficialReport = $officialReports[$operation] ?? null;
                    $officialGate = $officialReportGates[$operation] ?? ['ok' => false, 'reason' => 'Not checked.'];
                @endphp
                <div class="mt-3 pt-3 border-top">
                    @if($existingOfficialReport)
                        <span class="status-indicator status-indicator-{{ $existingOfficialReport->isCompleted() ? 'success' : 'info' }}">
                            Official Report:
                            {{ match(true) {
                                $existingOfficialReport->isCompleted() => 'Completed',
                                $existingOfficialReport->needsRevision() => 'Needs Revision (Director declined)',
                                $existingOfficialReport->isPendingDirector() => 'Pending Director',
                                default => 'Pending MD Production',
                            } }}
                        </span>
                    @elseif($officialGate['ok'])
                        <form method="POST" action="{{ route('subcon.admin.official-reports.send') }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <input type="hidden" name="production_group" value="{{ $prg }}">
                            <input type="hidden" name="operation" value="{{ $operation }}">
                            <div>
                                <label class="form-label small mb-1">Additional Deduction (Rp)</label>
                                <input type="number" step="0.01" min="0" name="additional_deduction" class="form-control form-control-sm" style="width: 140px;" value="0">
                            </div>
                            <div>
                                <label class="form-label small mb-1">Sales Price (Rp, optional — D365 auto-fill if blank)</label>
                                <input type="number" step="0.01" min="0" name="sales_price" class="form-control form-control-sm" style="width: 160px;">
                            </div>
                            <div class="flex-grow-1" style="min-width: 200px;">
                                <label class="form-label small mb-1">Remarks</label>
                                <input type="text" name="remarks" class="form-control form-control-sm">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file-signature me-1"></i> Send Official Report
                            </button>
                        </form>
                    @else
                        <span class="text-muted small"><i class="fas fa-circle-info me-1"></i> Official Report unavailable: {{ $officialGate['reason'] }}</span>
                    @endif
                </div>
            @endforeach

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const fmt = new Intl.NumberFormat();

                function balClass(b, has) {
                    if (!has) return 'text-muted';
                    return b === 0 ? 'text-success' : 'text-danger';
                }
                function balText(b, has) {
                    if (!has) return fmt.format(b);
                    return (b > 0 ? '+' : '') + fmt.format(b);
                }
                function parseOrNull(raw) {
                    return (raw !== '' && raw !== undefined) ? parseInt(raw) : null;
                }

                // Each operation is its own independent form — recompute is
                // scoped per-form so a PRG with two ADD-* operations never
                // lets one operation's totals affect the other's.
                document.querySelectorAll('form.add-process-form').forEach(function (form) {
                    const opIndex = form.dataset.opIndex;
                    const goodInputs = Array.from(form.querySelectorAll('.good-qty-input'));
                    const rejectInputs = Array.from(form.querySelectorAll('.reject-qty-input'));
                    const totalGoodEl = document.getElementById('ap_total_good_' + opIndex);
                    const totalRejectEl = document.getElementById('ap_total_reject_' + opIndex);
                    const totalBalEl = document.getElementById('ap_total_balance_' + opIndex);
                    const cutTotal = totalBalEl ? parseOrNull(totalBalEl.dataset.cutTotal) : null;

                    function recompute() {
                        let totalGood = 0, totalReject = 0;

                        goodInputs.forEach(input => { totalGood += parseInt(input.value) || 0; });
                        rejectInputs.forEach(input => { totalReject += parseInt(input.value) || 0; });

                        goodInputs.forEach(input => {
                            const cutQty = parseOrNull(input.dataset.cutQty);
                            const reject = parseInt(form.querySelector('[data-balance-target="' + input.dataset.balanceTarget + '"].reject-qty-input')?.value) || 0;
                            const good = parseInt(input.value) || 0;
                            const has = good > 0 || reject > 0;
                            const target = document.getElementById(input.dataset.balanceTarget);
                            if (target && cutQty !== null) {
                                const balance = cutQty - (good + reject);
                                target.textContent = balText(balance, has);
                                target.className = 'fw-semibold ' + balClass(balance, has);
                            }
                        });

                        if (totalGoodEl) totalGoodEl.textContent = fmt.format(totalGood);
                        if (totalRejectEl) totalRejectEl.textContent = fmt.format(totalReject);
                        if (totalBalEl && cutTotal !== null) {
                            const has = (totalGood + totalReject) > 0;
                            const tb = cutTotal - (totalGood + totalReject);
                            totalBalEl.textContent = has ? balText(tb, true) : '—';
                            totalBalEl.className = totalBalEl.className.replace(/text-(success|danger|muted|primary)/g, '').trim()
                                + ' ' + (has ? balClass(tb, true) : 'text-muted');
                        }
                    }

                    goodInputs.concat(rejectInputs).forEach(input => {
                        input.addEventListener('input', recompute);
                        input.addEventListener('change', recompute);
                    });
                });
            });
            </script>
        @endif
    @endif
@endif
@endsection
