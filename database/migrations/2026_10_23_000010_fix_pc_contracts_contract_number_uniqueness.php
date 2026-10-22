<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix: 2026_10_23_000002 put a bare unique() on contract_number, but
 * a revision deliberately REUSES its parent's contract_number (only
 * revision_no increments) — see PcContractController::revise(). The
 * unique constraint must be on the (contract_number, revision_no) pair,
 * not on contract_number alone, or the second revision of any contract
 * fails to save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pc_contracts', function (Blueprint $table) {
            $table->dropUnique('pc_contracts_contract_number_unique');
            $table->unique(['contract_number', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::table('pc_contracts', function (Blueprint $table) {
            $table->dropUnique(['contract_number', 'revision_no']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pc_contracts ADD CONSTRAINT pc_contracts_contract_number_unique UNIQUE (contract_number)');
        }
    }
};
