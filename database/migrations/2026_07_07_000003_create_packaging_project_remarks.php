<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staged, production_group-keyed store for vendor remarks the QC Console PULLS —
 * mirrors the fabric-lines publish/pull model. Unlike writing onto existing
 * sessions, this is upserted the moment a remark is saved (even before the
 * console has created its project/session), so nothing is missed. The console
 * reads a work order's remark by production_group.
 *
 * Guarded + additive; the portal owns this small shared table (it is NOT one of
 * the console's own tables).
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_remarks';

    public function up(): void
    {
        if (Schema::connection('qms')->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection('qms')->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('production_group')->unique(); // the pull key
            $table->string('project_id')->nullable();      // optional: console may adopt
            $table->text('remarks')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('qms')->dropIfExists(self::TABLE);
    }
};
