<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only — every vendor Accept/Query click inserts one row here.
 * pc_contracts.vendor_decision* columns always mirror the latest one for
 * cheap reads; this table keeps the full history. vendor_id is a snapshot
 * cross-check against pc_contracts.vendor_id, not a different source of
 * truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_vendor_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->uuid('vendor_id');
            $table->string('decision', 10); // accepted | queried
            $table->text('reason')->nullable();
            $table->uuid('responded_by');
            $table->string('responded_by_name_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_vendor_responses');
    }
};
