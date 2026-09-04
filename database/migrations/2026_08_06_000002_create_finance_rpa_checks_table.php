<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a finance admin self-assign ("check") an rpa_queues row (invoice or
 * deduction) to show who's handling which PO. rpa_queues lives on the remote
 * `rpa` connection, shared with other systems/bots, so this stays local
 * rather than writing back to that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_rpa_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('rpa_queue_id')->unique();
            $table->string('rpa_type', 20);
            $table->uuid('checked_by');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->foreign('checked_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_rpa_checks');
    }
};
