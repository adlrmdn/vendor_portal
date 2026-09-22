<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcon_official_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('production_group');
            // Raw D365 Operation value (e.g. "ADD-Was") — same casing used
            // throughout Add Process (SubconAddProcessReport, D365 sync).
            $table->string('operation');
            $table->string('status')->default('pending_ho');
            $table->unsignedInteger('revision_count')->default(0);

            // Snapshot of the matched AddPro PO at send time.
            $table->string('matched_po_number')->nullable();
            $table->string('matched_po_vendor_code')->nullable();
            $table->string('matched_po_vendor_name')->nullable();

            // Yield snapshot at send time.
            $table->unsignedInteger('order_qty')->default(0);
            $table->unsignedInteger('cut_qty')->default(0);
            $table->unsignedInteger('good_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);

            // Deduction inputs/outputs.
            $table->decimal('sales_price', 15, 2)->nullable();
            $table->string('sales_price_source')->nullable(); // 'd365' | 'manual'
            $table->unsignedInteger('allowed_limit')->default(0);
            $table->unsignedInteger('exceeding_reject_qty')->default(0);
            $table->decimal('penalty_price', 15, 2)->default(0);
            $table->decimal('reject_penalty', 15, 2)->default(0);
            $table->decimal('additional_deduction', 15, 2)->default(0);
            $table->text('remarks')->nullable();

            // Signatures.
            $table->string('ho_approval_signature')->nullable();
            $table->timestamp('ho_approved_at')->nullable();
            $table->string('director_approval_signature')->nullable();
            $table->timestamp('director_approved_at')->nullable();
            $table->text('director_decline_reason')->nullable();

            $table->timestamps();

            $table->unique(['production_group', 'operation']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcon_official_reports');
    }
};
