<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportSendTest extends TestCase
{
    private string $prg = 'MPG/PRG/TEST/GATE01';

    private string $jobTransactionId = 'MPG/JOB/TEST/SEND01';

    private string $poNumber = 'MPG/PO/TEST/SEND01';

    private string $plmId = 'PLM/TEST/SEND01';

    protected function setUp(): void
    {
        parent::setUp();
        SubconOfficialReport::query()->where('production_group', $this->prg)->delete();
        $this->cleanupVsmFixtures();
    }

    protected function tearDown(): void
    {
        SubconOfficialReport::query()->where('production_group', $this->prg)->delete();
        $this->cleanupVsmFixtures();
        parent::tearDown();
    }

    private function cleanupVsmFixtures(): void
    {
        DB::connection('vsm')->table('job_transaction_line_details')->where('JobTransactionId', $this->jobTransactionId)->delete();
        DB::connection('vsm')->table('job_transaction_headers')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('production_groups')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('po_lines')->where('PurchaseOrderNumber', $this->poNumber)->delete();
        DB::connection('vsm')->table('po_headers')->where('PurchaseOrderNumber', $this->poNumber)->delete();
        DB::connection('vsm')->table('plm_activity')->where('ProductionGroup', $this->prg)->delete();
    }

    public function test_send_leaves_sales_price_null_when_d365_fetch_fails_or_plm_is_missing()
    {
        // A production group forProductionGroup() resolves with no plm_id at
        // all (standalone/in-house, per addProcessPurchaseOrdersForGroup()'s
        // docblock) must not throw — send() should still create the row,
        // just with sales_price/sales_price_source left null so the admin
        // fills it in manually (spec §8).
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);
        DB::connection('vsm')->table('production_groups')->insert([
            'ProductionGroup' => $this->prg, 'PONumber' => $this->poNumber,
        ]);
        DB::connection('vsm')->table('po_headers')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'PurchPoolId' => 'AddPro', 'DocumentApprovalStatus' => 'Confirmed',
            'OrderVendorAccountNumber' => 'V_TEST_SEND', 'PurchaseOrderStatus' => 'Received',
        ]);
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing',
        ]);

        $mockD365 = $this->mock(\App\Services\D365JobTransactionService::class);
        $mockD365->shouldReceive('fetchSalesPrice')->andReturn(null)->byDefault();
        $mockD365->shouldReceive('fetchVendorNames')->andReturn([])->byDefault();

        $report = app(SubconOfficialReportService::class)->send($this->prg, 'ADD-Was', 0.0, null);

        $this->assertNull($report->sales_price);
        $this->assertNull($report->sales_price_source);
        $this->assertSame(0.0, $report->penalty_price); // salesPrice fell back to 0 in computeDeduction()
    }

    public function test_send_fetches_sales_price_from_d365_when_plm_activity_links_the_group()
    {
        // A production group WITH a real plm_activity row (the actual PLM
        // link, distinct from forProductionGroup()'s always-blank plm_id —
        // see addProcessPurchaseOrdersForGroup()'s docblock for the same
        // plm_activity.ProductionGroup -> PLMId source) must resolve
        // sales_price via D365JobTransactionService::fetchSalesPrice() and
        // stamp sales_price_source = 'd365'.
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);
        DB::connection('vsm')->table('production_groups')->insert([
            'ProductionGroup' => $this->prg, 'PONumber' => $this->poNumber,
        ]);
        DB::connection('vsm')->table('po_headers')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'PurchPoolId' => 'AddPro', 'DocumentApprovalStatus' => 'Confirmed',
            'OrderVendorAccountNumber' => 'V_TEST_SEND', 'PurchaseOrderStatus' => 'Received',
        ]);
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing',
        ]);
        DB::connection('vsm')->table('plm_activity')->insert([
            'PLMId' => $this->plmId, 'ProductionGroup' => $this->prg,
        ]);

        $mockD365 = $this->mock(\App\Services\D365JobTransactionService::class);
        $mockD365->shouldReceive('fetchSalesPrice')->with($this->plmId)->andReturn(125000.0);
        $mockD365->shouldReceive('fetchVendorNames')->andReturn([])->byDefault();

        $report = app(SubconOfficialReportService::class)->send($this->prg, 'ADD-Was', 0.0, null);

        $this->assertSame(125000.0, $report->sales_price);
        $this->assertSame('d365', $report->sales_price_source);
    }
}
