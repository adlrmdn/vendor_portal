<?php

namespace Tests\Feature;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncD365OrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.d365.tenant_id' => 'test-tenant',
            'services.d365.client_id' => 'test-client',
            'services.d365.client_secret' => 'test-secret',
            'services.d365.resource' => 'https://test.dynamics.com',
        ]);
    }

    /**
     * Regression case: vendor V0984 (PC ref MPG/PC/PDV/26/VII/333) had confirmed
     * fabric POs in D365 that never synced because the vendor account wasn't
     * registered locally — d365:sync-orders used to just skip them silently.
     */
    public function test_sync_auto_creates_unknown_vendor_and_imports_its_pos()
    {
        $this->withoutExceptionHandling();

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2608/00198',
                        'OrderVendorAccountNumber' => 'V0984',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'PurchPoolId' => 'Fab-Local',
                        'dataAreaId' => 'mpg',
                        'CreatedDateTime1' => '2026-08-06T03:06:46Z',
                        'RequestedDeliveryDate' => '2026-09-07T12:00:00Z',
                        'ReasonComment' => "SPRING'27",
                        'VendorOrderReference' => 'MPG/PC/PDV/26/VII/333',
                        'CurrencyCode' => 'IDR',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/TOC_PurchRequisitions*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2608/00198',
                        'ItemNumber' => 'ITEM-001',
                        'ItemBatchNumber' => 'B1',
                        'LineNumber' => 1,
                        'LineDescription' => 'Test Fabric Line',
                        'PurchaseRequisitionId' => '',
                        'OrderedPurchaseQuantity' => 500.0,
                        'AllowedUnderdeliveryPercentage' => 3.0,
                        'AllowedOverdeliveryPercentage' => 3.0,
                        'PurchaseUnitSymbol' => 'YD',
                        'PurchasePrice' => 2.5,
                        'LineAmount' => 1250.0,
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/VendorsV2*' => Http::response([
                'value' => [
                    [
                        'VendorAccountNumber' => 'V0984',
                        'VendorOrganizationName' => 'PT Test Fabric Vendor',
                        'PrimaryEmailAddress' => 'contact@testfabric.com',
                        'PrimaryPhoneNumber' => '021-555-0000',
                        'AddressStreet' => 'Jl. Test No. 1',
                    ],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('d365:sync-orders', [
            '--company' => 'mpg',
            '--months' => 6,
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        // Vendor was auto-created, enriched from VendorsV2.
        $vendor = Vendor::where('vendor_code', 'V0984')->first();
        $this->assertNotNull($vendor);
        $this->assertEquals('PT Test Fabric Vendor', $vendor->name);
        $this->assertEquals('fabric', $vendor->type);
        $this->assertEquals('contact@testfabric.com', $vendor->contact_info['email'] ?? null);

        // Login account was created alongside it, default password.
        $user = User::where('vendor_id', $vendor->id)->where('role', 'fabric_vendor')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password', $user->password));

        // The PO that used to be silently dropped is now synced.
        $po = PurchaseOrder::where('po_number', 'MPG/PO/2608/00198')->first();
        $this->assertNotNull($po);
        $this->assertEquals($vendor->id, $po->vendor_id);
        $this->assertEquals('MPG/PC/PDV/26/VII/333', $po->reference);
        $this->assertCount(1, $po->items);
        $this->assertEquals('ITEM-001', $po->items->first()->item_number);
    }

    public function test_sync_falls_back_to_vendor_code_when_no_master_record()
    {
        $this->withoutExceptionHandling();

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2608/00300',
                        'OrderVendorAccountNumber' => 'V9999',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'PurchPoolId' => 'Fab-Local',
                        'dataAreaId' => 'mpg',
                        'CreatedDateTime1' => '2026-08-06T03:06:46Z',
                        'CurrencyCode' => 'IDR',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/TOC_PurchRequisitions*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/VendorsV2*' => Http::response(['value' => []], 200),
        ]);

        $exitCode = Artisan::call('d365:sync-orders', [
            '--company' => 'mpg',
            '--months' => 6,
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        $vendor = Vendor::where('vendor_code', 'V9999')->first();
        $this->assertNotNull($vendor);
        $this->assertEquals('V9999', $vendor->name);
    }

    public function test_sync_still_imports_pos_for_already_known_vendor()
    {
        $this->withoutExceptionHandling();

        $vendor = new Vendor([
            'name' => 'Existing Vendor',
            'vendor_code' => 'V0001',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2608/00301',
                        'OrderVendorAccountNumber' => 'V0001',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'PurchPoolId' => 'Fab-Local',
                        'dataAreaId' => 'mpg',
                        'CreatedDateTime1' => '2026-08-06T03:06:46Z',
                        'CurrencyCode' => 'IDR',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/TOC_PurchRequisitions*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response(['value' => []], 200),
        ]);

        $exitCode = Artisan::call('d365:sync-orders', [
            '--company' => 'mpg',
            '--months' => 6,
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        // No VendorsV2 call should have happened, no duplicate vendor created.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'VendorsV2'));
        $this->assertEquals(1, Vendor::where('vendor_code', 'V0001')->count());

        $po = PurchaseOrder::where('po_number', 'MPG/PO/2608/00301')->first();
        $this->assertNotNull($po);
        $this->assertEquals($vendor->id, $po->vendor_id);
    }

    /**
     * Regression case: MPG/PO/2608/01433 had its D365 line revised (qty 571 -> 720,
     * price 11261.26 -> 8558.56) after the first sync. firstOrCreate() used to make
     * the insert step a no-op for a line that already matched, so the local
     * quantity/price/tolerance froze at the original values forever while the PO
     * header's total_amount (recalculated every run) kept tracking D365. Verify the
     * line itself now gets refreshed too, without touching the locally-owned
     * status field.
     */
    public function test_sync_refreshes_revised_item_without_touching_local_status()
    {
        $this->withoutExceptionHandling();

        $vendor = new Vendor([
            'name' => 'Existing Vendor',
            'vendor_code' => 'V0795',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        $baseLine = [
            'PurchaseOrderNumber' => 'MPG/PO/2608/01433',
            'ItemNumber' => '2605000000599',
            'ItemBatchNumber' => 'Minimal Greta Vest Brown_Light - SPRING-27',
            'LineNumber' => 1,
            'LineDescription' => 'WOVEN 100% POLYESTER',
            'PurchaseRequisitionId' => '',
            'AllowedUnderdeliveryPercentage' => 7.0,
            'AllowedOverdeliveryPercentage' => 7.0,
            'PurchaseUnitSymbol' => 'YD',
        ];
        $lineAsFirstSynced = $baseLine + [
            'OrderedPurchaseQuantity' => 571.0,
            'PurchasePrice' => 11261.26,
            'LineAmount' => 6430179.46,
        ];
        $lineAsRevisedByD365 = $baseLine + [
            'OrderedPurchaseQuantity' => 720.0,
            'PurchasePrice' => 8558.56,
            'LineAmount' => 6162163.20,
        ];

        // Http::fake() stacks rather than replaces stubs registered for the same
        // URL pattern (the first-registered match wins), so the two sync runs'
        // differing PurchaseOrderLinesV2 responses must be a single sequence,
        // not two separate Http::fake() calls.
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2608/01433',
                        'OrderVendorAccountNumber' => 'V0795',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'PurchPoolId' => 'Fab-Local',
                        'dataAreaId' => 'mpg',
                        'CreatedDateTime1' => '2026-09-02T13:00:31Z',
                        'CurrencyCode' => 'IDR',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/TOC_PurchRequisitions*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::sequence()
                ->push(['value' => [$lineAsFirstSynced]], 200)
                ->push(['value' => [$lineAsRevisedByD365]], 200),
        ]);

        $this->assertEquals(0, Artisan::call('d365:sync-orders', ['--company' => 'mpg', '--months' => 6]));

        $item = PoItem::where('item_number', '2605000000599')->first();
        $this->assertNotNull($item);
        $this->assertEquals(571.00, $item->quantity);

        // Simulate the vendor/admin having since marked the (then seemingly
        // in-tolerance) item completed through the app's own roll-saving flow.
        $item->status = 'completed';
        $item->save();

        // D365 revises the line: quantity up, price down. The header total
        // legitimately changes too (571*11261.26 -> 720*8558.56).
        $this->assertEquals(0, Artisan::call('d365:sync-orders', ['--company' => 'mpg', '--months' => 6]));

        $this->assertEquals(1, PoItem::where('item_number', '2605000000599')->count(), 'Revised line must update the existing row, not insert a duplicate.');

        $item->refresh();
        $this->assertEquals(720.00, $item->quantity);
        $this->assertEquals(8558.56, $item->unit_price);
        $this->assertEquals(6162163.20, $item->total_price);
        // Locally-owned field: must survive the D365 refresh untouched.
        $this->assertEquals('completed', $item->status);

        $po = PurchaseOrder::where('po_number', 'MPG/PO/2608/01433')->first();
        $this->assertEquals(6162163.20, $po->total_amount);
    }
}
