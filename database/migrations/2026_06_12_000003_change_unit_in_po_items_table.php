<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check constraint in PostgreSQL if it exists, or just change the type
        if (DB::getDriverName() === 'pgsql') {
            // Drop check constraint that pgsql automatically generates for enum
            DB::statement('ALTER TABLE po_items DROP CONSTRAINT IF EXISTS po_items_unit_check');
            DB::statement('ALTER TABLE po_items ALTER COLUMN unit TYPE VARCHAR(50)');
            DB::statement("ALTER TABLE po_items ALTER COLUMN unit SET DEFAULT 'YD'");
        } else {
            Schema::table('po_items', function (Blueprint $table) {
                $table->string('unit', 50)->default('YD')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->enum('unit', ['YD', 'M', 'KG'])->default('YD')->change();
        });
    }
};
