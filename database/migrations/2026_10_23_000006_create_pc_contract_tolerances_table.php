<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen copy of the WOVEN/KNIT tolerance table matching the contract's
 * fabric_type, written ONCE by PcContractFreezeService at confirm from
 * ToleranceTemplate::active()->forFabricType(...)->effectiveOn(...) —
 * same freeze-once contract as pc_contract_notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_tolerances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->string('fabric_type', 10);
            $table->string('row_label');
            $table->string('tolerance')->nullable();
            $table->boolean('highlight')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('source_tolerance_template_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_tolerances');
    }
};
