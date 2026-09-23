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

/**
 * Sent to MD Production (stage 'ho') or the Director (stage 'director') when
 * an Official Report is ready for their action. One mailable for both
 * stages, mirroring SubconApprovalRequestMailable's own $gate-param shape.
 */
class SubconOfficialReportApprovalMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOfficialReport $report;

    public string $stage;

    public string $approveUrl;

    public ?string $declineUrl = null;

    public function __construct(SubconOfficialReport $report, string $stage)
    {
        $this->report = $report;
        $this->stage = $stage;

        if ($stage === 'ho') {
            $this->approveUrl = url(URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false));
        } else {
            $this->approveUrl = url(URL::signedRoute('subcon.official-report.director-approve', ['report' => $report->id], absolute: false));
            $this->declineUrl = url(URL::signedRoute('subcon.official-report.director-decline', ['report' => $report->id], absolute: false));
        }
    }

    public function envelope(): Envelope
    {
        $label = $this->stage === 'ho' ? 'MD Production Approval' : 'Director Authorization';
        $ref = "{$this->report->production_group} — {$this->report->operation}";

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: "Official Report {$label} needed: {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.official-report-approval');
    }
}
