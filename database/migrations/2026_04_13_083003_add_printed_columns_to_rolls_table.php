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
        // Drop index if it already exists to avoid PG conflict
        DB::statement('DROP INDEX IF EXISTS rolls_is_printed_item_id_index');

        Schema::table('rolls', function (Blueprint $table) {
            $table->boolean('is_printed')->default(false)->after('qr_code_path');
            $table->timestamp('printed_at')->nullable()->after('is_printed');
            $table->index(['is_printed', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rolls', function (Blueprint $table) {
            $table->dropColumn(['is_printed', 'printed_at']);
        });
    }
};
