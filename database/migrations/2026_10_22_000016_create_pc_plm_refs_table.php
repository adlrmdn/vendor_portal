<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC PLM ID reference, synced read-only from the vsm connection's
 * plm_activity table (NOT a new D365 OData call — reuses the same source
 * already proven in SubconProductionService::forPo) by PcSyncPlmRefs —
 * additive upsert only, never deleted. Must be reloadable at least hourly
 * (PC spec 6.2: a PLM ID created this morning must be pickable this
 * morning). article_name prefers production_group_lines.SearchName over
 * plm_activity.ArticleName when available (same freshness caveat
 * SubconProductionService documents). brand/season/lot/supplier_name/
 * planned_qty are best-effort/nullable per the spec ("bila ada di D365").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_plm_refs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plm_id')->unique();
            $table->string('article_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('season')->nullable();
            $table->string('lot')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('planned_qty', 12, 2)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_plm_refs');
    }
};
