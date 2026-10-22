<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * must_change_password supported a PC-specific staff-account-creation flow
 * that no longer exists — PC is absorbed into the existing fabric admin
 * area and reuses existing admin/fabric_admin accounts, so there is no new
 * account-creation step to force a password change on. totp_secret/
 * totp_confirmed_at (added by the same earlier migration) stay — TOTP
 * step-up for Approvers is still part of the plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
        });
    }
};
