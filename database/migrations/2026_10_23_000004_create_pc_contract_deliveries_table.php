<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot attaches here (per shipment tranche), not on pc_contract_lines — a
 * dye/shipment lot is assigned per shipment, not per ordered item.
 * contract_id is denormalized alongside contract_line_id for fast
 * "all shipments for this contract" queries without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_contract_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contract_id')->index();
            $table->uuid('contract_line_id')->index();
            $table->uuid('lot_id')->nullable();
            $table->decimal('delivery_qty', 14, 3);
            $table->date('eta_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_contract_deliveries');
    }
};
