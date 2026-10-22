<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: Payment Term, decoded from the D365 payment-term code carried
 * on the vendor record (e.g. N90 -> "90 DAYS AFTER ETD") — PC spec 5.3/8.
 * Fixed/seed-managed; a contract's term is always resolved automatically
 * from pc_suppliers.d365_payment_code (PC-H06), never typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('d365_code')->nullable()->index(); // e.g. N60, N90, 5N, BL Date
            $table->string('label'); // e.g. "90 DAYS AFTER ETD"
            $table->string('method')->nullable(); // TT | LC | GIRO | CASH | MIXED
            $table->integer('days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
