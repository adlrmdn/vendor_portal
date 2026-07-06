<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Enable UUID extension (PostgreSQL specific)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        }

        $defaultUuid = DB::getDriverName() === 'pgsql' ? DB::raw('uuid_generate_v4()') : null;

        // Vendors table
        Schema::create('vendors', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->string('name');
            $table->string('vendor_code', 50)->unique();
            $table->string('group')->nullable();
            $table->jsonb('contact_info')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Users table
        Schema::create('users', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            if (DB::getDriverName() === 'sqlite') {
                $table->string('role', 20)->default('fabric_vendor');
            } else {
                $table->enum('role', ['vendor', 'admin'])->default('vendor');
            }
            $table->uuid('vendor_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->index(['role', 'vendor_id']);
        });

        // Purchase Orders table
        Schema::create('purchase_orders', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->string('po_number', 100)->unique();
            $table->uuid('vendor_id');
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->index(['status', 'vendor_id']);
            $table->index('po_number');
        });

        // PO Items table
        Schema::create('po_items', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->uuid('po_id');
            $table->string('item_number', 100);
            $table->string('description');
            $table->string('batch');
            $table->string('plm_number');
            $table->decimal('quantity', 10, 2);
            $table->decimal('underdelivery', 10, 2)->default(0);
            $table->decimal('overdelivery', 10, 2)->default(0);
            $table->enum('unit', ['YD', 'M', 'KG'])->default('YD');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('fabric_type')->nullable();
            $table->string('color')->nullable();
            $table->jsonb('specifications')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed'])->default('pending');
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->unique(['po_id', 'item_number']);
            $table->index('status');
        });

        // Rolls table
        Schema::create('rolls', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->uuid('item_id');
            $table->string('roll_number', 50);
            $table->integer('sequence');
            $table->decimal('length_yd', 10, 2)->nullable();
            $table->decimal('length_m', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->enum('unit', ['YD', 'M', 'KG'])->default('YD');
            $table->string('grade', 50)->nullable();
            $table->jsonb('defects')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('po_items')->onDelete('cascade');
            $table->unique(['item_id', 'roll_number']);
            $table->index(['is_printed', 'item_id']);
        });

        // Packing Slips table
        Schema::create('packing_slips', function (Blueprint $table) use ($defaultUuid) {
            $table->uuid('id')->primary()->default($defaultUuid);
            $table->string('slip_number', 100)->unique();
            $table->uuid('po_id');
            $table->uuid('vendor_id');
            $table->jsonb('items');
            $table->integer('printed_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->index('slip_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('packing_slips');
        Schema::dropIfExists('rolls');
        Schema::dropIfExists('po_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('users');
        Schema::dropIfExists('vendors');
    }
};
