<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step MD Production gate: "Review & Approve" (signs + queues the RAF
 * production run) is now separate from "Validate & Send Approval" (notifies
 * the Director). `ho_validation_signature` records the second step, same
 * 'Digitally Signed: …' contract as the other signature columns.
 *
 * Runs against the `qms` connection. Additive + nullable + hasColumn-guarded,
 * same pattern as the director-stage migration.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_sessions';

    public function up(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'ho_validation_signature')) {
                $table->string('ho_validation_signature')->nullable();
            }
        });

        // Backfill in-flight rows: under the single-step flow the Director was
        // emailed at HO approval, so every already-signed row must count as
        // validated — otherwise its already-sent Director link would dead-end
        // on the new directorStageGuard.
        DB::connection($this->connection)->table(self::TABLE)
            ->where('ho_approval_signature', 'like', 'Digitally Signed%')
            ->where(function ($q) {
                $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
            })
            ->update(['ho_validation_signature' => 'Auto-validated (single-step flow) [migrated 2026-07-17]']);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn(self::TABLE, 'ho_validation_signature')) {
                $table->dropColumn('ho_validation_signature');
            }
        });
    }
};
