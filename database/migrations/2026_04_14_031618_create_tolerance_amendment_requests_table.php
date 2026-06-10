<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tolerance_amendment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('po_item_id');
            $table->decimal('old_underdelivery', 8, 2);
            $table->decimal('old_overdelivery', 8, 2);
            $table->decimal('new_underdelivery', 8, 2);
            $table->decimal('new_overdelivery', 8, 2);
            $table->text('reason')->nullable();
            $table->string('approver_badge');
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending');
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->foreign('po_item_id')->references('id')->on('po_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tolerance_amendment_requests');
    }
};
