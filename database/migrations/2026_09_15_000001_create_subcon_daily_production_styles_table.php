<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// App-owned table, physically hosted on the `vsm` connection (per business
// requirement — see SubconDailyProductionService) rather than the local app
// DB. The migration itself is still tracked in the local app's `migrations`
// table; only the schema operations below target `vsm`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vsm')->create('subcon_daily_production_styles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('vsm')->dropIfExists('subcon_daily_production_styles');
    }
};
