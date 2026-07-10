<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the console-owned QMS `packaging_project_fabric_lines` table into
 * something the portal can PUBLISH into before a packaging project exists, so
 * the QC Console can PULL the fabric consumption by production_group whenever it
 * starts its project (decoupling cutting-approval readiness from project start).
 *
 * Runs against the `qms` connection only (mirrors the existing QMS migration);
 * in envs where `qms` is unreachable it simply isn't run. All changes are
 * additive/nullable + guarded → safe to re-run and backward compatible with the
 * console's current read-by-project_id path:
 *   - production_group  (nullable, indexed) — the portal's publish/fetch key
 *   - overconsumption / fabric_price / deduction (nullable) — figures the table
 *     couldn't previously store (overconsumption was being silently dropped)
 *   - project_id relaxed to NULLABLE — a row can now be STAGED (project not yet
 *     created); the console adopts it later by setting project_id.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_fabric_lines';

    public function up(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'production_group')) {
                $table->string('production_group')->nullable()->index();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'overconsumption')) {
                $table->double('overconsumption')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'fabric_price')) {
                $table->double('fabric_price')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'deduction')) {
                $table->double('deduction')->nullable();
            }
        });

        // Relax project_id NOT NULL so a row can exist in the "staged, not yet
        // adopted" state. Raw ALTER (not ->change()) to drop ONLY the constraint —
        // leaving the column type/length untouched. Idempotent in PostgreSQL.
        DB::connection($this->connection)->statement(
            'ALTER TABLE '.self::TABLE.' ALTER COLUMN project_id DROP NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            foreach (['production_group', 'overconsumption', 'fabric_price', 'deduction'] as $col) {
                if (Schema::connection($this->connection)->hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Intentionally NOT re-imposing NOT NULL on project_id: staged rows may
        // have NULLs, which would make the constraint fail. Left nullable.
    }
};
