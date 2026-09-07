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
        Schema::table('subcon_cutting_reports', function (Blueprint $table) {
            $table->dropColumn('cut_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcon_cutting_reports', function (Blueprint $table) {
            $table->integer('cut_plan')->nullable()->after('prod_id');
        });
    }
};
