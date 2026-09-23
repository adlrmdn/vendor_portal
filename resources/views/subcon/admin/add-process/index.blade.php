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
        table-layout: fixed;
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
        white-space: nowrap;
    }
    .modern-table td {
        padding: 14px 16px !important;
        border-bottom: 1px solid #dee2e6;
        color: #212529;
        vertical-align: middle;
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
        width: 100%;
    }
    .op-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 28px;
        font-size: 0.85rem;
    }
    .op-meta-row dt {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #868e96;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .op-meta-row dd {
        margin-bottom: 0;
        font-weight: 600;
        color: #212529;
    }
    .op-meta-row code {
        color: inherit;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="mb-0">{{ $prg !== '' ? ($summary['style'] ?? 'Add Process') : 'Add Process' }}</h2>
        @if($prg !== '')
            <small class="text-muted">PRG: <code>{{ $prg }}</code></small>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('subcon.admin.orders', ['add_process' => 1]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Work Orders
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
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

{{-- Secondary: jump to a different production group by hand. Works for BOTH
     CMT subcon production groups and in-house ones — it's a plain
     ProductionGroup lookup against D365, not a work-order lookup. Kept small
     and out of the way since arriving via "View" from the Work Orders list
     already fills $prg and renders the content directly below. --}}
<form method="GET" class="d-flex align-items-center gap-2 mb-4" style="max-width: 420px;">
    <input type="text" name="prg" class="form-control form-control-sm" placeholder="Jump to another PRG — e.g. MPG/PRG/2601/000070" value="{{ $prg }}">
    <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fas fa-search me-1"></i> Go</button>
</form>

@if($prg !== '')
    @php
        $lines = collect($productionGroups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        // $allOperations (not the confirmed-PO-filtered $operations) — a step
        // awaiting a confirmed PO still means the PRG was genuinely found.
        $notFound = $lines->isEmpty() && empty($allOperations) && ! $cmtOrder && empty($addProcessPos);
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
        {{-- The cutting/sewing route itself: CMT when a subcon work order
             covers it, in-house otherwise. Stated explicitly (not just implied
             by whether a work order happens to be linked below) — this is
             independent of add-process, which is always its own subcontracted
             step regardless of which flow this card shows. --}}
        <div class="modern-card mb-4">
            <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Production Flow</h5>
                <span class="status-indicator status-indicator-{{ $cmtOrder ? 'info' : 'secondary' }}">
                    {{ $cmtOrder ? 'CMT (Subcontracted)' : 'In-House' }}
                </span>
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
                    <p class="text-muted mb-0">Cutting and sewing for this production group are done in-house — no CMT work order.</p>
                @endif
            </div>
        </div>

        @if(empty($operations))
            <div class="modern-card p-4 text-center text-muted">
                <i class="fas fa-circle-info me-1"></i>
                @if(! empty($allOperations))
                    This production group has an add-process step ({{ implode(', ', $allOperations) }}) but no confirmed purchase order yet — nothing to report until D365 confirms one.
                @else
                    No add-process step (ADD-Was / ADD-Embro / ADD-Sab / ADD-Pleat) found on this production group's D365 route.
                @endif
            </div>
        @else
            {{-- Each operation is entirely independent — its own PO match, its
                 own form, its own submit, its own D365 sync. A PRG with two
                 ADD-* steps reports and syncs them as two separate actions,
                 never combined. --}}
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

                    $opPo = $operationPos[$operation] ?? null;
                    $opStep = $steps[$operation] ?? null;
                    $opJobTransactionId = $opStep['job_transaction_id'] ?? null;
                    // Always CMT in practice (confirmed live, see
                    // addProcessStepsForGroup()'s docblock) — shown from the
                    // actual D365 value rather than assumed, since this is a
                    // genuinely separate, always-subcontracted step regardless
                    // of the Production Flow card above (which can be in-house).
                    $opResource = $opStep['resource'] ?? null;
                    $opInvoiced = $opPo && strcasecmp($opPo['status'] ?? '', 'Invoiced') === 0;
                @endphp
                <div class="modern-card mb-4">
                    <div class="modern-card-header">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <h5 class="mb-0">
                                Additional Process &mdash; {{ $operation }}
                                @if($opResource)
                                    <span class="status-indicator status-indicator-info align-middle ms-1" style="font-size: 0.7rem;">{{ $opResource }}</span>
                                @endif
                            </h5>
                            @if($opInvoiced)
                                <span class="status-indicator status-indicator-success"><i class="fas fa-check-double"></i> Invoiced</span>
                            @endif
                        </div>
                        <dl class="op-meta-row mb-0">
                            <div>
                                <dt>Job Transaction</dt>
                                <dd><code>{{ $opJobTransactionId ?? '—' }}</code></dd>
                            </div>
                            <div>
                                <dt>Purchase Order</dt>
                                <dd>{{ $opPo['po_number'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Vendor</dt>
                                <dd>{{ $opPo['vendor_name'] ?? $opPo['vendor_code'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>PO Description</dt>
                                <dd class="fw-normal text-muted">{{ $opPo['description'] ?? '—' }}</dd>
                            </div>
                        </dl>
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
                    </div>

                    @if($opInvoiced)
                        <div class="p-4 text-muted">
                            <i class="fas fa-circle-info me-1"></i>
                            Purchase order <strong>{{ $opPo['po_number'] }}</strong> is already invoiced — {{ $operation }} reporting is closed.
                        </div>
                    @else
                    <form method="POST" action="{{ route('subcon.admin.add-process.submit') }}" class="add-process-form" data-op-index="{{ $opIndex }}">
                        @csrf
                        <input type="hidden" name="production_group" value="{{ $prg }}">
                        <input type="hidden" name="operation" value="{{ $operation }}">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 modern-table">
                                <colgroup>
                                    <col style="width: 7%;">
                                    <col style="width: 16%;">
                                    <col style="width: 13%;">
                                    <col style="width: 12%;">
                                    <col style="width: 12%;">
                                    <col style="width: 14%;">
                                    <col style="width: 14%;">
                                    <col style="width: 12%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="ps-4">Size</th>
                                        <th>PRD ID</th>
                                        <th>Status</th>
                                        <th class="text-end">Order Qty</th>
                                        <th class="text-end">Cut Qty</th>
                                        <th class="text-end">Good Qty</th>
                                        <th class="text-end">Reject Qty</th>
                                        <th class="text-end pe-4">Balance</th>
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
                                                <input type="number" min="0" class="form-control form-control-sm modern-input good-qty-input"
                                                       name="reports[{{ $loop->index }}][qty]"
                                                       data-cut-qty="{{ $cutQty ?? '' }}"
                                                       data-balance-target="ap_bal_{{ $opIndex }}_{{ $loop->index }}"
                                                       value="{{ old('reports.'.$loop->index.'.qty', $existing->qty ?? '') }}">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" min="0" class="form-control form-control-sm modern-input reject-qty-input"
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
