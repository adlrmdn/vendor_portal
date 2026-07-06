<?php

namespace Tests\Feature;

use App\Jobs\SyncSubconReportToD365;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\SubconCuttingReport;
use App\Models\SubconFabricReconciliation;
use App\Models\SubconOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SubconProductionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the merged "calculate + approve" cutting gate: the approver enters
 * fabric consumption and approves in one atomic action, both via the no-login
 * signed email link and via the in-app admin panel.
 */
class SubconCuttingApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SubconFabricReconciliation::query()->delete();
        SubconCuttingReport::query()->delete();
        SubconOrder::query()->delete();
        PurchaseOrder::query()->where('po_number', 'like', 'POFAB%')->delete(); // cascades po_items
        User::query()->where('email', 'like', '%@cutappr.test')->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_CUTAPPR%')->delete();
    }

    private function makeVendor(string $code = 'V_CUTAPPR'): Vendor
    {
        $vendor = new Vendor([
            'name' => 'Cut Approval Vendor',
            'vendor_code' => $code,
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        return $vendor;
    }

    private function makeOrder(Vendor $vendor, string $stage = SubconOrder::STAGE_CUTTING_REVIEW): SubconOrder
    {
        return SubconOrder::create([
            'order_number' => 'PO-CUTAPPR-'.Str::random(5),
            'vendor_id' => $vendor->id,
            'title' => 'Cut Approval PO',
            'status' => 'in_progress',
            'order_date' => now(),
            'workflow_stage' => $stage,
        ]);
    }

    public function test_signed_cutting_approve_link_renders_no_login_form()
    {
        $vendor = $this->makeVendor();
        $order = $this->makeOrder($vendor);

        $url = URL::signedRoute('subcon.approve', ['order' => $order->id, 'gate' => 'cutting'], absolute: false);

        // No actingAs — this must work without a login.
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('subcon.cutting-approval-form');
        $response->assertSee('Cutting Report Approval');
    }

    public function test_signed_cutting_submit_saves_consumption_and_approves_atomically()
    {
        Queue::fake();
        Mail::fake();

        $vendor = $this->makeVendor();
        $order = $this->makeOrder($vendor);

        // Basis for actual_consumption: total cutting qty = 150.
        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-1',
            'size' => 'M',
            'cutting_qty' => 150,
            'gramasi' => 180,
        ]);

        $url = URL::signedRoute('subcon.approve.cutting.submit', ['order' => $order->id], absolute: false);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post($url, [
            'fabrics' => [
                ['label' => 'Cotton 30s', 'fabric_sent' => 300, 'consumption_plan' => 1.5],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', true);

        // Consumption computed + snapshotted: cutt_plan = floor(300/1.5) = 200,
        // actual_consumption = 300/150 = 2.0.
        $recon = SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Cotton 30s')->first();
        $this->assertNotNull($recon);
        $this->assertEquals(200, (int) $recon->cutt_plan);
        $this->assertEquals(2.0, (float) $recon->actual_consumption); // no waste: (300-0)/150
        $this->assertEquals(0.3333, (float) $recon->overconsumption);  // (2.0-1.5)/1.5
        $this->assertEquals(300.0, (float) $recon->fabric_sent);

        // Stage advanced + stamped as an email approval.
        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_GRAMASI, $order->workflow_stage);
        $this->assertEquals('Email approval', $order->cutting_approved_by);
        $this->assertNotNull($order->cutting_approved_at);

        // D365 sync dispatched in the background.
        Queue::assertPushed(SyncSubconReportToD365::class);
    }

    public function test_actual_consumption_and_overconsumption_match_agreed_schema()
    {
        Queue::fake();
        Mail::fake();

        $vendor = $this->makeVendor('V_CUTAPPR_SPEC');
        $order = $this->makeOrder($vendor);

        // Qty Cutting (E) = 800 garments cut.
        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-1',
            'size' => 'M',
            'cutting_qty' => 800,
        ]);

        // Vendor-entered reconciliation: waste = short_roll + sisa_kain + kepala_kain
        // + retur_kain = 2 + 10 + 2 + 9 = 23 (all four now enter the calc).
        SubconFabricReconciliation::create([
            'order_id' => $order->id,
            'label' => 'Main Fabric',
            'short_roll' => 2,
            'sisa_kain' => 10,
            'kepala_kain' => 2,
            'retur_kain' => 9,
        ]);

        $url = URL::signedRoute('subcon.approve.cutting.submit', ['order' => $order->id], absolute: false);

        // Fabric Sent (B) = 1000, Cons. Plan (C) = 1.2.
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post($url, [
            'fabrics' => [
                ['label' => 'Main Fabric', 'fabric_sent' => 1000, 'consumption_plan' => 1.2],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertViewHas('success', true);

        $recon = SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Main Fabric')->first();
        // D = ROUNDDOWN(1000/1.2) = 833
        $this->assertEquals(833, (int) $recon->cutt_plan);
        // F = (1000 - (2+10+2+9)) / 800 = 977/800 = 1.22125 → 1.2213 (retur_kain included)
        $this->assertEquals(1.2213, (float) $recon->actual_consumption);
        // K = (1.2213 - 1.2) / 1.2 = 0.0178 (1.78%)
        $this->assertEquals(0.0178, (float) $recon->overconsumption);
        // 1.78% < 3% tolerance → no deduction even with a price.
        $this->assertEquals(0.0, (float) $recon->deduction);
    }

    public function test_local_fabric_price_is_per_unit_metric_not_price_unit_basis()
    {
        $vendor = $this->makeVendor('V_CUTAPPR_FAB');

        // Local fabric PO (as the fabric sync stores it): total_price is the line
        // total, unit_price is D365 PurchasePrice quoted per a price-unit basis.
        $po = new PurchaseOrder([
            'po_number' => 'POFAB1',
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'currency' => 'USD',
            'order_date' => now(),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $item = new PoItem([
            'po_id' => $po->id,
            'item_number' => 'FAB-1',
            'description' => 'Cotton 30s',
            'batch' => '',
            'plm_number' => '',
            'quantity' => 1000,      // ordered metres
            'unit_price' => 500000,  // per price-unit basis (over-stated) — must NOT be used
            'total_price' => 5000000, // line total → per unit = 5,000,000 / 1000 = 5000
            'unit' => 'M',
            'status' => 'pending',
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        $pricing = app(SubconProductionService::class)->resolveFabricPricing([
            ['label' => 'Cotton 30s (M)', 'item_numbers' => ['FAB-1'], 'po_numbers' => ['POFAB1'], 'fabric_price' => 999999],
        ]);

        // Per-unit metric price, not the 500000 price-unit-basis nor the VSM fallback.
        $this->assertEquals(5000.0, $pricing['Cotton 30s (M)']['price']);
        $this->assertEquals('USD', $pricing['Cotton 30s (M)']['currency']);
    }

    public function test_deduction_charged_only_above_three_percent_tolerance()
    {
        Queue::fake();
        Mail::fake();

        $vendor = $this->makeVendor('V_CUTAPPR_DED');
        $order = $this->makeOrder($vendor);

        // Total qty cut = 1000.
        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-1',
            'size' => 'M',
            'cutting_qty' => 1000,
        ]);

        $url = URL::signedRoute('subcon.approve.cutting.submit', ['order' => $order->id], absolute: false);

        // No waste; fabric_sent=1100, plan=1.0 → actual=1.1, overcons=10% (>3%).
        // excess/piece = 1.1 - 1.0*1.03 = 0.07; deduction = 0.07 * 1000 * 5000 = 350000.
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post($url, [
            'fabrics' => [
                ['label' => 'Main Fabric', 'fabric_sent' => 1100, 'consumption_plan' => 1.0, 'fabric_price' => 5000],
            ],
        ]);

        $response->assertViewHas('success', true);

        $recon = SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Main Fabric')->first();
        $this->assertEquals(0.1, (float) $recon->overconsumption);
        $this->assertEquals(350000.0, (float) $recon->deduction);
    }

    public function test_approver_can_override_vendor_waste_values()
    {
        Queue::fake();
        Mail::fake();

        $vendor = $this->makeVendor('V_CUTAPPR_OVR');
        $order = $this->makeOrder($vendor);

        // Qty cut = 800.
        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-1',
            'size' => 'M',
            'cutting_qty' => 800,
        ]);

        // Vendor entered waste = 2+10+2+9 = 23.
        SubconFabricReconciliation::create([
            'order_id' => $order->id,
            'label' => 'Main Fabric',
            'short_roll' => 2,
            'sisa_kain' => 10,
            'kepala_kain' => 2,
            'retur_kain' => 9,
        ]);

        $url = URL::signedRoute('subcon.approve.cutting.submit', ['order' => $order->id], absolute: false);

        // Approver overrides the waste directly on the form: 5+20+5+10 = 40.
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post($url, [
            'fabrics' => [[
                'label' => 'Main Fabric',
                'short_roll' => 5, 'sisa_kain' => 20, 'kepala_kain' => 5, 'retur_kain' => 10,
                'fabric_sent' => 1000, 'consumption_plan' => 1.2,
            ]],
        ]);

        $response->assertViewHas('success', true);

        $recon = SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Main Fabric')->first();
        // Overridden waste is persisted.
        $this->assertEquals(5.0, (float) $recon->short_roll);
        $this->assertEquals(20.0, (float) $recon->sisa_kain);
        $this->assertEquals(5.0, (float) $recon->kepala_kain);
        $this->assertEquals(10.0, (float) $recon->retur_kain);
        // Actual uses the overridden waste: (1000 - 40) / 800 = 960/800 = 1.2.
        $this->assertEquals(1.2, (float) $recon->actual_consumption);
    }

    public function test_in_app_cutting_approve_carries_consumption()
    {
        Queue::fake();
        Mail::fake();

        $vendor = $this->makeVendor('V_CUTAPPR_IN');
        $order = $this->makeOrder($vendor);

        SubconCuttingReport::create([
            'order_id' => $order->id,
            'prod_id' => 'PROD-1',
            'size' => 'M',
            'cutting_qty' => 100,
        ]);

        $admin = new User([
            'name' => 'Subcon Admin',
            'email' => 'admin@cutappr.test',
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $admin->id = (string) Str::uuid();
        $admin->save();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.orders.approve', $order->id), [
                'gate' => 'cutting',
                'fabrics' => [
                    ['label' => 'Linen', 'fabric_sent' => 250, 'consumption_plan' => 2.5],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $recon = SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Linen')->first();
        $this->assertNotNull($recon);
        $this->assertEquals(100, (int) $recon->cutt_plan); // floor(250/2.5)
        $this->assertEquals(2.5, (float) $recon->actual_consumption); // 250/100

        $order->refresh();
        $this->assertEquals(SubconOrder::STAGE_GRAMASI, $order->workflow_stage);
        $this->assertEquals('Subcon Admin', $order->cutting_approved_by);

        Queue::assertPushed(SyncSubconReportToD365::class);
    }

    public function test_cutting_submit_rejected_when_not_awaiting_review()
    {
        Queue::fake();

        $vendor = $this->makeVendor('V_CUTAPPR_LATE');
        // Already past the cutting review window.
        $order = $this->makeOrder($vendor, SubconOrder::STAGE_GRAMASI);

        $url = URL::signedRoute('subcon.approve.cutting.submit', ['order' => $order->id], absolute: false);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)->post($url, [
            'fabrics' => [
                ['label' => 'Cotton 30s', 'fabric_sent' => 300, 'consumption_plan' => 1.5],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertViewHas('success', false);

        // Nothing persisted, stage untouched, no D365 sync.
        $this->assertEquals(0, SubconFabricReconciliation::where('order_id', $order->id)->count());
        $this->assertEquals(SubconOrder::STAGE_GRAMASI, $order->fresh()->workflow_stage);
        Queue::assertNotPushed(SyncSubconReportToD365::class);
    }
}
