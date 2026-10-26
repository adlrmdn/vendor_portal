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
        // Manually pins an additional D365 production group onto an existing
        // subcon work order — covers the case where PLM spins up a brand-new
        // supplement production group for extra capacity on a style that
        // already has a CMT PO, instead of editing the original group's
        // quantity or adding a second po_lines row for it. D365 has no
        // mechanism this app can drive to express that link itself, so an
        // admin records it here; SubconProductionService resolves it
        // alongside the PO's own PLM-linked groups.
        Schema::create('subcon_order_extra_production_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subcon_order_id');
            $table->string('production_group');
            $table->string('added_by')->nullable();
            $table->timestamps();

            $table->foreign('subcon_order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->unique(['subcon_order_id', 'production_group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcon_order_extra_production_groups');
    }
};
