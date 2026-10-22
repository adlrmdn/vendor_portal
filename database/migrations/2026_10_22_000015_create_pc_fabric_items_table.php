<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC fabric item master, synced read-only from D365 ReleasedProductsV2 +
 * ProductTranslations (the proven combo already used by
 * D365JobTransactionService::fetchItemMaster) by PcSyncFabricItems —
 * additive upsert only, never deleted. item_name must come from
 * ProductTranslations.ProductName (LanguageId=en-US), never
 * ReleasedProductsV2.SearchName/ProductSearchName. See PC spec lampiran A
 * "fabric_item".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_fabric_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('item_number')->unique();
            $table->string('item_name')->nullable();
            $table->text('composition')->nullable();
            $table->string('construction')->nullable();
            $table->string('weave')->nullable();
            $table->string('cut_width')->nullable();
            $table->string('full_width')->nullable();
            $table->decimal('weight_gsm', 8, 2)->nullable();
            $table->string('process')->nullable();
            $table->string('finish')->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_fabric_items');
    }
};
