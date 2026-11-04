<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pc_contract_lines', function (Blueprint $table) {
            $table->uuid('lot_id')->nullable()->index()->after('hs_code_snapshot');
            $table->string('lot_snapshot', 50)->nullable()->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('pc_contract_lines', function (Blueprint $table) {
            $table->dropColumn(['lot_id', 'lot_snapshot']);
        });
    }
};
