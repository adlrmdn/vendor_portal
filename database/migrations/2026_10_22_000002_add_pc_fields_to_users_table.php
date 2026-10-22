<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC-specific additions to the shared users table: a TOTP secret for the
 * Approver/Admin step-up code (PC spec section 12), and a forced
 * password-change flag for PC staff accounts created with a default temp
 * password (see PcAdminController::storeUser / resetPassword).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('totp_secret')->nullable();
            $table->timestamp('totp_confirmed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at', 'must_change_password']);
        });
    }
};
