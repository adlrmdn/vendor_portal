<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partial cutting reports: the vendor can flag a cutting-report submission as
 * partial. The approval email carries the flag, and approving a partial report
 * returns the order to the cutting stage (instead of advancing to gramasi) so
 * the vendor can keep submitting the remaining quantities. The flag is set on
 * every submit — a final (non-partial) submit clears it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->boolean('cutting_partial')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn('cutting_partial');
        });
    }
};
