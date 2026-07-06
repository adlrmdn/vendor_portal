<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            // Staged approval workflow:
            // cutting -> cutting_review -> gramasi -> gramasi_review -> labels -> completed
            $table->string('workflow_stage', 20)->default('cutting')->after('status');
            $table->unsignedInteger('blister_capacity')->nullable()->after('workflow_stage');
            $table->timestamp('cutting_approved_at')->nullable()->after('blister_capacity');
            $table->string('cutting_approved_by')->nullable()->after('cutting_approved_at');
            $table->timestamp('gramasi_approved_at')->nullable()->after('cutting_approved_by');
            $table->string('gramasi_approved_by')->nullable()->after('gramasi_approved_at');
        });

        // Existing orders predate the gated flow. Derive a non-disruptive starting
        // stage from how far their D365 job-transaction sync had already progressed
        // so nothing currently in flight gets locked out of label printing.
        DB::table('subcon_orders')
            ->whereIn('job_trans_status', ['both_saved', 'gramasi_saved'])
            ->update(['workflow_stage' => 'labels']);

        DB::table('subcon_orders')
            ->whereIn('job_trans_status', ['qty_cutting_saved', 'first_saved'])
            ->update(['workflow_stage' => 'gramasi']);

        DB::table('subcon_orders')
            ->where('status', 'completed')
            ->update(['workflow_stage' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('subcon_orders', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_stage',
                'blister_capacity',
                'cutting_approved_at',
                'cutting_approved_by',
                'gramasi_approved_at',
                'gramasi_approved_by',
            ]);
        });
    }
};
