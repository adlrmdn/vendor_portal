<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set by d365:archive-invoiced-orders when a PO is old and D365 already
     * reports it Invoiced — hides it from the default Admin/Vendor PO lists
     * and dashboard widgets without deleting anything. Detail pages and the
     * admin vendor-detail PO history are unaffected (not globally scoped).
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
