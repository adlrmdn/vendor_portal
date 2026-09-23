<?php

namespace Tests\Unit;

use App\Services\SubconOfficialReportService;
use Tests\TestCase;

class SubconOfficialReportDeductionTest extends TestCase
{
    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    public function test_no_deduction_when_reject_within_one_percent_tolerance()
    {
        // cut_qty=1000 -> allowed_limit=floor(1000*0.01)=10; reject=10 is within tolerance.
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 10, salesPrice: 100000.0);

        $this->assertSame(10, $result['allowed_limit']);
        $this->assertSame(0, $result['exceeding_reject_qty']);
        $this->assertSame(70000.0, $result['penalty_price']);
        $this->assertSame(0.0, $result['reject_penalty']);
        $this->assertSame(0.0, $result['total_deduction']);
    }

    public function test_deduction_charged_only_on_the_exceeding_qty()
    {
        // allowed_limit=floor(1000*0.01)=10; reject=15 -> exceeding=5.
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 15, salesPrice: 100000.0);

        $this->assertSame(10, $result['allowed_limit']);
        $this->assertSame(5, $result['exceeding_reject_qty']);
        $this->assertSame(70000.0, $result['penalty_price']); // 100000 * 0.70
        $this->assertSame(350000.0, $result['reject_penalty']); // 5 * 70000
        $this->assertSame(350000.0, $result['total_deduction']);
    }

    public function test_additional_deduction_adds_to_the_total_but_not_reject_penalty()
    {
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 15, salesPrice: 100000.0, additionalDeduction: 50000.0);

        $this->assertSame(350000.0, $result['reject_penalty']);
        $this->assertSame(400000.0, $result['total_deduction']);
    }

    public function test_zero_sales_price_yields_zero_penalty_even_with_exceeding_reject()
    {
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 999, salesPrice: 0.0);

        $this->assertSame(0.0, $result['penalty_price']);
        $this->assertSame(0.0, $result['reject_penalty']);
    }

    public function test_zero_cut_qty_gives_zero_allowed_limit_so_any_reject_is_fully_charged()
    {
        $result = $this->service()->computeDeduction(cutQty: 0, rejectQty: 3, salesPrice: 10000.0);

        $this->assertSame(0, $result['allowed_limit']);
        $this->assertSame(3, $result['exceeding_reject_qty']);
        $this->assertSame(21000.0, $result['reject_penalty']); // 3 * 7000
    }
}
