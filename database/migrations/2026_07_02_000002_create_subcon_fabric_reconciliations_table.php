<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-fabric reconciliation for a subcon order. The vendor measures leftover
 * fabric (short roll / sisa kain / kepala kain / retur) per fabric type on the
 * cutting report; each fabric is identified by its VSM label (description +
 * unit), which is also the key the HO approval form prefills against.
 *
 * This supersedes the single per-order columns on subcon_orders (which are kept
 * but no longer feed the HO fabric lines).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subcon_fabric_reconciliations')) {
            return;
        }

        Schema::create('subcon_fabric_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('label', 500); // fabric description + unit (match key)
            $table->decimal('short_roll', 8, 2)->default(0);
            $table->decimal('sisa_kain', 8, 2)->default(0);
            $table->decimal('kepala_kain', 8, 2)->default(0);
            $table->decimal('retur_kain', 8, 2)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcon_fabric_reconciliations');
    }
};
