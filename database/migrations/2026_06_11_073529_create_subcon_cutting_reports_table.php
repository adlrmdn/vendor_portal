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
        Schema::create('subcon_cutting_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('prod_id');
            $table->string('size');
            $table->integer('cutting_qty')->default(0);
            $table->decimal('gramasi', 8, 2)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->unique(['order_id', 'prod_id']);
            $table->index(['order_id', 'prod_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcon_cutting_reports');
    }
};
