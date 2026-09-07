<?php

namespace Tests\Feature;

use App\Jobs\SendFinalApprovalEmail;
use App\Jobs\SendQcNotificationEmail;
use App\Jobs\SendWhatsAppNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

        // RpaQueueService offloads heavy payloads to the real rpa_lake S3
        // bucket — fake it so RPA queueing tests never touch production S3.
        Storage::fake('rpa_lake');

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
            ho_approval_signature TEXT, ho_validation_signature TEXT,
            director_approval_signature TEXT, inspector_email TEXT,
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
            id TEXT PRIMARY KEY, project_id TEXT, production_group TEXT, label TEXT,
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
            'ho_validation_signature' => 'Digitally Signed: MPG HO - MD Production [UTC+07:00: 2026-07-14 11:05:00]',
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
        $this->assertSame('pending', $invoice->status);
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

    public function test_director_approval_notifies_qc_head_when_configured(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_head_notification_email'],
            ['value' => 'qchead@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))->assertOk();

        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return $job->payload['view'] === 'emails.qc-completion-notification'
                && in_array('qchead@example.test', $job->payload['recipients'], true);
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

    public function test_director_rejection_returns_to_report_validation_and_keeps_md_signature(): void
    {
        Queue::fake();
        Mail::fake();

        $stale = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 pre-rejection');
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['verified_doc' => $stale]);

        $this->post(route('qc.director-decline.submit', ['token' => $this->token]), [
            'reason' => 'Deduction figure looks wrong',
        ])->assertOk()->assertSee('MD Production has been asked to re-validate and send again');

        // The shared document is re-rendered on rejection too — it must no
        // longer be the pre-rejection copy.
        $this->assertNotSame($stale, (string) DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->value('verified_doc'));

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        // Director stamp is NOT left as a lingering 'Rejected:' row — it's
        // cleared immediately so pendingValidateSends()/directorStageGuard()
        // don't treat the row as "already actioned". The rejection itself is
        // still in SubconApprovalLog (checked below).
        $this->assertNull($session->director_approval_signature);
        // Back to Report Validation ONLY: MD Production's consumption entry +
        // sign-off (ho_approval_signature) is KEPT — they are not made to
        // redo Review & Approve. Only the validation step is re-opened.
        $this->assertStringStartsWith('Digitally Signed: MPG HO - MD Production', $session->ho_approval_signature);
        $this->assertNull($session->ho_validation_signature);
        $this->assertNotNull($session->approval_signature);
        $this->assertSame('approved', $session->approval_status);

        // Project NOT completed, nothing queued to RPA.
        $this->assertNotSame('completed', DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)->value('status'));
        $this->assertSame(0, DB::connection('rpa')->table('rpa_queues')->count());

        // The row reappears on the Report Validation tab (not stuck hidden by
        // a lingering director stamp, and not requiring a fresh Review & Approve).
        $c = new \ReflectionClass(\App\Http\Controllers\SubconAdminController::class);
        $m = $c->getMethod('pendingValidateSends');
        $m->setAccessible(true);
        $pending = collect($m->invoke(app(\App\Http\Controllers\SubconAdminController::class)));
        $this->assertTrue($pending->contains('token', $this->token));

        // Decision permanently recorded regardless of the (now-cleared) column.
        $log = \App\Models\SubconApprovalLog::where('gate', 'director')->where('decision', 'declined')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Deduction figure looks wrong', (string) $log->note);

        // MD Production is re-sent the VALIDATE & SEND link (not the old Final
        // Approval / Review & Approve email), carrying the Director's reason.
        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-validate-send'
                && str_contains((string) ($job->payload['viewData']['note'] ?? ''), 'Deduction figure looks wrong');
        });
        Queue::assertNotPushed(SendFinalApprovalEmail::class);
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

        // Simulates a row left over from BEFORE the "reject → Report Validation
        // only" fix: ho + validation cleared and a lingering director 'Rejected:'
        // stamp (the old directorDecline() behavior). hoApprove()'s defensive
        // clearing (see its docblock) must still recover such a row today.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update([
                'ho_approval_signature' => null,
                'ho_validation_signature' => null,
                'director_approval_signature' => 'Rejected: MPG Director [UTC+07:00: 2026-07-14 12:00:00]',
            ]);

        // Step 1 — Review & Approve: signs + queues RAF, then bounces back to
        // the form (which now renders the validate-and-send step).
        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]))
            ->assertRedirect(route('qc.ho-approve', ['token' => $this->token]));

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Digitally Signed: MPG HO - MD Production', $session->ho_approval_signature);
        // Rerun wipes the stale Director rejection so the new Director link is live.
        $this->assertNull($session->director_approval_signature);
        // …and starts a fresh validate-and-send cycle.
        $this->assertNull($session->ho_validation_signature);

        // MD Prod approval queues the RAF RPA job (status 'pending').
        $raf = DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $this->projectId)->where('rpa_type', 'job_trans_raf')->first();
        $this->assertNotNull($raf);
        $this->assertSame('pending', $raf->status);

        // The Director is NOT notified at approve any more…
        Queue::assertNotPushed(SendQcNotificationEmail::class, function ($job) {
            return $job->payload['view'] === 'emails.qc-director-approval';
        });

        // Step 2 — Validate & Send: records the validation signature and only
        // now chains the Director authorization email.
        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('the Director has been notified', false);

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertStringStartsWith('Digitally Signed: MPG HO - MD Production', $session->ho_validation_signature);

        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return $job->payload['view'] === 'emails.qc-director-approval';
        });
    }

    public function test_ho_send_is_idempotent_and_requires_prior_approval(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Not approved yet → send refuses.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('has not been approved by MD Production yet');
        Queue::assertNotPushed(SendQcNotificationEmail::class);

        // Approved → first send goes through, second short-circuits.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => 'Digitally Signed: MPG HO - MD Production [UTC+07:00: 2026-07-14 11:00:00]']);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('the Director has been notified', false);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('already been sent to the Director');

        // Exactly one Director request despite the double click.
        $this->assertSame(1, collect(Queue::pushedJobs()[SendQcNotificationEmail::class] ?? [])
            ->filter(fn ($p) => $p['job']->payload['view'] === 'emails.qc-director-approval')->count());
    }

    public function test_director_form_requires_validate_and_send_first(): void
    {
        // Approved but not yet validated & sent → the Director stage stays shut.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null]);

        $this->get(route('qc.director-approve', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('has not validated');
    }

    public function test_validate_pending_session_lists_on_report_validation_tab_only(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Approved but not yet validated & sent (RAF job queued as pending).
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null]);
        DB::connection('rpa')->table('rpa_queues')->insert([
            'entity_type' => 'packaging_project', 'entity_id' => $this->projectId,
            'rpa_type' => 'job_trans_raf', 'status' => 'pending', 'payload' => '{}',
        ]);

        // Not awaiting the Director…
        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertDontSee(route('qc.director-approve', ['token' => $this->token]), false);

        // …not on the Final Approvals tab (that lists step-1 only)…
        $this->actingAs($this->makeSubconAdmin('admin2@example.test', 'Admin Two'))
            ->get(route('subcon.admin.approvals'))
            ->assertOk()
            ->assertDontSee(route('qc.ho-approve', ['token' => $this->token]), false);

        // …but on the Report Validation tab, with the RAF status, the Report
        // button (fresh-rendered PDF) and the Validate & Send action.
        $this->get(route('subcon.admin.report-validations'))
            ->assertOk()
            ->assertSee('Report Validations')
            ->assertSee('Pending')
            ->assertSee(route('qc.document', ['token' => $this->token]), false)
            ->assertSee('Validate &amp; Send', false)
            ->assertSee(route('qc.ho-approve', ['token' => $this->token]), false);
    }

    public function test_review_and_approve_emails_the_validate_and_send_link(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_ho_approver_email'],
            ['value' => 'md1@example.test, md2@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]))->assertRedirect();

        // One Validate & Send email per MD recipient, each link attributed via `as`.
        foreach (['md1@example.test', 'md2@example.test'] as $recipient) {
            Queue::assertPushed(SendQcNotificationEmail::class, function ($job) use ($recipient) {
                return ($job->payload['view'] ?? null) === 'emails.qc-validate-send'
                    && ($job->payload['recipients'] ?? []) === [$recipient]
                    && str_contains((string) ($job->payload['viewData']['url'] ?? ''), route('qc.ho-approve', ['token' => $this->token]))
                    && str_contains((string) ($job->payload['viewData']['url'] ?? ''), urlencode($recipient));
            });
        }
    }

    public function test_director_approval_email_carries_the_deduction_total(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Fabric overconsumption charge — the same figure the Director's
        // Authorize action will queue to the deduction RPA.
        DB::connection('qms')->table('packaging_project_fabric_lines')->insert([
            'id' => (string) Str::uuid(), 'project_id' => $this->projectId, 'production_group' => 'MPG/PRG/TEST/000001',
            'label' => 'Main Fabric', 'deduction' => 150000, 'created_by' => 'test', 'created_at' => now(),
        ]);

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))->assertOk();

        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-director-approval'
                && (float) ($job->payload['viewData']['deductionTotal'] ?? 0) === 150000.0;
        });
    }

    public function test_director_approval_email_shows_no_deduction_when_none(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))->assertOk();

        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-director-approval'
                && (float) ($job->payload['viewData']['deductionTotal'] ?? 0) === 0.0;
        });
    }

    public function test_director_approval_notifies_configured_phone_alongside_email(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_phone'],
            ['value' => '08123456789', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        DB::connection('qms')->table('packaging_project_fabric_lines')->insert([
            'id' => (string) Str::uuid(), 'project_id' => $this->projectId, 'production_group' => 'MPG/PRG/TEST/000001',
            'label' => 'Main Fabric', 'deduction' => 150000, 'created_by' => 'test', 'created_at' => now(),
        ]);
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))->assertOk();

        // The single configured phone pairs by index with the single email
        // recipient, so the WhatsApp link is attributed the same way. ONE
        // link only — the review page carries both Authorize and Reject, so
        // no separate decline URL is sent.
        Queue::assertPushed(SendWhatsAppNotification::class, function ($job) {
            return $job->to === '628123456789@c.us'
                && str_starts_with($job->message, '[Subcon Vendor Portal]')
                && str_contains($job->message, 'Director Authorization Needed')
                && str_contains($job->message, 'Deduction: Rp 150.000')
                && str_contains($job->message, route('qc.director-approve', ['token' => $this->token, 'as' => 'director@example.test']))
                && ! str_contains($job->message, route('qc.director-decline', ['token' => $this->token, 'as' => 'director@example.test']));
        });
    }

    public function test_director_approval_skips_whatsapp_when_no_phone_configured(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_phone'],
            ['value' => '', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))->assertOk();

        Queue::assertNotPushed(SendWhatsAppNotification::class);
        // Email still goes out regardless — phone is additive, not a replacement.
        Queue::assertPushed(SendQcNotificationEmail::class, fn ($job) => ($job->payload['view'] ?? null) === 'emails.qc-director-approval');
    }

    public function test_ho_form_renders_validate_step_after_approval(): void
    {
        Queue::fake();
        Mail::fake();

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]))
            ->assertRedirect(route('qc.ho-approve', ['token' => $this->token]));

        // The same form now renders step 2: the editable inputs POST to the
        // Validate & Send action (full deduction list, replace semantics)
        // + the live RAF run status (queued as 'pending' by the approve).
        $this->get(route('qc.ho-approve', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('Validate &amp; Send Approval', false)
            ->assertSee(route('qc.ho-send.submit', ['token' => $this->token]), false)
            ->assertSee('deductions_present', false)
            ->assertSee('RAF production run is still pending', false)
            ->assertDontSee('Review &amp; Approve', false);
    }

    public function test_validate_and_send_recalculates_revised_numbers_and_replaces_deductions(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Local order matching the seeded QMS project's production group, with
        // the cutting-approval snapshot MD Production is about to revise.
        $vendor = new \App\Models\Vendor([
            'name' => 'Validate Vendor', 'vendor_code' => 'V_QCVAL', 'type' => 'subcon', 'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();
        $order = \App\Models\SubconOrder::create([
            'order_number' => 'MPG/PO/TEST/00001',
            'vendor_id' => $vendor->id,
            'title' => 'Validate Test Article',
            'status' => 'in_progress',
            'order_date' => now(),
            'production_group' => 'MPG/PRG/TEST/000001',
            'workflow_stage' => \App\Models\SubconOrder::STAGE_COMPLETED,
        ]);
        \App\Models\SubconCuttingReport::create([
            'order_id' => $order->id, 'prod_id' => 'PROD-1', 'size' => 'M', 'cutting_qty' => 100, 'gramasi' => 180,
        ]);
        \App\Models\SubconFabricReconciliation::create([
            'order_id' => $order->id, 'label' => 'Main Fabric (M)',
            'fabric_sent' => 250, 'consumption_plan' => 2.4, 'fabric_price' => 10000,
        ]);
        DB::connection('qms')->table('packaging_session_deduction_lines')->insert([
            'session_id' => $this->sessionId, 'description' => 'Old row', 'amount' => 5000, 'created_by' => 'MPG HO - MD Production',
        ]);

        // Approved but not yet sent.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null, 'director_approval_signature' => null]);

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]), [
            'deductions_present' => '1',
            'fabrics' => [[
                'label' => 'Main Fabric (M)',
                'fabric_sent' => 300, 'consumption_plan' => 2.5, 'fabric_price' => 10000,
                'retur_kain' => 10,
            ]],
            'deductions' => [['description' => 'Label reprint', 'amount' => 25000]],
        ])->assertOk()->assertSee('the Director has been notified', false);

        // Revised consumption recomputed by the shared engine and overwritten:
        // actual = (300 − 10) / 100 = 2.9; over = (2.9 − 2.5) / 2.5 = 16%;
        // deduction = (2.9 − 2.5 × 1.03) × 100 × 10000 = 325,000.
        $recon = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)->where('label', 'Main Fabric (M)')->first();
        $this->assertSame(300.0, (float) $recon->fabric_sent);
        $this->assertSame(2.9, (float) $recon->actual_consumption);
        $this->assertSame(0.16, (float) $recon->overconsumption);
        $this->assertSame(325000.0, (float) $recon->deduction);

        // Re-published to QMS under the project.
        $line = DB::connection('qms')->table('packaging_project_fabric_lines')
            ->where('production_group', 'MPG/PRG/TEST/000001')->where('label', 'Main Fabric (M)')->first();
        $this->assertNotNull($line);
        $this->assertSame($this->projectId, $line->project_id);
        $this->assertSame(325000.0, (float) $line->deduction);

        // Deduction rows replaced, not appended.
        $deductions = DB::connection('qms')->table('packaging_session_deduction_lines')
            ->where('session_id', $this->sessionId)->get();
        $this->assertCount(1, $deductions);
        $this->assertSame('Label reprint', $deductions[0]->description);
        $this->assertSame(25000.0, (float) $deductions[0]->amount);

        // Signed + sent to the Director.
        $sig = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('ho_validation_signature');
        $this->assertStringStartsWith('Digitally Signed:', (string) $sig);
        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-director-approval';
        });
    }

    /**
     * Regression: SubconFabricLinePublisher must never zero-out a QMS fabric
     * line that already has good data just because the local reconciliation
     * row for that label is currently missing (wiped, or never created). A
     * label the local table DOES have a row for should still publish/update
     * normally.
     */
    public function test_fabric_publish_never_overwrites_existing_qms_line_with_no_local_data(): void
    {
        $vendor = new \App\Models\Vendor([
            'name' => 'Publisher Test Vendor', 'vendor_code' => 'V_PUBTEST', 'type' => 'subcon', 'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        $order = \App\Models\SubconOrder::create([
            'order_number' => 'MPG/PO/TEST/00099',
            'vendor_id' => $vendor->id,
            'title' => 'Publisher Test Article',
            'status' => 'in_progress',
            'order_date' => now(),
            'production_group' => 'MPG/PRG/TEST/000001', // matches seeded QMS project
            'workflow_stage' => \App\Models\SubconOrder::STAGE_COMPLETED,
        ]);

        // QMS already has a good, real snapshot for "Orphan Fabric" — but the
        // local reconciliation table has nothing for it (wiped, or never
        // created locally at all).
        DB::connection('qms')->table('packaging_project_fabric_lines')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $this->projectId,
            'production_group' => 'MPG/PRG/TEST/000001',
            'label' => 'Orphan Fabric (YD)',
            'fabric_sent' => 250, 'consumption_plan' => 2.4, 'cutt_plan' => 100,
            'actual_consumption' => 2.5, 'short_roll' => 1, 'sisa_kain' => 2,
            'kepala_kain' => 3, 'return_kain' => 4, 'fabric_price' => 10000,
            'created_by' => 'web_portal', 'created_at' => now(),
        ]);

        // A DIFFERENT label DOES have a local row — this one should publish
        // normally (create/update in QMS with the local figures).
        \App\Models\SubconFabricReconciliation::create([
            'order_id' => $order->id, 'label' => 'Known Fabric (YD)',
            'fabric_sent' => 500, 'consumption_plan' => 1.5, 'fabric_price' => 20000,
        ]);

        app(\App\Services\SubconFabricLinePublisher::class)->publish($order);

        // Orphan label: QMS row untouched (still the original good values).
        $orphan = DB::connection('qms')->table('packaging_project_fabric_lines')
            ->where('production_group', 'MPG/PRG/TEST/000001')->where('label', 'Orphan Fabric (YD)')->first();
        $this->assertSame(250.0, (float) $orphan->fabric_sent);
        $this->assertSame(2.4, (float) $orphan->consumption_plan);

        // Known label: published fresh from local data.
        $known = DB::connection('qms')->table('packaging_project_fabric_lines')
            ->where('production_group', 'MPG/PRG/TEST/000001')->where('label', 'Known Fabric (YD)')->first();
        $this->assertNotNull($known);
        $this->assertSame(500.0, (float) $known->fabric_sent);
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
            ->assertSee(route('qc.director-approve', ['token' => $this->token]), false)
            // No fabric/deduction rows seeded for this session → "None" badge.
            ->assertSee('None');
    }

    public function test_director_tab_shows_deduction_amount_when_present(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        DB::connection('qms')->table('packaging_project_fabric_lines')->insert([
            'id' => (string) Str::uuid(), 'project_id' => $this->projectId, 'production_group' => 'MPG/PRG/TEST/000001',
            'label' => 'Main Fabric', 'deduction' => 150000, 'created_by' => 'test', 'created_at' => now(),
        ]);
        DB::connection('qms')->table('packaging_session_deduction_lines')->insert([
            'session_id' => $this->sessionId, 'description' => 'Label reprint', 'amount' => 25000, 'created_by' => 'test',
        ]);

        // Same total the Director's Authorize action will actually queue to RPA
        // (RpaQueueService::deductionTotal): 150,000 + 25,000 = 175,000.
        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertSee('Rp 175.000')
            ->assertDontSee('None');
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

    public function test_removed_project_is_still_listed_counted_and_actionable(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Archived/hidden on a device in the QC console — NOT a signal that
        // the approval workflow is done (see SubconOrder::QMS_INACTIVE_PROJECT_STATUSES).
        // The project is restorable via a PRG re-scan, so it must remain
        // visible and actionable until it actually finishes the chain.
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['status' => 'removed']);

        // Still on the Director tab and its badge…
        $this->assertSame(1, \App\Models\SubconOrder::pendingDirectorApprovalCount());
        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertSee(route('qc.director-approve', ['token' => $this->token]), false);

        // …and the Director link still signs it.
        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('Director authorization recorded');

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->first();
        $this->assertNotEmpty($session->director_approval_signature);
    }

    public function test_removed_completed_project_is_not_listed_counted_or_actionable(): void
    {
        Queue::fake();
        Mail::fake();

        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@example.test', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );

        // Archived in the QC console AFTER the project was already completed.
        DB::connection('qms')->table('packaging_projects')
            ->where('project_id', $this->projectId)
            ->update(['status' => 'removed_completed']);

        // Off the Director tab and its badge…
        $this->assertSame(0, \App\Models\SubconOrder::pendingDirectorApprovalCount());
        $this->actingAs($this->makeSubconAdmin('director@example.test', 'Director'))
            ->get(route('subcon.admin.director-approvals'))
            ->assertOk()
            ->assertDontSee(route('qc.director-approve', ['token' => $this->token]), false);

        // …the Director link refuses…
        $this->post(route('qc.director-approve.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('has been removed');

        // …and Validate & Send refuses too (no pointless Director email).
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null]);
        $this->assertSame(0, \App\Models\SubconOrder::pendingValidateSendCount());
        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('has been removed');
        Queue::assertNotPushed(SendQcNotificationEmail::class);
    }

    public function test_director_badge_matches_the_tab(): void
    {
        // Approved but NOT validated & sent → in neither the badge nor the tab.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => null]);
        $this->assertSame(0, \App\Models\SubconOrder::pendingDirectorApprovalCount());

        // Validated & sent → in both.
        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_validation_signature' => 'Digitally Signed: MPG HO - MD Production [UTC+07:00: 2026-07-17 10:00:00]']);
        $this->assertSame(1, \App\Models\SubconOrder::pendingDirectorApprovalCount());
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
        ])->assertRedirect();

        $sig = (string) DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)->value('ho_approval_signature');
        $this->assertStringStartsWith('Digitally Signed: Fitri Yeni <fitri.yeni@megaputragarment.co.id>', $sig);
    }

    public function test_ho_approval_notifies_the_earlier_participants(): void
    {
        Queue::fake();
        Mail::fake();

        DB::connection('qms')->table('packaging_project_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['ho_approval_signature' => null, 'ho_validation_signature' => null, 'director_approval_signature' => null]);

        // The stage-progress notice fires at Validate & Send (that is when the
        // Director stage actually opens), not at Review & Approve.
        $this->post(route('qc.ho-approve.submit', ['token' => $this->token]))->assertRedirect();
        Queue::assertNotPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-stage-update';
        });

        $this->post(route('qc.ho-send.submit', ['token' => $this->token]))->assertOk();

        // QC inspector + factory representative are told MD Production approved.
        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-stage-update'
                && ($job->payload['viewData']['stage'] ?? null) === 'MD Production'
                && ($job->payload['recipients'] ?? []) === ['qc-inspector@example.test', 'factoryrep@example.test'];
        });
    }

    public function test_factory_approval_notifies_the_inspector(): void
    {
        Queue::fake();
        Mail::fake();

        $this->seedFreshUnapprovedSession();

        $this->get(route('qc.approve', ['token' => $this->token]))->assertOk();

        Queue::assertPushed(SendQcNotificationEmail::class, function ($job) {
            return ($job->payload['view'] ?? null) === 'emails.qc-stage-update'
                && ($job->payload['viewData']['stage'] ?? null) === 'Factory Representative'
                && ($job->payload['recipients'] ?? []) === ['qc-inspector@example.test'];
        });
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
                'ho_validation_signature' => null,
                'director_approval_signature' => null,
            ]);
    }
}
