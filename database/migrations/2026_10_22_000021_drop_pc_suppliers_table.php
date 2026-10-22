<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pc_suppliers duplicated the existing vendors table (fabric vendor
 * roster already exists and is already synced from D365 — see
 * SyncD365Orders::autoRegisterVendors). PC-specific supplier fields move
 * onto vendors itself instead (see
 * 2026_10_22_000022_add_pc_fields_to_vendors_table.php) rather than
 * maintaining a second, parallel supplier table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pc_suppliers');
    }

    public function down(): void
    {
        // Not recreated — see 2026_10_22_000014_create_pc_suppliers_table.php
        // if this ever needs reverting; intentionally left empty since this
        // table's data (none — never populated) is not worth restoring blind.
    }
};
