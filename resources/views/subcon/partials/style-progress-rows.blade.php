{{--
    One style's own full table: its own header row (Size, Order Qty, Cutting —
    one column per ratio/marker THIS style uses, then Sewing, then Finishing
    & Packing's own Finishing/QC/Packing group), its own size rows, its own
    Total row. Every style renders one of these, stacked one after another
    on the page — not one table shared across every style — because a
    single <colgroup> can't give two styles a different number of Cutting
    ratio columns; each style needs its own column grid for that to work.
    All of them still submit through the same outer <form> with a single
    Save button (see the page views).

    Expects: $group (from SubconDailyProductionController::buildStyleData,
    includes $group['ratioColumns'] — this style's own ratio labels — and
    $group['cuttingCollapsed']), $date, $flatStages (just Sewing),
    $finishingStages (Finishing/QC/Packing, in order), $mode ('edit'|'view').
    Optional $showAddRatioColumn, $showWorkOrderLink (+ $workOrderRouteName
    override, default subcon.vendor.orders.view) to link out to the
    style's actual SubconOrder work-order page — there's no dedicated
    single-style daily-production page anymore.
--}}
@php
    $order = $group['order'];
    $isClosed = $group['status'] === 'closed';
    $editable = ($mode ?? 'view') === 'edit';
    $ratioColumns = $group['ratioColumns'];
    $fullRatioCount = count($ratioColumns);
    $cuttingCollapsed = $group['cuttingCollapsed'] ?? false;
    // Collapsing never drops data — the Cutting Total column below is
    // always summed from the full $ratioColumns — it only renders zero
    // ratio columns, i.e. exactly what a style with no ratios yet already
    // renders. That's server-side (a reload via dpToggleCuttingRatios(),
    // see the view scripts), not a client-side show/hide: a
    // table-layout:fixed table's <colgroup> widths have to match what's on
    // the page from the first paint, and resizing columns after the fact
    // via JS kept breaking the layout (three different ways were tried).
    $renderRatioColumns = $cuttingCollapsed ? [] : $ratioColumns;
    $ratioCount = count($renderRatioColumns);
    $showAddRatioColumn = ($showAddRatioColumn ?? false) && ! $cuttingCollapsed;
    $stageCount = count($flatStages);
    $finishingCount = count($finishingStages);
    // No ratios yet: the "+" is the only thing in the Cutting group, so it
    // gets room to say "Add ratio/marker" instead of just being an icon —
    // otherwise a first-time vendor has no way to know what that button
    // does. Once at least one ratio exists, the label goes back to
    // icon-only to keep the ratio columns from getting cramped.
    $addColWidth = $fullRatioCount > 0 ? 3 : 14;
    // Size 11% + Order Qty 7% + (+col, edit only) + Cutting Total 8% fixed;
    // every other data-entry column (each ratio, Sewing, and each of the 3
    // Finishing/QC/Packing columns) shares the rest equally, so a style
    // with only 1-2 ratios doesn't get columns several times wider than
    // its other columns.
    $fixedPct = 18 + ($showAddRatioColumn ? $addColWidth : 0) + 8;
    $dataColWidth = round((100 - $fixedPct) / max($ratioCount + $stageCount + $finishingCount, 1), 2);

    // Computed once here (not per usage site) so the order-number link, the
    // PRG pill link, and the "View Work Order" text link can never drift
    // to different URLs.
    $workOrderUrl = ($showWorkOrderLink ?? false)
        ? route($workOrderRouteName ?? 'subcon.vendor.orders.view', $order->id)
        : null;

    $targetSum = array_sum(array_column($group['sizes'], 'target_qty'));

    // Always summed from the FULL ratio list, regardless of collapse, so
    // the Total column stays correct even when the individual columns
    // aren't rendered.
    $ratioSums = [];
    foreach ($ratioColumns as $r) {
        $daySum = 0;
        $hasDay = false;
        $cumSum = 0;
        foreach ($group['sizes'] as $s) {
            $cd = $s['cutting'][$r];
            if ($cd['day_value'] !== null) {
                $daySum += (int) $cd['day_value'];
                $hasDay = true;
            }
            $cumSum += (int) $cd['cumulative'];
        }
        $ratioSums[$r] = ['day' => $hasDay ? $daySum : null, 'cum' => $cumSum];
    }
    $cuttingTotalDaySum = null;
    $cuttingTotalCum = 0;
    foreach ($ratioSums as $rs) {
        if ($rs['day'] !== null) {
            $cuttingTotalDaySum = ($cuttingTotalDaySum ?? 0) + $rs['day'];
        }
        $cuttingTotalCum += $rs['cum'];
    }

    // Sewing + the 3 Finishing group columns all share this same plain
    // per-column sum shape (no combined "Finishing & Packing" total — see
    // finishingStages() in the controller for why summing sequential steps
    // would be misleading).
    $stageSums = [];
    foreach ($flatStages + $finishingStages as $key => $label) {
        $daySum = 0;
        $hasDay = false;
        $cumSum = 0;
        foreach ($group['sizes'] as $s) {
            $sd = $s['stages'][$key];
            if ($sd['day_value'] !== null) {
                $daySum += (int) $sd['day_value'];
                $hasDay = true;
            }
            $cumSum += (int) $sd['cumulative'];
        }
        $stageSums[$key] = ['day' => $hasDay ? $daySum : null, 'cum' => $cumSum];
    }
