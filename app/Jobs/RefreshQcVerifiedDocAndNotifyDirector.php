<?php

namespace App\Jobs;

use App\Http\Controllers\QcApprovalController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The tail end of hoSendApproval() ("Validate & Send Approval"): re-render the
 * verified_doc PDF with post-RAF numbers, then gather the D365-derived figures
 * for the Director email and queue it. Both steps are best-effort (same
 * try/catch contract they had inline in the controller) and were confirmed
 * live to add ~5s to the submit request when run synchronously — see the
 * dispatch site in QcApprovalController::hoSendApproval(). Deferred here so
 * the submitter's response returns as soon as the actual approval state
 * (consumption, published fabric lines, ho_validation_signature) is durably
 * written.
 */
class RefreshQcVerifiedDocAndNotifyDirector implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public int $timeout = 90;

    public function __construct(public string $token) {}

    public function handle(): void
    {
        $row = DB::connection('qms')->table('packaging_project_sessions')
            ->where('approval_token', $this->token)->first();

        if (! $row) {
            Log::warning('QC director handoff: session row not found for token', ['token' => $this->token]);

            return;
        }

        $controller = app(QcApprovalController::class);
        $controller->refreshVerifiedDoc($row);
        $controller->sendDirectorApprovalRequest($this->token, $row);
    }
}
