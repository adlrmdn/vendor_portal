<?php

namespace App\Services;

use App\Models\MaterialReturnAttachment;
use App\Models\MaterialReturnLine;
use App\Models\MaterialReturnTask;
use App\Models\SubconFabricReconciliation;
use App\Models\SubconOrder;
use Illuminate\Http\UploadedFile;

/**
 * Delivery-note attachments + per-item declared return QUANTITY lines
 * (fabric or accessory/trim, mostly PCS) + the "send to Material Flow"
 * inventory-check task, for material the SUBCON VENDOR returns to us (e.g.
 * unused/excess cut fabric or trims) — NOT material we return to our own
 * upstream fabric supplier, which is a separate, unbuilt flow; don't
 * repurpose this service/tables/disk for that. value_stream_ops's Material
 * Flow tab is where quantities get VALIDATED — it fills in `qty_actual` per
 * line (the "corrective measure": inventory's real count can differ from
 * what was declared) before marking the task checked. All three tables live
 * in the `wms` database (see config/database.php's `wms` connection).
 * Attachment files go to the `material_prod_return` S3 disk
 * (config/filesystems.php — bucket rpa-lake, same instance-role access as
 * rpa_lake, own prefix). Best-effort throughout: a `wms` outage must never
 * block the vendor/admin work-order flow, matching the qms helpers' contract
 * elsewhere in this app (SubconOrder::pending*Count()).
 */
class MaterialReturnService
{
    private const DISK = 'material_prod_return';

