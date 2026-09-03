<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item quantity lines for a material return — fabric (native unit, e.g.
 * YD/M/KG) or accessory/trim (PCS). One row per (order_id, item_type, label),
 * upserted (see MaterialReturnService::saveLines()) — this is a snapshot of
 * declared vs. actual quantity, not an append-only log like attachments.
 * `qty_declared` is entered by the vendor/admin; `qty_actual` is filled in by
 * value_stream_ops's Material Flow tab at check time (the "corrective
 * measure" — inventory's real count can differ from what was declared).
 * Same `wms` database, ownership, and return-to-us-not-to-supplier scope as
 * the sibling tables — see 2026_09_03_000001's docblock.
 */
return new class extends Migration
{
    protected $connection = 'wms';

    private const TABLE = 'material_return_lines';

    public function up(): void
    {
        if (Schema::connection('wms')->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection('wms')->create(self::TABLE, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id')->index();
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->uuid('task_id')->nullable()->index();
            $table->string('item_type'); // fabric|accessory
            $table->string('label'); // persistence key: description (+ unit) — mirrors subcon_fabric_reconciliations
            $table->string('item_number')->nullable();
            $table->string('unit')->nullable(); // PCS for accessories; fabric's native unit otherwise
            $table->decimal('qty_declared', 14, 2)->default(0);
            $table->decimal('qty_actual', 14, 2)->nullable();
            $table->string('uploaded_by_role'); // vendor|admin
            $table->string('uploaded_by_name')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'item_type', 'label']);
        });
    }

    public function down(): void
    {
        Schema::connection('wms')->dropIfExists(self::TABLE);
    }
};
