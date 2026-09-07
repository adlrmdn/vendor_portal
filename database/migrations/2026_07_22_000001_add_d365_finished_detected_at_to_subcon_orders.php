<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks when d365:sync-subcon-orders first observed a PO as "finished" on the
 * D365/VSM side, so deletion of the local order can be aged rather than
 * immediate — a PO flipping to a terminal D365 status doesn't mean the subcon
 * CMT paperwork (cutting/gramasi/labels) is actually done yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->timestamp('d365_finished_detected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('d365_finished_detected_at');
        });
    }
};
