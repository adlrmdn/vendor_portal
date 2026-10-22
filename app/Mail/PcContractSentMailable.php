<?php

namespace App\Mail;

use App\Models\PcContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the vendor a Preliminary Contract is awaiting their review.
 * Unlike ToleranceAmendmentMailable, this carries NO signed-URL action
 * links — Accept/Query need an authenticated fabric_vendor session since
 * the contract view also shows pricing, so this just points at the
 * login-gated vendor portal page.
 */
class PcContractSentMailable extends Mailable
{
    use Queueable, SerializesModels;

    public PcContract $contract;

    public string $viewUrl;

    public function __construct(PcContract $contract)
    {
        $this->contract = $contract;
        $this->viewUrl = route('vendor.pc.contracts.show', $contract);
    }

    public function envelope(): Envelope
    {
        $label = $this->contract->contract_number ?? 'a new Preliminary Contract';

        return new Envelope(
            subject: "Preliminary Contract {$label} — Review Requested",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pc-contract-sent',
        );
    }
}
