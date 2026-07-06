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
            $table->string('distribution_id', 100)->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('distribution_id');
        });
    }
};
