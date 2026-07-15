<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the Final (Head Office) approval email, attaching the SAME inspection
 * document as stage 1 — the console's `verified_doc` (a base64 data URI in QMS
 * `packaging_projects`, keyed by project_id).
 *
 * The console writes verified_doc at "Verify → Send" time, BEFORE the stage-1
 * email goes out, so by the time the portal approves stage 1 and this email
 * fires, the document is already in the DB — no dependency on the console running
 * at that moment. verified_doc is normally `data:application/pdf;base64,…` (used
 * as-is); a legacy `data:image/…` value is wrapped into a PDF. If it is somehow
 * still absent, a few short retries cover the write lag, then the email is sent
 * WITHOUT the attachment so the approver is never blocked.
 */
class SendFinalApprovalEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Short safety net for write lag (~5 tries × 5s); the doc is normally already present. */
    public int $tries = 5;

    public int $backoff = 5;

    /**
     * @param  array<string,mixed>  $payload  token, recipients[], subject, sessionId,
     *                                        projectId, productionGroup, orderNumber, remarks
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $projectId = $this->payload['projectId'] ?? null;
        $pdf = null;

        if ($projectId) {
            try {
                $doc = DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $projectId)
                    ->value('verified_doc');

                if (! empty($doc)) {
                    $pdf = $this->toPdfBytes((string) $doc);
                }
            } catch (\Throwable $e) {
                Log::warning('Final email: verified_doc fetch failed', [
                    'project' => $projectId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Doc not written yet — brief wait for the console's DB write, unless the
        // window is exhausted (then send without the attachment).
        if ($pdf === null && $this->attempts() < $this->tries) {
            $this->release($this->backoff);

            return;
        }

        $this->send($pdf);
    }

    /**
     * Decode a verified_doc data URI to PDF bytes. A PDF data URI is used
     * directly (the console's exact document); an image data URI is wrapped into
     * a one-page PDF (legacy fallback).
     */
    private function toPdfBytes(string $dataUri): ?string
    {
        try {
            if (! str_starts_with($dataUri, 'data:')) {
                return null;
            }
            $comma = strpos($dataUri, ',');
            if ($comma === false) {
                return null;
            }
            $meta = substr($dataUri, 0, $comma);         // e.g. "data:application/pdf;base64"
            $bytes = base64_decode(substr($dataUri, $comma + 1), true);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            // Already a PDF → attach the bytes as-is (the console's exact document).
            if (str_contains($meta, 'application/pdf')) {
                return $bytes;
            }

            // Legacy image (e.g. PNG) → wrap it into a single-page PDF.
            if (str_contains($meta, 'image/')) {
                $html = '<html><body style="margin:0;padding:0;text-align:center;">'
                    .'<img src="'.$dataUri.'" style="max-width:100%;"></body></html>';

                return Pdf::loadHTML($html)->setPaper('a4')->output();
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Final email: verified_doc → PDF failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function send(?string $pdf): void
    {
        $p = $this->payload;
        $token = $p['token'] ?? '';

        try {
            // One message per recipient: each approver gets links carrying their
            // own address (`as`), so the approval can be attributed to the person
            // who clicked — the shared token alone cannot identify them.
            foreach (($p['recipients'] ?? []) as $recipient) {
                Mail::send('emails.qc-ho-approval', [
                    'url' => route('qc.ho-approve', ['token' => $token, 'as' => $recipient]),
                    'declineUrl' => route('qc.ho-decline', ['token' => $token, 'as' => $recipient]),
                    'sessionId' => $p['sessionId'] ?? null,
                    'projectId' => $p['projectId'] ?? null,
                    'productionGroup' => $p['productionGroup'] ?? null,
                    'orderNumber' => $p['orderNumber'] ?? null,
                    'remarks' => $p['remarks'] ?? null,
                    'note' => $p['note'] ?? null,
                ], function ($m) use ($p, $pdf, $recipient) {
                    $m->from('rpa@megaperintis.co.id', 'Mega Perintis RPA')
                        ->to($recipient)
                        ->subject($p['subject'] ?? 'Approval needed: Final Approval');

                    if ($pdf !== null) {
                        $name = str_replace(['/', '\\'], '-', (string) ($p['orderNumber'] ?? 'inspection'));
                        $m->attachData($pdf, 'packaging-inspection-'.$name.'.pdf', ['mime' => 'application/pdf']);
                    }
                });
            }

            Log::info('QC final approval email sent', [
                'token' => $token,
                'recipients' => count($p['recipients'] ?? []),
                'attached' => $pdf !== null,
            ]);
        } catch (\Throwable $e) {
            // Never loop on a mail error — log and drop.
            Log::error('QC final approval email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }
}
