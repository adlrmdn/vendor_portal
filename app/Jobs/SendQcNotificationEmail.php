<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Generic sender for the QC workflow emails that carry the inspection PDF —
 * the Director authorization request (after MD Production approves) and the
 * completion notification (after the Director approves). The attachment is
 * read from packaging_projects.verified_doc, which the portal regenerates
 * (QcReportPdfService) at each milestone BEFORE dispatching this job, so no
 * retry-wait is needed (unlike SendFinalApprovalEmail's console-write race).
 *
 * Payload keys: view, recipients[], subject, viewData[], projectId (optional —
 * attach verified_doc when set), attachmentName.
 */
class SendQcNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    /** One SMTP send with the ~3-5 MB inspection PDF takes ~30s through Gmail. */
    public int $timeout = 120;

    /** @param array<string,mixed> $payload */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $p = $this->payload;

        $pdf = null;
        if (! empty($p['projectId'])) {
            try {
                $doc = DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $p['projectId'])
                    ->value('verified_doc');
                if (! empty($doc) && str_starts_with((string) $doc, 'data:application/pdf')) {
                    $comma = strpos((string) $doc, ',');
                    $bytes = $comma === false ? false : base64_decode(substr((string) $doc, $comma + 1), true);
                    $pdf = ($bytes === false || $bytes === '') ? null : $bytes;
                }
            } catch (\Throwable $e) {
                Log::warning('QC notification email: verified_doc fetch failed', [
                    'project' => $p['projectId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Mail::send($p['view'], $p['viewData'] ?? [], function ($m) use ($p, $pdf) {
                $m->to($p['recipients'] ?? [])
                    ->subject($p['subject'] ?? 'QC Packaging Notification');

                if ($pdf !== null) {
                    $name = str_replace(['/', '\\'], '-', (string) ($p['attachmentName'] ?? 'inspection'));
                    $m->attachData($pdf, $name.'.pdf', ['mime' => 'application/pdf']);
                }
            });

            Log::info('QC notification email sent', [
                'view' => $p['view'] ?? null,
                'to' => $p['recipients'] ?? [],
                'attached' => $pdf !== null,
            ]);
        } catch (\Throwable $e) {
            // Never loop on a mail error — log and drop.
            Log::error('QC notification email failed', ['view' => $p['view'] ?? null, 'error' => $e->getMessage()]);
        }
    }
}
