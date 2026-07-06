<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overconsumption = (actual_consumption − consumption_plan) / consumption_plan,
 * derived and snapshotted at cutting-report approval alongside the other
 * consumption figures. Stored as a ratio (0.0271 = 2.71%). Nullable — set only
 * once fabric_sent + consumption_plan are entered.
 *
 * NOTE (schema correction): actual_consumption is now
 *   (fabric_sent − (short_roll + sisa_kain + kepala_kain)) / total cutting qty
 * — retur_kain is stored only, NOT part of the consumption calc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'overconsumption')) {
                $table->decimal('overconsumption', 12, 4)->nullable()->after('actual_consumption');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('subcon_fabric_reconciliations', 'overconsumption')) {
                $table->dropColumn('overconsumption');
            }
        });
    }
};
