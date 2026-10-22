<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC-specific enrichment fields folded directly onto the existing vendors
 * table, rather than a separate pc_suppliers table — see
 * 2026_10_22_000021_drop_pc_suppliers_table.php. Populated by
 * PcEnrichVendors (pc:enrich-vendors), which only ever UPDATEs existing
 * fabric vendor rows (matched by vendor_code) — it never creates a vendor,
 * since that's the existing SyncD365Orders::autoRegisterVendors's job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('d365_payment_code')->nullable();
            $table->uuid('payment_term_id')->nullable()->index(); // soft reference to payment_terms.id
            $table->string('supplier_group_code')->nullable();
            $table->boolean('is_favourite')->default(false);
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'country', 'contact_person', 'currency_code', 'd365_payment_code',
                'payment_term_id', 'supplier_group_code', 'is_favourite', 'synced_at',
            ]);
        });
    }
};
