<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery-note attachments + the "send to Material Flow" inventory-check
 * task for material the SUBCON VENDOR returns to us (e.g. unused/excess cut
 * fabric) — NOT material we return to our own upstream fabric supplier,
 * which is a separate, unbuilt flow; a future "return to supplier" feature
 * should get its own tables, not reuse these. Both stored in the `wms`
 * database (already in production for the Goods Receiver scanner's supplier
 * goods-receipt flow — good_receipt_headers/lines, schema owned by that
 * app's own Rust backend, not touched here). The portal owns these two
 * tables the same way it already owns packaging_project_remarks inside
 * `qms` — a small shared surface a different app (value_stream_ops's
 * Material Flow tab) polls and updates. Guarded + additive.
 */
return new class extends Migration
{
    protected $connection = 'wms';

    private const TASKS_TABLE = 'material_return_tasks';

    private const ATTACHMENTS_TABLE = 'material_return_attachments';

    public function up(): void
    {
        if (! Schema::connection('wms')->hasTable(self::TASKS_TABLE)) {
            Schema::connection('wms')->create(self::TASKS_TABLE, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_id')->index();
                $table->string('order_number')->index();
                $table->string('vendor_name')->nullable();
                $table->string('production_group')->nullable()->index();
                $table->string('status')->default('pending'); // pending|checked
                $table->string('requested_by')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->string('checked_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->text('checked_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('wms')->hasTable(self::ATTACHMENTS_TABLE)) {
            Schema::connection('wms')->create(self::ATTACHMENTS_TABLE, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('order_id')->index();
                $table->string('order_number')->index();
                $table->string('vendor_name')->nullable();
                $table->uuid('task_id')->nullable()->index();
                $table->string('uploaded_by_role'); // vendor|admin
                $table->string('uploaded_by_name')->nullable();
                $table->string('s3_disk')->default('s3');
                $table->string('s3_path');
                $table->string('original_filename');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('wms')->dropIfExists(self::ATTACHMENTS_TABLE);
        Schema::connection('wms')->dropIfExists(self::TASKS_TABLE);
    }
};
