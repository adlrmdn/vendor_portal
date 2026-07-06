<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'pgsql') {
            // 1. Expand role column to accept new values (PostgreSQL enum alter)
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE varchar(20)');
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'fabric_vendor'");
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

            // 2. Migrate existing data before adding new constraint
            DB::statement("UPDATE users SET role = 'fabric_vendor' WHERE role = 'vendor'");
            DB::statement("UPDATE users SET role = 'fabric_admin' WHERE role = 'admin'");

            // 3. Add new constraint with full role set
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'fabric_admin', 'fabric_vendor', 'subcon_admin', 'subcon_vendor'))");
        }

        // 4. Add type column to vendors
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('type', ['fabric', 'subcon'])->default('fabric')->after('group');
        });

        $defaultUuid = DB::getDriverName() === 'pgsql' ? DB::raw('uuid_generate_v4()') : null;

        // 5. Subcon Orders table
        Schema::create('subcon_orders', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->string('order_number', 100)->unique();
            $table->uuid('vendor_id');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('order_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->index(['status', 'vendor_id']);
            $table->index('order_number');
        });

        // 6. Subcon Order Items table
        Schema::create('subcon_order_items', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->uuid('order_id');
            $table->string('item_number', 100);
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('PCS');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcon_orders')->onDelete('cascade');
            $table->unique(['order_id', 'item_number']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('subcon_order_items');
        Schema::dropIfExists('subcon_orders');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Revert role data
            DB::statement("UPDATE users SET role = 'vendor' WHERE role = 'fabric_vendor'");
            DB::statement("UPDATE users SET role = 'admin' WHERE role IN ('fabric_admin', 'subcon_admin', 'admin')");

            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE varchar(20)');
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'vendor'");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('vendor', 'admin'))");
        }
    }
};
