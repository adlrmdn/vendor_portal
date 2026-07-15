<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PC (Preliminary Contract) reference from the D365 PO header
     * (VendorOrderReference, e.g. "MPG/PC/PDV/26/V/244"). Distinct from
     * `notes` (ReasonComment / season, e.g. "FESTIVE-2027").
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
