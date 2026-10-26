<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * totp_secret is cast as 'encrypted' in App\Models\User.
 * Laravel's encrypted cast serializes an envelope with iv, value, mac, and tag
 * as base64 JSON which exceeds 255 characters (typically 256-320 chars).
 * Alter users.totp_secret from VARCHAR(255) to TEXT to avoid string truncation errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN totp_secret TYPE text');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->text('totp_secret')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN totp_secret TYPE varchar(255)');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('totp_secret', 255)->nullable()->change();
            });
        }
    }
};
