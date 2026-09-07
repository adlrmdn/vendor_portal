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

    public function test_sync_reassigns_vendor_id_when_d365_changes_it()
    {
        $this->withoutExceptionHandling();

        // The vendor D365 currently attributes the PO to.
        $correctVendor = new Vendor([
            'vendor_code' => 'V-CORRECT',
            'name' => 'Correct Vendor',
            'type' => 'subcon',
            'group' => 'subcon',
            'email' => 'correct@subcon.com',
            'phone' => '123456',
            'address' => 'Test Address',
            'city' => 'Test City',
            'is_active' => true,
        ]);
        $correctVendor->id = (string) \Illuminate\Support\Str::uuid();
        $correctVendor->save();

        // The (wrong) vendor the order was originally synced under.
        $staleVendor = new Vendor([
            'vendor_code' => 'V-STALE',
            'name' => 'Stale Vendor',
            'type' => 'subcon',
            'group' => 'subcon',
            'email' => 'stale@subcon.com',
            'phone' => '123456',
            'address' => 'Test Address',
            'city' => 'Test City',
            'is_active' => true,
        ]);
        $staleVendor->id = (string) \Illuminate\Support\Str::uuid();
        $staleVendor->save();

        $order = SubconOrder::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'order_number' => 'MPG/PO/2606/99999',
            'vendor_id' => $staleVendor->id,
            'status' => 'pending',
            'title' => 'Work Order MPG/PO/2606/99999',
            'order_date' => '2026-06-12',
            'workflow_stage' => SubconOrder::STAGE_CUTTING,
        ]);

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2606/99999',
                        'OrderVendorAccountNumber' => 'V-CORRECT',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'CreatedDateTime1' => '2026-06-12T00:00:00Z',
                        'RequestedDeliveryDate' => '2026-06-20T00:00:00Z',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/TOC_DT*' => Http::response(['value' => []], 200),
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('d365:sync-subcon-orders', [
            '--days' => 10,
            '--company' => 'mpg',
            '--vendor' => ['V-CORRECT'],
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".\Illuminate\Support\Facades\Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        $order->refresh();
        $this->assertEquals($correctVendor->id, $order->vendor_id);
    }

    /**
     * Regression test: previously, when D365 already had CMT-Cut job-transaction
     * data for a production group, the sync command stamped the local order's
     * cutting_approved_at/by directly ("ERP Sync") and jumped the workflow past
     * cutting_review — bypassing the mandatory fabric consumption/deduction calc
     * (SubconConsumptionService::persist(), see CLAUDE.md "cutting = calculate +
     * approve"). That left subcon_fabric_reconciliations permanently empty for
     * the order. The sync should only pull the cutting quantities into the local
     * reports and move the order into cutting_review for a human to approve.
     */
    public function test_sync_pulls_cutting_qty_without_spawning_approval()
    {
        $this->withoutExceptionHandling();

        $vendor = new Vendor([
            'vendor_code' => 'V-CUT',
            'name' => 'Cutting Test Vendor',
            'type' => 'subcon',
            'group' => 'subcon',
            'email' => 'cut@subcon.com',
            'phone' => '123456',
            'address' => 'Test Address',
            'city' => 'Test City',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'order_number' => 'MPG/PO/2607/00817',
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'title' => 'Work Order MPG/PO/2607/00817',
            'order_date' => '2026-06-12',
            'workflow_stage' => SubconOrder::STAGE_CUTTING,
        ]);

        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => 'MPG/PO/2607/00817',
            'PLMId' => 'PLM-CUT',
        ]);

        Schema::connection('vsm')->table('plm_activity', function ($table) {
            $table->string('ProductionGroup')->nullable();
            $table->string('ArticleCode')->nullable();
            $table->string('Brand')->nullable();
            $table->string('Colour')->nullable();
            $table->string('GroupName')->nullable();
            $table->string('PLMActivityStatus')->nullable();
            $table->string('Season')->nullable();
            $table->string('World')->nullable();
            $table->string('Department')->nullable();
            $table->string('Category')->nullable();
            $table->string('SubCategory')->nullable();
        });
        DB::connection('vsm')->table('plm_activity')->insert([
            'PLMId' => 'PLM-CUT',
            'ArticleName' => 'Test Garment Style',
            'ProductionGroup' => 'MPG/PRG/2604/000207',
        ]);

        Schema::connection('vsm')->create('production_group_lines', function ($table) {
            $table->string('ProductionGroup');
            $table->string('ProdId');
            $table->string('ItemId')->nullable();
            $table->string('Size')->nullable();
            $table->float('Qty')->nullable();
            $table->integer('LineNum')->nullable();
            $table->string('ProdStatus')->nullable();
            $table->string('InventSiteId')->nullable();
            $table->string('InventLocationId')->nullable();
            $table->string('SearchName')->nullable();
        });
        DB::connection('vsm')->table('production_group_lines')->insert([
            'ProductionGroup' => 'MPG/PRG/2604/000207',
            'ProdId' => 'MPG/PRD/2604/001123',
            'ItemId' => 'ITEM-001',
            'Size' => 'M',
            'Qty' => 1416,
            'LineNum' => 1,
        ]);

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'mock-token',
                'expires_in' => 3600,
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderHeadersV2*' => Http::response([
                'value' => [
                    [
                        'PurchaseOrderNumber' => 'MPG/PO/2607/00817',
                        'OrderVendorAccountNumber' => 'V-CUT',
                        'DocumentApprovalStatus' => 'Confirmed',
                        'CreatedDateTime1' => '2026-06-12T00:00:00Z',
                        'RequestedDeliveryDate' => '2026-06-20T00:00:00Z',
                    ],
                ],
            ], 200),
            'https://test.dynamics.com/data/PurchaseOrderLinesV2*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/TOC_DT*' => Http::response(['value' => []], 200),
            'https://test.dynamics.com/data/JobTransactionHeaders*' => Http::response([
                'value' => [
                    ['Operation' => 'CMT-Cut', 'JobTransactionId' => 'CUT-1'],
                ],
            ], 200),
            'https://test.dynamics.com/data/JobTransactionLinesDetails*' => Http::response([
                'value' => [
                    ['Size' => 'M', 'Jam7' => 1416],
                ],
            ], 200),
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('d365:sync-subcon-orders', [
            '--days' => 10,
            '--company' => 'mpg',
            '--vendor' => ['V-CUT'],
        ]);

        if ($exitCode !== 0) {
            echo "ARTISAN OUTPUT:\n".\Illuminate\Support\Facades\Artisan::output()."\n";
        }
        $this->assertEquals(0, $exitCode);

        $order->refresh();

        // Cutting quantity pulled in from D365 ...
        $report = \App\Models\SubconCuttingReport::where('order_id', $order->id)
            ->where('prod_id', 'MPG/PRD/2604/001123')
            ->first();
        $this->assertNotNull($report);
        $this->assertEquals(1416, (int) $report->cutting_qty);

        // ... but the sync never pushes the order into the approval queue itself
        // — only the vendor's actual in-portal submit does that (and runs the
        // mandatory consumption/deduction calc along with it). The order stays
        // right where the vendor left it, untouched.
        $this->assertEquals(SubconOrder::STAGE_CUTTING, $order->workflow_stage);
        $this->assertNull($order->cutting_approved_at);
        $this->assertNull($order->cutting_approved_by);
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
