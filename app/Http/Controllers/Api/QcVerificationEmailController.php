<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QcVerificationEmailController extends Controller
{
    /**
     * Network-fallback dispatch for the QC Console's Stage-1 "Verify & Sign"
     * email. The console tries direct SMTP first (primary relay + Gmail
     * backup) from the client machine; when both legs fail — typically the
     * client network blocking outbound port 587 entirely, not just the
     * primary relay being down — it POSTs the same payload here and the
     * portal sends it through its own already-configured mailer instead.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string',
            'subject' => 'required|string',
            'html_body' => 'required|string',
            'attachment_body' => 'nullable|string',
            'attachment_filename' => 'nullable|string',
        ]);

        $recipients = collect(explode(',', $data['recipient']))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values();

        if ($recipients->isEmpty()) {
            return response()->json(['message' => 'Recipient email address is empty'], 422);
        }

        [$attachmentBytes, $attachmentMime] = $this->decodeAttachment($data['attachment_body'] ?? null);
        $attachmentFilename = $this->resolveFilename($data['attachment_filename'] ?? null, $attachmentMime);

        $plainTextBody = "Dear Team,\n\nPlease find attached the official Quality Control Inspection Report.\n\n"
            ."Subject: {$data['subject']}\n\nThis is an automated system notification from Final Inspection QC.";

        try {
            foreach ($recipients as $recipient) {
                Mail::html($data['html_body'], function ($message) use ($recipient, $data, $plainTextBody, $attachmentBytes, $attachmentMime, $attachmentFilename) {
                    $message->to($recipient)
                        ->subject($data['subject'])
                        ->text($plainTextBody);

                    if ($attachmentBytes !== null) {
                        $message->attachData($attachmentBytes, $attachmentFilename, [
                            'mime' => $attachmentMime ?? 'application/pdf',
                        ]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('QC verification email fallback dispatch failed', [
                'recipient' => $data['recipient'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to dispatch email: '.$e->getMessage()], 502);
        }

        return response()->json(['status' => 'sent']);
    }

    /**
     * Mirrors the console's own decode (forge/src-tauri/src/lib.rs
     * send_email_report): `attachment_body` is a data URL, not raw base64.
     *
     * @return array{0: ?string, 1: ?string} [bytes, mime]
     */
    private function decodeAttachment(?string $dataUrl): array
    {
        if ($dataUrl === null || ! str_starts_with($dataUrl, 'data:')) {
            return [null, null];
        }

        $commaPos = strpos($dataUrl, ',');
        if ($commaPos === false) {
            return [null, null];
        }

        $header = substr($dataUrl, 0, $commaPos);
        $payload = substr($dataUrl, $commaPos + 1);

        $mime = null;
        if (preg_match('/^data:([^;]+);base64$/', $header, $matches)) {
            $mime = $matches[1];
        }

        $bytes = base64_decode(trim($payload), true);

        return $bytes === false ? [null, $mime] : [$bytes, $mime];
    }

    private function resolveFilename(?string $baseName, ?string $mime): string
    {
        $ext = match (true) {
            $mime === 'application/pdf' => '.pdf',
            $mime !== null && str_starts_with($mime, 'image/') => '.'.substr($mime, 6),
            default => '.pdf',
        };

        if ($baseName === null || $baseName === '') {
            return 'QC_Report'.$ext;
        }

        foreach (['.pdf', '.png', '.jpg', '.jpeg'] as $known) {
            if (str_ends_with($baseName, $known)) {
                return $baseName;
            }
        }

        return $baseName.$ext;
    }
}
