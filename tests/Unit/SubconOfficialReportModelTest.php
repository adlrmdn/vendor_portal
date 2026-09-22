<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubconOfficialReportModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SubconOfficialReport::query()->delete();
    }

    public function test_total_deduction_sums_reject_penalty_and_additional_deduction()
    {
        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000001',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'reject_penalty' => 150000.0,
            'additional_deduction' => 25000.0,
        ]);

        $this->assertSame(175000.0, $report->totalDeduction());
    }

    public function test_needs_revision_is_true_only_for_pending_ho_with_revisions()
    {
        $fresh = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000002',
            'operation' => 'ADD-Embro',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'revision_count' => 0,
        ]);
        $this->assertFalse($fresh->needsRevision());

        $resend = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000003',
            'operation' => 'ADD-Embro',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'revision_count' => 1,
        ]);
        $this->assertTrue($resend->needsRevision());

        $pendingDirector = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000004',
            'operation' => 'ADD-Embro',
            'status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR,
            'revision_count' => 1,
        ]);
        $this->assertFalse($pendingDirector->needsRevision());
    }

    public function test_unique_constraint_on_production_group_and_operation()
    {
        SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000005',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2601/000005',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
        ]);
    }
}
