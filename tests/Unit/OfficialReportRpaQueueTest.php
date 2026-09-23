<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use App\Services\RpaQueueService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialReportRpaQueueTest extends TestCase
{
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
            ->whereIn('rpa_type', ['invoice_addprocess', 'deduction_addprocess'])
            ->where('entity_id', 'like', 'MPG/PRG/TEST/RPA01%')
            ->delete();
        parent::tearDown();
    }

    public function test_queues_invoice_with_entity_id_and_rpa_type()
    {
        $report = $this->makeReport();

        $ok = app(RpaQueueService::class)->queueOfficialReportInvoice($report, 'data:application/pdf;base64,AAAA');

        $this->assertTrue($ok);
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Was',
            'rpa_type' => 'invoice_addprocess',
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
            'rpa_type' => 'deduction_addprocess',
        ], 'rpa');

        $withDeduction = $this->makeReport(['operation' => 'ADD-Sab', 'reject_penalty' => 100000]);
        $this->assertTrue($service->queueOfficialReportDeduction($withDeduction, 'data:application/pdf;base64,AAAA'));
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Sab',
            'rpa_type' => 'deduction_addprocess',
            'status' => 'pending',
        ], 'rpa');
    }
}
