<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// App-owned table, physically hosted on the `vsm` connection — see the
// styles-table migration alongside this one for why.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('vsm')->create('subcon_daily_production_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->string('stage')->index(); // cutting|load_sewing|sewing|load_finishing|finishing_packing
            $table->string('prod_id');
            $table->string('size')->nullable();
            $table->date('entry_date');
            $table->integer('quantity')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->unique(['order_id', 'stage', 'prod_id', 'entry_date'], 'subcon_dp_entries_unique_day');
        });
    }

    public function down(): void
    {
        Schema::connection('vsm')->dropIfExists('subcon_daily_production_entries');
    }
};
