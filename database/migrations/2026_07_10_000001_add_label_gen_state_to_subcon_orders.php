<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the state of the (asynchronous) packing-label generation so the UI can
 * show a real status instead of a fire-and-forget flash: lock the Generate
 * button while a run is in flight, and surface the RPA failure reason if the
 * DTT bot could not create the packing instruction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            // null = idle/never run; 'generating' = job in flight; 'failed' = last run errored.
            $table->string('label_gen_status')->nullable();
            $table->text('label_gen_error')->nullable();
            $table->timestamp('label_gen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn(['label_gen_status', 'label_gen_error', 'label_gen_at']);
        });
    }
};
