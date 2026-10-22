<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: the 9 standard numbered contract clauses, versioned by
 * effective date so an old contract can be reprinted with the clauses
 * that applied on its own contract_date (PC spec 6.1/8). #7/8/9 print in
 * red (emphasis=true), matching the current paper template. Frozen onto
 * contract_note at approve time — this table is never read for an already
 * -approved contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('clause_number'); // 1-9
            $table->text('body');
            $table->boolean('emphasis')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['clause_number', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_templates');
    }
};
