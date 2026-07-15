<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single D365 PO can carry the same fabric ItemNumber on multiple lines
     * that differ only by batch/colour (e.g. "MOC Eagle Blue" vs
     * "MOC Eagle Blue_Dark"). The old (po_id, item_number) unique constraint
     * collapsed those into one item and silently dropped the rest, so a line's
     * identity must include the batch.
     */
    public function up(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->dropUnique(['po_id', 'item_number']);
            $table->unique(['po_id', 'item_number', 'batch']);
        });
    }

    public function down(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->dropUnique(['po_id', 'item_number', 'batch']);
            $table->unique(['po_id', 'item_number']);
        });
    }
};
