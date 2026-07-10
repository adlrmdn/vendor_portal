<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for subcon cutting/gramasi approval decisions. Every approve and
 * reject at either gate — from the in-app admin panel OR the signed email link —
 * appends one immutable row here. The order only ever keeps the LATEST decision
 * (cutting_approved_by/at, gramasi_approved_by/at); this table keeps the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcon_approval_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable()->index();
            $table->string('order_number')->nullable();   // snapshot — survives order deletion
            $table->string('vendor_name')->nullable();     // snapshot for list display without a join
            $table->string('gate', 20);                    // cutting | gramasi
            $table->string('decision', 20);                // approved | declined
            $table->string('actor')->nullable();           // who decided (name/email, or "Email approval")
            $table->string('source', 20)->default('in_app'); // in_app | email
            $table->text('note')->nullable();              // result message snapshot
            $table->timestamps();

            $table->index(['gate', 'decision']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcon_approval_logs');
    }
};
