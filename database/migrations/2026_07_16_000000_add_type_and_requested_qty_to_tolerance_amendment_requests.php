<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The partial-shipment flow (VendorController@requestPartialShipment,
 * PoItem::pendingPartialShipmentRequest, process-item view) has always queried
 * `type` and written `requested_qty`, but the create migration never added
 * either column — the fabric Process Item page 500s the first time a vendor
 * opens it. Existing rows are tolerance amendments, hence the 'tolerance'
 * default (every reader only branches on `type === 'partial_shipment'`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tolerance_amendment_requests', function (Blueprint $table) {
            $table->string('type')->default('tolerance')->after('po_item_id');
            $table->decimal('requested_qty', 12, 2)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tolerance_amendment_requests', function (Blueprint $table) {
            $table->dropColumn(['type', 'requested_qty']);
        });
    }
};
