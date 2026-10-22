<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: Lot (e.g. 1-2, 3-4, 5-6). pc_preparer/pc_admin may add new
 * values (e.g. "Festive") directly from the contract-line/delivery-schedule
 * form (PC spec 6.5) — no approval step. Used values are never deleted,
 * only deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 10)->default('seed'); // seed | user
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
