<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unlike PackingSlip.pdf_path (dead, never written — see PC Phase 2 plan),
 * PcContractPdfService only creates a row here AFTER Storage::put()
 * succeeds, so a row here never points at a file that doesn't exist.
 * disk defaults to 'local' (no AWS bucket decision made for PC yet) —
 * swapping to S3 later is a one-column change, not a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->string('file_type', 30)->default('contract_pdf');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 60)->default('application/pdf');
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->uuid('generated_by')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_files');
    }
};
