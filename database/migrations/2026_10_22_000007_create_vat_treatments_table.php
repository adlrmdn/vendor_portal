<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: VAT/PPN treatment. Fixed two-row list (EXCLUDE, INCLUDE) per
 * PC spec 6.5 — not user-addable, no "source"/"created_by" tracking needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_treatments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique(); // EXCLUDE | INCLUDE
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_treatments');
    }
};
