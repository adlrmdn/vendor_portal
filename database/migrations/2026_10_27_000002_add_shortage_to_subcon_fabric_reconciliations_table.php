<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selisih/Shortage: a second waste figure alongside Retur Kain, entered at
 * the approval forms (cutting/HO) via SubconConsumptionService::persist().
 * Net fabric for the consumption calc is now:
 *   Net = Fabric Sent − (Retur Kain + Shortage)
 * feeding cutt_plan/actual_consumption/overconsumption/deduction the same way
 * Retur Kain alone used to. See SubconConsumptionService's class docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'shortage')) {
                $table->decimal('shortage', 8, 2)->default(0)->after('retur_kain');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('subcon_fabric_reconciliations', 'shortage')) {
                $table->dropColumn('shortage');
            }
        });
    }
};
