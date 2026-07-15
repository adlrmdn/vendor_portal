<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Director approval stage (stage 3) columns on the QMS
 * `packaging_project_sessions` table.
 *
 *   - `director_approval_signature` — portal-written, same prefix contract as
 *     the HO column: 'Digitally Signed: …' on approval, 'Rejected: …' on
 *     rejection. The console branches on the prefix, exactly like stage 2.
 *   - `inspector_email` — console-written: the QC inspector's device-registered
 *     email (captured by the console's startup identity popup). The portal
 *     reads it to send reject notifications and the completion notification.
 *
 * Runs against the `qms` connection. Additive + nullable + hasColumn-guarded,
 * so re-running is safe and the console's own guarded DDL (which mirrors these
 * columns) can win the race harmlessly.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_sessions';

    public function up(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'director_approval_signature')) {
                $table->string('director_approval_signature')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'inspector_email')) {
                $table->string('inspector_email')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            foreach (['director_approval_signature', 'inspector_email'] as $col) {
                if (Schema::connection($this->connection)->hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
