<?php

namespace Tests\Feature;

use App\Models\MaterialReturnLine;
use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SubconProductionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorLocalizationAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendorUser;
    private Vendor $vendor;
    private SubconOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Configure in-memory SQLite connection for 'vsm'
        config(['database.connections.vsm' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        // Create the app-owned VSM tables for testing
        Schema::connection('vsm')->create('subcon_daily_production_styles', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection('vsm')->create('subcon_daily_production_entries', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->string('stage')->index();
            $table->string('ratio_label')->default('');
            $table->string('prod_id');
            $table->date('entry_date');
            $table->integer('quantity')->default(0);
            $table->string('logged_by_name')->nullable();
            $table->timestamps();
        });

        // Configure in-memory SQLite connection for 'wms'
        config(['database.connections.wms' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        Schema::connection('wms')->create('material_return_lines', function ($table) {
            $table->uuid('id')->primary();
            $table->string('order_id')->index();
            $table->string('order_number')->index();
            $table->string('vendor_name')->nullable();
            $table->uuid('task_id')->nullable()->index();
            $table->string('item_type');
            $table->string('label');
            $table->string('item_number')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('qty_declared', 14, 2)->default(0);
            $table->decimal('qty_actual', 14, 2)->nullable();
            $table->decimal('qty_sent', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->string('uploaded_by_role');
            $table->string('uploaded_by_name')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'item_type', 'label']);
        });

        $this->admin = new User([
            'name' => 'Subcon Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
            'role' => 'subcon_admin',
        ]);
        $this->admin->id = (string) Str::uuid();
        $this->admin->save();

        $this->vendor = new Vendor([
            'name' => 'PT Mitra Garment Indo',
            'vendor_code' => 'VEND-001',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $this->vendor->id = (string) Str::uuid();
        $this->vendor->save();

        $this->vendorUser = new User([
            'name' => 'Vendor Staff',
            'email' => 'vendor@partner.com',
            'password' => bcrypt('secret'),
            'role' => 'subcon_vendor',
            'vendor_id' => $this->vendor->id,
        ]);
        $this->vendorUser->id = (string) Str::uuid();
        $this->vendorUser->save();

        $this->order = new SubconOrder([
            'order_number' => 'PO-TEST-LOCALE-001',
            'vendor_id' => $this->vendor->id,
            'title' => 'Test Garment Style',
            'workflow_stage' => SubconOrder::STAGE_CUTTING,
            'status' => 'in_progress',
            'order_date' => now(),
            'due_date' => now()->addDays(14),
        ]);
        $this->order->id = (string) Str::uuid();
        $this->order->save();
    }

    public function test_subcon_vendor_defaults_to_bahasa_indonesia(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.dashboard'));

        $response->assertOk();
        $this->assertEquals('id', app()->getLocale());
    }

    public function test_admin_defaults_to_english(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('subcon.admin.dashboard'));

        $response->assertOk();
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_language_switcher_toggles_locale_in_session(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('locale.switch', 'en'));

        $response->assertRedirect();
        $this->assertEquals('en', session('locale'));

        // Next request retains English
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.dashboard'));
        $this->assertEquals('en', app()->getLocale());

        // Switch back to Indonesian
        $response = $this->actingAs($this->vendorUser)
            ->get(route('locale.switch', 'id'));
        $response->assertRedirect();
        $this->assertEquals('id', session('locale'));
    }

    public function test_vendor_can_access_illustrated_guidebook(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.guidebook'));

        $response->assertOk();
        $response->assertViewIs('subcon.vendor.guidebook');
        $response->assertSee('Buku Panduan Mitra Subkontraktor');
        $response->assertSee('visualizer-lifecycle');
        $response->assertSee('visualizer-daily');
        $response->assertSee('visualizer-cutting');
        $response->assertSee('visualizer-recon');
    }

    public function test_vendor_dashboard_links_to_guidebook(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('subcon.vendor.guidebook'));
        $response->assertSee('Buku Panduan Alur Kerja Mitra Subkontraktor');
    }

    public function test_vendor_can_download_pdf_user_guide(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.user-guide'));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_vendor_work_order_renders_three_structured_tabs(): void
    {
        $this->withoutExceptionHandling();
        $mockProduction = $this->createMock(SubconProductionService::class);
        $mockProduction->method('forPo')->willReturn([
            [
                'production_group' => 'PRG-001',
                'article_name' => 'Test Polo Shirt',
                'season' => '2026',
                'world' => 'MEN',
                'department' => 'Casual',
                'category' => 'Tops',
                'subcategory' => 'Polo',
                'colour' => 'Navy',
                'total_qty' => 100,
                'plm_status' => 'StartedUp',
                'lines' => [
                    (object) ['ProdId' => 'PROD-S', 'Size' => 'S', 'Qty' => 50, 'ProdStatus' => 'StartedUp', 'CutPlanQty' => 50],
                    (object) ['ProdId' => 'PROD-M', 'Size' => 'M', 'Qty' => 50, 'ProdStatus' => 'StartedUp', 'CutPlanQty' => 50],
                ],
            ],
        ]);
        $mockProduction->method('summarize')->willReturn(['style' => 'Test Polo Shirt']);
        $mockProduction->method('fabricLinesForPo')->willReturn([]);
        $mockProduction->method('accessoryLinesForPo')->willReturn([]);
        $this->app->instance(SubconProductionService::class, $mockProduction);

        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.orders.view', $this->order->id));

        $response->assertOk();
        $response->assertSee('id="stage-tab"', false);
        $response->assertSee('id="recon-tab"', false);
        $response->assertSee('id="daily-tab"', false);
        $response->assertSee('id="remarks-stage"', false);
        $response->assertSee('id="remarks-recon"', false);
        $response->assertDontSee('Material Flow');
        $response->assertSee('data-bs-toggle="popover"', false);
    }

    public function test_vendor_can_add_and_save_custom_accessory_in_material_recon(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->post(route('subcon.vendor.orders.material-reconciliation', $this->order->id), [
                'accessories_recon' => [
                    [
                        'label' => 'Kancing Tambahan Custom',
                        'qty' => 25,
                        'unit' => 'PCS',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('material_return_lines', [
            'order_id' => $this->order->id,
            'item_type' => MaterialReturnLine::TYPE_ACCESSORY,
            'label' => 'Kancing Tambahan Custom',
            'qty_declared' => 25,
        ], 'wms');
    }

    public function test_vendor_can_submit_cutting_revision_after_cutting_approved(): void
    {
        // Setup order at gramasi stage with cutting previously approved
        $this->order->workflow_stage = SubconOrder::STAGE_GRAMASI;
        $this->order->cutting_approved_at = now()->subMinutes(30);
        $this->order->save();

        $this->assertTrue($this->order->canReviseCutting());

        // First, check the show view displays the revision collapse
        $mockProduction = $this->createMock(SubconProductionService::class);
        $mockProduction->method('forPo')->willReturn([
            [
                'production_group' => 'PRG-001',
                'article_name' => 'Test Polo Shirt',
                'season' => '2026',
                'world' => 'MEN',
                'department' => 'Casual',
                'category' => 'Tops',
                'subcategory' => 'Polo',
                'colour' => 'Navy',
                'total_qty' => 100,
                'plm_status' => 'StartedUp',
                'lines' => [
                    (object) ['ProdId' => 'PROD-S', 'Size' => 'S', 'Qty' => 50, 'ProdStatus' => 'StartedUp'],
                ],
            ],
        ]);
        $mockProduction->method('summarize')->willReturn(['style' => 'Test Polo Shirt']);
        $mockProduction->method('fabricLinesForPo')->willReturn([]);
        $mockProduction->method('accessoryLinesForPo')->willReturn([]);
        $this->app->instance(SubconProductionService::class, $mockProduction);

        $viewResponse = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.orders.view', $this->order->id));

        $viewResponse->assertOk();
        $viewResponse->assertSee('id="cuttingRevisionCollapse"', false);
        $viewResponse->assertSee('Ajukan Revisi Cutting');

        // Submit revision
        $postResponse = $this->actingAs($this->vendorUser)
            ->post(route('subcon.vendor.orders.submit-cutting', $this->order->id), [
                'reports' => [
                    '0_0' => [
                        'prod_id' => 'PROD-S',
                        'size' => 'S',
                        'cutting_qty' => 48,
                    ],
                ],
            ]);

        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        $this->order->refresh();
        $this->assertEquals(SubconOrder::STAGE_CUTTING_REVIEW, $this->order->workflow_stage);

        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $this->order->id,
            'prod_id' => 'PROD-S',
            'cutting_qty' => 48,
        ]);
    }
}
