<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Publishes an order's per-fabric consumption/reconciliation snapshot into the
 * QMS `packaging_project_fabric_lines` table so the external QC Console can PULL
 * it (keyed by production_group) whenever it starts its packaging project —
 * decoupling data-readiness (cutting approval) from project creation.
 *
 * Contract with the console:
 *   - Rows are STAGED with a NULL project_id at cutting-approval time (no project
 *     yet). The console adopts them by production_group when it starts a project.
 *   - At HO approval the portal knows the project_id and SETS it, so the console's
 *     existing read-by-project_id PDF path keeps working with zero change.
 *   - Upsert key is (production_group, label). project_id is only ever SET, never
 *     nulled, so a console-set linkage survives a re-publish (e.g. HO revision).
 *
 * Best-effort + fully guarded: a missing/older QMS schema or an unreachable QMS
 * DB logs-and-skips. It must NEVER block a cutting approval or an HO sign-off.
 */
class SubconFabricLinePublisher
{
    private const TABLE = 'packaging_project_fabric_lines';

    public function __construct(private SubconProductionService $production) {}

    /**
     * @param  string|null  $projectId  set to adopt the rows to a project (HO stage);
     *                                  null stages them (cutting stage, no project yet).
     */
    public function publish(SubconOrder $order, ?string $projectId = null): void
    {
        $pg = trim((string) ($order->production_group ?? ''));
        if ($pg === '') {
            return; // no production_group → nothing the console could fetch by
        }

        try {
            // production_group is the publish key — without it (un-migrated QMS)
            // there is nowhere to stage, so skip rather than write orphaned rows.
            if (! Schema::connection('qms')->hasTable(self::TABLE)
                || ! Schema::connection('qms')->hasColumn(self::TABLE, 'production_group')) {
                Log::warning('Subcon fabric publish skipped: QMS table/production_group column missing', [
                    'order' => $order->order_number,
                ]);

                return;
            }

            // Newer columns — only write them where the console schema has them.
            $hasOver = Schema::connection('qms')->hasColumn(self::TABLE, 'overconsumption');
            $hasPrice = Schema::connection('qms')->hasColumn(self::TABLE, 'fabric_price');
            $hasDed = Schema::connection('qms')->hasColumn(self::TABLE, 'deduction');

            $lines = $this->production->fabricLinesWithData($order);
            $written = 0;

            foreach ($lines as $f) {
                $label = (string) $f['label'];

                // NOT NULL numeric columns must never receive null → coalesce to 0.
                $vals = [
                    'label' => $label,
                    'fabric_sent' => round((float) ($f['fabric_sent'] ?? 0), 2),
                    'consumption_plan' => round((float) ($f['consumption_plan'] ?? 0), 4),
                    'cutt_plan' => (int) ($f['cutt_plan'] ?? 0),
                    'actual_consumption' => round((float) ($f['actual_consumption'] ?? 0), 4),
                    'short_roll' => round((float) ($f['short_roll'] ?? 0), 2),
                    'sisa_kain' => round((float) ($f['sisa_kain'] ?? 0), 2),
                    'kepala_kain' => round((float) ($f['kepala_kain'] ?? 0), 2),
                    'return_kain' => round((float) ($f['retur_kain'] ?? 0), 2), // portal retur_kain → QMS return_kain
                ];
                if ($hasOver) {
                    $vals['overconsumption'] = round((float) ($f['overconsumption'] ?? 0), 4);
                }
                if ($hasPrice) {
                    $vals['fabric_price'] = round((float) ($f['fabric_price'] ?? 0), 2);
                }
                if ($hasDed) {
                    $vals['deduction'] = round((float) ($f['deduction'] ?? 0), 2);
                }

                $exists = DB::connection('qms')->table(self::TABLE)
                    ->where('production_group', $pg)
                    ->where('label', $label)
                    ->exists();

                if ($exists) {
                    // Only SET project_id when we have one; never null an existing
                    // console-set linkage on re-publish.
                    if ($projectId !== null && $projectId !== '') {
                        $vals['project_id'] = $projectId;
                    }
                    DB::connection('qms')->table(self::TABLE)
                        ->where('production_group', $pg)
                        ->where('label', $label)
                        ->update($vals);
                } else {
                    $vals['id'] = (string) Str::uuid();
                    $vals['production_group'] = $pg;
                    $vals['project_id'] = ($projectId !== null && $projectId !== '') ? $projectId : null;
                    $vals['created_by'] = 'web_portal';
                    $vals['created_at'] = now();
                    DB::connection('qms')->table(self::TABLE)->insert($vals);
                }
                $written++;
            }

            Log::info('Subcon fabric lines published to QMS', [
                'order' => $order->order_number,
                'production_group' => $pg,
                'lines' => $written,
                'project_id' => $projectId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Subcon fabric publish failed', [
                'order' => $order->order_number ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
