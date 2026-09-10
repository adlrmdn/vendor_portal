<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `qty_sent` on material_return_lines: material MPG sent to the vendor for
 * this accessory (the accessory counterpart of
 * subcon_fabric_reconciliations.fabric_sent) — entered by the admin at Final
 * Approval / Report Validation, not tracked before that. Nullable, no
 * default, so "never entered" is distinguishable from "entered as 0". Purely
 * additive — this table is shared with value_stream_ops (Material Flow), so
 * the change must never break that repo.
 */
return new class extends Migration
{
    protected $connection = 'wms';

    private const TABLE = 'material_return_lines';

    public function up(): void
    {
        if (! Schema::connection('wms')->hasTable(self::TABLE)) {
            return;
        }

        if (Schema::connection('wms')->hasColumn(self::TABLE, 'qty_sent')) {
            return;
        }

        Schema::connection('wms')->table(self::TABLE, function (Blueprint $table) {
            $table->decimal('qty_sent', 14, 2)->nullable()->after('qty_declared');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('wms')->hasTable(self::TABLE)) {
            return;
        }

        if (Schema::connection('wms')->hasColumn(self::TABLE, 'qty_sent')) {
            Schema::connection('wms')->table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('qty_sent');
            });
        }
    }
};
