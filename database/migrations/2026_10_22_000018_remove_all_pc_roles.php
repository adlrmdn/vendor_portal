<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supersedes 2026_10_22_000001_add_pc_roles.php entirely. PC does not get
 * its own roles at all — it's absorbed into the existing fabric admin area
 * and uses the existing admin/fabric_admin roles (user's call, mid-build).
 * The Approver pool is a Setting-based email list on top of those existing
 * roles (PcAdminController::isPcApproverUser(), mirroring
 * SubconAdminController::isDirectorUser()'s identical pattern) rather than
 * a role at all. No user ever held any pc_* role (confirmed before writing
 * this), so this is a pure constraint revert, no data migration needed.
 */
return new class extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'fabric_admin', 'fabric_vendor', 'subcon_admin', 'subcon_vendor', 'finance_admin'))");
        }
    }

    public function down()
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'fabric_admin', 'fabric_vendor', 'subcon_admin', 'subcon_vendor', 'finance_admin', 'pc_admin', 'pc_preparer', 'pc_approver', 'pc_viewer'))");
        }
    }
};
