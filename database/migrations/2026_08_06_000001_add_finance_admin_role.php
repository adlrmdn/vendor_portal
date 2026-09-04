<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
            DB::statement("UPDATE users SET role = 'admin' WHERE role = 'finance_admin'");
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'fabric_admin', 'fabric_vendor', 'subcon_admin', 'subcon_vendor'))");
        }
    }
};
