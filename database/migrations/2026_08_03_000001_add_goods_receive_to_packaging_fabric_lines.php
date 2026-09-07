<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods Receive (the D365 confirmed receipt quantity for the fabric's linked
 * PO, shown before Fabric Sent on the inspection report) has no local mirror,
 * so it's published to QMS the same way overconsumption/fabric_price/deduction
 * are — additive + nullable, hasColumn-guarded on the write side
 * (SubconFabricLinePublisher).
 *
 * Runs against the `qms` connection, same pattern as the director-stage and
 * ho_validation_signature migrations.
 */
return new class extends Migration
{
    protected $connection = 'qms';

    private const TABLE = 'packaging_project_fabric_lines';

    public function up(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'goods_receive')) {
                $table->double('goods_receive')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn(self::TABLE, 'goods_receive_date')) {
                $table->string('goods_receive_date', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table(self::TABLE, function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn(self::TABLE, 'goods_receive')) {
                $table->dropColumn('goods_receive');
            }
            if (Schema::connection($this->connection)->hasColumn(self::TABLE, 'goods_receive_date')) {
                $table->dropColumn('goods_receive_date');
            }
        });
    }
};
