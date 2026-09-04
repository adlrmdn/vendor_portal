<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `source` (which system queued the job, e.g. 'subcon_vendor_portal') and
 * `checked` (manual/automated review flag, default false) to the shared
 * `rpa_queues` table on the remote `rpa` connection. Guarded + additive only —
 * this table is bootstrapped by the QC console (forge db.rs) and polled by
 * external bots outside this repo, so existing columns/rows are untouched.
 */
return new class extends Migration
{
    protected $connection = 'rpa';

    private const TABLE = 'rpa_queues';

    public function up(): void
    {
        if (! Schema::connection('rpa')->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection('rpa')->table(self::TABLE, function (Blueprint $table) {
            if (! Schema::connection('rpa')->hasColumn(self::TABLE, 'source')) {
                $table->string('source')->nullable();
            }
            if (! Schema::connection('rpa')->hasColumn(self::TABLE, 'checked')) {
                $table->boolean('checked')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('rpa')->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection('rpa')->table(self::TABLE, function (Blueprint $table) {
            if (Schema::connection('rpa')->hasColumn(self::TABLE, 'checked')) {
                $table->dropColumn('checked');
            }
            if (Schema::connection('rpa')->hasColumn(self::TABLE, 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
