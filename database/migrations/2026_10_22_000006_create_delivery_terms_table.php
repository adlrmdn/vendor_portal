<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: Terms of Delivery (seeded FOB, CIF Tanjung Priok, CIF Tanjung
 * Emas, FRANCO Pemalang). pc_preparer/pc_admin may add new values directly
 * from the contract header form (PC spec 6.5) — no approval step. Used
 * values are never deleted, only deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 10)->default('seed');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_terms');
    }
};
