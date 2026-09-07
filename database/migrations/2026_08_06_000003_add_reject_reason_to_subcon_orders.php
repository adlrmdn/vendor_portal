<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            // Most recent cutting/gramasi rejection — shown to the vendor until
            // they resubmit that same gate, at which point it's cleared. Final
            // (QC console) rejections are a separate flow, not tracked here yet.
            $table->string('reject_gate', 20)->nullable()->after('remarks');
            $table->text('reject_reason')->nullable()->after('reject_gate');
            $table->timestamp('rejected_at')->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn(['reject_gate', 'reject_reason', 'rejected_at']);
        });
    }
};
