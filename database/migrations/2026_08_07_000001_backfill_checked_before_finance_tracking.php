<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The finance admin Invoices/Debit Notes review queue (RpaQueueReadService)
 * is no longer date-scoped — it lists every unchecked completed job,
 * regardless of age, instead of resetting at midnight. `rpa_queues.checked`
 * predates the finance admin feature (shared with other consumers of this
 * table) and defaults to false, so without this backfill every invoice/
 * deduction job older than when finance started actually reviewing them
 * (Tue 2026-08-04) would suddenly surface as "needs checking". Marks
 * everything before that cutoff as checked=true so the queue starts clean
 * from when tracking actually began.
 */
return new class extends Migration
{
    protected $connection = 'rpa';

    private const TABLE = 'rpa_queues';

    private const TRACKING_STARTED_AT = '2026-08-04 00:00:00';

    public function up(): void
    {
        if (! Schema::connection('rpa')->hasTable(self::TABLE) || ! Schema::connection('rpa')->hasColumn(self::TABLE, 'checked')) {
            return;
        }

        DB::connection('rpa')->table(self::TABLE)
            ->whereIn('rpa_type', ['invoice', 'deduction'])
            ->where('updated_at', '<', self::TRACKING_STARTED_AT)
            ->where('checked', false)
            ->update(['checked' => true]);
    }

    public function down(): void
    {
        // Not reversible — we don't know which of the backfilled rows were
        // genuinely unchecked vs. already true before this ran.
    }
};
