<?php

namespace Tests\Feature;

use App\Jobs\SyncNewFabricVendorOrders;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FabricVendorManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->admin = new User([
            'name' => 'Fabric Admin',
            'email' => 'admin@fabric.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_admin',
        ]);
        $this->admin->id = (string) Str::uuid();
        $this->admin->save();
    }

    public function test_fabric_admin_can_view_and_search_vendors()
    {
        $vendor1Id = (string) Str::uuid();
        $vendor1 = new Vendor([
            'name' => 'Alpha Fabric Mills',
            'vendor_code' => 'F001',
            'group' => 'Local',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor1->id = $vendor1Id;
        $vendor1->save();

        $u1 = new User([
            'name' => 'Alpha Fabric',
            'email' => 'vendor@alphafabric.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_vendor',
            'vendor_id' => $vendor1Id,
        ]);
        $u1->id = (string) Str::uuid();
        $u1->save();

        $vendor2Id = (string) Str::uuid();
        $vendor2 = new Vendor([
            'name' => 'Beta Textile Works',
            'vendor_code' => 'F002',
            'group' => 'Import',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor2->id = $vendor2Id;
        $vendor2->save();

        $response = $this->actingAs($this->admin)->get(route('admin.vendors'));
        $response->assertStatus(200);
        $response->assertSee('Alpha Fabric Mills');
        $response->assertSee('Beta Textile Works');
        $response->assertSee('vendor@alphafabric.com');

        $responseSearchName = $this->actingAs($this->admin)->get(route('admin.vendors', ['search' => 'Alpha']));
        $responseSearchName->assertStatus(200);
        $responseSearchName->assertSee('Alpha Fabric Mills');
        $responseSearchName->assertDontSee('Beta Textile Works');

        $responseSearchEmail = $this->actingAs($this->admin)->get(route('admin.vendors', ['search' => 'alphafabric']));
        $responseSearchEmail->assertStatus(200);
        $responseSearchEmail->assertSee('Alpha Fabric Mills');
        $responseSearchEmail->assertDontSee('Beta Textile Works');

        $responseSearchCode = $this->actingAs($this->admin)->get(route('admin.vendors', ['search' => 'F002']));
        $responseSearchCode->assertStatus(200);
        $responseSearchCode->assertSee('Beta Textile Works');
        $responseSearchCode->assertDontSee('Alpha Fabric Mills');
    }

    public function test_fabric_admin_can_create_vendor_with_auto_provisioned_login()
    {
        // Avoid the sync-queue test config actually running SyncNewFabricVendorOrders,
        // which would call out to the real D365 OAuth endpoint (see phpunit.xml).
        Queue::fake();

        $response = $this->actingAs($this->admin)->post(route('admin.vendor.store'), [
            'name' => 'PT Tupai Adyamas Indonesia',
            'vendor_code' => 'F010',
            'group' => 'Local',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.vendors'));
        $response->assertSessionHas('success');

        $vendor = Vendor::where('vendor_code', 'F010')->first();
        $this->assertNotNull($vendor);
        $this->assertEquals('fabric', $vendor->type);
        $this->assertEquals('Local', $vendor->group);

        $createdUser = User::where('vendor_id', $vendor->id)->where('role', 'fabric_vendor')->first();
        $this->assertNotNull($createdUser);
        // Fabric's convention (unlike subcon's) drops the PT/CV/... company-form
        // prefix entirely rather than keeping it — matches vendors:register-fabric-accounts.
        $this->assertEquals('vendor@tupaiai.com', $createdUser->email);
        $this->assertTrue(Hash::check('password', $createdUser->password));

        $response->assertSessionHas('new_vendor_credentials', function ($cred) {
            return $cred['login_email'] === 'vendor@tupaiai.com' && $cred['password'] === 'password';
        });

        Queue::assertPushed(SyncNewFabricVendorOrders::class, function ($job) {
            return $job->vendorCode === 'F010';
        });
    }

    public function test_fabric_admin_can_reset_vendor_password()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Delta Fabric Co',
            'vendor_code' => 'F011',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $vendorUser = new User([
            'name' => 'Delta Fabric Co',
            'email' => 'vendor@deltafabricco.com',
            'password' => bcrypt('oldpassword123'),
            'role' => 'fabric_vendor',
            'vendor_id' => $vendorId,
        ]);
        $vendorUser->id = (string) Str::uuid();
        $vendorUser->save();

        $response = $this->actingAs($this->admin)->post(
            route('admin.vendor.reset-password', $vendorId),
            ['password' => 'newsecurepass123']
        );

        $response->assertRedirect(route('admin.vendors'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('new_vendor_credentials', function ($cred) {
            return $cred['login_email'] === 'vendor@deltafabricco.com' &&
                   $cred['password'] === 'newsecurepass123' &&
                   ! empty($cred['is_reset']);
        });

        $this->assertTrue(Hash::check('newsecurepass123', $vendorUser->fresh()->password));
    }

    public function test_fabric_admin_can_create_missing_vendor_user_account()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Epsilon Fabric House',
            'vendor_code' => 'F012',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $response = $this->actingAs($this->admin)->post(
            route('admin.vendor.create-account', $vendorId)
        );

        $response->assertRedirect(route('admin.vendors'));
        $response->assertSessionHas('success');

        $createdUser = User::where('vendor_id', $vendorId)->where('role', 'fabric_vendor')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('password', $createdUser->password));
    }

    public function test_fabric_admin_can_toggle_vendor_status()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Zeta Fabric Ltd',
            'vendor_code' => 'F013',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $response = $this->actingAs($this->admin)->post(route('admin.vendor.toggle-status', $vendorId));
        $response->assertRedirect(route('admin.vendors'));
        $this->assertFalse($vendor->fresh()->is_active);

        $this->actingAs($this->admin)->post(route('admin.vendor.toggle-status', $vendorId));
        $this->assertTrue($vendor->fresh()->is_active);
    }

    public function test_fabric_admin_cannot_delete_vendor_with_purchase_orders()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Eta Fabric Corp',
            'vendor_code' => 'F014',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $po = new \App\Models\PurchaseOrder([
            'po_number' => 'PO-F014-1',
            'vendor_id' => $vendorId,
            'status' => 'pending',
            'order_date' => now(),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $response = $this->actingAs($this->admin)->delete(route('admin.vendor.destroy', $vendorId));
        $response->assertRedirect(route('admin.vendors'));
        $response->assertSessionHas('error');

        $this->assertNotNull(Vendor::find($vendorId));
    }

    public function test_fabric_admin_can_delete_vendor_without_purchase_orders()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Theta Fabric Inc',
            'vendor_code' => 'F015',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $response = $this->actingAs($this->admin)->delete(route('admin.vendor.destroy', $vendorId));
        $response->assertRedirect(route('admin.vendors'));
        $response->assertSessionHas('success');

        $this->assertNull(Vendor::find($vendorId));
    }
}
