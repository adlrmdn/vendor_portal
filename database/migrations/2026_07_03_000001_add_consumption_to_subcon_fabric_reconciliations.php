<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumption figures now filled by the subcon admin at the cutting-report
 * approval stage (moved off the HO form) and stored per fabric alongside the
 * vendor's reconciliation. `fabric_sent` and `consumption_plan` are entered;
 * `cutt_plan` = floor(fabric_sent / consumption_plan) and `actual_consumption`
 * = fabric_sent / total cutting qty are derived and snapshotted on save. All
 * nullable — a fabric row may exist (reconciliation) before consumption is set.
 */
return new class extends Migration
{
    private const COLUMNS = ['fabric_sent', 'consumption_plan', 'cutt_plan', 'actual_consumption'];

    public function up(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'fabric_sent')) {
                $table->decimal('fabric_sent', 12, 2)->nullable()->after('retur_kain');
            }
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'consumption_plan')) {
                $table->decimal('consumption_plan', 12, 4)->nullable()->after('fabric_sent');
            }
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'cutt_plan')) {
                $table->integer('cutt_plan')->nullable()->after('consumption_plan');
            }
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'actual_consumption')) {
                $table->decimal('actual_consumption', 12, 4)->nullable()->after('cutt_plan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('subcon_fabric_reconciliations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
