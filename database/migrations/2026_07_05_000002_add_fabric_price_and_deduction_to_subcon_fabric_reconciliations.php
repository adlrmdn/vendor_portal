<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric price + overconsumption deduction, per fabric.
 *
 * fabric_price (IDR per fabric unit) is prefilled from the linked fabric PO in
 * VSM (LineAmount / OrderedPurchaseQuantity) but editable at cutting approval —
 * VSM carries no currency, so a foreign-currency PO is corrected by hand.
 *
 * deduction (IDR) is charged only when overconsumption > 3.00%:
 *   deduction = max(0, actual_consumption − 1.03 × consumption_plan)
 *               × total cutting qty × fabric_price
 * Snapshotted at approval alongside the other consumption figures.
 */
return new class extends Migration
{
    private const COLUMNS = ['fabric_price', 'deduction'];

    public function up(): void
    {
        if (! Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::table('subcon_fabric_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'fabric_price')) {
                $table->decimal('fabric_price', 16, 2)->nullable()->after('overconsumption');
            }
            if (! Schema::hasColumn('subcon_fabric_reconciliations', 'deduction')) {
                $table->decimal('deduction', 16, 2)->nullable()->after('fabric_price');
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
