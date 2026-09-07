<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A D365 PO line's true identity is its LineNumber. (po_id, item_number,
     * batch) is not actually unique — D365 can carry two distinct lines for
     * the same item/batch (e.g. a quantity split across lines), which the old
     * key collapsed into one row and silently dropped the rest. line_number
     * makes the sync's firstOrCreate match D365's real per-line identity.
     */
    public function up(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->integer('line_number')->nullable()->after('batch');
        });

        Schema::table('po_items', function (Blueprint $table) {
            $table->dropUnique(['po_id', 'item_number', 'batch']);
            $table->unique(['po_id', 'item_number', 'batch', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->dropUnique(['po_id', 'item_number', 'batch', 'line_number']);
            $table->unique(['po_id', 'item_number', 'batch']);
        });

        Schema::table('po_items', function (Blueprint $table) {
            $table->dropColumn('line_number');
        });
    }
};
