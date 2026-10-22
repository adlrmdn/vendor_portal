<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen copy of the standard numbered clauses, written ONCE by
 * PcContractFreezeService at the confirm transition from
 * NoteTemplate::active()->effectiveOn($contract->contract_date) — never
 * touched again, so a later NoteTemplate edit never retroactively changes
 * an already-confirmed contract's printed clauses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->unsignedSmallInteger('clause_number');
            $table->text('body');
            $table->boolean('emphasis')->default(false);
            $table->uuid('source_note_template_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_notes');
    }
};