@endphp

<div class="card mb-4 dp-style-card" id="dp-style-{{ $order->id }}">
    <div class="card-body p-0">
        <div class="dp-style-header {{ $isClosed ? 'is-closed' : '' }} p-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <span class="fw-semibold">{{ $order->title ?: $order->order_number }}</span>
                        @if($workOrderUrl)
                            <a href="{{ $workOrderUrl }}" class="small font-monospace dp-order-link">{{ $order->order_number }}</a>
                        @else
                            <span class="text-muted small font-monospace">{{ $order->order_number }}</span>
                        @endif
                        @if($order->production_group)
                            @if($workOrderUrl)
                                <a href="{{ $workOrderUrl }}" class="code-pill dp-prg-link">{{ $order->production_group }}</a>
                            @else
                                <span class="code-pill">{{ $order->production_group }}</span>
                            @endif
                        @endif
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-light text-dark border">{{ $order->stageLabel() }}</span>
                        @if($showVendorName ?? false)
                            <span class="text-muted small">{{ $order->vendor->name ?? '—' }}</span>
                        @endif
                        @if($order->due_date)
                            <span class="text-muted small"><i class="fas fa-calendar-day me-1"></i>Due {{ $order->due_date->format('d M Y') }}</span>
                        @endif
                        @if($isClosed)
                            <span class="badge bg-secondary">Closed</span>
                        @else
                            <span class="badge bg-success">Active</span>
                        @endif
                        @if($group['readyToClose'] && ! $isClosed)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="fas fa-check-circle me-1"></i>Ready to close
                            </span>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($workOrderUrl)
                        <a href="{{ $workOrderUrl }}" class="small text-muted">
                            View Work Order <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                        </a>
                    @endif
                    @if($editable)
                        @if($isClosed)
                            <button type="submit" formaction="{{ route('subcon.vendor.daily-production.reopen', $order->id) }}" formnovalidate
                                    class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-rotate-left me-1"></i>Reopen
                            </button>
                        @else
                            <button type="submit" formaction="{{ route('subcon.vendor.daily-production.close', $order->id) }}" formnovalidate
                                    class="btn btn-sm btn-outline-secondary"
                                    onclick="return confirm('Close this style? It stays on the board as a record, but no further output can be logged.');">
                                <i class="fas fa-flag-checkered me-1"></i>Close
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="table-responsive dp-table-wrap">
            <table class="table table-bordered mb-0 dp-table align-middle">
                <colgroup>
                    <col style="width: 11%;">
                    <col style="width: 7%;">
                    @for($i = 0; $i < $ratioCount; $i++)
                        <col class="dp-ratio-col" style="width: {{ $dataColWidth }}%;">
                    @endfor
                    @if($showAddRatioColumn)
                        <col style="width: {{ $addColWidth }}%;">
                    @endif
                    <col style="width: 8%;">
                    @for($i = 0; $i < $stageCount; $i++)
                        <col style="width: {{ $dataColWidth }}%;">
                    @endfor
                    @for($i = 0; $i < $finishingCount; $i++)
                        <col style="width: {{ $dataColWidth }}%;">
                    @endfor
                </colgroup>
                <thead>
                    <tr>
                        <th class="ps-3" rowspan="2">Size</th>
                        <th class="text-end" rowspan="2">Order Qty</th>
                        <th class="text-center" colspan="{{ $ratioCount + 1 + ($showAddRatioColumn ? 1 : 0) }}">
                            Cutting
                            @if($fullRatioCount > 0)
                                <button type="button" class="dp-cutting-toggle"
                                        onclick="dpToggleCuttingRatios({{ \Illuminate\Support\Js::from($order->id) }})"
                                        title="{{ $cuttingCollapsed ? 'Show the ratio columns' : 'Hide the ratio columns' }}">
                                    <i class="fas {{ $cuttingCollapsed ? 'fa-chevron-down' : 'fa-chevron-up' }}"></i>
                                </button>
                            @endif
                        </th>
                        @foreach($flatStages as $key => $label)
                            <th class="text-end" rowspan="2">{{ $label }}</th>
                        @endforeach
                        <th class="text-center" colspan="{{ $finishingCount }}">Finishing &amp; Packing</th>
                    </tr>
                    <tr>
                        @foreach($renderRatioColumns as $r)
                            <th class="text-end dp-ratio-cell">{{ $r }}</th>
                        @endforeach
                        @if($showAddRatioColumn)
                            <th class="text-center" style="border-right:none;">
                                <button type="button" class="btn btn-sm btn-link p-0 text-primary d-inline-flex align-items-center gap-1"
                                        onclick="dpAddRatioColumn({{ \Illuminate\Support\Js::from($order->id) }}, {{ \Illuminate\Support\Js::from($ratioColumns) }})"
                                        title="Add a ratio/marker column for this style">
                                    <i class="fas fa-plus-circle"></i>
                                    @if($fullRatioCount === 0)
                                        <span class="text-nowrap">Add ratio/marker</span>
                                    @endif
                                </button>
                            </th>
                        @endif
                        <th class="text-end dp-cutting-total">Total</th>
                        @foreach($finishingStages as $key => $label)
                            <th class="text-end dp-finishing-cell">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($group['sizes'] as $size)
                        @php
                            $rowCuttingDaySum = null;
                            $rowCuttingCum = 0;
                            foreach ($ratioColumns as $r) {
                                $cd = $size['cutting'][$r];
                                if ($cd['day_value'] !== null) {
                                    $rowCuttingDaySum = ($rowCuttingDaySum ?? 0) + (int) $cd['day_value'];
                                }
                                $rowCuttingCum += (int) $cd['cumulative'];
                            }
                        @endphp
                        <tr class="dp-size-row {{ $isClosed ? 'is-closed' : '' }}">
                            <td class="ps-4 fw-semibold">{{ $size['size'] ?: '—' }}</td>
                            <td class="text-end">{{ $size['target_qty'] > 0 ? number_format($size['target_qty']) : '—' }}</td>

                            @foreach($renderRatioColumns as $r)
                                @php $cd = $size['cutting'][$r]; @endphp
                                <td class="text-end dp-ratio-cell">
                                    @if($editable)
                                        <input type="number" min="0" step="1" inputmode="numeric" autocomplete="off" placeholder="0"
                                               name="entries[{{ $order->id }}][cutting][{{ $r }}][{{ $size['prod_id'] }}]"
                                               value="{{ $cd['day_value'] }}"
                                               class="form-control form-control-sm text-end w-100 dp-input {{ $cd['day_value'] !== null ? 'dp-has-value' : '' }}"
                                               @if($isClosed) disabled @endif>
                                    @else
                                        <div class="fw-semibold">{{ $cd['day_value'] !== null ? number_format($cd['day_value']) : '—' }}</div>
                                    @endif
                                    <div class="dp-sofar">
                                        {{ number_format($cd['cumulative']) }} cut
                                    </div>
                                </td>
                            @endforeach
                            @if($showAddRatioColumn)
                                <td></td>
                            @endif
                            <td class="text-end dp-cutting-total">
                                <div class="fw-semibold">{{ $rowCuttingDaySum !== null ? number_format($rowCuttingDaySum) : '—' }}</div>
                                <div class="dp-sofar">
                                    {{ number_format($rowCuttingCum) }} cut
                                </div>
                            </td>

                            @php
                                $stagePrevTargets = [
                                    'sewing' => $rowCuttingCum,
                                    'finishing' => (int) ($size['stages']['sewing']['cumulative'] ?? 0),
                                    'qc' => (int) ($size['stages']['finishing']['cumulative'] ?? 0),
                                    'packing' => (int) ($size['stages']['qc']['cumulative'] ?? 0),
                                ];
                            @endphp
                            @foreach($flatStages + $finishingStages as $key => $label)
                                @php
                                    $sd = $size['stages'][$key];
                                    $stageTarget = $stagePrevTargets[$key] ?? 0;
                                @endphp
                                <td class="text-end {{ array_key_exists($key, $finishingStages) ? 'dp-finishing-cell' : '' }}">
                                    @if($editable)
                                        <input type="number" min="0" step="1" inputmode="numeric" autocomplete="off" placeholder="0"
                                               name="entries[{{ $order->id }}][{{ $key }}][{{ $size['prod_id'] }}]"
                                               value="{{ $sd['day_value'] }}"
                                               class="form-control form-control-sm text-end w-100 dp-input {{ $sd['day_value'] !== null ? 'dp-has-value' : '' }}"
                                               @if($isClosed) disabled @endif>
                                    @else
                                        <div class="fw-semibold">{{ $sd['day_value'] !== null ? number_format($sd['day_value']) : '—' }}</div>
                                    @endif
                                    <div class="dp-sofar">
                                        @if($stageTarget > 0)
                                            {{ number_format($sd['cumulative']) }} of {{ number_format($stageTarget) }} made
                                        @else
                                            {{ number_format($sd['cumulative']) }} made
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + $ratioCount + 1 + ($showAddRatioColumn ? 1 : 0) + $stageCount + $finishingCount }}" class="text-center text-muted py-3 small">
                                No sized production lines resolved for this PO yet — check back once VSM/PLM data is linked.
                            </td>
                        </tr>
                    @endforelse

                    @if(! empty($group['sizes']))
                        <tr class="dp-total-row fw-semibold">
                            <td class="ps-4 text-muted">Total</td>
                            <td class="text-end">{{ $targetSum > 0 ? number_format($targetSum) : '—' }}</td>
                            @foreach($renderRatioColumns as $r)
                                <td class="text-end dp-ratio-cell">
                                    {{ $ratioSums[$r]['day'] !== null ? number_format($ratioSums[$r]['day']) : '—' }}
                                    <div class="dp-sofar">
                                        {{ number_format($ratioSums[$r]['cum']) }} cut
                                    </div>
                                </td>
                            @endforeach
                            @if($showAddRatioColumn)
                                <td></td>
                            @endif
                            <td class="text-end dp-cutting-total">
                                {{ $cuttingTotalDaySum !== null ? number_format($cuttingTotalDaySum) : '—' }}
                                <div class="dp-sofar">
                                    {{ number_format($cuttingTotalCum) }} cut
                                </div>
                            </td>
                            @php
                                $totalStagePrevTargets = [
                                    'sewing' => $cuttingTotalCum,
                                    'finishing' => (int) ($stageSums['sewing']['cum'] ?? 0),
                                    'qc' => (int) ($stageSums['finishing']['cum'] ?? 0),
                                    'packing' => (int) ($stageSums['qc']['cum'] ?? 0),
                                ];
                            @endphp
                            @foreach($flatStages + $finishingStages as $key => $label)
                                @php
                                    $totalTarget = $totalStagePrevTargets[$key] ?? 0;
                                @endphp
                                <td class="text-end {{ array_key_exists($key, $finishingStages) ? 'dp-finishing-cell' : '' }}">
                                    {{ $stageSums[$key]['day'] !== null ? number_format($stageSums[$key]['day']) : '—' }}
                                    <div class="dp-sofar">
                                        @if($totalTarget > 0)
                                            {{ number_format($stageSums[$key]['cum']) }} of {{ number_format($totalTarget) }} made
                                        @else
                                            {{ number_format($stageSums[$key]['cum']) }} made
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @php
            $styleNote = $group['dayNote'] ?? '';
        @endphp
        @if($editable && ! $isClosed)
            <div class="px-3 py-2 bg-light border-top d-flex align-items-center gap-2">
                <label for="note-{{ $order->id }}" class="form-label mb-0 text-muted small text-nowrap">
                    <i class="fas fa-sticky-note text-secondary me-1"></i> Daily Remarks (Optional):
                </label>
                <input type="text" id="note-{{ $order->id }}" name="notes[{{ $order->id }}]"
                       class="form-control form-control-sm bg-white"
                       placeholder="e.g. Fabric delay, machine issue, overtime, etc."
                       value="{{ $styleNote }}" maxlength="2000">
            </div>
        @elseif(! empty($styleNote))
            <div class="px-3 py-2 bg-light border-top small text-muted">
                <i class="fas fa-sticky-note text-secondary me-1"></i>
                <span class="fw-semibold">Daily Remarks:</span> {{ $styleNote }}
            </div>
        @endif
    </div>
</div>
