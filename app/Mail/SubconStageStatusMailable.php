<?php

namespace App\Mail;

use App\Models\SubconOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the vendor when a stage is approved or declined. When the gramasi
 * stage is approved this is also the "labels are now unlocked" notification
 * that initiates label printing.
 */
class SubconStageStatusMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOrder $order;

    /** 'cutting' or 'gramasi'. */
    public string $gate;

    /** 'approved' or 'declined'. */
    public string $outcome;

    public string $gateLabel;

    /** True when this approval unlocks label printing. */
    public bool $labelsUnlocked;

    public string $portalUrl;

    public function __construct(SubconOrder $order, string $gate, string $outcome)
    {
        $this->order = $order;
        $this->gate = $gate;
        $this->outcome = $outcome;
        $this->gateLabel = $gate === 'gramasi' ? 'Gramasi & Blister Capacity' : 'Cutting Report';
        $this->labelsUnlocked = $gate === 'gramasi' && $outcome === 'approved';

        if ($this->labelsUnlocked) {
            $this->portalUrl = url(\Illuminate\Support\Facades\URL::signedRoute('subcon.generate-labels', ['order' => $order->id], absolute: false));
        } else {
            $this->portalUrl = route('subcon.vendor.orders.view', $order->id);
        }
    }

    public function envelope(): Envelope
    {
        $verb = $this->outcome === 'approved' ? 'approved' : 'returned';
        $ref = $this->subjectRef();

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: $this->gateLabel.' '.$verb.' — '.$ref,
        );
    }

    /** Style — PO — production group, omitting any blank parts. */
    private function subjectRef(): string
    {
        $parts = array_filter([
            trim((string) $this->order->title),
            trim((string) $this->order->order_number),
            trim((string) $this->order->production_group),
        ], fn ($p) => $p !== '');

        return implode(' — ', $parts);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subcon-stage-status');
    }
}
