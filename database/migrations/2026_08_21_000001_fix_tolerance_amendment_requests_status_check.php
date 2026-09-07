<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tolerance_amendment_requests DROP CONSTRAINT IF EXISTS tolerance_amendment_requests_status_check');
            DB::statement('ALTER TABLE tolerance_amendment_requests ALTER COLUMN status TYPE VARCHAR(50)');
            DB::statement("ALTER TABLE tolerance_amendment_requests ALTER COLUMN status SET DEFAULT 'pending'");
        } else {
            Schema::table('tolerance_amendment_requests', function (Blueprint $table) {
                $table->string('status', 50)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        //
    }
};
