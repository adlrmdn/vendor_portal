<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: Currency. Fixed (RMB, IDR, USD) with the D365 code each maps
 * to (D365 uses CNY for RMB) — PC spec 5.3/8. A contract's currency is
 * always inherited from pc_suppliers.currency_code, never typed (PC-H03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique(); // RMB | IDR | USD
            $table->string('d365_code', 3)->nullable(); // e.g. RMB -> CNY
            $table->string('label')->nullable(); // e.g. UNITED STATES DOLLAR
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
