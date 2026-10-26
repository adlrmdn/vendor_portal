<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use App\Services\RpaQueueService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialReportRpaQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SubconOfficialReport::where('production_group', 'MPG/PRG/TEST/RPA01')->delete();
    }

    private function makeReport(array $overrides = []): SubconOfficialReport
    {
        return SubconOfficialReport::create(array_merge([
            'production_group' => 'MPG/PRG/TEST/RPA01',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR,
            'matched_po_number' => 'MPG/PO/TEST/00099',
            'matched_po_vendor_code' => 'V0099',
            'matched_po_vendor_name' => 'Test Vendor',
            'good_qty' => 500,
            'reject_penalty' => 0,
            'additional_deduction' => 0,
        ], $overrides));
    }

    protected function tearDown(): void
    {
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice', 'deduction'])
            ->where('entity_id', 'like', 'MPG/PRG/TEST/RPA01%')
            ->delete();
        SubconOfficialReport::where('production_group', 'MPG/PRG/TEST/RPA01')->delete();
        parent::tearDown();
    }

    public function test_queues_invoice_with_entity_id_and_rpa_type()
    {
        $report = $this->makeReport();

        $ok = app(RpaQueueService::class)->queueOfficialReportInvoice($report, 'data:application/pdf;base64,AAAA');

        $this->assertTrue($ok);
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Was',
            // packaging_project, not 'add_process' — see
            // RpaQueueService::queueOfficialReportInvoice()'s docblock: the
            // automaton bots hard-filter entity_type='packaging_project',
            // so tagging this 'add_process' leaves the row picked up by no
            // bot (commit e76ac7e).
            'entity_type' => 'packaging_project',
            'rpa_type' => 'invoice',
            'status' => 'pending',
        ], 'rpa');
    }

    public function test_deduction_is_queued_only_when_total_deduction_positive()
    {
        $zero = $this->makeReport(['operation' => 'ADD-Embro']);
        $service = app(RpaQueueService::class);

        $this->assertTrue($service->queueOfficialReportDeduction($zero, 'data:application/pdf;base64,AAAA'));
        $this->assertDatabaseMissing('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Embro',
            'rpa_type' => 'deduction',
        ], 'rpa');

        $withDeduction = $this->makeReport(['operation' => 'ADD-Sab', 'reject_penalty' => 100000]);
        $this->assertTrue($service->queueOfficialReportDeduction($withDeduction, 'data:application/pdf;base64,AAAA'));
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Sab',
            'entity_type' => 'packaging_project',
            'rpa_type' => 'deduction',
            'status' => 'pending',
        ], 'rpa');
    }
}
