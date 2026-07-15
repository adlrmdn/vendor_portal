<?php

namespace Tests\Feature;

use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use Tests\TestCase;

class SubconCuttingReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing records from previous runs to ensure isolation
        SubconCuttingReport::query()->delete();
        SubconOrder::query()->delete();
        User::query()->where('email', 'like', '%@test.com')->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_TEST%')->delete();

        // Mock D365JobTransactionService to prevent actual UAT sandbox and port 8072 HTTP requests
        $mockD365Service = $this->mock(\App\Services\D365JobTransactionService::class);
        $mockD365Service->shouldReceive('startJobs')->andReturn(true)->byDefault();
        $mockD365Service->shouldReceive('triggerJobs')->andReturn(true)->byDefault();
        $mockD365Service->shouldReceive('syncReportToD365')->andReturn([
            'odata_cut_updates' => 1,
            'odata_pak_updates' => 1,
            'trigger_cut' => true,
            'trigger_pak' => true,
            'errors' => [],
        ])->byDefault();
    }

    public function test_subcon_vendor_can_save_cutting_report()
    {
        // 1. Create a subcon vendor
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        // 2. Create a subcon vendor user
        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        // 3. Create a subcon order
        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-123',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        // 4. Submit cutting report data
        $payload = [
            'reports' => [
                [
                    'prod_id' => 'PROD-001',
                    'size' => 'M',
                    'cutting_qty' => 120,
                    'gramasi' => 180.50,
                ],
                [
                    'prod_id' => 'PROD-002',
                    'size' => 'L',
                    'cutting_qty' => 150,
                    'gramasi' => 180.50,
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.submit-cutting', $order->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify database records
        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'size' => 'M',
            'cutting_qty' => 120,
            'gramasi' => 180.50,
        ]);

        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-002',
            'size' => 'L',
            'cutting_qty' => 150,
            'gramasi' => 180.50,
        ]);
    }

    public function test_subcon_vendor_can_download_template()
    {
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_TMP',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_tmp@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO/TEST/456',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO 2',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        $response = $this->actingAs($user)
            ->get(route('subcon.vendor.orders.download-template', $order->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=cutting_report_PO-TEST-456.xlsx');
    }

    public function test_subcon_vendor_can_upload_excel_report_and_merge_independently()
    {
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_EXCEL',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_excel@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-789',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO 3',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        // Mock production lines
        $productionMock = $this->mock(\App\Services\SubconProductionService::class);
        $productionMock->shouldReceive('forPo')
            ->with($order->order_number)
            ->andReturn([
                [
                    'plm_id' => 'PLM-1',
                    'production_group' => 'PRG-1',
                    'article_code' => 'ART-1',
                    'article_name' => 'Article 1',
                    'brand' => 'Brand 1',
                    'colour' => 'Red',
                    'group_name' => 'Group 1',
                    'plm_status' => 'Released',
                    'total_qty' => 100.0,
                    'lines' => [
                        (object) [
                            'ProdId' => 'PROD-001',
                            'Size' => 'M',
                            'Qty' => 100,
                            'ProdStatus' => 'Released',
                            'InventSiteId' => 'SITE1',
                            'InventLocationId' => 'LOC1',
                        ],
                    ],
                ],
            ]);

        // Generate test Excel file on disk using mock Array exporter
        $firstExcelData = [
            ['Production ID', 'Size', 'Qty Cut', 'Gramasi (g)'],
            ['PROD-001', 'M', 90, 180.00],
        ];

        $firstExport = new class($firstExcelData) implements \Maatwebsite\Excel\Concerns\FromArray
        {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        };

        \Maatwebsite\Excel\Facades\Excel::store($firstExport, 'temp_excel_test.xlsx', 'local');

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path('temp_excel_test.xlsx');
        $file = new \Illuminate\Http\UploadedFile(
            $fullPath,
            'temp_excel_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Upload Excel with the values (cutting_qty = 90, gramasi = 180.00)
        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.upload-report', $order->id), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'cutting_qty' => 90,
            'gramasi' => 180.00,
        ]);

        // Now test independent/merge behavior.
        // Let's create another Excel file where we only supply gramasi (let's set cutting_qty to blank).
        $customExcelData = [
            ['Production ID', 'Size', 'Qty Cut', 'Gramasi (g)'],
            ['PROD-001', 'M', '', 195.50],
        ];

        // Store standard array using custom class
        $customExport = new class($customExcelData) implements \Maatwebsite\Excel\Concerns\FromArray
        {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        };

        \Maatwebsite\Excel\Facades\Excel::store($customExport, 'temp_custom_excel.xlsx', 'local');
        $customPath = \Illuminate\Support\Facades\Storage::disk('local')->path('temp_custom_excel.xlsx');
        $customFile = new \Illuminate\Http\UploadedFile(
            $customPath,
            'temp_custom_excel.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Upload custom excel
        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.upload-report', $order->id), [
                'file' => $customFile,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The gramasi should update to 195.50, but cutting_qty should REMAIN 90 (complement each other)!
        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'cutting_qty' => 90,
            'gramasi' => 195.50,
        ]);
    }

    public function test_subcon_vendor_can_upload_excel_using_only_size_fallback()
    {
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_SIZE',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_size@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-555',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Fallback',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        $productionMock = $this->mock(\App\Services\SubconProductionService::class);
        $productionMock->shouldReceive('forPo')
            ->with($order->order_number)
            ->andReturn([
                [
                    'plm_id' => 'PLM-1',
                    'production_group' => 'PRG-1',
                    'article_code' => 'ART-1',
                    'article_name' => 'Article 1',
                    'brand' => 'Brand 1',
                    'colour' => 'Red',
                    'group_name' => 'Group 1',
                    'plm_status' => 'Released',
                    'total_qty' => 100.0,
                    'lines' => [
                        (object) [
                            'ProdId' => 'PROD-001',
                            'Size' => 'M',
                            'Qty' => 100,
                            'ProdStatus' => 'Released',
                            'InventSiteId' => 'SITE1',
                            'InventLocationId' => 'LOC1',
                        ],
                    ],
                ],
            ]);

        // Excel file without Production ID column (testing header search and size matching fallback)
        $excelData = [
            ['Size', 'Qty Cut', 'Gramasi (g)'],
            ['M', 115, 178.50],
        ];

        $export = new class($excelData) implements \Maatwebsite\Excel\Concerns\FromArray
        {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        };

        \Maatwebsite\Excel\Facades\Excel::store($export, 'temp_size_fallback.xlsx', 'local');
        $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path('temp_size_fallback.xlsx');
        $file = new \Illuminate\Http\UploadedFile(
            $filePath,
            'temp_size_fallback.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.upload-report', $order->id), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'size' => 'M',
            'cutting_qty' => 115,
            'gramasi' => 178.50,
        ]);
    }

    public function test_subcon_vendor_can_upload_pdf_report_and_parse_with_heuristics()
    {
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_PDF',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_pdf@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-999',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO 4',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        // Mock production lines
        $productionMock = $this->mock(\App\Services\SubconProductionService::class);
        $productionMock->shouldReceive('forPo')
            ->with($order->order_number)
            ->andReturn([
                [
                    'plm_id' => 'PLM-1',
                    'production_group' => 'PRG-1',
                    'article_code' => 'ART-1',
                    'article_name' => 'Article 1',
                    'brand' => 'Brand 1',
                    'colour' => 'Red',
                    'group_name' => 'Group 1',
                    'plm_status' => 'Released',
                    'total_qty' => 100.0,
                    'lines' => [
                        (object) [
                            'ProdId' => 'PROD-001',
                            'Size' => 'M',
                            'Qty' => 100,
                            'ProdStatus' => 'Released',
                            'InventSiteId' => 'SITE1',
                            'InventLocationId' => 'LOC1',
                        ],
                    ],
                ],
            ]);

        // Mock PDF Parser
        $pdfDocumentMock = $this->mock(\Smalot\PdfParser\Document::class);
        $pdfDocumentMock->shouldReceive('getText')
            ->andReturn("Some random PDF header text\nPROD-001 Qty Cut: 85 Gramasi: 175.50\nFooter text");

        $pdfParserMock = $this->mock(\Smalot\PdfParser\Parser::class);
        $pdfParserMock->shouldReceive('parseFile')
            ->andReturn($pdfDocumentMock);

        $this->app->instance(\Smalot\PdfParser\Parser::class, $pdfParserMock);

        $file = \Illuminate\Http\UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.upload-report', $order->id), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'cutting_qty' => 85,
            'gramasi' => 175.50,
        ]);
    }

    public function test_subcon_vendor_job_trans_status_sequence()
    {
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_SEQ',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_seq@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-SEQ',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Seq',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        // Mock production lines
        $productionMock = $this->mock(\App\Services\SubconProductionService::class);
        $productionMock->shouldReceive('forPo')
            ->with($order->order_number)
            ->andReturn([
                [
                    'plm_id' => 'PLM-1',
                    'production_group' => 'PRG-1',
                    'article_code' => 'ART-1',
                    'article_name' => 'Article 1',
                    'brand' => 'Brand 1',
                    'colour' => 'Red',
                    'group_name' => 'Group 1',
                    'plm_status' => 'Released',
                    'total_qty' => 100.0,
                    'lines' => [
                        (object) [
                            'ProdId' => 'PROD-001',
                            'Size' => 'M',
                            'Qty' => 100,
                            'ProdStatus' => 'Released',
                            'InventSiteId' => 'SITE1',
                            'InventLocationId' => 'LOC1',
                        ],
                    ],
                ],
            ]);

        // We expect startJobs to be called.
        $d365Mock = $this->mock(\App\Services\D365JobTransactionService::class);
        $d365Mock->shouldReceive('startJobs')->with('PRG-1')->zeroOrMoreTimes()->andReturn(true);
        $d365Mock->shouldReceive('syncReportToD365')->zeroOrMoreTimes()->andReturn([
            'odata_cut_updates' => 1,
            'odata_pak_updates' => 1,
            'trigger_cut' => true,
            'trigger_pak' => true,
            'errors' => [],
        ]);

        $this->assertEquals('not_saved', $order->fresh()->job_trans_status);

        // 1. Save first time
        $payload1 = [
            'reports' => [
                [
                    'prod_id' => 'PROD-001',
                    'size' => 'M',
                    'cutting_qty' => 120,
                    'gramasi' => null,
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.submit-cutting', $order->id), $payload1);

        $response->assertRedirect();
        $this->assertEquals('first_saved', $order->fresh()->job_trans_status);

        // 2. Subsequent save with cutting qty only
        $payload2 = [
            'reports' => [
                [
                    'prod_id' => 'PROD-001',
                    'size' => 'M',
                    'cutting_qty' => 150,
                    'gramasi' => null,
                ],
            ],
        ];

        $response2 = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.submit-cutting', $order->id), $payload2);

        $response2->assertRedirect();
        $this->assertEquals('qty_cutting_saved', $order->fresh()->job_trans_status);
    }

    public function test_subcon_vendor_can_print_packaging_labels_successfully()
    {
        // 1. Create a subcon vendor
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_PRINT',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        // 2. Create a subcon vendor user
        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_print@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        // 3. Create a subcon order with distribution_id
        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-PRINT-123',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Print',
            'status' => 'pending',
            'order_date' => now(),
            'distribution_id' => 'DST-TEST-999',
            'workflow_stage' => 'labels',
        ]);

        // 4. Create local cutting report with gramasi
        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-001',
            'size' => 'M',
            'cutting_qty' => 10,
            'gramasi' => 150.00,
        ]);

        // 5. Mock D365 OData fetches
        $this->mock(\App\Services\D365JobTransactionService::class, function ($mock) {
            $mock->shouldReceive('fetchPackingInstructionGroups')
                ->andReturn([
                    [
                        'packing_code' => 'PACK-001',
                        'store_id' => '11242',
                        'store_name' => 'SHOWROOM MN SUPERMAL KARAWACI',
                        'status' => 'Normal',
                        'page_count' => 1,
                        'total_qty' => 20,
                        'total_weight' => 3.0,
                        'items' => [
                            [
                                'size' => 'M',
                                'qty' => 20,
                                'variant_id' => '2508000000076',
                                'item_code' => 'ITEM-001',
                                'gramasi_real' => 0.150,
                            ]
                        ]
                    ]
                ]);

            $mock->shouldReceive('fetchWarehouses')
                ->with(['11242'])
                ->andReturn([
                    '11242' => [
                        'WarehouseId' => '11242',
                        'WarehouseName' => 'SHOWROOM MN SUPERMAL KARAWACI',
                        'PrimaryAddressStreet' => 'Jl. Boulevard Diponegoro, Kelapa Dua',
                        'PrimaryAddressZipCode' => '15810',
                        'PrimaryAddressCountryRegionId' => 'IDN',
                        'PrimaryAddressCity' => 'Tangerang',
                        'PrimaryAddressStateId' => 'BANTEN',
                    ],
                ]);
        });

        // 6. Act
        $response = $this->actingAs($user)
            ->get(route('subcon.vendor.orders.print-labels', $order->id));

        // 7. Assert PDF output (response should be successful and stream a PDF file)
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_subcon_admin_can_print_packaging_labels_successfully()
    {
        // 1. Create an admin user
        $admin = new User([
            'name' => 'Admin User',
            'email' => 'admin_print@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        // 2. Create subcon order with distribution_id
        $vendor = new Vendor([
            'name' => 'Test Vendor',
            'vendor_code' => 'V_TEST_PRINT_AD',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-PRINT-456',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Print Admin',
            'status' => 'pending',
            'order_date' => now(),
            'distribution_id' => 'DST-TEST-888',
            'workflow_stage' => 'labels',
        ]);

        // 3. Mock D365 OData fetches
        $this->mock(\App\Services\D365JobTransactionService::class, function ($mock) {
            $mock->shouldReceive('fetchPackingInstructionGroups')
                ->andReturn([
                    [
                        'packing_code' => 'PACK-002',
                        'store_id' => '11242',
                        'store_name' => 'SHOWROOM MN SUPERMAL KARAWACI',
                        'status' => 'Normal',
                        'page_count' => 1,
                        'total_qty' => 5,
                        'total_weight' => 0.75,
                        'items' => [
                            [
                                'size' => 'S',
                                'qty' => 5,
                                'variant_id' => 'ITEM-002-S',
                                'item_code' => 'ITEM-002',
                                'gramasi_real' => 0.150,
                            ]
                        ]
                    ]
                ]);

            $mock->shouldReceive('fetchWarehouses')
                ->with(['11242'])
                ->andReturn([]);
        });

        // 4. Act
        $response = $this->actingAs($admin)
            ->get(route('subcon.admin.orders.print-labels', $order->id));

        // 5. Assert
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_packaging_labels_redirects_back_if_distribution_id_missing()
    {
        // 1. Create vendor and user
        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_MISSING',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor_missing@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        // 2. Create subcon order WITHOUT distribution_id
        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-PRINT-MISSING',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Print Missing',
            'status' => 'pending',
            'order_date' => now(),
            'distribution_id' => null, // empty
            'workflow_stage' => 'labels',
        ]);

        // 3. Act
        $response = $this->actingAs($user)
            ->from(route('subcon.vendor.orders.view', $order->id))
            ->get(route('subcon.vendor.orders.print-labels', $order->id));

        // 4. Assert redirect back with error
        $response->assertRedirect(route('subcon.vendor.orders.view', $order->id));
        $response->assertSessionHas('error', 'This order does not have a Distribution ID associated.');
    }

    public function test_generate_labels_signed_success()
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:8071/process' => \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => ['pi' => 'MPR/PI/12345', 'generated' => true, 'auto_scan' => 'success']], 200),
        ]);

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_GEN',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-GEN-123',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Gen',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => 'MPR/DST/2603/06363',
            'workflow_stage' => 'waiting_distribution',
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('subcon.generate-labels', ['order' => $order->id], absolute: false);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', true);
        $response->assertSee('Packing labels generated successfully');

        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_LABELS, $order->workflow_stage);
    }

    public function test_generate_labels_signed_api_failure_in_production()
    {
        $originalEnv = config('app.env');
        config(['app.env' => 'production']);

        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:8071/process' => \Illuminate\Support\Facades\Http::response(['success' => false, 'message' => 'Failed to process DTT'], 400),
        ]);

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_GEN_FAIL',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-GEN-FAIL',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Gen Fail',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => 'MPR/DST/2603/06363',
            'workflow_stage' => 'waiting_distribution',
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('subcon.generate-labels', ['order' => $order->id], absolute: false);

        $response = $this->get($url);

        config(['app.env' => $originalEnv]);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', false);
        $response->assertSee('Failed to generate labels via background API call');

        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_WAITING_DISTRIBUTION, $order->workflow_stage);
    }

    public function test_generate_labels_signed_api_failure_in_local_bypass()
    {
        $originalEnv = config('app.env');
        config(['app.env' => 'local']);

        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:8071/process' => \Illuminate\Support\Facades\Http::response(['success' => false, 'message' => 'Connection timeout'], 504),
        ]);

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_GEN_BYPASS',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-GEN-BYPASS',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Gen Bypass',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => 'MPR/DST/2603/06363',
            'workflow_stage' => 'waiting_distribution',
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('subcon.generate-labels', ['order' => $order->id], absolute: false);

        $response = $this->get($url);

        config(['app.env' => $originalEnv]);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', true);
        $response->assertSee('Packing labels generated successfully');

        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_LABELS, $order->workflow_stage);
    }

    public function test_generate_labels_signed_dynamic_resolution()
    {
        // Redirect 'vsm' connection to an in-memory SQLite database for testing.
        config(['database.connections.vsm' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        // Create VSM schemas needed for lookup.
        \Illuminate\Support\Facades\Schema::connection('vsm')->create('po_lines', function ($table) {
            $table->string('PurchaseOrderNumber');
            $table->string('PLMId');
        });

        \Illuminate\Support\Facades\Schema::connection('vsm')->create('plm_trans', function ($table) {
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

        // Seed VSM databases.
        \Illuminate\Support\Facades\DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => 'PO-TEST-DYNAMIC-DST',
            'PLMId' => 'PLM-999',
        ]);

        \Illuminate\Support\Facades\DB::connection('vsm')->table('plm_trans')->insert([
            'PLMId' => 'PLM-999',
            'ActivityName' => 'SO Intercompany',
            'ActivityNo' => 'SO-100200',
        ]);

        // Fake HTTP responses for D365 token, TOC_DT, and label generator API
        \Illuminate\Support\Facades\Http::fake([
            'https://login.microsoftonline.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'mock-token', 'expires_in' => 3600], 200),
            'https://test.dynamics.com/data/TOC_DT*' => \Illuminate\Support\Facades\Http::response(['value' => [['DistributionID' => 'RESOLVED-DST-777']]], 200),
            'http://localhost:8071/process' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_GEN_DYN',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-DYNAMIC-DST',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Gen Dyn',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => null, // empty, needs resolution
            'workflow_stage' => 'waiting_distribution',
        ]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('subcon.generate-labels', ['order' => $order->id], absolute: false);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', true);
        $response->assertSee('Packing labels generated successfully');

        $order->refresh();
        $this->assertEquals('RESOLVED-DST-777', $order->distribution_id);
        $this->assertEquals(SubconOrder::STAGE_LABELS, $order->workflow_stage);
    }

    public function test_admin_can_view_orders_waiting_distribution()
    {
        $admin = new User([
            'name' => 'Admin User',
            'email' => 'admin_waiting@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_WAITING',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-WAITING-123',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Waiting',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => 'MPR/DST/2603/06363',
            'workflow_stage' => 'waiting_distribution',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('subcon.admin.orders-waiting-distribution'));

        $response->assertStatus(200);
        $response->assertViewIs('subcon.admin.waiting-distribution');
        $response->assertSee('PO-TEST-WAITING-123');
    }

    public function test_admin_can_trigger_label_generation_manual_success()
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:8071/process' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);

        $admin = new User([
            'name' => 'Admin User',
            'email' => 'admin_manual@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        $vendor = new Vendor([
            'name' => 'Test Subcon Vendor',
            'vendor_code' => 'V_TEST_MANUAL',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-TEST-MANUAL-123',
            'vendor_id' => $vendor->id,
            'title' => 'Test CMT PO Manual',
            'status' => 'in_progress',
            'order_date' => now(),
            'distribution_id' => 'MPR/DST/2603/06363',
            'workflow_stage' => 'waiting_distribution',
        ]);

        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->from(route('subcon.admin.orders-waiting-distribution'))
            ->post(route('subcon.admin.orders.generate-labels-manual', $order->id));

        $response->assertRedirect(route('subcon.admin.orders-waiting-distribution'));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_LABELS, $order->workflow_stage);
    }

    public function test_subcon_vendor_can_view_profile()
    {
        $vendor = new Vendor([
            'name' => 'Test Profile Vendor',
            'vendor_code' => 'V_TEST_PROF1',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Profile User',
            'email' => 'profile@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $response = $this->actingAs($user)
            ->get(route('subcon.vendor.profile'));

        $response->assertStatus(200);
        $response->assertViewIs('subcon.vendor.profile');
        $response->assertSee('Profile Settings');
    }

    public function test_subcon_vendor_can_update_email_contact()
    {
        $vendor = new Vendor([
            'name' => 'Test Profile Vendor',
            'vendor_code' => 'V_TEST_PROF2',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Profile User',
            'email' => 'profile2@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('subcon.vendor.profile.update'), [
                'email' => 'updated_contact@vendor.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertEquals('updated_contact@vendor.com', $vendor->contact_info['email']);
    }

    public function test_subcon_vendor_can_update_password()
    {
        $vendor = new Vendor([
            'name' => 'Test Profile Vendor',
            'vendor_code' => 'V_TEST_PROF3',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Profile User',
            'email' => 'profile3@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        // Success password change
        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('subcon.vendor.profile.password'), [
                'current_password' => 'password',
                'new_password' => 'new_password123',
                'new_password_confirmation' => 'new_password123',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new_password123', $user->fresh()->password));

        // Failure password change (wrong current password)
        $response2 = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('subcon.vendor.profile.password'), [
                'current_password' => 'wrong_password',
                'new_password' => 'new_password456',
                'new_password_confirmation' => 'new_password456',
            ]);

        $response2->assertRedirect();
        $response2->assertSessionHasErrors('current_password');
    }

    public function test_subcon_admin_can_view_and_update_workflow_settings()
    {
        $admin = new User([
            'name' => 'Subcon Admin User',
            'email' => 'subadmin_wf@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        $response = $this->actingAs($admin)
            ->get(route('subcon.admin.workflow'));

        $response->assertStatus(200);
        $response->assertViewIs('subcon.admin.workflow');

        $response2 = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.workflow.update'), [
                'subcon_cutting_approver_email' => 'cut@test.com',
                'subcon_gramasi_approver_email' => 'gram@test.com',
                'subcon_label_generator_email' => 'label@test.com',
                'subcon_notification_email' => 'fallback@test.com',
            ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('success');

        $this->assertEquals('cut@test.com', \App\Models\Setting::getValue('subcon_cutting_approver_email'));
        $this->assertEquals('gram@test.com', \App\Models\Setting::getValue('subcon_gramasi_approver_email'));
        $this->assertEquals('label@test.com', \App\Models\Setting::getValue('subcon_label_generator_email'));
        $this->assertEquals('fallback@test.com', \App\Models\Setting::getValue('subcon_notification_email'));
    }

    public function test_admin_can_manage_vendors()
    {
        $admin = new User([
            'name' => 'Subcon Admin User',
            'email' => 'subadmin_vend@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        $vendor = new Vendor([
            'name' => 'Test Vendor Manage',
            'vendor_code' => 'V_TEST_MNG',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        // 1. Edit vendor
        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('subcon.admin.vendors.update', $vendor->id), [
                'name' => 'Test Vendor Manage Edited',
                'vendor_code' => 'V_TEST_MNG',
                'group' => 'Edited Group',
                'contact_info' => [
                    'phone' => '12345',
                    'email' => 'edited@vendor.com',
                    'address' => 'Edited Address',
                ],
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('subcon.admin.vendors'));
        $response->assertSessionHas('success');
        $this->assertEquals('Test Vendor Manage Edited', $vendor->fresh()->name);
        $this->assertEquals('Edited Group', $vendor->fresh()->group);
        $this->assertEquals('edited@vendor.com', $vendor->fresh()->contact_info['email']);

        // 2. Toggle status
        $response2 = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.vendors.toggle-status', $vendor->id));

        $response2->assertRedirect(route('subcon.admin.vendors'));
        $response2->assertSessionHas('success');
        $this->assertFalse($vendor->fresh()->is_active);

        // 3. Delete vendor (and check user cascade deletion)
        $user = new User([
            'name' => 'Vendor Login User',
            'email' => 'login_vend@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $response3 = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->delete(route('subcon.admin.vendors.destroy', $vendor->id));

        $response3->assertRedirect(route('subcon.admin.vendors'));
        $response3->assertSessionHas('success');
        $this->assertNull(Vendor::find($vendor->id));
        $this->assertNull(User::find($user->id));
    }

    public function test_workflow_email_routing_destinations()
    {
        // Setup settings
        \App\Models\Setting::updateOrCreate(['key' => 'subcon_cutting_approver_email'], ['value' => 'cut_appr@test.com', 'group' => 'subcon', 'type' => 'string']);
        \App\Models\Setting::updateOrCreate(['key' => 'subcon_gramasi_approver_email'], ['value' => 'gram_appr@test.com', 'group' => 'subcon', 'type' => 'string']);
        \App\Models\Setting::updateOrCreate(['key' => 'subcon_label_generator_email'], ['value' => 'label_gen@test.com', 'group' => 'subcon', 'type' => 'string']);

        $vendor = new Vendor([
            'name' => 'Routing Test Vendor',
            'vendor_code' => 'V_TEST_ROUTE',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) \Illuminate\Support\Str::uuid();
        $vendor->save();

        $user = new User([
            'name' => 'Routing User',
            'email' => 'route_vend@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        $order = SubconOrder::create([
            'order_number' => 'PO-ROUTE-123',
            'vendor_id' => $vendor->id,
            'title' => 'Routing PO',
            'status' => 'pending',
            'order_date' => now(),
            'workflow_stage' => 'cutting',
        ]);

        \Illuminate\Support\Facades\Mail::fake();

        // 1. Submit cutting -> routes to subcon_cutting_approver_email
        $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.submit-cutting', $order->id), [
                'reports' => [
                    [
                        'prod_id' => 'PROD-R1',
                        'size' => 'S',
                        'cutting_qty' => 10,
                    ],
                ],
            ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SubconApprovalRequestMailable::class, function ($mail) {
            return $mail->hasTo('cut_appr@test.com') && $mail->gate === 'cutting';
        });

        // 2. Submit gramasi -> routes to subcon_gramasi_approver_email
        $order->workflow_stage = 'gramasi';
        $order->save();

        $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.vendor.orders.submit-gramasi', $order->id), [
                'blister_capacity' => 100,
                'reports' => [
                    [
                        'prod_id' => 'PROD-R1',
                        'size' => 'S',
                        'gramasi' => 180.50,
                    ],
                ],
            ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SubconApprovalRequestMailable::class, function ($mail) {
            return $mail->hasTo('gram_appr@test.com') && $mail->gate === 'gramasi';
        });

        // 3. Admin approves gramasi -> routes label generation to subcon_label_generator_email
        $order->workflow_stage = 'gramasi_review';
        $order->save();

        $admin = new User([
            'name' => 'Subcon Admin User',
            'email' => 'subadmin_route@test.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $admin->id = (string) \Illuminate\Support\Str::uuid();
        $admin->save();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.orders.approve', $order->id), [
                'gate' => 'gramasi',
            ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SubconStageStatusMailable::class, function ($mail) {
            return $mail->hasTo('label_gen@test.com') && $mail->gate === 'gramasi' && $mail->outcome === 'approved';
        });
    }
}
