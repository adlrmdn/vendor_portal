<?php

namespace Tests\Feature;

use App\Models\HsCode;
use App\Models\PcAuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PC Phase 1, absorbed into the existing fabric admin area: no dedicated
 * role, admin/fabric_admin gate the whole /admin/pc/* surface, and the
 * Approver pool is a Setting-based email list (PcAdminController::
 * isPcApproverUser()), mirroring SubconAdminController::isDirectorUser().
 */
class PcModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $fabricAdmin;

    private User $fabricVendorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->fabricAdmin = new User([
            'name' => 'Leon Oscar',
            'email' => 'leon@megaperintis.co.id',
            'password' => bcrypt('password'),
            'role' => 'fabric_admin',
        ]);
        $this->fabricAdmin->id = (string) Str::uuid();
        $this->fabricAdmin->save();

        $this->fabricVendorUser = new User([
            'name' => 'Some Vendor',
            'email' => 'vendor@example.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_vendor',
        ]);
        $this->fabricVendorUser->id = (string) Str::uuid();
        $this->fabricVendorUser->save();
    }

    public function test_fabric_vendor_cannot_reach_pc_tab(): void
    {
        $this->actingAs($this->fabricVendorUser)
            ->get(route('admin.pc.index'))
            ->assertForbidden();
    }

    public function test_fabric_admin_sees_pc_tab_with_master_data_counts(): void
    {
        HsCode::create(['code' => '5208599090', 'description' => '100% COTTON', 'source' => 'seed']);

        $response = $this->actingAs($this->fabricAdmin)->get(route('admin.pc.index'));

        $response->assertOk();
        $response->assertSee('Preliminary Contract');
        $response->assertSee('HS Codes');
    }

    public function test_fabric_admin_sees_data_management_tabs(): void
    {
        HsCode::create(['code' => '5208599090', 'description' => '100% COTTON', 'source' => 'seed']);

        $response = $this->actingAs($this->fabricAdmin)->get(route('admin.pc.data.index'));

        $response->assertOk();
        $response->assertSee('PC Master Data');
        $response->assertSee('PC Audit Log');
        $response->assertSee('PC Approver Settings');
        $response->assertSee('5208599090');
    }

    public function test_fabric_admin_can_add_hs_code_and_it_is_audited(): void
    {
        $response = $this->actingAs($this->fabricAdmin)->post(route('admin.pc.master-data.hs-codes.store'), [
            'code' => '1234567890',
            'description' => 'Test fabric',
        ]);

        $response->assertRedirect(route('admin.pc.data.index').'#master-data');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('hs_codes', [
            'code' => '1234567890',
            'source' => 'user',
        ]);

        $log = PcAuditLog::where('event_type', 'master_data.hs_code.add')->first();
        $this->assertNotNull($log);
        $this->assertSame('leon@megaperintis.co.id', $log->actor);
    }

    public function test_duplicate_hs_code_fails_validation(): void
    {
        HsCode::create(['code' => '1234567890', 'source' => 'seed']);

        $response = $this->actingAs($this->fabricAdmin)->post(route('admin.pc.master-data.hs-codes.store'), [
            'code' => '1234567890',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_approver_eligibility_is_setting_based_not_a_role(): void
    {
        $this->assertFalse(\App\Http\Controllers\PcAdminController::isPcApproverUser($this->fabricAdmin));

        Setting::updateOrCreate(
            ['key' => 'pc_approver_emails'],
            ['value' => 'someone@else.com, '.$this->fabricAdmin->email]
        );

        $this->assertTrue(\App\Http\Controllers\PcAdminController::isPcApproverUser($this->fabricAdmin->fresh()));
    }

    public function test_updating_approver_list_redirects_to_approver_settings_tab(): void
    {
        $response = $this->actingAs($this->fabricAdmin)->post(route('admin.pc.workflow.update'), [
            'pc_approver_emails' => 'aslam@megaperintis.co.id',
        ]);

        $response->assertRedirect(route('admin.pc.data.index').'#approver-settings');
        $this->assertSame('aslam@megaperintis.co.id', Setting::getValue('pc_approver_emails'));
    }

    public function test_configured_approver_can_confirm_totp_setup(): void
    {
        Setting::updateOrCreate(
            ['key' => 'pc_approver_emails'],
            ['value' => $this->fabricAdmin->email]
        );

        $totp = app(TotpService::class);
        $secret = $totp->generateSecretKey();
        $this->fabricAdmin->totp_secret = $secret;
        $this->fabricAdmin->save();

        $engine = new \PragmaRX\Google2FA\Google2FA;
        $validCode = $engine->getCurrentOtp($secret);

        $response = $this->actingAs($this->fabricAdmin->fresh())->post(route('admin.pc.totp.confirm'), [
            'code' => $validCode,
        ]);

        $response->assertRedirect(route('admin.pc.data.index').'#approver-settings');
        $this->assertNotNull($this->fabricAdmin->fresh()->totp_confirmed_at);
    }

    public function test_non_approver_fabric_admin_gets_403_confirming_totp(): void
    {
        $response = $this->actingAs($this->fabricAdmin)->post(route('admin.pc.totp.confirm'), [
            'code' => '123456',
        ]);

        $response->assertForbidden();
    }

    public function test_sync_poll_endpoint_returns_feed_states(): void
    {
        $response = $this->actingAs($this->fabricAdmin)->get(route('admin.pc.sync.poll'));

        $response->assertOk();
        $response->assertJsonStructure([
            'vendor-enrichment' => ['running', 'last'],
            'fabric-items' => ['running', 'last'],
            'plm-refs' => ['running', 'last'],
        ]);
    }
}
