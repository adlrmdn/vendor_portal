<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publishes a work order's vendor remarks to the QC Console using the STAGED,
 * production_group-keyed model (mirrors SubconFabricLinePublisher): the remark is
 * upserted into the QMS `packaging_project_remarks` table keyed by
 * production_group the moment it is saved — even before the console has created
 * its packaging project/session — so the console can PULL it by production_group
 * on its own schedule and nothing is missed.
 *
 * Best-effort + fully guarded: a missing table or unreachable QMS is logged and
 * skipped — it must NEVER block saving remarks or an approval.
 */
class SubconRemarksPublisher
{
    private const TABLE = 'packaging_project_remarks';

    public function publish(SubconOrder $order): void
    {
        $pg = trim((string) ($order->production_group ?? ''));
        if ($pg === '') {
            return; // no production_group → nothing the console could pull by
        }

        try {
            if (! Schema::connection('qms')->hasTable(self::TABLE)) {
                Log::warning('Subcon remarks publish skipped: QMS staging table missing', [
                    'order' => $order->order_number,
                ]);

                return;
            }

            $exists = DB::connection('qms')->table(self::TABLE)
                ->where('production_group', $pg)->exists();

            if ($exists) {
                DB::connection('qms')->table(self::TABLE)
                    ->where('production_group', $pg)
                    ->update([
                        'remarks' => $order->remarks,
                        'updated_by' => 'web_portal',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::connection('qms')->table(self::TABLE)->insert([
                    'production_group' => $pg,
                    'remarks' => $order->remarks,
                    'updated_by' => 'web_portal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Subcon remarks staged to QMS', [
                'order' => $order->order_number,
                'production_group' => $pg,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subcon remarks publish failed', [
                'order' => $order->order_number ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
