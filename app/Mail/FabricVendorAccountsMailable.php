<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Carries the fabric vendor account roster Excel export as an attachment. */
class FabricVendorAccountsMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $excelBinary,
        public int $totalVendors,
        public int $newAccounts,
        public int $resetAccounts = 0,
    ) {}

    public function envelope(): Envelope
    {
        $subject = 'Fabric Vendor Accounts — Full List ('.$this->totalVendors.' vendors, '.$this->newAccounts.' newly registered';
        if ($this->resetAccounts > 0) {
            $subject .= ', '.$this->resetAccounts.' password reset';
        }
        $subject .= ')';

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.fabric-vendor-accounts');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->excelBinary, 'fabric_vendors_accounts.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
