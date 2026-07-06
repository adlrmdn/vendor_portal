<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the web-portal approval columns to the QMS `packaging_project_sessions`
 * table (the same rows the Tauri QC Console polls).
 *
 * Runs against the `qms` connection, NOT the app's primary DB. All columns are
 * additive + nullable and guarded with hasColumn(), so re-running is safe and
 * the existing QC Console (which only reads `factory_representative`) is
 * unaffected. `factory_representative` itself is owned by the console — we only
 * write to it, we do not create it here.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_sessions';

    public function up(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            // Bearer credential: the console writes a random UUID here when it
            // sends the approval email, and puts the same value in the link.
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approval_token')) {
                $table->uuid('approval_token')->nullable()->index();
            }
            // Intended signer, recorded server-side by the console at send time.
            // Identity is read from THIS column, never from the URL.
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approval_email')) {
                $table->string('approval_email')->nullable();
            }
            // Structured audit trail (portal-owned). The console keeps reading
            // the formatted `factory_representative` string; these give us clean
            // who/when/source data without any console change.
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approved_by')) {
                $table->string('approved_by')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approved_at')) {
                $table->timestampTz('approved_at')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approval_source')) {
                $table->string('approval_source')->nullable()->default('web_portal');
            }
            // 'approved' | 'rejected'. The console detects the decision here.
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'approval_status')) {
                $table->string('approval_status')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            foreach (['approval_token', 'approval_email', 'approved_by', 'approved_at', 'approval_source', 'approval_status'] as $col) {
                if (Schema::connection($this->connection)->hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
