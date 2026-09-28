<?php

namespace App\Services;

use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vsm\SubconDailyProductionDayNote;
use App\Models\Vsm\SubconDailyProductionEntry;
use App\Models\Vsm\SubconDailyProductionStyle;
use Illuminate\Support\Collection;

/**
 * Factory-floor daily production tracking: for each active style (SubconOrder)
 * the vendor logs daily output at the physical production steps — Cutting
 * (its own ratio/marker breakdown), Sewing, then Finishing & Packing broken
 * down into its own 3 real steps (Finishing, QC, Packing) — independent of
 * (and not gated by) the SubconOrder approval-workflow stage. Load to Sewing
 * and Load to Finishing are no longer entered directly; see forOrder()'s
 * computed totals for those two.
 *
 * Storage lives on the `vsm` connection per business requirement — see
 * SubconDailyProductionEntry/Style for the table-ownership note. All *reads*
 * here are best-effort (never throw) to match the app's existing VSM-read
 * convention; saveEntry()/closeStyle()/reopenStyle() are real writes and are
 * NOT best-effort — a vsm failure must surface to the caller since there is
 * no local copy of this data to fall back on.
 */
class SubconDailyProductionService
{
    public function __construct(private SubconProductionService $production) {}

    public function stages(): array
    {
        return SubconDailyProductionEntry::stages();
    }

