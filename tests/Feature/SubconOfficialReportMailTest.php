<?php

namespace Tests\Feature;

use App\Mail\SubconOfficialReportApprovalMailable;
use App\Mail\SubconOfficialReportDeclinedMailable;
use App\Models\SubconOfficialReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubconOfficialReportMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_mailable_renders_for_ho_and_director_stages()
    {
        Mail::fake();

        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/TEST/MAIL01',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
        ]);

        Mail::to('ho@test.com')->send(new SubconOfficialReportApprovalMailable($report, 'ho'));
        Mail::to('director@test.com')->send(new SubconOfficialReportApprovalMailable($report, 'director'));

        Mail::assertSent(SubconOfficialReportApprovalMailable::class, 2);
    }

    public function test_declined_mailable_sends()
    {
        Mail::fake();

        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/TEST/MAIL02',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'director_decline_reason' => 'Please recheck deduction.',
        ]);

        Mail::to('ho@test.com')->send(new SubconOfficialReportDeclinedMailable($report));

        Mail::assertSent(SubconOfficialReportDeclinedMailable::class);
    }
}
