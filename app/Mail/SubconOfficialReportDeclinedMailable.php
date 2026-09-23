<?php

namespace App\Mail;

use App\Models\SubconOfficialReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Notifies MD Production that the Director declined and a revision is needed. */
class SubconOfficialReportDeclinedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOfficialReport $report;

    public string $reviseUrl;

    public function __construct(SubconOfficialReport $report)
    {
        $this->report = $report;
        $this->reviseUrl = url(URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false));
    }

    public function envelope(): Envelope
    {
        $ref = "{$this->report->production_group} — {$this->report->operation}";

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: "Official Report declined by Director — revision needed: {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.official-report-declined');
    }
}
