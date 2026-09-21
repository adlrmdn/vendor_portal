<?php

namespace Tests\Feature;

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
}
