<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SubconOfficialReport;
use App\Models\User;
use App\Services\D365JobTransactionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubconOfficialReportFlowTest extends TestCase
{
    use RefreshDatabase;

    // A dedicated LOCAL fixture PRG/PO — deliberately not a live production
    // group. The original version of these tests pinned to a real
    // MPG/PRG/2512/000014 + its real AddPro PO (MPG/PO/2601/00302), which
    // breaks the moment finance actually marks that PO Invoiced in the live
    // D365/VSM system (final-review Fix 6 — a ticking time bomb unrelated to
    // any code change). These tests now seed their own local vsm fixtures,
    // the same pattern SubconOfficialReportSendTest/GatingTest already use.
    private string $prg = 'MPG/PRG/TEST/FLOW01';

    private string $jobTransactionId = 'MPG/JOB/TEST/FLOW01';

    private string $poNumber = 'MPG/PO/TEST/FLOW01';

    // Separate fixture PRG for the guest/signed-link test (Fix 5) — created
    // directly via the model (no gate() dependency needed there), kept apart
    // from $prg so the two groups of tests never share cleanup state.
    private string $guestPrg = 'MPG/PRG/TEST/GUESTFLOW01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        SubconOfficialReport::query()->where('production_group', $this->prg)->delete();
        SubconOfficialReport::query()->where('production_group', $this->guestPrg)->delete();
        User::query()->whereIn('email', ['flowtest@test.com', 'directorflowtest@test.com'])->delete();
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice', 'deduction'])
            ->where('entity_id', 'like', $this->prg.':%')
            ->delete();
        DB::connection('vsm')->table('job_transaction_line_details')->where('JobTransactionId', $this->jobTransactionId)->delete();
        DB::connection('vsm')->table('job_transaction_headers')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('production_groups')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('po_lines')->where('PurchaseOrderNumber', $this->poNumber)->delete();
        DB::connection('vsm')->table('po_headers')->where('PurchaseOrderNumber', $this->poNumber)->delete();
    }

    /**
     * Seeds a local vsm fixture that passes gate()'s in-house cut-qty check
     * (job_transaction_headers/line_details) and its PO-matching check
     * (production_groups -> po_headers/po_lines, the "standalone" path) —
     * same shape as SubconOfficialReportSendTest's fixtures. No plm_activity
     * row, so send() never needs a live D365 sales-price fetch either.
     */
    private function seedVsmFixtures(): void
    {
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);
        DB::connection('vsm')->table('production_groups')->insert([
            'ProductionGroup' => $this->prg, 'PONumber' => $this->poNumber,
        ]);
        DB::connection('vsm')->table('po_headers')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'PurchPoolId' => 'AddPro', 'DocumentApprovalStatus' => 'Confirmed',
            'OrderVendorAccountNumber' => 'V_TEST_FLOW', 'PurchaseOrderStatus' => 'Received',
        ]);
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => $this->poNumber, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing',
            'ItemNumber' => '2312000100004',
        ]);
    }

    /** No live D365 calls from these tests — fetchVendorNames/fetchSalesPrice mocked out. */
    private function mockD365(): void
    {
        $mock = $this->mock(D365JobTransactionService::class);
        $mock->shouldReceive('fetchSalesPrice')->andReturn(null)->byDefault();
        $mock->shouldReceive('fetchVendorNames')->andReturn([])->byDefault();
    }

    private function admin(): User
    {
        $user = new User(['name' => 'Flow Admin', 'email' => 'flowtest@test.com', 'password' => bcrypt('password'), 'role' => 'subcon_admin']);
        $user->id = (string) Str::uuid();
        $user->save();

        return $user;
    }

    /** A second account whose email is the configured Director — needed since Fix 4. */
    private function director(): User
    {
        $user = new User(['name' => 'Flow Director', 'email' => 'directorflowtest@test.com', 'password' => bcrypt('password'), 'role' => 'subcon_admin']);
        $user->id = (string) Str::uuid();
        $user->save();

        return $user;
    }

    private function seedSettings(): void
    {
        Setting::updateOrCreate(
            ['key' => 'qc_ho_approver_email'],
            ['value' => 'ho@test.com', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
        Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'directorflowtest@test.com', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
    }

    public function test_full_send_to_director_approval_happy_path()
    {
        Mail::fake();
        $this->seedSettings();
        $this->seedVsmFixtures();
        $this->mockD365();

        $admin = $this->admin();
        $director = $this->director();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), [
                'production_group' => $this->prg,
                'operation' => 'ADD-Was',
                'additional_deduction' => 0,
                'remarks' => 'Test send',
            ]);

        $response->assertSessionHas('success');
        $report = SubconOfficialReport::where('production_group', $this->prg)->where('operation', 'ADD-Was')->firstOrFail();
        // send() now folds in the HO approval — whoever sends it already has
        // a subcon_admin account, same as every qc_ho_approver_email
        // recipient (confirmed live), so a separate HO-approve click on the
        // same figures moments later would just be a rubber stamp. Goes
        // straight to pending_director, signed as the sending admin.
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);
        $this->assertStringContainsString($admin->name, $report->ho_approval_signature);

        // Director approves in-app — must act as the configured Director (Fix 4's route-level gate).
        $response3 = $this->actingAs($director)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);
        $response3->assertSessionHas('success');
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);

        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => $this->prg.':ADD-Was',
            'rpa_type' => 'invoice',
        ], 'rpa');
    }

    public function test_director_decline_then_resend_then_approve()
    {
        Mail::fake();
        $this->seedSettings();
        $this->seedVsmFixtures();
        $this->mockD365();
        $admin = $this->admin();
        $director = $this->director();

        $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => $this->prg, 'operation' => 'ADD-Was']);
        // send() now folds in the HO approval (see the happy-path test) —
        // the report is already pending_director here, no separate
        // ho-approve call needed before the Director can decline it.
        $report = SubconOfficialReport::where('production_group', $this->prg)->where('operation', 'ADD-Was')->firstOrFail();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);

        $this->actingAs($director)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-decline', $report->id), ['reason' => 'Please recheck.']);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);
        $this->assertSame(1, $report->revision_count);
        $this->assertTrue($report->needsRevision());

        // THIS ho-approve call is the real, still-untouched revision path —
        // a genuine second look at figures the Director already rejected
        // once, reached from the Report Validation tab's "Revise & Resend"
        // (not send() again).
        $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.ho-approve', $report->id), ['additional_deduction' => 10000]);
        $this->actingAs($director)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $this->assertSame(10000.0, $report->additional_deduction);
    }

    /**
     * The actual reported vulnerability: URL::signedRoute() produces a
     * non-expiring signature, so a stale HO-approve email link stays valid
     * forever. If clicked after the Director has already approved, it must
     * not be able to regress status back to pending_director or overwrite
     * the (now terminal) signature/timestamp — the service's isPendingHo()
     * guard must reject it.
     */
    public function test_replayed_signed_ho_approve_link_after_completion_does_not_regress_status()
    {
        Mail::fake();
        $this->seedSettings();
        $this->seedVsmFixtures();
        $this->mockD365();
        $admin = $this->admin();
        $director = $this->director();

        $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => $this->prg, 'operation' => 'ADD-Was']);
        // send() folds in the HO approval now — already pending_director here.
        $report = SubconOfficialReport::where('production_group', $this->prg)->where('operation', 'ADD-Was')->firstOrFail();

        $this->actingAs($director)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $completedHoSignature = $report->ho_approval_signature;
        $completedHoApprovedAt = $report->ho_approved_at;

        // A NEW signed link generated now (simulating the original stale
        // email link, which never expires) still targets the same report id.
        $staleUrl = URL::signedRoute('subcon.official-report.ho-approve.submit', ['report' => $report->id], absolute: false);

        $replayResponse = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post($staleUrl, ['approver' => 'Replay Attacker']);

        $replayResponse->assertOk();
        $replayResponse->assertViewIs('approvals.result');
        $replayResponse->assertViewHas('success', false);
        $replayResponse->assertSee('This report is no longer awaiting MD Production approval.');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status, 'replay must not regress status');
        $this->assertSame($completedHoSignature, $report->ho_approval_signature, 'replay must not overwrite the signature');
        $this->assertEquals($completedHoApprovedAt, $report->ho_approved_at, 'replay must not overwrite the timestamp');
    }

    /** Same replay-guard class, but for a Director-decline hitting an already-completed report (in-app entry point). */
    public function test_director_decline_on_completed_report_is_rejected_in_app()
    {
        Mail::fake();
        $this->seedSettings();
        $this->seedVsmFixtures();
        $this->mockD365();
        $admin = $this->admin();
        $director = $this->director();

        $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => $this->prg, 'operation' => 'ADD-Was']);
        // send() folds in the HO approval now — already pending_director here.
        $report = SubconOfficialReport::where('production_group', $this->prg)->where('operation', 'ADD-Was')->firstOrFail();

        $this->actingAs($director)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);

        $replayResponse = $this->actingAs($director)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-decline', $report->id), ['reason' => 'Replayed decline attempt.']);

        $replayResponse->assertSessionHas('error');
        $replayResponse->assertSessionMissing('success');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status, 'replay must not regress status');
        $this->assertSame(0, $report->revision_count, 'replay must not increment revision_count');
        $this->assertNull($report->director_decline_reason);
    }

    public function test_send_blocked_when_gate_fails()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), [
                'production_group' => 'MPG/PRG/DOES/NOT/EXIST',
                'operation' => 'ADD-Was',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('subcon_official_reports', ['production_group' => 'MPG/PRG/DOES/NOT/EXIST']);
    }

    // --- Fix 2: send() must refuse to re-send a report that has already moved past pending_ho ---

    public function test_resend_of_a_completed_report_is_rejected_and_leaves_it_unchanged()
    {
        Mail::fake();
        $this->seedSettings();
        $this->seedVsmFixtures();
        $this->mockD365();
        $admin = $this->admin();
        $director = $this->director();

        $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => $this->prg, 'operation' => 'ADD-Was']);
        // send() folds in the HO approval now — already pending_director here.
        $report = SubconOfficialReport::where('production_group', $this->prg)->where('operation', 'ADD-Was')->firstOrFail();

        $this->actingAs($director)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $hoSignature = $report->ho_approval_signature;
        $directorSignature = $report->director_approval_signature;

        // gate() itself still passes (the PO isn't Invoiced in our fixture) —
        // it's send()'s own existing-row status guard that must block this.
        $resendResponse = $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => $this->prg, 'operation' => 'ADD-Was']);

        $resendResponse->assertSessionHas('error', "ADD-Was already has an Official Report in progress or completed — it can't be re-sent from here.");

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status, 'resend must not regress status');
        $this->assertSame($hoSignature, $report->ho_approval_signature);
        $this->assertSame($directorSignature, $report->director_approval_signature);
    }

    // --- Fix 4: the in-app Director routes must reject a non-Director admin ---

    public function test_director_in_app_routes_reject_a_non_director_admin_with_403()
    {
        $this->seedSettings();
        $admin = $this->admin(); // NOT the configured director

        $report = SubconOfficialReport::create([
            'production_group' => $this->prg,
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR,
            'ho_approval_signature' => 'Digitally Signed: Ika',
        ]);

        $approveResponse = $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);
        $approveResponse->assertStatus(403);

        $declineResponse = $this->actingAs($admin)->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-decline', $report->id), ['reason' => 'nope']);
        $declineResponse->assertStatus(403);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status, '403 must not have mutated the report');
        $this->assertNull($report->director_approval_signature);
        $this->assertNull($report->director_decline_reason);
    }

    // --- Fix 5: real anonymous-guest signed-link walk + signature tampering ---

    /**
     * No actingAs() anywhere in this test — this is the actual real-world
     * path: an anonymous recipient clicking links in an email, with no
     * portal login at all.
     */
    public function test_guest_can_walk_the_full_signed_link_approval_chain_without_authentication()
    {
        Mail::fake();

        $report = SubconOfficialReport::create([
            'production_group' => $this->guestPrg,
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'matched_po_number' => 'MPG/PO/TEST/GUESTFLOW01',
            'cut_qty' => 100, 'good_qty' => 95, 'reject_qty' => 5,
            'allowed_limit' => 1, 'exceeding_reject_qty' => 4,
            'sales_price' => 100000, 'penalty_price' => 70000, 'reject_penalty' => 280000,
        ]);

        // 1. GET the signed HO-approve link — renders the confirm form, mutates nothing.
        $hoGetUrl = URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false);
        $hoGetResponse = $this->get($hoGetUrl);
        $hoGetResponse->assertOk();
        $hoGetResponse->assertSee('Additional Deduction');
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status, 'GET must not mutate');

        // 2. POST the signed HO-approve submit link.
        $hoSubmitUrl = URL::signedRoute('subcon.official-report.ho-approve.submit', ['report' => $report->id], absolute: false);
        $hoSubmitResponse = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post($hoSubmitUrl, ['approver' => 'Guest MD Production']);
        $hoSubmitResponse->assertOk();
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);
        $this->assertStringContainsString('Guest MD Production', $report->ho_approval_signature);

        // 3. GET the signed Director-approve link.
        $directorGetUrl = URL::signedRoute('subcon.official-report.director-approve', ['report' => $report->id], absolute: false);
        $directorGetResponse = $this->get($directorGetUrl);
        $directorGetResponse->assertOk();
        $directorGetResponse->assertSee('Director Authorization');

        // 4. POST the signed Director-approve submit link.
        $directorSubmitUrl = URL::signedRoute('subcon.official-report.director-approve.submit', ['report' => $report->id], absolute: false);
        $directorSubmitResponse = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post($directorSubmitUrl, ['approver' => 'Guest Director']);
        $directorSubmitResponse->assertOk();

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $this->assertStringContainsString('Guest Director', $report->director_approval_signature);
    }

    /** Proves the signed:relative middleware is actually wired, not just present in the route definition. */
    public function test_tampered_signature_on_a_signed_link_is_rejected_with_403()
    {
        $report = SubconOfficialReport::create([
            'production_group' => $this->guestPrg,
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
        ]);

        $validUrl = URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false);
        $tamperedUrl = $validUrl.'tampered';

        $response = $this->get($tamperedUrl);
        $response->assertStatus(403);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);
    }
}