    /**
     * Attaching does NOT auto-dispatch to Material Flow — "Send to Material
     * Flow" (dispatchTask()) stays a deliberate, separate press by MD
     * Production. The gate is still airtight without that: isReturnCheckPending()
     * blocks on any attachment that hasn't been linked to a task at all, not
     * just on an existing task's status — see its docblock.
     */
    public function upload(SubconOrder $order, UploadedFile $file, ?string $note, string $role, string $actorName): MaterialReturnAttachment
    {
        $path = $file->store($order->order_number, self::DISK);

        return MaterialReturnAttachment::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'vendor_name' => $order->vendor?->name,
            'uploaded_by_role' => $role,
            'uploaded_by_name' => $actorName,
            's3_disk' => self::DISK,
            's3_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'note' => $note,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Upserts declared return-quantity lines (fabric or accessory, keyed by
     * (order_id, item_type, label) — same upsert-by-label shape as
     * SubconFabricReconciliation, not an append-only log). Rows with an
     * empty/zero qty are skipped, same "skip empty rows" convention as the
     * HO-approval-form deduction rows. Does NOT auto-dispatch — same reasoning
     * as upload().
     *
     * @param  array<int, array{item_type: string, label: string, item_number?: ?string, unit?: ?string, qty_declared: mixed}>  $lines
     * @return \Illuminate\Support\Collection<int, MaterialReturnLine>
     */
    public function saveLines(SubconOrder $order, array $lines, string $role, string $actorName): \Illuminate\Support\Collection
    {
        $saved = collect();

        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $qty = $line['qty_declared'] ?? null;
            if ($label === '' || $qty === null || $qty === '' || (float) $qty <= 0) {
                continue;
            }

            $saved->push(MaterialReturnLine::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'item_type' => $line['item_type'] === MaterialReturnLine::TYPE_ACCESSORY
                        ? MaterialReturnLine::TYPE_ACCESSORY
                        : MaterialReturnLine::TYPE_FABRIC,
                    'label' => $label,
                ],
                [
                    'order_number' => $order->order_number,
                    'vendor_name' => $order->vendor?->name,
                    'item_number' => $line['item_number'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'qty_declared' => (float) $qty,
                    'uploaded_by_role' => $role,
                    'uploaded_by_name' => $actorName,
                ]
            ));
        }

        return $saved;
    }

    /**
     * Material Reconciliation (Fabric + Accessory), shared by vendor's
     * saveMaterialReconciliation()/submitCuttingReport() and the admin/HO
     * signed form (QcApprovalController) — both the cutting-stage embedded
     * copy and the standalone save-anytime copy call this.
     *
     * Fabric: upsert-by-label, updating ONLY the reconciliation fields so an
     * admin's consumption figures (fabric_sent/consumption_plan/…) on the
     * same row are never wiped by a resubmit. Rows for fabrics no longer
     * listed are pruned (only when the submitted list is non-empty — an
     * empty list from an unrendered form must never wipe everything).
     *
     * `$lockReturKain`: true (vendor) forces retur_kain = short_roll +
     * sisa_kain + kepala_kain server-side regardless of the posted value
     * (the vendor form's field is readonly but tamperable client-side).
     * false (admin/HO) respects the submitted retur_kain directly — CLAUDE.md's
     * documented rule that "the approver can still override it directly."
     *
     * Accessory: same upsert-by-label + prune shape, on the wms
     * material_return_lines table (item_type=accessory). Best-effort — a wms
     * outage must never block the fabric side / the caller's own save.
     */
    public function persistReconciliation(
        SubconOrder $order,
        array $fabricsRecon,
        array $accessoriesRecon,
        string $role,
        string $actorName,
        bool $lockReturKain = true
    ): void {
        $keptLabels = [];
        foreach ($fabricsRecon as $rec) {
            $label = trim((string) ($rec['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $keptLabels[] = $label;
            $short = round((float) ($rec['short_roll'] ?? 0), 2);
            $sisa = round((float) ($rec['sisa_kain'] ?? 0), 2);
            $kepala = round((float) ($rec['kepala_kain'] ?? 0), 2);
            $returKain = $lockReturKain
                ? round($short + $sisa + $kepala, 2)
                : round((float) ($rec['retur_kain'] ?? ($short + $sisa + $kepala)), 2);
            SubconFabricReconciliation::updateOrCreate(
                ['order_id' => $order->id, 'label' => $label],
                [
                    'short_roll' => $short,
                    'sisa_kain' => $sisa,
                    'kepala_kain' => $kepala,
                    'retur_kain' => $returKain,
                ]
            );
        }
        if (! empty($keptLabels)) {
            SubconFabricReconciliation::where('order_id', $order->id)
                ->whereNotIn('label', $keptLabels)
                ->delete();
        }

        try {
            $keptAccLabels = [];
            foreach ($accessoriesRecon as $rec) {
                $label = trim((string) ($rec['label'] ?? ''));
                $qty = (float) ($rec['qty'] ?? 0);
                if ($label === '' || $qty <= 0) {
                    continue;
                }
                $keptAccLabels[] = $label;
                MaterialReturnLine::updateOrCreate(
                    ['order_id' => $order->id, 'item_type' => MaterialReturnLine::TYPE_ACCESSORY, 'label' => $label],
                    [
                        'order_number' => $order->order_number,
                        'vendor_name' => $order->vendor?->name,
                        'unit' => trim((string) ($rec['unit'] ?? '')) ?: 'PCS',
                        'qty_declared' => round($qty, 2),
                        'uploaded_by_role' => $role,
                        'uploaded_by_name' => $actorName,
                    ]
                );
            }
            if (! empty($keptAccLabels)) {
                MaterialReturnLine::where('order_id', $order->id)
                    ->where('item_type', MaterialReturnLine::TYPE_ACCESSORY)
                    ->whereNotIn('label', $keptAccLabels)
                    ->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Idempotent: reuses an existing non-checked task instead of duplicating,
     * and backfills any attachments/lines saved before this dispatch onto it.
     *
     * Also snapshots fabric Retur Kain into material_return_lines
     * (item_type=fabric) so Material Flow can review/correct it exactly like
     * accessory lines — fabric's real editable source of truth stays
     * SubconFabricReconciliation (a different database entirely;
     * value_stream_ops has no connection to it), this is a point-in-time
     * copy, re-synced to the latest figure every time dispatch is (re-)pressed
     * while the task is still pending. Zero-value lines aren't snapshotted —
     * they aren't an actual return.
     */
    public function dispatchTask(SubconOrder $order, string $requestedBy): MaterialReturnTask
    {
        $task = MaterialReturnTask::where('order_id', $order->id)
            ->where('status', MaterialReturnTask::STATUS_PENDING)
            ->first();

        if (! $task) {
            $task = MaterialReturnTask::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'vendor_name' => $order->vendor?->name,
                'production_group' => $order->production_group,
                'status' => MaterialReturnTask::STATUS_PENDING,
                'requested_by' => $requestedBy,
                'requested_at' => now(),
            ]);
        }

        try {
            foreach (SubconFabricReconciliation::where('order_id', $order->id)->get() as $rec) {
                $returKain = round((float) $rec->retur_kain, 2);
                if ($returKain <= 0) {
                    continue;
                }
                MaterialReturnLine::updateOrCreate(
                    ['order_id' => $order->id, 'item_type' => MaterialReturnLine::TYPE_FABRIC, 'label' => $rec->label],
                    [
                        'order_number' => $order->order_number,
                        'vendor_name' => $order->vendor?->name,
                        'task_id' => $task->id,
                        'unit' => SubconProductionService::displayUnit($rec->label),
                        'qty_declared' => $returKain,
                        // Only admin/MD Production ever dispatches (see dispatchRoute
                        // being null on the vendor page) — attribute the snapshot to them.
                        'uploaded_by_role' => MaterialReturnAttachment::ROLE_ADMIN,
                        'uploaded_by_name' => $requestedBy,
                    ]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        MaterialReturnAttachment::where('order_id', $order->id)
            ->whereNull('task_id')
            ->update(['task_id' => $task->id]);

        MaterialReturnLine::where('order_id', $order->id)
            ->whereNull('task_id')
            ->update(['task_id' => $task->id]);

        return $task;
    }

    /**
     * True if either (a) a dispatched task hasn't been checked yet, or (b)
     * an attachment/line exists that was never linked to any task at all —
     * i.e. something was declared but "Send to Material Flow" was never
     * pressed. (b) is what closes the 2026-09-03 incident (MPG/PO/2606/01342
     * sent to the Director with an attached-but-never-dispatched note):
     * upload()/saveLines() deliberately do NOT auto-dispatch, so an
     * un-dispatched row is the normal state right after declaring it — it
     * blocks Director-send until MD Production either dispatches it (then
     * inventory checks it) — there is no "this doesn't need a check" escape
     * hatch by design. An order with nothing declared and no task has none
     * of these conditions, so this stays a no-op gate for it.
     */
    public function isReturnCheckPending(SubconOrder $order): bool
    {
        try {
            $hasUncheckedTask = MaterialReturnTask::where('order_id', $order->id)
                ->where('status', '!=', MaterialReturnTask::STATUS_CHECKED)
                ->exists();
            if ($hasUncheckedTask) {
                return true;
            }

            if (MaterialReturnAttachment::where('order_id', $order->id)->whereNull('task_id')->exists()) {
                return true;
            }

            return MaterialReturnLine::where('order_id', $order->id)->whereNull('task_id')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function attachmentsFor(SubconOrder $order)
    {
        try {
            return MaterialReturnAttachment::where('order_id', $order->id)
                ->orderByDesc('uploaded_at')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function linesFor(SubconOrder $order)
    {
        try {
            return MaterialReturnLine::where('order_id', $order->id)
                ->orderBy('item_type')
                ->orderBy('label')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function activeTaskFor(SubconOrder $order): ?MaterialReturnTask
    {
        try {
            return MaterialReturnTask::where('order_id', $order->id)
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
