<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps an "extra" production group (a second D365 production/cutting batch
 * pinned onto an existing SubconOrder via SubconOrderExtraProductionGroup —
 * same PO, same vendor) to the order's "primary" production group. The QC
 * Console reads this to resolve BOTH groups to the same packaging_projects
 * row (one inspection, one approval chain, one invoice) instead of creating
 * two independent projects for what is really one PO.
 *
 * Guarded + additive; the portal owns this small shared table (it is NOT one
 * of the console's own tables) — mirrors the packaging_project_remarks /
 * fabric-lines publish model (see qc-console-integration.md on the console
 * side).
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_group_links';

    public function up(): void
    {
        if (Schema::connection('qms')->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection('qms')->create(self::TABLE, function (Blueprint $table) {
            $table->string('production_group')->primary(); // the extra/child group
            $table->string('primary_production_group');     // the group whose project this adopts into
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('qms')->dropIfExists(self::TABLE);
    }
};
