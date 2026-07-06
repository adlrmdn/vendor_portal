<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds fabric-reconciliation fields captured during the cutting-report stage.
 * These are single per-order values (they apply across all sizes, just like
 * blister/sack capacity), stored as decimal meters and defaulting to 0.
 *
 * All columns are additive + hasColumn()-guarded so the migration is idempotent
 * and safe to re-run.
 */
return new class extends Migration
{
    private const COLUMNS = ['short_roll', 'sisa_kain', 'kepala_kain', 'retur_kain'];

    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('subcon_orders', $col)) {
                    $table->decimal($col, 8, 2)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('subcon_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
