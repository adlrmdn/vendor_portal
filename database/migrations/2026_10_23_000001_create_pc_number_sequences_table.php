<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Race-safe document-number counter (PcContractNumberService::issue()).
 * Locked via lockForUpdate() inside a transaction — see that service for
 * the exact shape, copied from SubconVendorController::submitCuttingReport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_number_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_type', 30);
            $table->string('year_yy', 2);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['doc_type', 'year_yy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_number_sequences');
    }
};
