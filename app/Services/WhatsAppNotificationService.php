<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the internal WhatsApp channel bot (~/channel on this box —
 * whatsapp-web.js behind a tiny HTTP API, see channel/APPROVALS.md). Used to
 * mirror an approval-request email as a phone notification; it is never a
 * substitute for the token-based approve/reject links themselves, which the
 * message just carries as plain URLs (WhatsApp auto-linkifies them).
 *
 * Best-effort throughout: a missing key, unreachable bot, or send failure
 * logs and returns false rather than throwing — callers fire this alongside
 * email and must never let it block an approval flow that already committed.
 */
class WhatsAppNotificationService
{
    /**
     * Send a plain WhatsApp text message via POST /send on the channel bot.
     */
    public function send(string $to, string $message): bool
    {
        $base = trim((string) config('services.whatsapp.base_url'));
        $key = trim((string) config('services.whatsapp.api_key'));

        if ($base === '' || $key === '') {
            Log::warning('WhatsApp notification skipped: WA_API_BASE/WA_API_KEY not configured');

            return false;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(10)
                ->post(rtrim($base, '/').'/send', [
                    'to' => $to,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp notification failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp notification failed', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Normalize a raw phone number into a WhatsApp chat id (`<digits>@c.us`).
     * A value that already contains '@' (a full chat/group id) passes through
     * unchanged. A leading '0' is treated as Indonesian local format → '62'.
     */
    public static function toChatId(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, '@')) {
            return $raw;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits.'@c.us';
    }

    /**
     * Split a Setting value that may hold one or many comma/semicolon-separated
     * phone numbers into normalized WhatsApp chat ids. Mirrors
     * SubconApprovalController::parseRecipients for emails.
     *
     * @return array<int, string>
     */
    public static function parsePhoneList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $numbers = preg_split('/[,;]+/', $raw) ?: [];
        $chatIds = array_filter(array_map(
            fn ($n) => self::toChatId(trim($n)),
            $numbers
        ));

        return array_values(array_unique($chatIds));
    }
}
