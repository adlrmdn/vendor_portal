<?php

namespace App\Console\Commands;

use App\Jobs\WarmQcHoApprovalCache;
use App\Models\SubconOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarmQcApprovalCaches extends Command
{
    protected $signature = 'qc:warm-approval-caches';

    protected $description = "Re-dispatches WarmQcHoApprovalCache for every session currently sitting in the Report Validation or Director Approval queue. WarmQcHoApprovalCache already fires once, right when the HO/Director approval email is queued (QcApprovalController::sendHoApprovalRequest/sendValidateSendRequest) — but the D365 response cache it populates (fabricLinesWithData/accessoryLinesForPo/resolveGoodsReceipts/context) is only 1h TTL, while a real pending backlog (each order reviewed once, in whatever order an admin gets to them) routinely sits for hours to days. Confirmed live: hoApprovalForm() costs ~4.9s cold vs ~60ms warm, and any order not opened within an hour of its one-time warm-up goes cold again — this is what made opening 'Review & Approve'/'Validate & Send'/the Director page feel randomly slow. Scheduled to run well inside that 1h TTL (see routes/console.php) so a pending order's cache is never more than one run-interval away from having been refreshed.";

    public function handle(): int
    {
        if (! Schema::connection('qms')->hasTable('packaging_project_sessions')) {
            $this->info('QMS unreachable — nothing to warm.');

            return Command::SUCCESS;
        }

        $tokens = collect();

        // Pending HO ("Review & Approve" / "Validate & Send") — same filter as
        // SubconAdminController::pendingValidateSends().
        if (Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature')) {
            $tokens = $tokens->merge(
                DB::connection('qms')->table('packaging_project_sessions')
                    ->whereNotNull('approval_token')
                    ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                    ->where(function ($q) { $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', ''); })
                    ->where(function ($q) { $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', ''); })
                    ->whereNotIn('project_id', function ($q) {
                        $q->select('project_id')->from('packaging_projects')
                            ->whereIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES);
                    })
                    ->pluck('approval_token')
            );

            // Pending Director authorization — same filter as
            // SubconAdminController::pendingDirectorApprovals().
            $tokens = $tokens->merge(
                DB::connection('qms')->table('packaging_project_sessions')
                    ->whereNotNull('approval_token')
                    ->whereNotNull('ho_validation_signature')->where('ho_validation_signature', '!=', '')
                    ->where(function ($q) { $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', ''); })
                    ->whereNotIn('project_id', function ($q) {
                        $q->select('project_id')->from('packaging_projects')
                            ->whereIn('status', SubconOrder::QMS_INACTIVE_PROJECT_STATUSES);
                    })
                    ->pluck('approval_token')
            );
        }

        $tokens = $tokens->unique()->values();

        foreach ($tokens as $token) {
            WarmQcHoApprovalCache::dispatch($token);
        }

        $this->info("Queued cache warm-up for {$tokens->count()} pending session(s).");

        return Command::SUCCESS;
    }
}
