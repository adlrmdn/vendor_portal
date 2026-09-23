<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubconOfficialReportFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SubconOfficialReport::query()->where('production_group', 'MPG/PRG/2512/000014')->delete();
        User::query()->where('email', 'flowtest@test.com')->delete();
    }

    protected function tearDown(): void
    {
        SubconOfficialReport::query()->where('production_group', 'MPG/PRG/2512/000014')->delete();
        User::query()->where('email', 'flowtest@test.com')->delete();
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice_addprocess', 'deduction_addprocess'])
            ->where('entity_id', 'like', 'MPG/PRG/2512/000014:%')
            ->delete();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = new User(['name' => 'Flow Admin', 'email' => 'flowtest@test.com', 'password' => bcrypt('password'), 'role' => 'subcon_admin']);
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->save();

        return $user;
    }

    private function seedSettings(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_ho_approver_email'],
            ['value' => 'ho@test.com', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'qc_director_approver_email'],
            ['value' => 'director@test.com', 'group' => 'subcon', 'type' => 'string', 'description' => 'test']
        );
    }

    public function test_full_send_to_director_approval_happy_path()
    {
        Mail::fake();
        $this->seedSettings();

        $admin = $this->admin();

        // Send — real PRG/operation verified reachable against live D365/VSM earlier this session.
        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), [
                'production_group' => 'MPG/PRG/2512/000014',
                'operation' => 'ADD-Was',
                'additional_deduction' => 0,
                'remarks' => 'Test send',
            ]);

        $response->assertSessionHas('success');
        $report = SubconOfficialReport::where('production_group', 'MPG/PRG/2512/000014')->where('operation', 'ADD-Was')->firstOrFail();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);

        // HO approves in-app.
        $response2 = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.ho-approve', $report->id), []);
        $response2->assertSessionHas('success');
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);

        // Director approves in-app.
        $response3 = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);
        $response3->assertSessionHas('success');
        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);

        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/2512/000014:ADD-Was',
            'rpa_type' => 'invoice_addprocess',
        ], 'rpa');
    }

    public function test_director_decline_then_resend_then_approve()
    {
        Mail::fake();
        $this->seedSettings();
        $admin = $this->admin();

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), ['production_group' => 'MPG/PRG/2512/000014', 'operation' => 'ADD-Was']);
        $report = SubconOfficialReport::where('production_group', 'MPG/PRG/2512/000014')->where('operation', 'ADD-Was')->firstOrFail();

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.ho-approve', $report->id), []);

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-decline', $report->id), ['reason' => 'Please recheck.']);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);
        $this->assertSame(1, $report->revision_count);
        $this->assertTrue($report->needsRevision());

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.ho-approve', $report->id), ['additional_deduction' => 10000]);
        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.director-approve', $report->id), []);

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $this->assertSame(10000.0, $report->additional_deduction);
    }

    public function test_send_blocked_when_gate_fails()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('subcon.admin.official-reports.send'), [
                'production_group' => 'MPG/PRG/DOES/NOT/EXIST',
                'operation' => 'ADD-Was',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('subcon_official_reports', ['production_group' => 'MPG/PRG/DOES/NOT/EXIST']);
    }
}
