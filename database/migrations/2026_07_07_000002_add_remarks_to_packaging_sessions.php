<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `remarks` column to the console-owned QMS
 * `packaging_project_sessions` table so the portal can push a work order's
 * vendor remarks for the QC Console to render. Guarded + additive only — the
 * portal never creates the console's tables.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_sessions';

    public function up(): void
    {
        if (! Schema::connection('qms')->hasTable(self::TABLE)
            || Schema::connection('qms')->hasColumn(self::TABLE, 'remarks')) {
            return;
        }

        Schema::connection('qms')->table(self::TABLE, function (Blueprint $table) {
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::connection('qms')->hasTable(self::TABLE)
            && Schema::connection('qms')->hasColumn(self::TABLE, 'remarks')) {
            Schema::connection('qms')->table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('remarks');
            });
        }
    }
};
