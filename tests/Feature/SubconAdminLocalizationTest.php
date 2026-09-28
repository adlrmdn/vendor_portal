<?php

namespace Tests\Feature;

use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubconAdminLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
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
            'name' => 'Subcon Admin User',
            'email' => 'admin@example.com',
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

        $this->order = new SubconOrder([
            'order_number' => 'PO-TEST-LOC-01',
            'title' => 'Test Regular Fit Shirt',
            'vendor_id' => $this->vendor->id,
            'status' => 'in_progress',
            'workflow_stage' => 'cutting_review',
            'production_group' => 'PRG-LOC-01',
            'order_date' => now(),
            'due_date' => now()->addDays(14),
        ]);
        $this->order->id = (string) Str::uuid();
        $this->order->save();
    }

    public function test_admin_defaults_to_english_locale(): void
    {
        $response = $this->actingAs($this->admin)->get(route('subcon.admin.dashboard'));

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
        $response->assertSee('Welcome back');
        $response->assertSee('Pending Approvals');
        $response->assertSee('Recent Work Orders');
    }

    public function test_admin_can_switch_to_indonesian_via_locale_toggle(): void
    {
        $switch = $this->actingAs($this->admin)->get(route('locale.switch', 'id'));
        $switch->assertSessionHas('locale', 'id');

        $response = $this->actingAs($this->admin)->withSession(['locale' => 'id'])->get(route('subcon.admin.dashboard'));
        $response->assertStatus(200);
        $this->assertEquals('id', app()->getLocale());
        $response->assertSee('Selamat datang kembali');
        $response->assertSee('Menunggu Persetujuan');
        $response->assertSee('Surat Perintah Kerja Terkini');
        $response->assertSee('Buku Panduan Admin');
    }

    public function test_admin_sidebar_includes_guidebook(): void
    {
        $response = $this->actingAs($this->admin)->get(route('subcon.admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('subcon.admin.guidebook'));
    }

    public function test_admin_can_view_interactive_guidebook(): void
    {
        // Test English view
        $response = $this->actingAs($this->admin)->get(route('subcon.admin.guidebook'));

        $response->assertStatus(200);
        $response->assertSee('Subcontractor Admin');
        $response->assertSee('QC Console');
        $response->assertSee('VSM Material Flow');
        $response->assertSee('value_stream_ops');
        $response->assertSee('Report Validation');
        $response->assertSee('Send to Material Flow');
        $response->assertSee('Cutt Plan');
        $response->assertSee('ADD-Was');
        $response->assertSee('Retur Kain');
        $response->assertSee('70%');
        $response->assertSee('Troubleshooting Runbook');

        // Test Indonesian view
        $this->actingAs($this->admin)->get(route('locale.switch', 'id'));
        $responseId = $this->actingAs($this->admin)->withSession(['locale' => 'id'])->get(route('subcon.admin.guidebook'));
        $responseId->assertStatus(200);
        $this->assertEquals('id', app()->getLocale());
        $responseId->assertSee('Buku Panduan Kontrol & Troubleshooting Admin Subkontraktor');
        $responseId->assertSee('QC Console');
        $responseId->assertSee('VSM Material Flow');
        $responseId->assertSee('TIDAK AKAN BISA dikirim ke Direktur', false);
    }

    public function test_admin_can_stream_user_guide_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('subcon.admin.user-guide'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type') ?? '', 'application/pdf'));
    }

    public function test_admin_dashboard_and_approvals_render_contextual_info_tooltips(): void
    {
        $dashboardResponse = $this->actingAs($this->admin)->get(route('subcon.admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('data-bs-toggle="tooltip"', false);
        $dashboardResponse->assertSee('fas fa-info-circle', false);

        $approvalsResponse = $this->actingAs($this->admin)->get(route('subcon.admin.approvals'));
        $approvalsResponse->assertStatus(200);
        $approvalsResponse->assertSee('data-bs-toggle="tooltip"', false);
        $approvalsResponse->assertSee('fas fa-info-circle', false);
    }
}
