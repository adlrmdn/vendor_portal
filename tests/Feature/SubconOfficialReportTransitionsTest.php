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
            ->whereIn('rpa_type', ['invoice_addprocess', 'deduction_addprocess'])
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
            'order_number' => 'MPG/PRG/2512/000014',
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
            'order_number' => 'MPG/PRG/2512/000014',
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
            'rpa_type' => 'invoice_addprocess',
        ], 'rpa');
        $this->assertDatabaseHas('subcon_job_logs', [
            'job_type' => 'official_report:ADD-Was',
            'status' => 'director_approved',
            'order_number' => 'MPG/PRG/2512/000014',
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
            'order_number' => 'MPG/PRG/2512/000014',
        ]);
    }
}
