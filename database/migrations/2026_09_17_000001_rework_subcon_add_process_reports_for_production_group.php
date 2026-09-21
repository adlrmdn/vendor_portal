<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Process (ADD-Was/ADD-Embro/ADD-Sab/ADD-Pleat/...) is not a subcon-only
 * concept — in-house production groups carry the same ADD-* route steps but
 * never get a SubconOrder row (subcon_orders only syncs CMT vendor POs). The
 * original table keyed by order_id, which made it CMT-only. Reworked to key
 * directly by ProductionGroup instead, so it works for both. Safe to drop and
 * recreate: the table has never held real submitted data (only Queue::fake()
 * dispatch tests, cleaned up immediately after).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subcon_add_process_reports');

        Schema::create('subcon_add_process_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('production_group');
            $table->string('prod_id');
            // Raw D365 Operation casing (e.g. "ADD-Was") — same value used to
            // resolve the JobTransactionHeaders row and PATCH/trigger against D365.
            $table->string('operation');
            $table->string('size');
            $table->integer('qty')->default(0);
            $table->integer('reject_qty')->default(0);
            $table->timestamps();

            $table->unique(['production_group', 'prod_id', 'operation']);
            $table->index(['production_group', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcon_add_process_reports');
    }
};