    /**
     * Status + last-entry-date + closed_at for a batch of orders (for an
     * index list), fetched in two batched vsm queries rather than
     * per-order. closed_at drives the board's "a closed style disappears
     * the day after it closed" rule (see
     * SubconDailyProductionController::isVisibleOnBoard()) — kept here
     * rather than hidden immediately so closing one is still visible as
     * confirmation on the day it happens.
     *
     * @return array<string, array{status: string, last_entry_date: ?string, closed_at: ?string}>
     */
    public function statusesFor(Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->all();
        if (empty($orderIds)) {
            return [];
        }

        try {
            $styles = SubconDailyProductionStyle::whereIn('order_id', $orderIds)->get()->keyBy('order_id');
        } catch (\Throwable $e) {
            report($e);
            $styles = collect();
        }

        try {
            $lastEntries = SubconDailyProductionEntry::whereIn('order_id', $orderIds)
                ->selectRaw('order_id, MAX(entry_date) as last_date')
                ->groupBy('order_id')
                ->get()
                ->keyBy('order_id');
        } catch (\Throwable $e) {
            report($e);
            $lastEntries = collect();
        }

        $out = [];
        foreach ($orderIds as $id) {
            $out[$id] = [
                'status' => $styles->get($id)?->status ?? 'not_started',
                'last_entry_date' => $lastEntries->get($id)?->last_date,
                'closed_at' => $styles->get($id)?->closed_at?->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * Full detail for one order's daily production page: the production
     * groups/sizes (same source the cutting report uses), the style header,
     * per-stage/per-size cumulative totals, and per-stage day-by-day history.
     */
    public function forOrder(SubconOrder $order): array
    {
        try {
            $productionGroups = $this->production->forPo($order->order_number);
        } catch (\Throwable $e) {
            report($e);
            $productionGroups = [];
        }

        $sizes = [];
        foreach ($productionGroups as $g) {
            foreach ($g['lines'] as $line) {
                $sizes[] = [
                    'prod_id' => $line->ProdId,
                    'size' => $line->Size,
                    'target_qty' => (float) ($line->Qty ?? 0),
                    'group_name' => $g['group_name'] ?: ($g['article_name'] ?? null),
                ];
            }
        }

        try {
            $style = SubconDailyProductionStyle::where('order_id', $order->id)->first();
        } catch (\Throwable $e) {
            report($e);
            $style = null;
        }

        try {
            $entries = SubconDailyProductionEntry::where('order_id', $order->id)
                ->orderBy('entry_date')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $entries = collect();
        }

        $stages = array_keys($this->stages());
        $totals = array_fill_keys($stages, []);
        $history = array_fill_keys($stages, []);

        foreach ($entries->groupBy('stage') as $stageKey => $stageEntries) {
            if (! array_key_exists($stageKey, $totals)) {
                continue; // guard against an unrecognised stage value
            }

            foreach ($stageEntries as $entry) {
                $totals[$stageKey][$entry->prod_id] = ($totals[$stageKey][$entry->prod_id] ?? 0) + (int) $entry->quantity;
            }

            $byDate = $stageEntries->groupBy(fn ($e) => $e->entry_date->format('Y-m-d'));
            foreach ($byDate as $date => $rows) {
                $history[$stageKey][] = [
                    'entry_date' => $date,
                    'rows' => $rows->keyBy('prod_id')->map(fn ($r) => (int) $r->quantity)->all(),
                    'total' => (int) $rows->sum('quantity'),
                ];
            }
            usort($history[$stageKey], fn ($a, $b) => strcmp($b['entry_date'], $a['entry_date']));
        }

        // Load to Sewing / Load to Finishing are no longer entered by the
        // vendor — they're computed as a mirror of the stage right before
        // them (everything cut eventually gets loaded to sewing, everything
        // sewn eventually gets loaded to finishing) rather than read from
        // stored rows, since nothing writes to them anymore.
        $totals[SubconDailyProductionEntry::STAGE_LOAD_SEWING] = $totals[SubconDailyProductionEntry::STAGE_CUTTING] ?? [];
        $totals[SubconDailyProductionEntry::STAGE_LOAD_FINISHING] = $totals[SubconDailyProductionEntry::STAGE_SEWING] ?? [];

        return [
            'style' => $style,
            'production_groups' => $productionGroups,
            'sizes' => $sizes,
            'totals' => $totals,
            'history' => $history,
        ];
    }

    /**
     * Persist one day's worth of output for one stage across sizes. Real
     * write — throws on failure (vsm unreachable, closed style) rather than
     * degrading silently.
     *
     * Cutting is the one stage with a third dimension: which marker/lay
     * ("Ratio A", "Ratio B", ...) produced the output — two different
     * ratios can both contribute to the same size on the same date, which
     * a flat (stage, prod_id, date) key can't represent. `$ratioLabel` is
     * therefore REQUIRED for the cutting stage (a blank/omitted one
     * throws) and ignored (forced to NO_RATIO) for every other stage.
     *
     * @param  array<int, array{prod_id: string, size: ?string, quantity: int|string|null}>  $rows
     */
    public function saveEntry(SubconOrder $order, string $stage, string $entryDate, array $rows, User $user, ?string $ratioLabel = null): void
    {
        if (! array_key_exists($stage, $this->stages())) {
            throw new \InvalidArgumentException("Unknown daily production stage: {$stage}");
        }

        $isCutting = $stage === SubconDailyProductionEntry::STAGE_CUTTING;
        $ratioLabel = $isCutting ? trim((string) $ratioLabel) : SubconDailyProductionEntry::NO_RATIO;
        if ($isCutting && $ratioLabel === '') {
            throw new \InvalidArgumentException('Enter a ratio/marker label (e.g. "Ratio A") before saving a cutting entry.');
        }

        $style = SubconDailyProductionStyle::firstOrNew(['order_id' => $order->id]);
        if ($style->exists && $style->isClosed()) {
            throw new \RuntimeException('This style is closed to new daily production entries — reopen it first.');
        }

        if (! $style->exists) {
            $style->order_number = $order->order_number;
            $style->vendor_name = $order->vendor->name ?? null;
            $style->status = SubconDailyProductionStyle::STATUS_OPEN;
            $style->save();
        }

        foreach ($rows as $row) {
            if (! isset($row['prod_id']) || $row['prod_id'] === '') {
                continue;
            }
            if (! isset($row['quantity']) || $row['quantity'] === '' || $row['quantity'] === null) {
                continue; // untouched size for this day — leave no row
            }

            $payload = [
                'order_number' => $order->order_number,
                'vendor_name' => $order->vendor->name ?? null,
                'quantity' => (int) $row['quantity'],
                'entered_by_user_id' => $user->id,
                'entered_by_name' => $user->name,
            ];
            // Only touch `size` when the caller actually sent one — a bulk
            // save that omits it (size is resolved elsewhere) must not null
            // out a value an earlier save already recorded for this row.
            if (! empty($row['size'])) {
                $payload['size'] = $row['size'];
            }

            SubconDailyProductionEntry::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'stage' => $stage,
                    'ratio_label' => $ratioLabel,
                    'prod_id' => $row['prod_id'],
                    'entry_date' => $entryDate,
                ],
                $payload
            );
        }
    }

    /**
     * Cutting output grouped by ratio/marker, then by date — mirrors the
     * factory's own cutting-report layout (Ratio A's dates, then Ratio B's,
     * ..., then a grand total per size). Best-effort ([] on failure).
     * Pre-ratio-feature rows (ratio_label = '') surface under the
     * "Unspecified" bucket rather than disappearing.
     *
     * @return array{ratios: array<int, array{label: string, raw_label: string, dates: array, subtotal: array<string,int>}>, grand_total: array<string,int>}
     */
    public function cuttingRatioBreakdown(SubconOrder $order): array
    {
        try {
            $entries = SubconDailyProductionEntry::where('order_id', $order->id)
                ->where('stage', SubconDailyProductionEntry::STAGE_CUTTING)
                ->orderBy('ratio_label')
                ->orderBy('entry_date')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $entries = collect();
        }

        $ratios = [];
        $grandTotal = [];

        foreach ($entries->groupBy('ratio_label') as $ratioLabel => $ratioEntries) {
            $dates = [];
            $subtotal = [];

            foreach ($ratioEntries->groupBy(fn ($e) => $e->entry_date->format('Y-m-d')) as $date => $dayEntries) {
                $values = $dayEntries->keyBy('prod_id')->map(fn ($e) => (int) $e->quantity)->all();
                foreach ($values as $prodId => $qty) {
                    $subtotal[$prodId] = ($subtotal[$prodId] ?? 0) + $qty;
                }
                $dates[] = [
                    'entry_date' => $date,
                    'values' => $values,
                    'total' => (int) $dayEntries->sum('quantity'),
                ];
            }
            usort($dates, fn ($a, $b) => strcmp($a['entry_date'], $b['entry_date']));

            foreach ($subtotal as $prodId => $qty) {
                $grandTotal[$prodId] = ($grandTotal[$prodId] ?? 0) + $qty;
            }

            $ratios[] = [
                'label' => $ratioLabel === SubconDailyProductionEntry::NO_RATIO ? 'Unspecified' : $ratioLabel,
                'raw_label' => $ratioLabel,
                'dates' => $dates,
                'subtotal' => $subtotal,
            ];
        }

        return ['ratios' => $ratios, 'grand_total' => $grandTotal];
    }

    /**
     * Marks a style as actively tracked (creates its open header) without
     * requiring an entry yet — the board's "+ Add Style" action. Idempotent:
     * does nothing if the style already has a header (open or closed) —
     * activation only ever creates, it never reopens a closed style.
     */
    public function activateStyle(SubconOrder $order, User $user): void
    {
        $style = SubconDailyProductionStyle::firstOrNew(['order_id' => $order->id]);
        if ($style->exists) {
            return;
        }

        $style->order_number = $order->order_number;
        $style->vendor_name = $order->vendor->name ?? null;
        $style->status = SubconDailyProductionStyle::STATUS_OPEN;
        $style->save();
    }

    public function closeStyle(SubconOrder $order, User $user, ?string $notes = null): void
    {
        $style = SubconDailyProductionStyle::firstOrNew(['order_id' => $order->id]);
        if (! $style->exists) {
            $style->order_number = $order->order_number;
            $style->vendor_name = $order->vendor->name ?? null;
        }
        $style->status = SubconDailyProductionStyle::STATUS_CLOSED;
        $style->closed_at = now();
        $style->closed_by_name = $user->name;
        if ($notes !== null) {
            $style->notes = $notes;
        }
        $style->save();
    }

    public function reopenStyle(SubconOrder $order): void
    {
        $style = SubconDailyProductionStyle::where('order_id', $order->id)->first();
        if (! $style) {
            return;
        }
        $style->status = SubconDailyProductionStyle::STATUS_OPEN;
        $style->closed_at = null;
        $style->closed_by_name = null;
        $style->save();
    }

    /**
     * Cumulative cutting-stage totals per prod_id, for prefilling the
     * existing cutting-report approval form. Best-effort ([] on failure).
     */
    public function cuttingPlaceholderTotals(SubconOrder $order): array
    {
        try {
            return SubconDailyProductionEntry::where('order_id', $order->id)
                ->where('stage', SubconDailyProductionEntry::STAGE_CUTTING)
                ->get()
                ->groupBy('prod_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'))
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * True once every sized line's Packing cumulative — the terminal step of the
     * sequential flow (Cutting → Sewing → Finishing → QC → Packing) — has reached
     * the actual production cut quantity (or the PO target qty if cutting wasn't logged).
     * Sizes with no resolvable target (0) are skipped rather than blocking forever.
     */
    public function isReadyToClose(array $forOrderResult): bool
    {
        $sizes = $forOrderResult['sizes'] ?? [];
        if (empty($sizes)) {
            return false;
        }

        $finishingTotals = $forOrderResult['totals'][SubconDailyProductionEntry::STAGE_PACKING] ?? [];
        $cuttingTotals = $forOrderResult['totals'][SubconDailyProductionEntry::STAGE_CUTTING] ?? [];
        $sawTarget = false;
        foreach ($sizes as $s) {
            $cutQty = (float) ($cuttingTotals[$s['prod_id']] ?? 0);
            $target = $cutQty > 0 ? $cutQty : (float) $s['target_qty'];
            if ($target <= 0) {
                continue;
            }
            $sawTarget = true;
            $done = (float) ($finishingTotals[$s['prod_id']] ?? 0);
            if ($done < $target) {
                return false;
            }
        }

        return $sawTarget;
    }

    /**
     * Fetch optional daily notes for a batch of orders and an entry date.
     * Best-effort ([] on failure).
     *
     * @return array{styles: array<string, string>, general: ?string}
     */
    public function dayNotesFor(Collection $orders, string $entryDate, ?string $vendorId = null): array
    {
        $orderIds = $orders->pluck('id')->filter()->all();

        try {
            $query = SubconDailyProductionDayNote::whereDate('entry_date', $entryDate);
            if ($vendorId && empty($orderIds)) {
                $query->where('vendor_id', $vendorId);
            } else {
                $query->where(function ($q) use ($orderIds, $vendorId) {
                    if (! empty($orderIds)) {
                        $q->whereIn('order_id', $orderIds);
                    }
                    if ($vendorId) {
                        $q->orWhere(function ($sub) use ($vendorId) {
                            $sub->where('vendor_id', $vendorId)->whereNull('order_id');
                        });
                    }
                });
            }

            $rows = $query->get();
        } catch (\Throwable $e) {
            report($e);
            $rows = collect();
        }

        $styles = [];
        $general = null;

        foreach ($rows as $row) {
            if ($row->order_id) {
                $styles[$row->order_id] = $row->notes ?? '';
            } elseif ($vendorId && $row->vendor_id === $vendorId) {
                $general = $row->notes;
            }
        }

        return [
            'styles' => $styles,
            'general' => $general,
        ];
    }

    /**
     * Save an optional daily note for a specific style and date.
     */
    public function saveStyleDayNote(SubconOrder $order, string $entryDate, ?string $note, User $user): void
    {
        $note = trim((string) $note);
        if ($note === '') {
            try {
                SubconDailyProductionDayNote::where('order_id', $order->id)
                    ->whereDate('entry_date', $entryDate)
                    ->delete();
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        SubconDailyProductionDayNote::updateOrCreate(
            [
                'order_id' => $order->id,
                'entry_date' => $entryDate,
            ],
            [
                'vendor_id' => $order->vendor_id,
                'order_number' => $order->order_number,
                'notes' => $note,
                'entered_by_user_id' => $user->id,
                'entered_by_name' => $user->name,
            ]
        );
    }

    /**
     * Save an optional overall day note for a vendor and date.
     */
    public function saveVendorDayNote(string $vendorId, string $entryDate, ?string $note, User $user): void
    {
        $note = trim((string) $note);
        if ($note === '') {
            try {
                SubconDailyProductionDayNote::where('vendor_id', $vendorId)
                    ->whereNull('order_id')
                    ->whereDate('entry_date', $entryDate)
                    ->delete();
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        SubconDailyProductionDayNote::updateOrCreate(
            [
                'vendor_id' => $vendorId,
                'order_id' => null,
                'entry_date' => $entryDate,
            ],
            [
                'notes' => $note,
                'entered_by_user_id' => $user->id,
                'entered_by_name' => $user->name,
            ]
        );
    }
}

