<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: Season (e.g. FALL'26). Admin-managed only — unlike HS
 * Code/Lot/Transport/Delivery Term, the spec does not make this one
 * pc_preparer-addable (PC spec 5.3 / PC-H04: must be open for new
 * contracts to be selectable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('is_open')->default(true);
            $table->integer('sort_key')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
