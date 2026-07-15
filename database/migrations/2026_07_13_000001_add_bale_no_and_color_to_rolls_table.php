<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rolls', function (Blueprint $table) {
            // The vendor's own roll number from their packing list — distinct from
            // the portal-generated roll_number (PO-ITEM-SEQ).
            $table->string('vendor_roll_no', 100)->nullable()->after('internal_id');
            $table->string('bale_no', 100)->nullable()->after('vendor_roll_no');
            $table->string('color', 100)->nullable()->after('bale_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rolls', function (Blueprint $table) {
            $table->dropColumn(['vendor_roll_no', 'bale_no', 'color']);
        });
    }
};
