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
 * Tail end of hoApprove() ("Review & Approve") and directorDecline(): re-render
 * verified_doc, then email MD Production the "Validate & Send" link. Same
 * reasoning as RefreshQcVerifiedDocAndNotifyDirector — refreshVerifiedDoc()'s
 * context() render alone is confirmed live (this session) to cost 1-4s of D365
 * round trips, and nothing in either caller's response depends on it or the
 * email having completed before returning.
 */
class RefreshQcVerifiedDocAndNotifyHo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public int $timeout = 90;

    public function __construct(
        public string $token,
        public string $approvedBy,
        public ?string $note = null,
    ) {}

    public function handle(): void
    {
        $row = DB::connection('qms')->table('packaging_project_sessions')
            ->where('approval_token', $this->token)->first();

        if (! $row) {
            Log::warning('QC HO handoff: session row not found for token', ['token' => $this->token]);

            return;
        }

        $controller = app(QcApprovalController::class);
        $controller->refreshVerifiedDoc($row);
        $controller->sendValidateSendRequest($this->token, $row, $this->approvedBy, $this->note);
    }
}
