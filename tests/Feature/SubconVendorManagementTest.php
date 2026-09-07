<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubconVendorManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->admin = new User([
            'name' => 'Subcon Admin',
            'email' => 'admin@subcon.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $this->admin->id = (string) Str::uuid();
        $this->admin->save();
    }

    public function test_subcon_admin_can_view_and_search_vendors()
    {
        $vendor1Id = (string) Str::uuid();
        $vendor1 = new Vendor([
            'name' => 'Alpha Garment Studio',
            'vendor_code' => 'V001',
            'group' => 'CMT-North',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor1->id = $vendor1Id;
        $vendor1->save();

        $u1 = new User([
            'name' => 'Alpha Garment',
            'email' => 'vendor@alphastudio.com',
            'password' => bcrypt('password'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendor1Id,
        ]);
        $u1->id = (string) Str::uuid();
        $u1->save();

        $vendor2Id = (string) Str::uuid();
        $vendor2 = new Vendor([
            'name' => 'Beta Stitching Factory',
            'vendor_code' => 'V002',
            'group' => 'CMT-South',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor2->id = $vendor2Id;
        $vendor2->save();

        // 1. Unfiltered page view
        $response = $this->actingAs($this->admin)->get(route('subcon.admin.vendors'));
        $response->assertStatus(200);
        $response->assertSee('Alpha Garment Studio');
        $response->assertSee('Beta Stitching Factory');
        $response->assertSee('vendor@alphastudio.com');

        // 2. Search by vendor name
        $responseSearchName = $this->actingAs($this->admin)->get(route('subcon.admin.vendors', ['search' => 'Alpha']));
        $responseSearchName->assertStatus(200);
        $responseSearchName->assertSee('Alpha Garment Studio');
        $responseSearchName->assertDontSee('Beta Stitching Factory');

        // 3. Search by vendor login email
        $responseSearchEmail = $this->actingAs($this->admin)->get(route('subcon.admin.vendors', ['search' => 'alphastudio']));
        $responseSearchEmail->assertStatus(200);
        $responseSearchEmail->assertSee('Alpha Garment Studio');
        $responseSearchEmail->assertDontSee('Beta Stitching Factory');

        // 4. Search by vendor code
        $responseSearchCode = $this->actingAs($this->admin)->get(route('subcon.admin.vendors', ['search' => 'V002']));
        $responseSearchCode->assertStatus(200);
        $responseSearchCode->assertSee('Beta Stitching Factory');
        $responseSearchCode->assertDontSee('Alpha Garment Studio');
    }

    public function test_subcon_admin_can_reset_vendor_password()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'Delta Subcon',
            'vendor_code' => 'V003',
            'group' => 'CMT-East',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $vendorUser = new User([
            'name' => 'Delta Subcon',
            'email' => 'vendor@deltasubcon.com',
            'password' => bcrypt('oldpassword123'),
            'role' => 'subcon_vendor',
            'vendor_id' => $vendorId,
        ]);
        $vendorUser->id = (string) Str::uuid();
        $vendorUser->save();

        $response = $this->actingAs($this->admin)->post(
            route('subcon.admin.vendors.reset-password', $vendorId),
            ['password' => 'newsecurepass123']
        );

        $response->assertRedirect(route('subcon.admin.vendors'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('new_vendor_credentials', function ($cred) {
            return $cred['login_email'] === 'vendor@deltasubcon.com' &&
                   $cred['password'] === 'newsecurepass123' &&
                   !empty($cred['is_reset']);
        });

        $this->assertTrue(Hash::check('newsecurepass123', $vendorUser->fresh()->password));
    }

    public function test_subcon_admin_can_create_missing_vendor_user_account()
    {
        $vendorId = (string) Str::uuid();
        $vendor = new Vendor([
            'name' => 'PT Tupai Adyamas Indonesia',
            'vendor_code' => 'V004',
            'group' => 'CMT-West',
            'type' => 'subcon',
            'is_active' => true,
        ]);
        $vendor->id = $vendorId;
        $vendor->save();

        $response = $this->actingAs($this->admin)->post(
            route('subcon.admin.vendors.create-account', $vendorId)
        );

        $response->assertRedirect(route('subcon.admin.vendors'));
        $response->assertSessionHas('success');

        $createdUser = User::where('vendor_id', $vendorId)->where('role', 'subcon_vendor')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('vendor@pttupaiai.com', $createdUser->email);
        $this->assertTrue(Hash::check('password', $createdUser->password));
    }
}
