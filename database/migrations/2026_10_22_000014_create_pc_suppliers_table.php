<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC supplier master, synced read-only from D365 VendorsV2 (mpg-scoped) by
 * PcSyncSuppliers — additive upsert only, never deleted (see
 * SyncD365FabricVendors for the destructive pattern this deliberately does
 * NOT follow). payment_term_id/portal_vendor_id are soft, indexed
 * references (no FK) so a sync pass is never blocked by a not-yet-synced
 * side table — see PC spec lampiran A "supplier".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vendor_code')->unique();
            $table->string('legal_name');
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('d365_payment_code')->nullable();
            $table->uuid('payment_term_id')->nullable()->index(); // soft reference to payment_terms.id
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fabric_supplier')->default(true);
            $table->boolean('is_favourite')->default(false);
            $table->string('supplier_group_code')->nullable();
            $table->uuid('portal_vendor_id')->nullable()->index(); // soft reference to vendors.id
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_suppliers');
    }
};
