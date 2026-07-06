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
            $table->string('production_group', 100)->nullable()->after('jobs_started');
            $table->index('production_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropIndex(['production_group']);
            $table->dropColumn('production_group');
        });
    }
};
