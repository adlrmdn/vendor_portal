<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC master: the two fixed shipment-tolerance tables (WOVEN, KNIT),
 * versioned by effective date, always printed on every contract PDF (PC
 * spec 6.1/9). Frozen onto contract_tolerance at approve time.
 *
 * Shape modeled as a flat quantity-band list per fabric_type (row_label
 * free-text, e.g. "More than 3000 mtrs/yds", tolerance_plus free-text,
 * e.g. "+2%") — matches the PC-L04 plan's noted open item: confirm this
 * against the real lampiran C tolerance CSV once supplied, in case it
 * implies an extra axis this shape doesn't anticipate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tolerance_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fabric_type', 10); // WOVEN | KNIT
            $table->string('row_label'); // e.g. "More than 3000 mtrs/yds", "Rib + Flat Knit Collar + Cuff"
            $table->string('tolerance')->nullable(); // e.g. "+2%" — kept as display text, not decimal
            $table->boolean('highlight')->default(false); // yellow-highlighted row (e.g. Rib + Flat Knit Collar + Cuff)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['fabric_type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tolerance_templates');
    }
};
