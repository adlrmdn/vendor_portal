<?php

namespace Tests\Feature;

use App\Jobs\SendFinalApprovalEmail;
use App\Jobs\SendQcNotificationEmail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the Director authorization stage (stage 3) of the QC packaging
 * approval chain and its side effects: signature contract, back-to-MD-Prod
 * rejection routing, RPA queueing (invoice + portal-computed deduction),
 * automatic project completion, and the 4-party completion notification.
 *
 * The shared QMS and RPA databases are faked with in-memory SQLite: the
 * controller only uses portable query-builder calls, so the same code paths
 * run against the fakes.
 */
class QcDirectorApprovalTest extends TestCase
{
    private string $token;

    private string $projectId = 'PRJ-TEST-DIRECTOR-1';

    private string $sessionId = 'SES-TEST-DIRECTOR-1';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['qms', 'rpa'] as $name) {
            Config::set("database.connections.$name", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]);
            DB::purge($name);
        }

        $this->createQmsSchema();
        $this->createRpaSchema();
        $this->seedProjectAndSession();

        $this->useWritableDefaultDatabase();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * The sqlite test-DB file is read-only for the container user, so writes
     * on the default connection (Setting seeding, SubconApprovalLog) fail.
     * Point the default connection at a fresh writable temp copy per test.
     */
    private function useWritableDefaultDatabase(): void
    {
        $default = config('database.default');
        $source = config("database.connections.$default.database");
        if ($default !== 'sqlite' || ! is_string($source) || ! is_file($source)) {
            return;
        }

        $copy = sys_get_temp_dir().'/qc_director_test_'.getmypid().'.sqlite';
        copy($source, $copy);
        Config::set("database.connections.$default.database", $copy);
        DB::purge($default);
    }

    private function createQmsSchema(): void
    {
        $qms = DB::connection('qms');
        $qms->statement('CREATE TABLE packaging_projects (
            project_id TEXT PRIMARY KEY, plm_id TEXT, brand TEXT, season TEXT, article_name TEXT,
            production_group TEXT, po_info TEXT, po_qty REAL, po_plan_date TEXT, po_vendor TEXT,
            status TEXT, cmt_cut_job_id TEXT, cmt_pak_job_id TEXT, sales_price REAL,
            verified_doc TEXT, has_deduction INTEGER DEFAULT 0, deduction_amount REAL DEFAULT 0,
            created_at TEXT, updated_at TEXT)');
        $qms->statement('CREATE TABLE packaging_project_sessions (
            session_id TEXT PRIMARY KEY, project_id TEXT, cycle_number INTEGER, inspector_id TEXT,
            status TEXT, started_at TEXT, ended_at TEXT, inspection_date TEXT,
            check_wash INTEGER DEFAULT 0, check_style_as_sample INTEGER DEFAULT 0,
            check_main_label INTEGER DEFAULT 0, check_flag_fit_label INTEGER DEFAULT 0,
            check_print_embro_artwork INTEGER DEFAULT 0, check_hangtag INTEGER DEFAULT 0,
            check_waist_tag INTEGER DEFAULT 0, check_barcode INTEGER DEFAULT 0,
            check_packing_list INTEGER DEFAULT 0, check_shipping_mark INTEGER DEFAULT 0,
            check_other_1 INTEGER DEFAULT 0, check_other_1_label TEXT,
            check_other_2 INTEGER DEFAULT 0, check_other_2_label TEXT,
            qty_available INTEGER DEFAULT 0, total_store INTEGER DEFAULT 0, store_inspected INTEGER DEFAULT 0,
            cutting_pcs INTEGER DEFAULT 0, sewing_pcs INTEGER DEFAULT 0, finishing_pcs INTEGER DEFAULT 0,
            packing_pcs INTEGER DEFAULT 0, sampling_pcs INTEGER DEFAULT 0,
            aql REAL DEFAULT 0, level_val REAL DEFAULT 0,
            factory_representative TEXT, inspector TEXT, version TEXT, result TEXT,
            approval_token TEXT, approval_email TEXT, approved_by TEXT, approved_at TEXT,
            approval_source TEXT, approval_status TEXT, approval_signature TEXT,
            ho_approval_signature TEXT, director_approval_signature TEXT, inspector_email TEXT,
            remarks TEXT, retur_kain REAL)');
        $qms->statement('CREATE TABLE packaging_project_reports (
            report_id TEXT PRIMARY KEY, session_id TEXT, project_id TEXT, size_val TEXT,
            line_no INTEGER DEFAULT 0, global_display_order INTEGER DEFAULT 0,
            qty_order INTEGER DEFAULT 0, session_qty REAL DEFAULT 0, total_good_qty INTEGER DEFAULT 0,
            reject_produksi INTEGER DEFAULT 0, reject_finishing INTEGER DEFAULT 0, reject_embro INTEGER DEFAULT 0,
            reject_cutting INTEGER DEFAULT 0, reject_printing INTEGER DEFAULT 0, reject_sewing INTEGER DEFAULT 0,
            reject_washing INTEGER DEFAULT 0, reject_bahan INTEGER DEFAULT 0, btj INTEGER DEFAULT 0,
            barang_hilang INTEGER DEFAULT 0, created_at TEXT)');
        $qms->statement('CREATE TABLE packaging_project_fabric_lines (
            id INTEGER PRIMARY KEY AUTOINCREMENT, project_id TEXT, production_group TEXT, label TEXT,
            fabric_sent REAL DEFAULT 0, consumption_plan REAL DEFAULT 0, cutt_plan REAL DEFAULT 0,
            actual_consumption REAL DEFAULT 0, short_roll REAL DEFAULT 0, sisa_kain REAL DEFAULT 0,
            kepala_kain REAL DEFAULT 0, return_kain REAL DEFAULT 0, overconsumption REAL,
            fabric_price REAL, deduction REAL, created_by TEXT, created_at TEXT)');
        $qms->statement('CREATE TABLE packaging_session_deduction_lines (
            id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT, description TEXT,
            amount REAL DEFAULT 0, created_by TEXT, created_at TEXT)');
        $qms->statement('CREATE TABLE packaging_defect_images (
            image_id TEXT PRIMARY KEY, project_id TEXT, session_id TEXT, image_path TEXT,
            defect_type TEXT, description TEXT, major INTEGER DEFAULT 0, minor INTEGER DEFAULT 0,
            captured_at TEXT)');
        $qms->statement('CREATE TABLE packaging_project_remarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, production_group TEXT, project_id TEXT,
            remarks TEXT, updated_by TEXT, created_at TEXT, updated_at TEXT)');
    }

    private function createRpaSchema(): void
    {
        DB::connection('rpa')->statement('CREATE TABLE rpa_queues (
            id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id TEXT, rpa_type TEXT,
            status TEXT, payload TEXT, attempts INTEGER DEFAULT 0, error_message TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedProjectAndSession(array $sessionOverrides = []): void
    {
        $this->token = (string) Str::uuid();

        DB::connection('qms')->table('packaging_projects')->insert([
            'project_id' => $this->projectId,
            'production_group' => 'MPG/PRG/TEST/000001',
            'po_info' => 'MPG/PO/TEST/00001',
            'po_qty' => 100,
            'po_vendor' => 'CV Test Vendor',
            'article_name' => 'Test Article Black',
            'season' => 'FALL-26',
            'plm_id' => 'PLM/TEST/1',
            'status' => 'downloaded',
            'sales_price' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('qms')->table('packaging_project_sessions')->insert(array_merge([
            'session_id' => $this->sessionId,
            'project_id' => $this->projectId,
            'cycle_number' => 2,
            'inspector_id' => 'inspector-1',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subHour(),
            'inspection_date' => now()->toDateString(),
            'inspector' => 'Wulan',
            'inspector_email' => 'qc-inspector@example.test',
            'version' => 'v2.0',
            'result' => 'Passed',
            'approval_token' => $this->token,
            'approval_email' => 'factoryrep@example.test',
            'approval_status' => 'approved',
            'approved_by' => 'factoryrep@example.test',
            'approved_at' => now()->subHour(),
            'approval_signature' => 'Digitally Signed: factoryrep@example.test [UTC+07:00: 2026-07-14 10:00:00]',
            'ho_approval_signature' => 'Digitally Signed: MPG HO - MD Production [UTC+07:00: 2026-07-14 11:00:00]',
        ], $sessionOverrides));
    }

    // ---------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------

    public function test_director_form_requires_md_prod_approval_first(): void
    {
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null]);

        $this->get(route('qc.director-approve', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('not ready for Director authorization', false);
    }

    public function test_director_form_renders_when_md_prod_has_signed(): void
    {
        $this->get(route('qc.director-approve', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('Director Authorization')
            ->assertSee('Authorize');
    }

    public function test_invalid_token_shows_friendly_page(): void
    {
        $this->get(route('qc.director-approve', ['token' => 'not-a-uuid']))
            ->assertOk()
            ->assertSee('invalid or has expired');
    }

    // ---------------------------------------------------------------
    // Approve: sign → RPA → complete → notify
    // ---------------------------------------------------------------

    public function test_director_approval_signs_queues_rpa_completes_and_notifies(): void
    {
        Queue::fake();
        Mail::fake();

        // Portal-computed deduction sources: one fabric overconsumption charge
        // + one manual HO deduction line.
        DB::connection('qms')->table('packaging_project_fabric_lines')->insert([
            'project_id' => $this->projectId,
            'production_group' => 'MPG/PRG/TEST/000001',
            'label' => 'COTTON JERSEY (KG)',
            'deduction' => 150000,
            'created_by' => 'web_portal',
            'created_at' => now(),
        ]);
        DB::connection('qms')->table('packaging_session_deduction_lines')->insert([
            'session_id' => $this->sessionId,
            'description' => 'Label reprint',
            'amount' => 25000,
            'created_by' => 'MPG HO - MD Production',
            'created_at' => now(),
        ]);

        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('Director authorization recorded');

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Digitally Signed: MPG Director', $session->director_approval_signature);

        $project = DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->first();
        $this->assertSame('completed', $project->status);
        $this->assertEquals(175000.0, (float) $project->deduction_amount);
        // The fully-signed PDF was regenerated server-side.
        $this->assertStringStartsWith('data:application/pdf;base64,', (string) $project->verified_doc);

        // Invoice RPA: console payload shape, amount = sales_price × po_qty.
        $invoice = DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'invoice')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('incomplete', $invoice->status);
        $payload = json_decode($invoice->payload, true);
        $this->assertSame('MPG/PO/TEST/00001', $payload['PO']);
        $this->assertEquals(50000 * 100, $payload['amount']);
        $this->assertSame('v2.0', $payload['latest_version']);
        $this->assertStringStartsWith('data:application/pdf', $payload['signed_doc']);

        // Deduction RPA: portal-computed total (fabric 150k + manual 25k).
        $deduction = DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'deduction')->first();
        $this->assertNotNull($deduction);
        $this->assertEquals(175000.0, json_decode($deduction->payload, true)['amount']);

        // Completion notification job queued for all four parties.
        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return $job->payload['view'] === 'emails.qc-completion-notification'
                && in_array('qc-inspector@example.test', $job->payload['recipients'], true)
                && in_array('factoryrep@example.test', $job->payload['recipients'], true);
        });
    }

    public function test_director_approval_without_deductions_queues_no_deduction_job(): void
    {
        Queue::fake();
        Mail::fake();

        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))->assertOk();

        $this->assertSame(0, DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'deduction')->count());
        $this->assertSame(1, DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'invoice')->count());
    }

    public function test_director_approval_is_idempotent(): void
    {
        Queue::fake();
        Mail::fake();

        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))->assertOk();
        $first = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('director_approval_signature');

        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('already');

        $second = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('director_approval_signature');
        $this->assertSame($first, $second);
    }

    // ---------------------------------------------------------------
    // Reject: back to MD Production (not back to QC)
    // ---------------------------------------------------------------

    public function test_director_rejection_returns_to_md_prod_and_keeps_factory_signature(): void
    {
        Queue::fake();
        Mail::fake();

        $stale = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 pre-rejection');
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['verified_doc' => $stale]);

        $this->post(route('qc.director-decline.submit', ['token' => $this->token]), [
            'reason' => 'Deduction figure looks wrong',
        ])->assertOk()->assertSee('MD Production has been asked to review again');

        // The shared document is re-rendered on rejection too — it must no
        // longer be the pre-rejection copy (which still showed the HO sig).
        $this->assertNotSame($stale, (string) DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->value('verified_doc'));

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Rejected: MPG Director', $session->director_approval_signature);
        // Back to MD Production: HO signature cleared, factory-rep signature kept.
        $this->assertNull($session->ho_approval_signature);
        $this->assertNotNull($session->approval_signature);
        $this->assertSame('approved', $session->approval_status);

        // Project NOT completed, nothing queued to RPA.
        $this->assertNotSame('completed', DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->value('status'));
        $this->assertSame(0, DB::connection('rpa')->table('rpa_queues')->count());

        // MD Production re-request email queued, carrying the Director's reason.
        Queue::assertPushed(SendFinalApprovalEmail::class, function ($job) {
            return str_contains((string) ($job->payload['note'] ?? ''), 'Deduction figure looks wrong');
        });
    }

    public function test_md_prod_reapproval_after_director_reject_clears_director_stamp_and_queues_raf(): void
    {
        Queue::fake();
        Mail::fake();

        // Recipient list for the chained Director email (settings live on the
        // main test DB, which is empty by default).
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Director rejected earlier: ho cleared, director stamp 'Rejected:'.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update([
                'ho_approval_signature' => null,
                'director_approval_signature' => 'Rejected: MPG Director [UTC+07:00: 2026-07-14 12:00:00]',
            ]);

        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]))->assertOk();

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Digitally Signed: MPG HO - MD Production', $session->ho_approval_signature);
        // Rerun wipes the stale Director rejection so the new Director link is live.
        $this->assertNull($session->director_approval_signature);

        // MD Prod approval queues the RAF RPA job (status 'pending').
        $raf = DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'job_trans_raf')->first();
        $this->assertNotNull($raf);
        $this->assertSame('pending', $raf->status);

        // And chains the Director authorization email.
        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return $job->payload['view'] === 'emails.qc-director-approval';
        });
    }

    // ---------------------------------------------------------------
    // Reject notifications back to the QC inspector
    // ---------------------------------------------------------------

    public function test_factory_rep_rejection_notifies_inspector(): void
    {
        // Uses the real array transport so the view-based Mail::send renders
        // (validating the blade) and lands in the transport buffer.
        $this->seedFreshUnapprovedSession();

        $this->get(route('qc.reject', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('Rejection recorded');

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $mail = $messages->first()->getOriginalMessage();
        $this->assertStringContainsString('qc-inspector@example.test', $mail->getHeaders()->get('To')->getBodyAsString());
        $this->assertStringContainsString('Factory Representative', $mail->getHeaders()->get('Subject')->getBodyAsString());
    }

    public function test_md_prod_rejection_notifies_inspector(): void
    {
        Queue::fake();

        // The base seed pre-signs the HO stage (for the Director tests) —
        // clear it so this session is genuinely pending MD Prod action.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-decline.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('rejection recorded', false);

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Rejected: MPG HO - MD Production', $session->ho_approval_signature);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('MD Production', $messages->first()->getOriginalMessage()->getHeaders()->get('Subject')->getBodyAsString());
    }

    // ---------------------------------------------------------------
    // Separated Director tab (subcon admin area)
    // ---------------------------------------------------------------

    public function test_director_tab_lists_pending_authorizations_for_the_director(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertSee('Director Authorizations')
            // Session is HO-signed with no director signature → pending row
            // linking into the existing token-based workflow form.
            ->assertSee($this->projectId)
            ->assertSee(route('qc.director-approve', ['token' => $this->token]), false);
    }

    public function test_director_tab_is_forbidden_for_regular_subcon_admins(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        $this->actingAs($this->makeSubconAdmin('admin@example.test', 'Regular Admin'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertForbidden();
    }

    public function test_completed_legacy_project_is_not_listed_or_reauthorizable(): void
    {
        Queue::fake();
        Mail::fake();

        // Old two-stage flow: HO-signed, no director stamp, but the project was
        // already completed (console "Complete & Sync") and invoiced.
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['status' => 'completed']);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Not offered in the Director tab…
        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertDontSee(route('qc.director-approve', ['token' => $this->token]), false);

        // …and the workflow guard refuses to re-sign (no re-queued invoice).
        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('already completed');

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertEmpty($session->director_approval_signature);
        $this->assertSame(0, DB::connection('rpa')->table('rpa_queues')->count());
    }

    public function test_ho_approval_is_attributed_to_the_email_recipient(): void
    {
        Queue::fake();
        Mail::fake();

        $this->makeSubconAdmin('fitri.yeni@megaputragarment.co.id', 'Fitri Yeni');
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'director_approval_signature' => null]);

        // The per-recipient email link carries `as`; the form posts it back.
        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]), [
            'as' => 'fitri.yeni@megaputragarment.co.id',
        ])->assertOk();

        $sig = (string) DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('ho_approval_signature');
        $this->assertStringStartsWith('Digitally Signed: Fitri Yeni <fitri.yeni@megaputragarment.co.id>', $sig);
    }

    public function test_director_authorization_is_attributed_to_the_logged_in_account(): void
    {
        Queue::fake();
        Mail::fake();

        $director = $this->makeSubconAdmin('director@example.test', 'Adil Ramadhan');

        $this->actingAs($director)
            ->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk();

        $sig = (string) DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('director_approval_signature');
        $this->assertStringStartsWith('Digitally Signed: Adil Ramadhan <director@example.test>', $sig);

        // The decision log carries the person too.
        $log = \App\Models\SubconApprovalLog::where('gate', 'director')->latest()->first();
        $this->assertSame('Adil Ramadhan <director@example.test>', $log->actor);
        $this->assertSame('portal', $log->source);
    }

    public function test_document_endpoint_renders_fresh_pdf_and_pushes_it_to_verified_doc(): void
    {
        // A stale stored doc (e.g. written before the latest signature) must
        // NOT be served — the endpoint renders from current workflow state.
        $stale = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 stale');
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['verified_doc' => $stale]);

        $response = $this->get(route('qc.document', ['token' => $this->token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertNotSame('%PDF-1.4 stale', $response->getContent());

        // …and the fresh render is pushed back into the shared container so
        // the console preview and emails follow.
        $doc = (string) DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->value('verified_doc');
        $this->assertNotSame($stale, $doc);
        $this->assertStringStartsWith('data:application/pdf;base64,', $doc);

        // Unknown token → friendly invalid-link page, no document.
        $this->get(route('qc.document', ['token' => (string) Str::uuid()]))
            ->assertOk()
            ->assertSee('invalid or has expired');
    }

    private function makeSubconAdmin(string $email, string $name): \App\Models\User
    {
        // id is not mass-assignable and the sqlite copy has no column default.
        $user = (new \App\Models\User)->forceFill([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => 'subcon_admin',
        ]);
        $user->save();

        return $user;
    }

    private function seedFreshUnapprovedSession(): void
    {
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update([
                'approval_status' => null,
                'approval_signature' => null,
                'approved_by' => null,
                'approved_at' => null,
                'ho_approval_signature' => null,
                'director_approval_signature' => null,
            ]);
    }
}
