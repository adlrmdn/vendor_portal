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
 * Pre-warms the D365 response caches QcApprovalController::hoApprovalForm()
 * needs — see warmApprovalCaches()'s docblock for the measured cold/warm
 * cost. Dispatched right after the HO "Review & Approve" / "Validate & Send"
 * email is queued (sendHoApprovalRequest(), sendValidateSendRequest()), so it
 * runs during the gap between the email going out and a human clicking it.
 */
class WarmQcHoApprovalCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $token) {}

    public function handle(): void
    {
        $row = DB::connection('qms')->table('packaging_project_sessions')
            ->where('approval_token', $this->token)->first();

        if (! $row) {
            Log::warning('QC approval cache warm-up: session row not found for token', ['token' => $this->token]);

            return;
        }

        app(QcApprovalController::class)->warmApprovalCaches($row);
    }
}
