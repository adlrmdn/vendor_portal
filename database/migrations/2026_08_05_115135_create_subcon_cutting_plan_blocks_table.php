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
        Schema::create('subcon_cutting_plan_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('marker_type'); // fabric label, free text (e.g. "SHELL", "CW 1115 S")
            $table->string('cutt_width')->nullable();
            $table->string('full_width')->nullable();
            $table->string('gsm')->nullable();
            $table->date('plan_date')->nullable();
            $table->decimal('tolerance_pct', 6, 4)->default(0); // the "+TOL" input
            $table->decimal('kg_per_pc', 8, 4)->nullable(); // manual kg/pc factor, sheet leaves this blank/manual
            $table->decimal('fabric_available_override', 12, 2)->nullable(); // manual "GR/fabric sent" override, yds
            $table->text('notes')->nullable(); // free-text annotations (e.g. "XL - 16 PCS U/S")
            $table->jsonb('groups')->nullable(); // [{jml_layer, marker_length, rasio: {size: qty}}, ...]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcon_cutting_plan_blocks');
    }
};
