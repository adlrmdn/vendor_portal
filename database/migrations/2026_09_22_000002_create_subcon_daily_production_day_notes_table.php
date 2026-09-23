<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// App-owned table, physically hosted on the `vsm` connection (per business
// requirement — see SubconDailyProductionService) rather than the local app
// DB. The migration itself is tracked in the local app's `migrations` table;
// only the schema operations target `vsm`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vsm')->create('subcon_daily_production_day_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->uuid('order_id')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->date('entry_date')->index();
            $table->text('notes')->nullable();
            $table->uuid('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'entry_date'], 'subcon_dp_day_notes_vendor_date_idx');
            $table->index(['order_id', 'entry_date'], 'subcon_dp_day_notes_order_date_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('vsm')->dropIfExists('subcon_daily_production_day_notes');
    }
};
