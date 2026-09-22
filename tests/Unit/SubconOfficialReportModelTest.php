<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_deduction_sums_reject_penalty_and_additional_deduction()
    {
        $this->ensureTableExists();

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
        $this->ensureTableExists();

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
        $this->ensureTableExists();

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

    private function ensureTableExists(): void
    {
        if (! DB::connection('sqlite')->getSchemaBuilder()->hasTable('subcon_official_reports')) {
            DB::connection('sqlite')->statement($this->getCreateTableSQL());
        }
    }

    private function getCreateTableSQL(): string
    {
        return <<<'SQL'
CREATE TABLE subcon_official_reports (
    id TEXT PRIMARY KEY,
    production_group TEXT NOT NULL,
    operation TEXT NOT NULL,
    status TEXT DEFAULT 'pending_ho' NOT NULL,
    revision_count INTEGER DEFAULT 0 NOT NULL,
    matched_po_number TEXT,
    matched_po_vendor_code TEXT,
    matched_po_vendor_name TEXT,
    order_qty INTEGER DEFAULT 0 NOT NULL,
    cut_qty INTEGER DEFAULT 0 NOT NULL,
    good_qty INTEGER DEFAULT 0 NOT NULL,
    reject_qty INTEGER DEFAULT 0 NOT NULL,
    sales_price REAL,
    sales_price_source TEXT,
    allowed_limit INTEGER DEFAULT 0 NOT NULL,
    exceeding_reject_qty INTEGER DEFAULT 0 NOT NULL,
    penalty_price REAL DEFAULT 0 NOT NULL,
    reject_penalty REAL DEFAULT 0 NOT NULL,
    additional_deduction REAL DEFAULT 0 NOT NULL,
    remarks TEXT,
    ho_approval_signature TEXT,
    ho_approved_at TIMESTAMP,
    director_approval_signature TEXT,
    director_approved_at TIMESTAMP,
    director_decline_reason TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(production_group, operation)
)
SQL;
    }
}
