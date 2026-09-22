<?php

namespace Tests\Unit;

use App\Services\D365JobTransactionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class D365SalesPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_null_for_blank_plm_id()
    {
        $service = app(D365JobTransactionService::class);
        $this->assertNull($service->fetchSalesPrice(''));
        $this->assertNull($service->fetchSalesPrice('   '));
    }

    public function test_returns_sales_price_from_d365_response()
    {
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response([
                'value' => [['SalesPrice' => 125000.5]],
            ], 200),
        ]);

        $service = $this->getMockBuilder(D365JobTransactionService::class)
            ->onlyMethods(['getD365Token'])
            ->getMock();
        $service->method('getD365Token')->willReturn('fake-token');

        $price = $service->fetchSalesPrice('PLM/26/04/00137');

        $this->assertSame(125000.5, $price);
    }

    public function test_returns_null_when_d365_has_no_matching_record()
    {
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response(['value' => []], 200),
        ]);

        $service = $this->getMockBuilder(D365JobTransactionService::class)
            ->onlyMethods(['getD365Token'])
            ->getMock();
        $service->method('getD365Token')->willReturn('fake-token');

        $this->assertNull($service->fetchSalesPrice('PLM/DOES/NOT/EXIST'));
    }
}
