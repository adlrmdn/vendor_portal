<?php

namespace Tests\Feature;

use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Vsm\SubconDailyProductionDayNote;
use App\Models\Vsm\SubconDailyProductionEntry;
use App\Models\Vsm\SubconDailyProductionStyle;
use App\Services\SubconProductionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubconDailyProductionTest extends TestCase
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
            $table->string('size')->nullable();
            $table->date('entry_date');
            $table->integer('quantity')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
            $table->timestamps();
        });

        Schema::connection('vsm')->create('subcon_daily_production_day_notes', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->uuid('order_id')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->date('entry_date')->index();
            $table->text('notes')->nullable();
            $table->uuid('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
            $table->timestamps();
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
            'name' => 'Subcon Partner PT',
            'vendor_code' => 'V-999',
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
            'order_number' => 'PO-TEST-001',
            'title' => 'Shirt Casual SS',
            'vendor_id' => $this->vendor->id,
            'status' => 'confirmed',
            'workflow_stage' => 'cutting',
        ]);
        $this->order->id = (string) Str::uuid();
        $this->order->save();

        // Mark style as open in daily production
        SubconDailyProductionStyle::create([
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'vendor_name' => $this->vendor->name,
            'status' => 'open',
        ]);

        // Mock production sizes
        $this->mock(SubconProductionService::class, function ($mock) {
            $mock->shouldReceive('forPo')->andReturn([
                [
                    'group_name' => 'PRG-01',
                    'article_name' => 'Shirt Casual SS',
                    'lines' => [
                        (object) ['ProdId' => 'PROD-S', 'Size' => 'S', 'Qty' => 100],
                        (object) ['ProdId' => 'PROD-M', 'Size' => 'M', 'Qty' => 150],
                    ],
                ],
            ]);
        });
    }

    public function test_vendor_can_view_daily_production_board()
    {
        $response = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.daily-production.index'));

        $response->assertOk();
        $response->assertSee('Daily Production');
        $response->assertSee('PO-TEST-001');
        $response->assertSee('Overall Day Remarks (Optional):');
    }

    public function test_vendor_can_save_daily_production_with_notes()
    {
        $today = now()->toDateString();

        $payload = [
            'entry_date' => $today,
            'entries' => [
                $this->order->id => [
                    'sewing' => [
                        'PROD-S' => 25,
                        'PROD-M' => 30,
                    ],
                ],
            ],
            'notes' => [
                $this->order->id => 'Style remarks: sewing line overtime 1hr.',
            ],
            'day_note' => 'Overall remarks: rain in afternoon, generator on.',
        ];

        $response = $this->actingAs($this->vendorUser)
            ->post(route('subcon.vendor.daily-production.store-all'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check entries were stored
        $this->assertDatabaseHas('subcon_daily_production_entries', [
            'order_id' => $this->order->id,
            'stage' => 'sewing',
            'prod_id' => 'PROD-S',
            'quantity' => 25,
            'entry_date' => $today,
        ], 'vsm');

        // Check style day note was stored
        $this->assertDatabaseHas('subcon_daily_production_day_notes', [
            'order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $today,
            'notes' => 'Style remarks: sewing line overtime 1hr.',
        ], 'vsm');

        // Check overall vendor day note was stored
        $this->assertDatabaseHas('subcon_daily_production_day_notes', [
            'order_id' => null,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $today,
            'notes' => 'Overall remarks: rain in afternoon, generator on.',
        ], 'vsm');

        // View board on same date and assert notes appear
        $viewResponse = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.daily-production.index', ['date' => $today]));

        $viewResponse->assertOk();
        $viewResponse->assertSee('Style remarks: sewing line overtime 1hr.');
        $viewResponse->assertSee('Overall remarks: rain in afternoon, generator on.');
    }

    public function test_notes_are_date_isolated()
    {
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        // Save note on yesterday
        SubconDailyProductionDayNote::create([
            'order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $yesterday,
            'notes' => 'Yesterday specific note',
        ]);

        // Today board should NOT see yesterday's note
        $todayResponse = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.daily-production.index', ['date' => $today]));

        $todayResponse->assertOk();
        $todayResponse->assertDontSee('Yesterday specific note');

        // Yesterday board should see it
        $yesterdayResponse = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.daily-production.index', ['date' => $yesterday]));

        $yesterdayResponse->assertOk();
        $yesterdayResponse->assertSee('Yesterday specific note');
    }

    public function test_vendor_can_update_or_clear_notes()
    {
        $today = now()->toDateString();

        // Initial save
        SubconDailyProductionDayNote::create([
            'order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $today,
            'notes' => 'Initial note',
        ]);

        // Submit empty note to clear it
        $payload = [
            'entry_date' => $today,
            'entries' => [],
            'notes' => [
                $this->order->id => '',
            ],
            'day_note' => '',
        ];

        $response = $this->actingAs($this->vendorUser)
            ->post(route('subcon.vendor.daily-production.store-all'), $payload);

        $response->assertRedirect();

        // Assert note was deleted
        $this->assertDatabaseMissing('subcon_daily_production_day_notes', [
            'order_id' => $this->order->id,
            'entry_date' => $today,
        ], 'vsm');
    }

    public function test_admin_can_view_daily_production_and_notes()
    {
        $today = now()->toDateString();

        SubconDailyProductionDayNote::create([
            'order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $today,
            'notes' => 'Admin visible style note',
        ]);

        SubconDailyProductionDayNote::create([
            'order_id' => null,
            'vendor_id' => $this->vendor->id,
            'entry_date' => $today,
            'notes' => 'Admin visible overall note',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('subcon.admin.daily-production.index', ['date' => $today, 'vendor_id' => $this->vendor->id]));

        $response->assertOk();
        $response->assertSee('Admin visible style note');
        $response->assertSee('Admin visible overall note');
    }

    public function test_daily_cutting_prefills_cutting_report_and_vendor_can_override()
    {
        $today = now()->toDateString();

        // 1. Log 40 pcs of cutting in Daily Production for PROD-S
        SubconDailyProductionEntry::create([
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'vendor_name' => $this->vendor->name,
            'stage' => SubconDailyProductionEntry::STAGE_CUTTING,
            'ratio_label' => 'Ratio A',
            'prod_id' => 'PROD-S',
            'size' => 'S',
            'entry_date' => $today,
            'quantity' => 40,
        ]);

        // 2. Visit vendor order page — should see prefill and visual hint
        $showResponse = $this->actingAs($this->vendorUser)
            ->get(route('subcon.vendor.orders.show', $this->order->id));

        $showResponse->assertOk();
        $showResponse->assertSee('value="40"', false);
        $showResponse->assertSee('From Daily: 40');

        // 3. Vendor overrides the prefill with 45 pcs for PROD-S and 60 for PROD-M
        $submitPayload = [
            'reports' => [
                [
                    'prod_id' => 'PROD-S',
                    'size' => 'S',
                    'cutting_qty' => 45, // overridden!
                ],
                [
                    'prod_id' => 'PROD-M',
                    'size' => 'M',
                    'cutting_qty' => 60,
                ],
            ],
        ];

        $postResponse = $this->actingAs($this->vendorUser)
            ->post(route('subcon.vendor.orders.cutting-report.submit', $this->order->id), $submitPayload);

        $postResponse->assertRedirect();

        // 4. Verify SubconCuttingReport stored the overridden 45, NOT 40
        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $this->order->id,
            'prod_id' => 'PROD-S',
            'cutting_qty' => 45,
        ]);
        $this->assertDatabaseHas('subcon_cutting_reports', [
            'order_id' => $this->order->id,
            'prod_id' => 'PROD-M',
            'cutting_qty' => 60,
        ]);
    }
}
