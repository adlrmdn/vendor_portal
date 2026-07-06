<?php

namespace Tests\Feature;

use App\Models\SubconOrder;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncD365SubconOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Redirect 'vsm' connection to an in-memory SQLite database for testing.
        config(['database.connections.vsm' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        // Create VSM schemas needed for lookup.
        Schema::connection('vsm')->create('po_lines', function ($table) {
            $table->string('PurchaseOrderNumber');
            $table->string('PLMId');
        });

        Schema::connection('vsm')->create('plm_activity', function ($table) {
            $table->string('PLMId');
            $table->string('ArticleName');
        });

        Schema::connection('vsm')->create('plm_trans', function ($table) {
            $table->string('PLMId');
            $table->string('ActivityName');
            $table->string('ActivityNo');
        });

        // Set up client D365 configurations for authentication mock.
        config([
            'services.d365.tenant_id' => 'test-tenant',
            'services.d365.client_id' => 'test-client',
            'services.d365.client_secret' => 'test-secret',
            'services.d365.resource' => 'https://test.dynamics.com',
        ]);
    }

    public function test_sync_subcon_order_with_distribution_id()
    {
        $this->withoutExceptionHandling();

        // 1. Arrange: Create subcon vendor in local db
        $vendor = new Vendor([
            'vendor_code' => 'V-TEST',
            'name' => 'Test Subcon Vendor',
            'type' => 'subcon',
            'group' => 'subcon',
            'email' => 'test@subcon.com',
            'phone' => '123456',
            'address' => 'Test Address',
            'city' => 'Test City',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        // 2. Seed VSM databases.
        // Map MPG/PO/2605/12345 -> PLM-123 -> MPG/SO/2604/00185
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => 'MPG/PO/2605/12345',
            'PLMId' => 'PLM-123',
        ]);

        DB::connection('vsm')->table('plm_activity')->insert([
            'PLMId' => 'PLM-123',
            'ArticleName' => 'Test Garment Style',
        ]);

        DB::connection('vsm')->table('plm_trans')->insert([
            'PLMId' => 'PLM-123',
            'ActivityName' => 'SO Intercompany',
            'ActivityNo' => 'MPG/SO/2604/00185',
        ]);

        // 3. Mock HTTP requests to Dynamics 365
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2605/12345',
                        'OrderVendorAccountNumber' => 'V-TEST',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'CreatedDateTime1' => '2026-06-12T00:00:00Z',
                        'RequestedDeliveryDate' => '2026-06-20T00:00:00Z',
                        'ReasonComment' => 'Sync Test Notes',
                        'VendorOrderReference' => 'Test Reference',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2605/12345',
                        'ItemNumber' => 'ITEM-001',
                        'LineDescription' => 'CMT Service Item 1',
                        'OrderedPurchaseQuantity' => 100.0,
                        'PurchaseUnitSymbol' => 'PCS',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/TOC_DT*' => Http::response([
                'value' => [
                    [
                        'SOID' => 'MPG/SO/2604/00185',
                        'DistributionID' => 'MPR/DST/2603/06363',
                        'dataAreaId' => 'mpr',
                    ],
                ],
            ], 200),
        ]);

        // 4. Act: Run the Artisan command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('d365:sync-subcon-orders', [
            '--days' => 10,
            '--company' => 'mpg',
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".\Illuminate\Support\Facades\Artisan::output()."\n";
        }

        $this->assertEquals(0, $exitCode);

        // 5. Assert: Verify order was created with the resolved DistributionID
        $order = SubconOrder::where('order_number', 'MPG/PO/2605/12345')->first();
        $this->assertNotNull($order);
        $this->assertEquals($vendor->id, $order->vendor_id);
        $this->assertEquals('MPR/DST/2603/06363', $order->distribution_id);
        $this->assertEquals('Sync Test Notes', $order->notes);

        // Verify items were synced
        $this->assertCount(1, $order->items);
        $this->assertEquals('ITEM-001', $order->items->first()->item_number);
        $this->assertEquals(100.0, (float) $order->items->first()->quantity);
    }

    public function test_sync_subcon_vendors()
    {
        // 1. Arrange: Create an old dummy subcon vendor and associated user
        $oldVendor = new Vendor([
            'vendor_code' => 'OLD-DUMMY',
            'name' => 'Old Dummy Vendor',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $oldVendor->id = (string) \Illuminate\Support\Str::uuid();
        $oldVendor->save();

        $oldUser = new \App\Models\User([
            'name' => 'Old Dummy User',
            'email' => 'vendor@olddummy.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $oldVendor->id,
        ]);
        $oldUser->id = (string) \Illuminate\Support\Str::uuid();
        $oldUser->save();

        // Mock HTTP requests for D365 token and VendorsV2 query
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/VendorsV2*' => Http::response([
                'value' => [
                    [
                        'VendorAccountNumber' => 'V0054',
                        'VendorOrganizationName' => 'PT SEMPURNA JAYA MAKMUR MULIA',
                        'VendorSearchName' => 'PT SEMPURNA JAYA MAK',
                        'PrimaryPhoneNumber' => '(021) 87901758',
                        'PrimaryEmailAddress' => 'stefaneztan@sempurnajmm.com',
                        'AddressStreet' => 'JL. Raya Bogor Cibinong',
                        'VendorGroupId' => 'subcon',
                    ],
                ],
            ], 200),
        ]);

        // 2. Act: Run the Artisan command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('d365:sync-subcon-vendors');

        // 3. Assert
        $this->assertEquals(0, $exitCode);

        // Verify old dummy vendor and user are gone
        $this->assertNull(Vendor::where('vendor_code', 'OLD-DUMMY')->first());
        $this->assertNull(\App\Models\User::where('email', 'vendor@olddummy.com')->first());

        // Verify new vendor is registered
        $newVendor = Vendor::where('vendor_code', 'V0054')->first();
        $this->assertNotNull($newVendor);
        $this->assertEquals('PT SEMPURNA JAYA MAKMUR MULIA', $newVendor->name);
        $this->assertEquals('subcon', $newVendor->type);

        // Verify new user login is registered with the correct slugified email pattern
        $newUser = \App\Models\User::where('vendor_id', $newVendor->id)->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('vendor@sempurnajmm.com', $newUser->email);
        $this->assertEquals('subcon_vendor', $newUser->role);
    }
}
