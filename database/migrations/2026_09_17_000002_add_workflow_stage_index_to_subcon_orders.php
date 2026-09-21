<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            // Primary filter column on the admin orders list and Approvals tab
            // (SubconAdminController::orders()/approvals()) — was unindexed, forcing
            // a sequential scan as the table grows. created_at is the default sort.
            $table->index('workflow_stage');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropIndex(['workflow_stage']);
            $table->dropIndex(['created_at']);
        });
    }
};
