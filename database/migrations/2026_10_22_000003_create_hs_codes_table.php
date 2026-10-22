<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: HS Code. Seeded from the legacy PC archive's 97 known codes;
 * pc_preparer/pc_admin may add new ones directly from the contract-line form
 * (PC spec 6.5) — no approval step. Used values are never deleted, only
 * deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hs_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 10)->default('seed'); // seed | user
            $table->uuid('created_by')->nullable(); // soft reference to users.id — snapshot, no FK
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hs_codes');
    }
};
