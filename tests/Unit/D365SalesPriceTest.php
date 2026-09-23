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

    public function test_null_result_is_cached_and_not_refetched()
    {
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response(['value' => []], 200),
        ]);

        $service = $this->getMockBuilder(D365JobTransactionService::class)
            ->onlyMethods(['getD365Token'])
            ->getMock();
        $service->method('getD365Token')->willReturn('fake-token');

        $first = $service->fetchSalesPrice('PLM/CACHED/NULL');
        $this->assertNull($first);
        Http::assertSentCount(1);

        // Swap the fake to a response that WOULD return a non-null price if
        // fetchSalesPrice actually hit D365 again. If the cached null is
        // correctly recognised as "already cached", this second call must
        // return null from cache without making a new HTTP request at all —
        // proving Cache::has() no longer misreports a cached-null as
        // "not cached" (the bug this test guards against).
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response([
                'value' => [['SalesPrice' => 999999.0]],
            ], 200),
        ]);

        $second = $service->fetchSalesPrice('PLM/CACHED/NULL');

        $this->assertNull($second);
        // Http::fake() resets the recorded-request log, so this counts only
        // requests made after the swap above: zero proves the second call
        // was served from cache and never hit the (now price-returning) fake.
        Http::assertNothingSent();
    }
}
