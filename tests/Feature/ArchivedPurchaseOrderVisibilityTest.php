<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchivedPurchaseOrderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private User $admin;

    private User $vendorUser;

    private PurchaseOrder $visible;

    private PurchaseOrder $archived;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = new Vendor([
            'name' => 'Visibility Test Vendor',
            'vendor_code' => 'V-VIS',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $this->vendor->id = (string) Str::uuid();
        $this->vendor->save();

        $this->admin = new User([
            'name' => 'Fabric Admin',
            'email' => 'admin@visibility-test.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_admin',
        ]);
        $this->admin->id = (string) Str::uuid();
        $this->admin->save();

        $this->vendorUser = new User([
            'name' => 'Visibility Vendor',
            'email' => 'vendor@visibility-test.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_vendor',
            'vendor_id' => $this->vendor->id,
        ]);
        $this->vendorUser->id = (string) Str::uuid();
        $this->vendorUser->save();

        $this->visible = new PurchaseOrder([
            'po_number' => 'MPG/PO/2609/00001',
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'order_date' => '2026-09-01',
            'currency' => 'IDR',
        ]);
        $this->visible->id = (string) Str::uuid();
        $this->visible->save();

        $this->archived = new PurchaseOrder([
            'po_number' => 'MPG/PO/2501/00002',
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'order_date' => '2025-01-01',
            'currency' => 'IDR',
        ]);
        $this->archived->id = (string) Str::uuid();
        $this->archived->archived_at = now();
        $this->archived->save();
    }

    public function test_admin_po_list_and_dashboard_exclude_archived()
    {
        $this->actingAs($this->admin);

        $list = $this->get(route('admin.purchase-orders'));
        $list->assertSee('MPG/PO/2609/00001');
        $list->assertDontSee('MPG/PO/2501/00002');

        $dashboard = $this->get(route('admin.dashboard'));
        $dashboard->assertDontSee('MPG/PO/2501/00002');
    }

    public function test_admin_can_still_open_archived_po_directly()
    {
        $this->actingAs($this->admin);

        $detail = $this->get(route('admin.purchase-order.view', $this->archived->id));
        $detail->assertOk();
        $detail->assertSee('MPG/PO/2501/00002');
    }

    public function test_vendor_po_list_and_dashboard_exclude_archived()
    {
        $this->actingAs($this->vendorUser);

        $list = $this->get(route('vendor.purchase-orders'));
        $list->assertSee('MPG/PO/2609/00001');
        $list->assertDontSee('MPG/PO/2501/00002');

        $dashboard = $this->get(route('vendor.dashboard'));
        $dashboard->assertDontSee('MPG/PO/2501/00002');
    }
}
