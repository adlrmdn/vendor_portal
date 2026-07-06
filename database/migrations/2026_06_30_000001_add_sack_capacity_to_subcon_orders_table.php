<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            // Sack (karung) capacity — pieces per sack for the Pemalang
            // distribution warehouses (WH Replenish / WH Online), which ship in
            // sacks rather than blisters. Their packing-instruction Blister count
            // is derived from this (ceil qty / sack_capacity), Coli following 1:1.
            $table->unsignedInteger('sack_capacity')->nullable()->default(50)->after('blister_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('sack_capacity');
        });
    }
};
