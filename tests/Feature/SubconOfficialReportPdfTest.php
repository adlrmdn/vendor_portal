<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubconOfficialReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_a_pdf_data_uri_for_an_unsigned_report()
    {
        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2512/000014', // real PRG verified reachable earlier this session
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'order_qty' => 2646, 'cut_qty' => 2600, 'good_qty' => 2500, 'reject_qty' => 20,
            'sales_price' => 100000, 'allowed_limit' => 26, 'exceeding_reject_qty' => 0,
            'penalty_price' => 70000, 'reject_penalty' => 0, 'additional_deduction' => 0,
        ]);

        $uri = app(SubconOfficialReportService::class)->renderPdfDataUri($report);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:application/pdf;base64,', $uri);

        // Strengthen beyond the data-URI prefix: decode the payload and
        // confirm it's actually a real, non-trivial PDF, not e.g. an empty
        // or truncated render that still happens to carry the right prefix.
        $bytes = base64_decode(substr($uri, strlen('data:application/pdf;base64,')));
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
    }
}
