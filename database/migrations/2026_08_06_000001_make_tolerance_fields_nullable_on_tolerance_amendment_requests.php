<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partial-shipment requests (type = 'partial_shipment') never had tolerance
 * values or an approver badge to record — VendorController@requestPartialShipment
 * has never set old/new_underdelivery, old/new_overdelivery, or approver_badge.
 * Those columns were left NOT NULL by the create migration, so every partial
 * shipment request has been throwing a DB-level not-null violation (500) on
 * submit — there has been no working partial-delivery approval workflow.
 * approver_badge is additionally superseded by the fabric_approver_email
 * setting, so it's nullable for tolerance requests too now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tolerance_amendment_requests', function (Blueprint $table) {
            $table->decimal('old_underdelivery', 8, 2)->nullable()->change();
            $table->decimal('old_overdelivery', 8, 2)->nullable()->change();
            $table->decimal('new_underdelivery', 8, 2)->nullable()->change();
            $table->decimal('new_overdelivery', 8, 2)->nullable()->change();
            $table->string('approver_badge')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tolerance_amendment_requests', function (Blueprint $table) {
            $table->decimal('old_underdelivery', 8, 2)->nullable(false)->change();
            $table->decimal('old_overdelivery', 8, 2)->nullable(false)->change();
            $table->decimal('new_underdelivery', 8, 2)->nullable(false)->change();
            $table->decimal('new_overdelivery', 8, 2)->nullable(false)->change();
            $table->string('approver_badge')->nullable(false)->change();
        });
    }
};
