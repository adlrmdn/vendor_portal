<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC Phase 2 — the contract header. Status machine (see PC Phase 2 plan):
 * draft -> pending_vendor_review -> pending_final_approval -> confirmed,
 * with vendor query / approver reject both returning to draft, plus
 * cancelled (any non-terminal status, staff-only) and superseded (set on
 * the OLD revision only once its replacement reaches confirmed).
 *
 * contract_number/contract_date/header_snapshot are null until the first
 * confirm — PcContractFreezeService assigns them once, inside the same
 * transaction as approval, and never touches them again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_number')->nullable()->unique();
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->uuid('previous_revision_id')->nullable()->index();
            $table->string('status', 30)->default('draft')->index();

            $table->uuid('vendor_id')->index();
            $table->string('fabric_type', 10)->nullable(); // WOVEN | KNIT
            $table->uuid('currency_id')->nullable();
            $table->uuid('payment_term_id')->nullable();
            $table->uuid('delivery_term_id')->nullable();
            $table->uuid('transport_mode_id')->nullable();
            $table->uuid('vat_treatment_id')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->uuid('season_id')->nullable();
            $table->uuid('order_type_id')->nullable();

            $table->date('contract_date')->nullable();

            $table->uuid('created_by');
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('submitted_by')->nullable();

            $table->string('vendor_decision', 10)->nullable(); // accepted | queried
            $table->timestamp('vendor_decision_at')->nullable();
            $table->uuid('vendor_decision_by')->nullable();
            $table->text('vendor_decision_reason')->nullable();

            $table->string('approval_decision', 10)->nullable(); // approved | rejected
            $table->timestamp('approval_decision_at')->nullable();
            $table->uuid('approval_decision_by')->nullable();
            $table->text('approval_decision_reason')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->uuid('confirmed_by')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->json('header_snapshot')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->text('internal_remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contracts');
    }
};
