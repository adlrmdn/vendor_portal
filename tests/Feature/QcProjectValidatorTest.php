<?php

namespace Tests\Feature;

use App\Models\SubconOrder;
use App\Models\Vendor;
use App\Services\QcProjectValidator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QcProjectValidator runs at report generation (GET /qc/print) and corrects a stale
 * PO/vendor snapshot on a QC project. The shared QMS and VSM databases are faked with
 * in-memory SQLite, as in QcDirectorApprovalTest.
 */
class QcProjectValidatorTest extends TestCase
{
    private const GROUP = 'MPG/PRG/2607/000003';

    private const CMT_PO = 'MPG/PO/2607/00754';

    private const ADDPRO_PO = 'MPG/PO/2607/00755';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['qms', 'vsm'] as $name) {
            Config::set("database.connections.$name", [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ]);
            DB::purge($name);
        }

        DB::connection('qms')->statement('CREATE TABLE packaging_projects (
            project_id TEXT PRIMARY KEY, production_group TEXT, po_info TEXT, po_vendor TEXT, status TEXT, updated_at TEXT)');
        DB::connection('qms')->statement('CREATE TABLE packaging_project_reports (
            report_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id TEXT, session_id TEXT, size_val TEXT)');
        $vsm = DB::connection('vsm');
        $vsm->statement('CREATE TABLE production_groups (ProductionGroup TEXT PRIMARY KEY, PONumber TEXT)');
        $vsm->statement('CREATE TABLE po_headers (PurchaseOrderNumber TEXT PRIMARY KEY, PurchaseOrderName TEXT, PurchPoolId TEXT)');
        $vsm->statement('CREATE TABLE production_group_lines (ProdId TEXT PRIMARY KEY, ProductionGroup TEXT, Size TEXT)');
        $vsm->table('po_headers')->insert([
            ['PurchaseOrderNumber' => self::CMT_PO, 'PurchaseOrderName' => 'PT WORLD KNK SURYA ANUGERAH', 'PurchPoolId' => 'CMT'],
            ['PurchaseOrderNumber' => self::ADDPRO_PO, 'PurchaseOrderName' => 'PD CAHAYA BINTANG TIMUR', 'PurchPoolId' => 'AddPro'],
        ]);

        $this->useWritableDefaultDatabase();

        SubconOrder::query()->where('production_group', self::GROUP)->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_QCVAL%')->delete();
    }

    /**
     * The sqlite test-DB file is read-only for the container user, so point the
     * default connection at a fresh writable temp copy (same as QcDirectorApprovalTest).
     */
    private function useWritableDefaultDatabase(): void
    {
        $default = config('database.default');
        $source = config("database.connections.$default.database");
        if ($default !== 'sqlite' || ! is_string($source) || ! is_file($source)) {
            return;
        }

        $copy = sys_get_temp_dir().'/qc_validator_test_'.getmypid().'.sqlite';
        copy($source, $copy);
        Config::set("database.connections.$default.database", $copy);
        DB::purge($default);
    }

    private function project(string $po, string $vendor, string $status = 'downloaded'): void
    {
        DB::connection('qms')->table('packaging_projects')->insert([
            'project_id' => 'PRJ-1', 'production_group' => self::GROUP, 'po_info' => $po,
            'po_vendor' => $vendor, 'status' => $status, 'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function localOrder(string $number): void
    {
        $vendor = new Vendor(['name' => 'PT WORLD KNK SURYA ANUGERAH', 'vendor_code' => 'V_QCVAL_'.Str::random(4), 'type' => 'subcon', 'is_active' => true]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        SubconOrder::create([
            'order_number' => $number, 'vendor_id' => $vendor->id, 'title' => 'QC validator PO', 'status' => 'in_progress',
            'production_group' => self::GROUP, 'order_date' => '2026-07-13', 'due_date' => '2026-08-14',
            'workflow_stage' => SubconOrder::STAGE_CUTTING,
        ]);
    }

    private function stored(): object
    {
        return DB::connection('qms')->table('packaging_projects')->where('project_id', 'PRJ-1')->first();
    }

    public function test_corrects_an_addpro_po_and_its_vendor_to_the_cmt_po(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::ADDPRO_PO, 'PD CAHAYA BINTANG TIMUR');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame(['po_info', 'po_vendor'], array_keys($result['healed']));
        $this->assertSame(self::CMT_PO, $this->stored()->po_info);
        $this->assertSame('PT WORLD KNK SURYA ANUGERAH', $this->stored()->po_vendor);
    }

    public function test_corrects_only_the_vendor_when_the_po_is_already_right(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::CMT_PO, 'PD CAHAYA BINTANG TIMUR');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame(['po_vendor'], array_keys($result['healed']));
        $this->assertSame(self::CMT_PO, $this->stored()->po_info);
        $this->assertSame('PT WORLD KNK SURYA ANUGERAH', $this->stored()->po_vendor);
    }

    public function test_a_correct_project_is_left_untouched(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::CMT_PO, 'PT WORLD KNK SURYA ANUGERAH');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame([], $result['healed']);
        $this->assertSame('2026-01-01 00:00:00', $this->stored()->updated_at);
    }

    public function test_falls_back_to_vsm_when_there_is_no_synced_order(): void
    {
        DB::connection('vsm')->table('production_groups')->insert(['ProductionGroup' => self::GROUP, 'PONumber' => self::CMT_PO]);
        $this->project(self::ADDPRO_PO, 'PD CAHAYA BINTANG TIMUR');

        app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame(self::CMT_PO, $this->stored()->po_info);
        $this->assertSame('PT WORLD KNK SURYA ANUGERAH', $this->stored()->po_vendor);
    }

    public function test_never_falls_back_to_a_non_cmt_vsm_po(): void
    {
        DB::connection('vsm')->table('production_groups')->insert(['ProductionGroup' => self::GROUP, 'PONumber' => self::ADDPRO_PO]);
        $this->project(self::CMT_PO, 'PT WORLD KNK SURYA ANUGERAH');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame([], $result['healed']);
        $this->assertSame(self::CMT_PO, $this->stored()->po_info);
    }

    public function test_a_multi_po_project_that_still_contains_a_group_po_is_kept(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project('MPG/PO/2606/01579, '.self::CMT_PO, 'CV ONE, CV TWO');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame([], $result['healed']);
        $this->assertSame('CV ONE, CV TWO', $this->stored()->po_vendor);
    }

    public function test_an_ambiguous_group_is_reported_not_guessed(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->localOrder('MPG/PO/2607/00760');
        $this->project(self::ADDPRO_PO, 'PD CAHAYA BINTANG TIMUR');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertArrayNotHasKey('po_info', $result['healed']);
        $this->assertSame(self::ADDPRO_PO, $this->stored()->po_info);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_completed_projects_are_never_touched(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::ADDPRO_PO, 'PD CAHAYA BINTANG TIMUR', 'completed');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1');

        $this->assertSame([], $result['healed']);
        $this->assertSame(self::ADDPRO_PO, $this->stored()->po_info);
    }

    public function test_reports_missing_sizes_without_writing_report_rows(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::CMT_PO, 'PT WORLD KNK SURYA ANUGERAH');
        foreach (['L', 'M'] as $size) {
            DB::connection('qms')->table('packaging_project_reports')->insert(['project_id' => 'PRJ-1', 'session_id' => 'S1', 'size_val' => $size]);
        }
        foreach (['L', 'M', 'XXXL'] as $i => $size) {
            DB::connection('vsm')->table('production_group_lines')->insert(['ProdId' => "P$i", 'ProductionGroup' => self::GROUP, 'Size' => $size]);
        }

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1', 'S1');

        $this->assertSame([], $result['healed']);
        $this->assertStringContainsString('XXXL', implode(' ', $result['warnings']));
        $this->assertSame(2, DB::connection('qms')->table('packaging_project_reports')->count());
    }

    public function test_an_unreachable_vsm_never_blocks_the_report(): void
    {
        $this->localOrder(self::CMT_PO);
        $this->project(self::CMT_PO, 'PT WORLD KNK SURYA ANUGERAH');
        DB::connection('vsm')->statement('DROP TABLE po_headers');
        DB::connection('vsm')->statement('DROP TABLE production_group_lines');

        $result = app(QcProjectValidator::class)->validateAndHeal('PRJ-1', 'S1');

        $this->assertSame([], $result['healed']);
        $this->assertSame(self::CMT_PO, $this->stored()->po_info);
    }
}
