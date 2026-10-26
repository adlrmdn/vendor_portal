<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Models\SubconOrder;
use App\Models\Vendor;
use App\Services\SubconOfficialReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportGatingTest extends TestCase
{
    private string $prg = 'MPG/PRG/TEST/GATE01';

    private string $jobTransactionId = 'MPG/JOB/TEST/GATE01';

    protected function setUp(): void
    {
        parent::setUp();
        SubconOfficialReport::query()->delete();
        SubconOrder::query()->where('production_group', $this->prg)->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_GATE%')->delete();
        $this->cleanupVsmFixtures();
    }

    protected function tearDown(): void
    {
        SubconOrder::query()->where('production_group', $this->prg)->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_GATE%')->delete();
        $this->cleanupVsmFixtures();
        parent::tearDown();
    }

    private function cleanupVsmFixtures(): void
    {
        DB::connection('vsm')->table('job_transaction_line_details')->where('JobTransactionId', $this->jobTransactionId)->delete();
        DB::connection('vsm')->table('job_transaction_headers')->where('ProductionGroup', $this->prg)->delete();
    }

    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    private function makeVendor(string $code): Vendor
    {
        $vendor = new Vendor(['name' => 'V', 'vendor_code' => $code, 'type' => 'subcon', 'is_active' => true]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        return $vendor;
    }

    public function test_cmt_group_blocked_when_no_cutting_output_yet()
    {
        $vendor = $this->makeVendor('V_GATE');
        SubconOrder::create([
            'order_number' => 'PO-GATE-01', 'vendor_id' => $vendor->id, 'title' => 'T',
            'status' => 'pending', 'order_date' => now(),
            'production_group' => $this->prg, 'workflow_stage' => SubconOrder::STAGE_CUTTING_REVIEW,
        ]);

        // No job_transaction_headers/line_details and no SubconCuttingReport
        // rows seeded for this group, so there's genuinely no cutting output
        // yet — gate() blocks on that, not on workflow_stage.
        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cutting', strtolower($result['reason']));
    }

    public function test_cmt_group_passes_cutting_check_based_on_cut_output_not_approval_stage()
    {
        $vendor = $this->makeVendor('V_GATE2');
        SubconOrder::create([
            'order_number' => 'PO-GATE-02', 'vendor_id' => $vendor->id, 'title' => 'T',
            'status' => 'pending', 'order_date' => now(),
            // Still unapproved (STAGE_CUTTING, not past cutting_review) — the
            // point of this test: gate() no longer cares, only cut OUTPUT
            // existing matters (see gate()'s docblock, changed 2026-10-26).
            'production_group' => $this->prg, 'workflow_stage' => SubconOrder::STAGE_CUTTING,
        ]);

        // Real cutting output already synced from D365 (CMT-Cut), even
        // though the work order's own in-app cutting approval hasn't run.
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'CMT-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);

        $result = $this->service()->gate($this->prg, 'ADD-Was');

        // No matched PO for this made-up PRG, so still blocked — but NOT on
        // a cutting-output reason.
        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('cutting', strtolower($result['reason']));
        $this->assertStringContainsString('purchase order', strtolower($result['reason']));
    }

    public function test_in_house_group_blocked_when_no_cut_qty_synced()
    {
        // No SubconOrder at all for this PRG, and no vsm job_transaction_line_details rows.
        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cut', strtolower($result['reason']));
    }

    public function test_in_house_group_passes_cutting_check_when_cut_qty_present()
    {
        // Matches cutQuantitiesForGroup()'s own join exactly: a
        // job_transaction_headers row (ProductionGroup + Operation='IN-Cut')
        // resolves a JobTransactionId, whose job_transaction_line_details
        // rows carry the actual TotalGoodQty/TotalRejectQty per size.
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);

        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('cut qty', strtolower($result['reason']));
    }
}
