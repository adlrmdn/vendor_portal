<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for the PC module, modeled exactly on
 * subcon_approval_logs. Every sensitive PC event — login, TOTP setup,
 * master-data additions, staff account creation/reset, and (from Phase
 * 2/3 onward) approve/reject/send/cancel — appends one immutable row.
 * Snapshot columns, not FKs, so history survives deletion of the actor or
 * the entity it refers to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor')->nullable(); // name/email snapshot
            $table->string('actor_role')->nullable();
            $table->string('event_type', 60)->index(); // e.g. login_success, totp_setup, master_data.hs_code.add
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable(); // snapshot, not FK
            $table->string('entity_label')->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_logs');
    }
};
