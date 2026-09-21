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
        Schema::create('subcon_add_process_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('prod_id');
            // Raw D365 Operation casing (e.g. "ADD-Was") — same value used to
            // resolve the JobTransactionHeaders row and PATCH/trigger against D365.
            $table->string('operation');
            $table->string('size');
            $table->integer('qty')->default(0);
            $table->integer('reject_qty')->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->unique(['order_id', 'prod_id', 'operation']);
            $table->index(['order_id', 'operation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcon_add_process_reports');
    }
};
