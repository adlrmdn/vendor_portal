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
        $this->contract = $contract->loadMissing([
            'vendor',
            'currency',
            'paymentTerm',
            'deliveryTerm',
            'transportMode',
            'season',
            'lines.hsCode',
            'deliveries',
            'notes',
            'files',
        ]);
        $this->viewUrl = route('vendor.pc.contracts.show', $contract);
    }

    public function envelope(): Envelope
    {
        $label = $this->contract->contract_number ?? 'a new Preliminary Contract';

        return new Envelope(
            subject: "[ACTION REQUIRED] Preliminary Contract Review: {$label} — PT Mega Perintis Tbk",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pc-contract-sent',
        );
    }

    /**
     * Attach the approved contract PDF directly to the email for immediate review.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdfFile = $this->contract->files()
            ->where('file_type', 'contract_pdf')
            ->latest('generated_at')
            ->first();

        if ($pdfFile && \Illuminate\Support\Facades\Storage::disk($pdfFile->disk)->exists($pdfFile->path)) {
            $filename = str_replace(['/', '\\'], '_', (string) ($this->contract->contract_number ?: 'Preliminary_Contract')) . '.pdf';

            return [
                \Illuminate\Mail\Mailables\Attachment::fromStorageDisk($pdfFile->disk, $pdfFile->path)
                    ->as($filename)
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
