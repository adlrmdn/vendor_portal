<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `ho_remarks` column to the console-owned QMS
 * `packaging_project_sessions` table — MD Production's own free-text remarks,
 * entered on the Review & Approve / Validate & Send form, distinct from the
 * vendor's remarks (`subcon_orders.remarks`) and the QC inspector's remarks
 * (this table's existing `remarks` column). Guarded + additive only — the
 * portal never creates the console's tables.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_sessions';

    public function up(): void
    {
        if (! Schema::connection('qms')->hasTable(self::TABLE)
            || Schema::connection('qms')->hasColumn(self::TABLE, 'ho_remarks')) {
            return;
        }

        Schema::connection('qms')->table(self::TABLE, function (Blueprint $table) {
            $table->text('ho_remarks')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::connection('qms')->hasTable(self::TABLE)
            && Schema::connection('qms')->hasColumn(self::TABLE, 'ho_remarks')) {
            Schema::connection('qms')->table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('ho_remarks');
            });
        }
    }
};
