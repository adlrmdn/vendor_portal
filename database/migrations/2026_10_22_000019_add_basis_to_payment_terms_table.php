<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missed from the original payment_terms migration: PC spec lampiran A
 * lists `basis` (e.g. INVOICE_RECEIVED, BL_DATE, ETD, DELIVERY, AT_SIGHT,
 * BEFORE_DELIVERY) alongside method/days — "setelah invoice diterima"
 * for the N-codes is noted there as an assumption still needing Aslam's
 * confirmation, but the column itself belongs in the schema regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->string('basis')->nullable()->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->dropColumn('basis');
        });
    }
};
