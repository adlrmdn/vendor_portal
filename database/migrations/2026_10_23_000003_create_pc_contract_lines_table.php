<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No stored line_total/unit_price_ex_vat — PcContractLine computes both via
 * accessors (pure function of this row + the parent contract's
 * vat_treatment/vat_rate, both immutable once the contract leaves draft).
 * *_snapshot columns are copied at line-save time so a line stays readable
 * even if the referenced master row later changes or (for plm_ref, a
 * sync-owned table) disappears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->unsignedSmallInteger('line_no');

            $table->uuid('plm_ref_id')->nullable()->index(); // soft FK — pc_plm_refs is sync-owned
            $table->string('plm_id_snapshot')->nullable();
            $table->string('plm_article_name_snapshot')->nullable();

            $table->uuid('fabric_item_id')->index();
            $table->string('item_number_snapshot');
            $table->string('item_name_snapshot')->nullable();
            $table->string('uom_snapshot', 10)->nullable();

            $table->uuid('hs_code_id')->index();
            $table->string('hs_code_snapshot', 10)->nullable();

            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 14, 4);
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['contract_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_lines');
    }
};
