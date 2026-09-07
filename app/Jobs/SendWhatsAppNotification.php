<?php

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued WhatsApp text send via the internal channel bot
 * (WhatsAppNotificationService). Mirrors SendQcNotificationEmail's shape so
 * a phone notification never blocks the request that triggered it — same
 * reason emails are queued rather than sent inline.
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public int $timeout = 30;

    public function __construct(public string $to, public string $message) {}

    public function handle(WhatsAppNotificationService $whatsapp): void
    {
        $whatsapp->send($this->to, $this->message);
    }
}
