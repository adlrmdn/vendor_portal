<?php

namespace App\Mail;

use App\Models\ToleranceAmendmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ToleranceAmendmentMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $request;

    public $approveUrl;

    public $declineUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(ToleranceAmendmentRequest $request)
    {
        $this->request = $request;

        // Generate signed URLs for approval/decline. Use a relative signature
        // (absolute: false) so it validates behind the HTTPS reverse proxy,
        // where the internal request host/scheme differs from the public URL.
        // Wrapped in url() to keep the email link a full clickable https URL.
        $this->approveUrl = url(URL::signedRoute('tolerance.approve', ['request' => $request->id], absolute: false));
        $this->declineUrl = url(URL::signedRoute('tolerance.decline', ['request' => $request->id], absolute: false));
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = ($this->request->type === 'partial_shipment' ? 'Partial Shipment' : 'Tolerance Amendment').
                   ' Request - Item '.$this->request->poItem->item_number;

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tolerance-amendment',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
