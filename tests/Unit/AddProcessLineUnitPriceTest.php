<?php

namespace Tests\Unit;

use App\Services\SubconProductionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddProcessLineUnitPriceTest extends TestCase
{
    private string $po = 'MPG/PO/TEST/00001';

    protected function setUp(): void
    {
        parent::setUp();

        // po_lines.PurchaseOrderNumber has a live FK to po_headers on the
        // real vsm connection; a bare po_lines insert (as in the task brief)
        // fails with a foreign key violation without this parent row.
        DB::connection('vsm')->table('po_headers')->insert([
            'PurchaseOrderNumber' => $this->po,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('vsm')->table('po_lines')->where('PurchaseOrderNumber', $this->po)->delete();
        DB::connection('vsm')->table('po_headers')->where('PurchaseOrderNumber', $this->po)->delete();
        parent::tearDown();
    }

    public function test_resolves_price_by_operation_keyword_match()
    {
        DB::connection('vsm')->table('po_lines')->insert([
            ['PurchaseOrderNumber' => $this->po, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Sablon', 'LineAmount' => 500000, 'OrderedPurchaseQuantity' => 1000],
            ['PurchaseOrderNumber' => $this->po, 'LineNumber' => 2, 'LineDescription' => 'Item Jasa Add Process Embro', 'LineAmount' => 800000, 'OrderedPurchaseQuantity' => 1000],
        ]);

        $service = app(SubconProductionService::class);

        $this->assertSame(500.0, $service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Sab'));
        $this->assertSame(800.0, $service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Embro'));
    }

    public function test_returns_null_when_po_has_no_lines()
    {
        $service = app(SubconProductionService::class);

        $this->assertNull($service->resolveAddProcessLineUnitPrice('MPG/PO/DOES/NOT/EXIST', 'ADD-Was'));
    }

    public function test_returns_null_when_quantity_is_zero()
    {
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => $this->po, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing', 'LineAmount' => 500000, 'OrderedPurchaseQuantity' => 0,
        ]);

        $service = app(SubconProductionService::class);

        $this->assertNull($service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Was'));
    }
}
