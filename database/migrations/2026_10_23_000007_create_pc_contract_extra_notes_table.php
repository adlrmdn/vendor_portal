<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only comment thread (distinct from the fixed numbered clauses in
 * pc_contract_notes, and from the formal accept/query/reject reasons on
 * pc_contracts/pc_contract_vendor_responses). is_internal=true is
 * staff-only and never shown on the vendor's read-only view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_extra_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->uuid('author_id');
            $table->string('author_name_snapshot');
            $table->string('author_role_snapshot', 30)->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_extra_notes');
    }
};
