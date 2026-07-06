<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_slips', function (Blueprint $table) {
            $table->string('delivery_note')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('packing_slips', function (Blueprint $table) {
            $table->dropColumn('delivery_note');
        });
    }
};
