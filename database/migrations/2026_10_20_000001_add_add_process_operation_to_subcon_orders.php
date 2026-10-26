<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a subcon_orders row that is actually an Add Process PO (e.g. the
 * D365 JobTransactionHeaders "ADD-Was"/"ADD-Embro"/"ADD-Sab"/"ADD-Pleat"
 * operation) rather than a real CMT cutting work order. d365:sync-subcon-
 * orders no longer creates these going forward (classified by line
 * ItemNumber), but a PO already ingested before that fix — or flagged by
 * hand — stays here as an inert, admin-visible record instead of being
 * deleted: null means a normal CMT order, any other value is the operation
 * code. Vendor-facing queries must exclude it (SubconOrder::scopeForVendor()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->string('add_process_operation', 20)->nullable()->after('production_group');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('add_process_operation');
        });
    }
};
