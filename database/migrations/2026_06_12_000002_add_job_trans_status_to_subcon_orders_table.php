<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->string('job_trans_status')->default('not_saved')->after('status');
            if (Schema::hasColumn('subcon_orders', 'jobs_started')) {
                $table->dropColumn('jobs_started');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('job_trans_status');
            $table->boolean('jobs_started')->default(false)->after('status');
        });
    }
};
