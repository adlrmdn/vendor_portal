<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(array $overrides = []): SubconOfficialReport
    {
        return SubconOfficialReport::create(array_merge([
            'production_group' => 'MPG/PRG/2512/000014',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'matched_po_number' => 'MPG/PO/2601/00302',
            'matched_po_vendor_code' => 'V0061',
            'matched_po_vendor_name' => 'CV YOGA CIPTA PERKASA',
            'order_qty' => 2646, 'cut_qty' => 2600, 'good_qty' => 2500, 'reject_qty' => 20,
            'sales_price' => 100000, 'allowed_limit' => 26, 'exceeding_reject_qty' => 0,
            'penalty_price' => 70000, 'reject_penalty' => 0, 'additional_deduction' => 0,
        ], $overrides));
    }

    protected function tearDown(): void
    {
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice', 'deduction'])
            ->where('entity_id', 'like', 'MPG/PRG/2512/000014:%')
            ->delete();
        parent::tearDown();
    }

    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    public function test_ho_approval_moves_to_pending_director_and_signs()
    {
        $report = $this->makeReport();

        $this->service()->applyHoApproval($report, 'Ika (MD Production)');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);
        $this->assertStringContainsString('Ika', $report->ho_approval_signature);
        $this->assertNotNull($report->ho_approved_at);
        $this->assertDatabaseHas('subcon_job_logs', [
            'job_type' => 'official_report:ADD-Was',
            'status' => 'ho_approved',
            'order_number' => 'MPG/PO/2601/00302', // matched_po_number, not the production group — see SubconOfficialReportService::logResult()'s docblock
        ]);
    }

    public function test_ho_approval_can_revise_deduction_and_remarks_on_resend()
    {
        $report = $this->makeReport(['revision_count' => 1, 'additional_deduction' => 0]);

        $this->service()->applyHoApproval($report, 'Ika', additionalDeduction: 75000.0, remarks: 'Revised after director feedback');

        $report->refresh();
        $this->assertSame(75000.0, $report->additional_deduction);
        $this->assertSame('Revised after director feedback', $report->remarks);
        $this->assertDatabaseHas('subcon_job_logs', [
            'job_type' => 'official_report:ADD-Was',
            'status' => 'ho_approved',
            'order_number' => 'MPG/PO/2601/00302', // matched_po_number, not the production group — see SubconOfficialReportService::logResult()'s docblock
        ]);
    }

    public function test_director_approval_completes_and_queues_rpa()
    {
        $report = $this->makeReport(['status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR, 'ho_approval_signature' => 'Digitally Signed: Ika']);

        $this->service()->applyDirectorApproval($report, 'The Director');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $this->assertStringContainsString('The Director', $report->director_approval_signature);
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/2512/000014:ADD-Was',
            'rpa_type' => 'invoice',
        ], 'rpa');
        $this->assertDatabaseHas('subcon_job_logs', [
            'job_type' => 'official_report:ADD-Was',
            'status' => 'director_approved',
            'order_number' => 'MPG/PO/2601/00302', // matched_po_number, not the production group — see SubconOfficialReportService::logResult()'s docblock
        ]);
    }

    public function test_director_decline_returns_to_pending_ho_and_increments_revision_count()
    {
        $report = $this->makeReport(['status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR, 'revision_count' => 0]);

        $this->service()->applyDirectorDecline($report, 'Deduction figures look off, please recheck.');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);
        $this->assertSame(1, $report->revision_count);
        $this->assertSame('Deduction figures look off, please recheck.', $report->director_decline_reason);
        $this->assertDatabaseHas('subcon_job_logs', [
            'job_type' => 'official_report:ADD-Was',
            'status' => 'director_declined',
            'order_number' => 'MPG/PO/2601/00302', // matched_po_number, not the production group — see SubconOfficialReportService::logResult()'s docblock
        ]);
    }

    // --- Replay guards (a stale, non-expiring signed email link clicked after
    // the report has already moved past the stage it targets must not be able
    // to regress status or overwrite a later signature) ---

    public function test_ho_approval_rejects_a_report_that_is_not_pending_ho()
    {
        $report = $this->makeReport([
            'status' => SubconOfficialReport::STATUS_COMPLETED,
            'ho_approval_signature' => 'Digitally Signed: Ika',
            'director_approval_signature' => 'Digitally Signed: The Director',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This report is no longer awaiting MD Production approval.');

        try {
            $this->service()->applyHoApproval($report, 'Replay Attacker');
        } finally {
            $report->refresh();
            $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
            $this->assertSame('Digitally Signed: Ika', $report->ho_approval_signature);
        }
    }

    public function test_director_approval_rejects_a_report_that_is_not_pending_director_and_does_not_double_queue_rpa()
    {
        // Seed a REAL completion first (this legitimately queues one
        // invoice row), then simulate a stale signed link being
        // clicked again on the now-completed report.
        $report = $this->makeReport(['status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR, 'ho_approval_signature' => 'Digitally Signed: Ika']);
        $this->service()->applyDirectorApproval($report, 'The Director');
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This report is no longer awaiting Director authorization.');

        try {
            $this->service()->applyDirectorApproval($report, 'Replay Attacker');
        } finally {
            $report->refresh();
            $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
            $this->assertStringStartsWith('Digitally Signed: The Director [UTC+07:00:', $report->director_approval_signature);
            $this->assertSame(1, DB::connection('rpa')->table('rpa_queues')
                ->where('entity_id', 'MPG/PRG/2512/000014:ADD-Was')
                ->where('rpa_type', 'invoice')
                ->count());
        }
    }

    public function test_director_decline_rejects_a_report_that_is_not_pending_director()
    {
        $report = $this->makeReport([
            'status' => SubconOfficialReport::STATUS_COMPLETED,
            'ho_approval_signature' => 'Digitally Signed: Ika',
            'director_approval_signature' => 'Digitally Signed: The Director',
            'revision_count' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This report is no longer awaiting Director authorization.');

        try {
            $this->service()->applyDirectorDecline($report, 'Replayed decline attempt.');
        } finally {
            $report->refresh();
            $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
            $this->assertSame(0, $report->revision_count);
            $this->assertNull($report->director_decline_reason);
        }
    }
}
