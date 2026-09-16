<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cutting output needs a third dimension beyond (date, size): which
// marker/lay ("Ratio A", "Ratio B", ...) produced it — two different
// ratios can both contribute to the same size on the same date, which a
// flat (order, stage, prod_id, entry_date) key can't represent. Only the
// cutting stage uses this; every other stage's rows keep ratio_label = ''
// (never null, so the unique index still dedupes them correctly — a
// Postgres unique index treats each NULL as distinct from every other
// NULL, which would silently break the existing upsert-by-day behavior).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vsm')->table('subcon_daily_production_entries', function (Blueprint $table) {
            $table->string('ratio_label')->default('')->after('stage');
        });

        Schema::connection('vsm')->table('subcon_daily_production_entries', function (Blueprint $table) {
            $table->dropUnique('subcon_dp_entries_unique_day');
            $table->unique(
                ['order_id', 'stage', 'ratio_label', 'prod_id', 'entry_date'],
                'subcon_dp_entries_unique_day'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('vsm')->table('subcon_daily_production_entries', function (Blueprint $table) {
            $table->dropUnique('subcon_dp_entries_unique_day');
            $table->unique(['order_id', 'stage', 'prod_id', 'entry_date'], 'subcon_dp_entries_unique_day');
        });

        Schema::connection('vsm')->table('subcon_daily_production_entries', function (Blueprint $table) {
            $table->dropColumn('ratio_label');
        });
    }
};
