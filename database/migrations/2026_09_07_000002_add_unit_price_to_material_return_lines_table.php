<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `unit_price` on material_return_lines: the accessory counterpart of
 * subcon_fabric_reconciliations.fabric_price — admin-editable, prefilled
 * from a VSM-derived weighted-average reference price
 * (SubconProductionService::accessoryLinesForPo()'s unit_price), entered at
 * Final Approval / Report Validation. Nullable, no default, so "never
 * entered" is distinguishable from "entered as 0". Purely additive — this
 * table is shared with value_stream_ops (Material Flow), so the change must
 * never break that repo.
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

        if (Schema::connection('wms')->hasColumn(self::TABLE, 'unit_price')) {
            return;
        }

        Schema::connection('wms')->table(self::TABLE, function (Blueprint $table) {
            $table->decimal('unit_price', 14, 2)->nullable()->after('qty_sent');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('wms')->hasTable(self::TABLE)) {
            return;
        }

        if (Schema::connection('wms')->hasColumn(self::TABLE, 'unit_price')) {
            Schema::connection('wms')->table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }
    }
};
