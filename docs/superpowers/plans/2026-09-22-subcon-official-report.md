# Subcon Official Report (Berita Acara) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-Add-Process-operation "Official Report (Berita Acara)" — a two-signature (MD Production → Director) sign-off with a reject-tolerance deduction, that queues Invoice/Deduction RPA jobs on Director approval.

**Architecture:** New local table `subcon_official_reports` (one row per `(production_group, operation)`), a new `SubconOfficialReportService` for gating/deduction/state transitions, a new `SubconOfficialReportController` with both signed-email and in-app entry points (mirroring `SubconApprovalController`'s dual-entry pattern), and two small additions to existing D365/RPA services. Merges into two existing admin tabs (Report Validation, Director Approvals) instead of new list pages.

**Tech Stack:** Laravel 12 / PHP 8.2+, Blade + Bootstrap 5, `barryvdh/laravel-dompdf`, PostgreSQL (`vsm`/`qms`/`rpa` external connections + local app DB), PHPUnit (`database/database_test.sqlite`).

**Spec:** `docs/superpowers/specs/2026-09-22-subcon-official-report-design.md`

## Global Constraints

- One report row per `(production_group, operation)` — never per production group alone (spec §2).
- New table lives on the app's default (local) connection — never `qms` (spec §3).
- Status values are exactly `pending_ho` | `pending_director` | `completed` — no persisted `draft` state; a row is created directly in `pending_ho` at first send (spec §3–4).
- Gating (cutting approved AND not-yet-invoiced) is re-verified server-side on every send/resend POST, never trusted from the client (spec §5).
- Delivery uses Laravel signed routes (`signed:relative`), never a bearer-token column (spec §6).
- Recipient emails reuse existing `Setting` keys `qc_ho_approver_email` / `qc_director_approver_email` — no new Setting keys (spec §6).
- Deduction formula is the exact shape from `QcReportPdfService::calculateDeductions()`, scoped to one operation, with no "barang hilang" term (spec §8).
- RPA queueing on Director approval uses new `rpa_type`s (`invoice_addprocess`/`deduction_addprocess`) — never routes through `directorApprove()` or the existing `queueInvoice()`/`queueDeduction()` (spec §10).
- Every mutating email-link action is POST-only behind a GET that only renders (prefetch-safe split) — never a mutating GET (spec §6, and the app-wide rule in CLAUDE.md's QC/HO/Director section).
- All state-transition writes wrapped in `DB::transaction()` (spec §11).
- D365 fetch, RPA queueing, and email sending are all best-effort/try-catch — never block the underlying state transition (spec §11).

---

## Task 1: Migration + `SubconOfficialReport` model

**Files:**
- Create: `database/migrations/2026_09_22_000001_create_subcon_official_reports_table.php`
- Create: `app/Models/SubconOfficialReport.php`
- Test: `tests/Unit/SubconOfficialReportModelTest.php`

**Interfaces:**
- Produces: `SubconOfficialReport` Eloquent model, uuid PK, table `subcon_official_reports`, unique on `(production_group, operation)`. Status constants `SubconOfficialReport::STATUS_PENDING_HO`, `::STATUS_PENDING_DIRECTOR`, `::STATUS_COMPLETED`. Instance method `totalDeduction(): float`.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcon_official_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('production_group');
            // Raw D365 Operation value (e.g. "ADD-Was") — same casing used
            // throughout Add Process (SubconAddProcessReport, D365 sync).
            $table->string('operation');
            $table->string('status')->default('pending_ho');
            $table->unsignedInteger('revision_count')->default(0);

            // Snapshot of the matched AddPro PO at send time.
            $table->string('matched_po_number')->nullable();
            $table->string('matched_po_vendor_code')->nullable();
            $table->string('matched_po_vendor_name')->nullable();

            // Yield snapshot at send time.
            $table->unsignedInteger('order_qty')->default(0);
            $table->unsignedInteger('cut_qty')->default(0);
            $table->unsignedInteger('good_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);

            // Deduction inputs/outputs.
            $table->decimal('sales_price', 15, 2)->nullable();
            $table->string('sales_price_source')->nullable(); // 'd365' | 'manual'
            $table->unsignedInteger('allowed_limit')->default(0);
            $table->unsignedInteger('exceeding_reject_qty')->default(0);
            $table->decimal('penalty_price', 15, 2)->default(0);
            $table->decimal('reject_penalty', 15, 2)->default(0);
            $table->decimal('additional_deduction', 15, 2)->default(0);
            $table->text('remarks')->nullable();

            // Signatures.
            $table->string('ho_approval_signature')->nullable();
            $table->timestamp('ho_approved_at')->nullable();
            $table->string('director_approval_signature')->nullable();
            $table->timestamp('director_approved_at')->nullable();
            $table->text('director_decline_reason')->nullable();

            $table->timestamps();

            $table->unique(['production_group', 'operation']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcon_official_reports');
    }
};
```

- [ ] **Step 2: Run the migration on the test DB and verify the table exists**

Run: `docker exec vendor_portal_app php artisan migrate --database=sqlite --env=testing` — if that connection name doesn't resolve, instead run `docker exec vendor_portal_app php artisan test --filter=NoSuchTest` once (PHPUnit auto-migrates `database/database_test.sqlite` per `phpunit.xml`), then:
```
docker exec vendor_portal_app php artisan tinker --execute="echo Schema::hasTable('subcon_official_reports') ? 'yes' : 'no';"
```
Expected: `yes` (after also running `docker exec vendor_portal_app php artisan migrate` against the real dev DB so the app itself picks it up).

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconOfficialReport extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    public const STATUS_PENDING_HO = 'pending_ho';

    public const STATUS_PENDING_DIRECTOR = 'pending_director';

    public const STATUS_COMPLETED = 'completed';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'production_group',
        'operation',
        'status',
        'revision_count',
        'matched_po_number',
        'matched_po_vendor_code',
        'matched_po_vendor_name',
        'order_qty',
        'cut_qty',
        'good_qty',
        'reject_qty',
        'sales_price',
        'sales_price_source',
        'allowed_limit',
        'exceeding_reject_qty',
        'penalty_price',
        'reject_penalty',
        'additional_deduction',
        'remarks',
        'ho_approval_signature',
        'ho_approved_at',
        'director_approval_signature',
        'director_approved_at',
        'director_decline_reason',
    ];

    protected $casts = [
        'id' => 'string',
        'revision_count' => 'integer',
        'order_qty' => 'integer',
        'cut_qty' => 'integer',
        'good_qty' => 'integer',
        'reject_qty' => 'integer',
        'allowed_limit' => 'integer',
        'exceeding_reject_qty' => 'integer',
        'sales_price' => 'float',
        'penalty_price' => 'float',
        'reject_penalty' => 'float',
        'additional_deduction' => 'float',
        'ho_approved_at' => 'datetime',
        'director_approved_at' => 'datetime',
    ];

    /** reject_penalty + additional_deduction — the figure RPA queueing and the PDF both use. */
    public function totalDeduction(): float
    {
        return (float) $this->reject_penalty + (float) $this->additional_deduction;
    }

    public function isPendingHo(): bool
    {
        return $this->status === self::STATUS_PENDING_HO;
    }

    public function isPendingDirector(): bool
    {
        return $this->status === self::STATUS_PENDING_DIRECTOR;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** A pending_ho row that's actually a post-decline resend, not a first send. */
    public function needsRevision(): bool
    {
        return $this->isPendingHo() && $this->revision_count > 0;
    }
}
```

- [ ] **Step 4: Write and run a plain model test**

```php
<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use Tests\TestCase;

class SubconOfficialReportModelTest extends TestCase
{
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
```

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportModelTest`
Expected: 3 passed.

- [ ] **Step 5: Lint and commit**

```bash
docker exec vendor_portal_app php -l database/migrations/2026_09_22_000001_create_subcon_official_reports_table.php
docker exec vendor_portal_app php -l app/Models/SubconOfficialReport.php
docker exec vendor_portal_app php vendor/bin/pint app/Models/SubconOfficialReport.php database/migrations/2026_09_22_000001_create_subcon_official_reports_table.php tests/Unit/SubconOfficialReportModelTest.php
git add database/migrations/2026_09_22_000001_create_subcon_official_reports_table.php app/Models/SubconOfficialReport.php tests/Unit/SubconOfficialReportModelTest.php
git commit -m "subcon: add subcon_official_reports table + model"
```

---

## Task 2: D365 sales price + AddPro line unit price lookups

**Files:**
- Modify: `app/Services/D365JobTransactionService.php` (add `fetchSalesPrice`, near `fetchVendorNames`)
- Modify: `app/Services/SubconProductionService.php` (add `resolveAddProcessLineUnitPrice`, near `resolveAddProcessLineNumber`)
- Test: `tests/Unit/D365SalesPriceTest.php`
- Test: `tests/Unit/AddProcessLineUnitPriceTest.php`

**Interfaces:**
- Produces: `D365JobTransactionService::fetchSalesPrice(string $plmId): ?float`
- Produces: `SubconProductionService::resolveAddProcessLineUnitPrice(string $poNumber, string $operation): ?float`
- Consumes (Task 2's own tests only): `Illuminate\Support\Facades\Http::fake()`, `DB::connection('vsm')`

- [ ] **Step 1: Add `fetchSalesPrice` to `D365JobTransactionService`**

Add directly after the existing `fetchVendorNames()` method (confirm it's still there, then paste below it):

```php
    /**
     * Garment sales price direct from D365's `ReleasedProductMastersV2`
     * (`SalesPrice` field), keyed by item number = PLM id. Mirrors the QC
     * Console's own `fetch_odata_sales_price` (Rust) — reused here for the
     * Official Report's reject-tolerance deduction (penalty_price =
     * sales_price * 0.70, see SubconOfficialReportService). Best-effort:
     * null on any failure, a blank PLMId, or no matching D365 record —
     * callers must let the admin fill the price in by hand in that case.
     */
    public function fetchSalesPrice(string $plmId): ?float
    {
        $plmId = trim($plmId);
        if ($plmId === '') {
            return null;
        }

        $cacheKey = 'd365_sales_price_'.md5($plmId);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return null;
        }

        try {
            $filter = 'ItemNumber eq '.$this->odataLit($plmId);
            $rows = $this->odataGet($token, 'ReleasedProductMastersV2', $filter);
            $price = isset($rows[0]['SalesPrice']) ? (float) $rows[0]['SalesPrice'] : null;

            Cache::put($cacheKey, $price, now()->addHour());

            return $price;
        } catch (\Throwable $e) {
            Log::error('D365 sales price fetch exception: '.$e->getMessage());

            return null;
        }
    }
```

- [ ] **Step 2: Write the test for it (Http::fake, mirroring the existing D365 fetch test style)**

```php
<?php

namespace Tests\Unit;

use App\Services\D365JobTransactionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class D365SalesPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_null_for_blank_plm_id()
    {
        $service = app(D365JobTransactionService::class);
        $this->assertNull($service->fetchSalesPrice(''));
        $this->assertNull($service->fetchSalesPrice('   '));
    }

    public function test_returns_sales_price_from_d365_response()
    {
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response([
                'value' => [['SalesPrice' => 125000.5]],
            ], 200),
        ]);

        $service = $this->getMockBuilder(D365JobTransactionService::class)
            ->onlyMethods(['getD365Token'])
            ->getMock();
        $service->method('getD365Token')->willReturn('fake-token');

        $price = $service->fetchSalesPrice('PLM/26/04/00137');

        $this->assertSame(125000.5, $price);
    }

    public function test_returns_null_when_d365_has_no_matching_record()
    {
        Http::fake([
            '*/data/ReleasedProductMastersV2*' => Http::response(['value' => []], 200),
        ]);

        $service = $this->getMockBuilder(D365JobTransactionService::class)
            ->onlyMethods(['getD365Token'])
            ->getMock();
        $service->method('getD365Token')->willReturn('fake-token');

        $this->assertNull($service->fetchSalesPrice('PLM/DOES/NOT/EXIST'));
    }
}
```

Note: `getD365Token()` must be visible enough to mock (it already is `protected`/`private` elsewhere in this codebase's own tests via the same `getMockBuilder(...)->onlyMethods([...])` pattern used for D365-touching methods — if it is currently `private`, widen it to `protected` in this task since PHPUnit mocks can override `protected` but not `private`; check its current visibility first with `grep -n "function getD365Token" app/Services/D365JobTransactionService.php` and adjust only if needed).

- [ ] **Step 3: Run the test**

Run: `docker exec vendor_portal_app php artisan test --filter=D365SalesPriceTest`
Expected: 3 passed.

- [ ] **Step 4: Add `resolveAddProcessLineUnitPrice` to `SubconProductionService`**

Add directly after `resolveAddProcessLineNumber()`:

```php
    /**
     * Per-unit price (LineAmount ÷ OrderedPurchaseQuantity — never
     * PurchasePrice, same principle as resolveFabricPricing()) for the same
     * AddPro PO line resolveAddProcessLineNumber() would match for this
     * operation. A deliberate sibling method, not a change to that method's
     * return shape — resolveAddProcessLineNumber()'s existing caller
     * (SyncSubconAddProcessToD365, goods receive queueing) expects a bare
     * int and is already relied on; this keeps that contract untouched.
     *
     * Same KNOWN LIMITATION as resolveAddProcessLineNumber(): on a PO where
     * more than one line shares the same operation's description keyword
     * (see that method's docblock), this returns the lowest-numbered
     * matching line's price, which is a guess when those lines could carry
     * different amounts.
     */
    public function resolveAddProcessLineUnitPrice(string $poNumber, string $operation): ?float
    {
        try {
            $lines = DB::connection('vsm')->table('po_lines')
                ->where('PurchaseOrderNumber', $poNumber)
                ->orderBy('LineNumber')
                ->get(['LineNumber', 'LineDescription', 'LineAmount', 'OrderedPurchaseQuantity']);

            if ($lines->isEmpty()) {
                return null;
            }

            $keyword = $this->addProcessKeyword($operation);
            $match = null;
            if (! empty($keyword)) {
                $match = $lines->first(fn ($l) => str_contains(strtolower((string) ($l->LineDescription ?? '')), strtolower($keyword)));
            }
            $match ??= $lines->count() === 1 ? $lines->first() : null;

            $qty = (float) ($match->OrderedPurchaseQuantity ?? 0);
            if (! $match || $qty <= 0) {
                return null;
            }

            return (float) $match->LineAmount / $qty;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
```

- [ ] **Step 5: Write the test (real `vsm` insert/cleanup, mirroring `SubconCuttingReportTest`'s own `DB::connection('vsm')->table(...)->insert()` pattern)**

```php
<?php

namespace Tests\Unit;

use App\Services\SubconProductionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddProcessLineUnitPriceTest extends TestCase
{
    private string $po = 'MPG/PO/TEST/00001';

    protected function tearDown(): void
    {
        DB::connection('vsm')->table('po_lines')->where('PurchaseOrderNumber', $this->po)->delete();
        parent::tearDown();
    }

    public function test_resolves_price_by_operation_keyword_match()
    {
        DB::connection('vsm')->table('po_lines')->insert([
            ['PurchaseOrderNumber' => $this->po, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Sablon', 'LineAmount' => 500000, 'OrderedPurchaseQuantity' => 1000],
            ['PurchaseOrderNumber' => $this->po, 'LineNumber' => 2, 'LineDescription' => 'Item Jasa Add Process Embro', 'LineAmount' => 800000, 'OrderedPurchaseQuantity' => 1000],
        ]);

        $service = app(SubconProductionService::class);

        $this->assertSame(500.0, $service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Sab'));
        $this->assertSame(800.0, $service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Embro'));
    }

    public function test_returns_null_when_po_has_no_lines()
    {
        $service = app(SubconProductionService::class);

        $this->assertNull($service->resolveAddProcessLineUnitPrice('MPG/PO/DOES/NOT/EXIST', 'ADD-Was'));
    }

    public function test_returns_null_when_quantity_is_zero()
    {
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => $this->po, 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing', 'LineAmount' => 500000, 'OrderedPurchaseQuantity' => 0,
        ]);

        $service = app(SubconProductionService::class);

        $this->assertNull($service->resolveAddProcessLineUnitPrice($this->po, 'ADD-Was'));
    }
}
```

- [ ] **Step 6: Run the test**

Run: `docker exec vendor_portal_app php artisan test --filter=AddProcessLineUnitPriceTest`
Expected: 3 passed. (Requires the `vsm` connection to be reachable — same requirement every other VSM-touching test in this suite already has.)

- [ ] **Step 7: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Services/D365JobTransactionService.php
docker exec vendor_portal_app php -l app/Services/SubconProductionService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/D365JobTransactionService.php app/Services/SubconProductionService.php tests/Unit/D365SalesPriceTest.php tests/Unit/AddProcessLineUnitPriceTest.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Services/D365JobTransactionService.php app/Services/SubconProductionService.php tests/Unit/D365SalesPriceTest.php tests/Unit/AddProcessLineUnitPriceTest.php
git commit -m "subcon: add D365 sales price + AddPro line unit price lookups"
```
Expected: same pre-existing failure count as the session baseline (11 failures, all unrelated — verified earlier this session via stash-compare), no new failures.

---

## Task 3: `SubconOfficialReportService` — yield computation + deduction formula (pure logic)

**Files:**
- Create: `app/Services/SubconOfficialReportService.php`
- Test: `tests/Unit/SubconOfficialReportDeductionTest.php`

**Interfaces:**
- Consumes: `SubconProductionService::forProductionGroup()`, `::cutQuantitiesForGroup()` (both existing); `App\Models\SubconAddProcessReport` (existing).
- Produces: `SubconOfficialReportService::computeYield(string $productionGroup, string $operation): array{order_qty:int, cut_qty:int, good_qty:int, reject_qty:int}`; `SubconOfficialReportService::computeDeduction(int $cutQty, int $rejectQty, float $salesPrice, float $additionalDeduction = 0.0): array{allowed_limit:int, exceeding_reject_qty:int, penalty_price:float, reject_penalty:float, total_deduction:float}`.

- [ ] **Step 1: Write the failing tests (deduction formula — pure, no DB)**

```php
<?php

namespace Tests\Unit;

use App\Services\SubconOfficialReportService;
use Tests\TestCase;

class SubconOfficialReportDeductionTest extends TestCase
{
    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    public function test_no_deduction_when_reject_within_one_percent_tolerance()
    {
        // cut_qty=1000 -> allowed_limit=floor(1000*0.01)=10; reject=10 is within tolerance.
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 10, salesPrice: 100000.0);

        $this->assertSame(10, $result['allowed_limit']);
        $this->assertSame(0, $result['exceeding_reject_qty']);
        $this->assertSame(70000.0, $result['penalty_price']);
        $this->assertSame(0.0, $result['reject_penalty']);
        $this->assertSame(0.0, $result['total_deduction']);
    }

    public function test_deduction_charged_only_on_the_exceeding_qty()
    {
        // allowed_limit=floor(1000*0.01)=10; reject=15 -> exceeding=5.
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 15, salesPrice: 100000.0);

        $this->assertSame(10, $result['allowed_limit']);
        $this->assertSame(5, $result['exceeding_reject_qty']);
        $this->assertSame(70000.0, $result['penalty_price']); // 100000 * 0.70
        $this->assertSame(350000.0, $result['reject_penalty']); // 5 * 70000
        $this->assertSame(350000.0, $result['total_deduction']);
    }

    public function test_additional_deduction_adds_to_the_total_but_not_reject_penalty()
    {
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 15, salesPrice: 100000.0, additionalDeduction: 50000.0);

        $this->assertSame(350000.0, $result['reject_penalty']);
        $this->assertSame(400000.0, $result['total_deduction']);
    }

    public function test_zero_sales_price_yields_zero_penalty_even_with_exceeding_reject()
    {
        $result = $this->service()->computeDeduction(cutQty: 1000, rejectQty: 999, salesPrice: 0.0);

        $this->assertSame(0.0, $result['penalty_price']);
        $this->assertSame(0.0, $result['reject_penalty']);
    }

    public function test_zero_cut_qty_gives_zero_allowed_limit_so_any_reject_is_fully_charged()
    {
        $result = $this->service()->computeDeduction(cutQty: 0, rejectQty: 3, salesPrice: 10000.0);

        $this->assertSame(0, $result['allowed_limit']);
        $this->assertSame(3, $result['exceeding_reject_qty']);
        $this->assertSame(21000.0, $result['reject_penalty']); // 3 * 7000
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportDeductionTest`
Expected: FAIL — class `SubconOfficialReportService` not found.

- [ ] **Step 3: Write `SubconOfficialReportService` with `computeDeduction()` and `computeYield()`**

```php
<?php

namespace App\Services;

use App\Models\SubconAddProcessReport;

/**
 * Business logic for the Official Report (Berita Acara) — the per-Add-Process-
 * operation MD Production -> Director sign-off. See
 * docs/superpowers/specs/2026-09-22-subcon-official-report-design.md for the
 * full design; this class implements it.
 */
class SubconOfficialReportService
{
    public function __construct(
        private readonly SubconProductionService $production,
        private readonly D365JobTransactionService $d365,
    ) {}

    /**
     * Order/Cut/Good/Reject qty for one Add Process operation on a
     * production group, summed across every line VSM's own production
     * detail has for the group. Deliberately duplicates the same math
     * `resources/views/subcon/admin/add-process/index.blade.php` already
     * computes inline for its Yield/TOTAL row, rather than refactoring that
     * already-working view to call a shared method — this keeps the
     * Official Report's snapshot logic independent of a view template, and
     * matches this codebase's existing convention of tolerating this kind
     * of small duplication over coupling unrelated things (see
     * AdminController/VendorController::saveItemRolls in CLAUDE.md).
     *
     * @return array{order_qty: int, cut_qty: int, good_qty: int, reject_qty: int}
     */
    public function computeYield(string $productionGroup, string $operation): array
    {
        $groups = $this->production->forProductionGroup($productionGroup);
        $lines = collect($groups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);

        $reports = SubconAddProcessReport::where('production_group', $productionGroup)
            ->where('operation', $operation)
            ->get()
            ->keyBy('prod_id');

        return [
            'order_qty' => (int) $lines->sum('Qty'),
            'cut_qty' => (int) $lines->sum(fn ($l) => $cutQuantities[$l->Size]['good'] ?? 0),
            'good_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->qty ?? 0),
            'reject_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->reject_qty ?? 0),
        ];
    }

    /**
     * Per-operation port of QcReportPdfService::calculateDeductions()'s
     * formula shape (spec §8) — floor(cutQty*0.01) tolerance,
     * salesPrice*0.70 penalty price, charged only on the qty exceeding that
     * tolerance, plus a manual additional_deduction folded into the total.
     *
     * @return array{allowed_limit: int, exceeding_reject_qty: int, penalty_price: float, reject_penalty: float, total_deduction: float}
     */
    public function computeDeduction(int $cutQty, int $rejectQty, float $salesPrice, float $additionalDeduction = 0.0): array
    {
        $allowedLimit = (int) floor($cutQty * 0.01);
        $exceedingReject = max(0, $rejectQty - $allowedLimit);
        $penaltyPrice = $salesPrice * 0.70;
        $rejectPenalty = $exceedingReject * $penaltyPrice;

        return [
            'allowed_limit' => $allowedLimit,
            'exceeding_reject_qty' => $exceedingReject,
            'penalty_price' => $penaltyPrice,
            'reject_penalty' => $rejectPenalty,
            'total_deduction' => $rejectPenalty + $additionalDeduction,
        ];
    }
}
```

- [ ] **Step 4: Run the tests again**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportDeductionTest`
Expected: 5 passed.

- [ ] **Step 5: Lint and commit**

```bash
docker exec vendor_portal_app php -l app/Services/SubconOfficialReportService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/SubconOfficialReportService.php tests/Unit/SubconOfficialReportDeductionTest.php
git add app/Services/SubconOfficialReportService.php tests/Unit/SubconOfficialReportDeductionTest.php
git commit -m "subcon: add SubconOfficialReportService deduction formula + yield computation"
```

---

## Task 4: Gating logic (`gate()`) + `send()`

**Files:**
- Modify: `app/Services/SubconOfficialReportService.php`
- Test: `tests/Feature/SubconOfficialReportGatingTest.php`
- Test: `tests/Feature/SubconOfficialReportSendTest.php`

**Interfaces:**
- Consumes: `SubconOrder::STAGE_CUTTING`, `::STAGE_CUTTING_REVIEW` (existing constants); `SubconProductionService::matchAddProcessPoForOperation()`, `::addProcessPurchaseOrdersForGroup()`, `::cutQuantitiesForGroup()` (existing).
- Produces: `SubconOfficialReportService::gate(string $productionGroup, string $operation): array{ok: bool, reason: ?string, cmt_order: ?SubconOrder, matched_po: ?array}`; `SubconOfficialReportService::send(string $productionGroup, string $operation, float $additionalDeduction, ?string $remarks, ?float $salesPriceOverride = null): SubconOfficialReport` (throws `\RuntimeException` with the gate's `reason` on failure).

- [ ] **Step 1: Write the failing gating tests**

```php
<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Models\SubconOrder;
use App\Models\Vendor;
use App\Services\SubconOfficialReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportGatingTest extends TestCase
{
    private string $prg = 'MPG/PRG/TEST/GATE01';

    private string $jobTransactionId = 'MPG/JOB/TEST/GATE01';

    protected function setUp(): void
    {
        parent::setUp();
        SubconOfficialReport::query()->delete();
        SubconOrder::query()->where('production_group', $this->prg)->delete();
        $this->cleanupVsmFixtures();
    }

    protected function tearDown(): void
    {
        SubconOrder::query()->where('production_group', $this->prg)->delete();
        $this->cleanupVsmFixtures();
        parent::tearDown();
    }

    private function cleanupVsmFixtures(): void
    {
        DB::connection('vsm')->table('job_transaction_line_details')->where('JobTransactionId', $this->jobTransactionId)->delete();
        DB::connection('vsm')->table('job_transaction_headers')->where('ProductionGroup', $this->prg)->delete();
    }

    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    public function test_cmt_group_blocked_while_cutting_not_yet_approved()
    {
        $vendor = Vendor::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'V', 'vendor_code' => 'V_GATE', 'type' => 'subcon', 'is_active' => true]);
        SubconOrder::create([
            'order_number' => 'PO-GATE-01', 'vendor_id' => $vendor->id, 'title' => 'T',
            'status' => 'pending', 'order_date' => now(),
            'production_group' => $this->prg, 'workflow_stage' => SubconOrder::STAGE_CUTTING_REVIEW,
        ]);

        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cutting', strtolower($result['reason']));
    }

    public function test_cmt_group_passes_cutting_check_once_past_cutting_review()
    {
        $vendor = Vendor::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'V', 'vendor_code' => 'V_GATE2', 'type' => 'subcon', 'is_active' => true]);
        SubconOrder::create([
            'order_number' => 'PO-GATE-02', 'vendor_id' => $vendor->id, 'title' => 'T',
            'status' => 'pending', 'order_date' => now(),
            'production_group' => $this->prg, 'workflow_stage' => SubconOrder::STAGE_GRAMASI,
        ]);

        $result = $this->service()->gate($this->prg, 'ADD-Was');

        // No matched PO for this made-up PRG, so still blocked — but NOT on
        // the cutting-approval reason.
        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('cutting', strtolower($result['reason']));
    }

    public function test_in_house_group_blocked_when_no_cut_qty_synced()
    {
        // No SubconOrder at all for this PRG, and no vsm job_transaction_line_details rows.
        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('cut', strtolower($result['reason']));
    }

    public function test_in_house_group_passes_cutting_check_when_cut_qty_present()
    {
        // Matches cutQuantitiesForGroup()'s own join exactly: a
        // job_transaction_headers row (ProductionGroup + Operation='IN-Cut')
        // resolves a JobTransactionId, whose job_transaction_line_details
        // rows carry the actual TotalGoodQty/TotalRejectQty per size.
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => $this->jobTransactionId,
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => $this->jobTransactionId, 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);

        $result = $this->service()->gate($this->prg, 'ADD-Was');

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('cut qty', strtolower($result['reason']));
    }
}
```

Column names above (`job_transaction_headers.ProductionGroup`/`.Operation`/`.JobTransactionId`, `job_transaction_line_details.JobTransactionId`/`.Size`/`.TotalGoodQty`/`.TotalRejectQty`) were confirmed live against the `vsm` connection this session — safe to use as-is. `job_transaction_headers` may have other NOT NULL columns beyond what's inserted here; if the insert fails on a missing column, check `information_schema.columns` for that table and add the missing ones (existing rows/other tests in this codebase that already insert into `job_transaction_headers` are the fastest reference).

Also write a second, separate test file covering spec §12's explicit "PLM-less sales-price-blank case" (own file/class per this codebase's one-class-per-file convention):

```php
<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportSendTest extends TestCase
{
    private string $prg = 'MPG/PRG/TEST/GATE01';

    protected function tearDown(): void
    {
        SubconOfficialReport::query()->where('production_group', $this->prg)->delete();
        parent::tearDown();
    }

    public function test_send_leaves_sales_price_null_when_d365_fetch_fails_or_plm_is_missing()
    {
        // A production group forProductionGroup() resolves with no plm_id at
        // all (standalone/in-house, per addProcessPurchaseOrdersForGroup()'s
        // docblock) must not throw — send() should still create the row,
        // just with sales_price/sales_price_source left null so the admin
        // fills it in manually (spec §8).
        DB::connection('vsm')->table('job_transaction_headers')->insert([
            'ProductionGroup' => $this->prg, 'Operation' => 'IN-Cut', 'JobTransactionId' => 'MPG/JOB/TEST/SEND01',
        ]);
        DB::connection('vsm')->table('job_transaction_line_details')->insert([
            'JobTransactionId' => 'MPG/JOB/TEST/SEND01', 'Size' => 'M', 'TotalGoodQty' => 100, 'TotalRejectQty' => 0,
        ]);
        DB::connection('vsm')->table('production_groups')->insert([
            'ProductionGroup' => $this->prg, 'PONumber' => 'MPG/PO/TEST/SEND01',
        ]);
        DB::connection('vsm')->table('po_lines')->insert([
            'PurchaseOrderNumber' => 'MPG/PO/TEST/SEND01', 'LineNumber' => 1, 'LineDescription' => 'Item Jasa Add Process Washing',
        ]);
        DB::connection('vsm')->table('po_headers')->insert([
            'PurchaseOrderNumber' => 'MPG/PO/TEST/SEND01', 'PurchPoolId' => 'AddPro', 'DocumentApprovalStatus' => 'Confirmed',
            'OrderVendorAccountNumber' => 'V_TEST_SEND', 'PurchaseOrderStatus' => 'Received',
        ]);

        $mockD365 = $this->mock(\App\Services\D365JobTransactionService::class);
        $mockD365->shouldReceive('fetchSalesPrice')->andReturn(null)->byDefault();
        $mockD365->shouldReceive('fetchVendorNames')->andReturn([])->byDefault();

        $report = app(SubconOfficialReportService::class)->send($this->prg, 'ADD-Was', 0.0, null);

        $this->assertNull($report->sales_price);
        $this->assertNull($report->sales_price_source);
        $this->assertSame(0.0, $report->penalty_price); // salesPrice fell back to 0 in computeDeduction()

        DB::connection('vsm')->table('job_transaction_headers')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('job_transaction_line_details')->where('JobTransactionId', 'MPG/JOB/TEST/SEND01')->delete();
        DB::connection('vsm')->table('production_groups')->where('ProductionGroup', $this->prg)->delete();
        DB::connection('vsm')->table('po_lines')->where('PurchaseOrderNumber', 'MPG/PO/TEST/SEND01')->delete();
        DB::connection('vsm')->table('po_headers')->where('PurchaseOrderNumber', 'MPG/PO/TEST/SEND01')->delete();
    }
}
```

Before writing this test, confirm `production_groups`/`po_headers`/`po_lines`'s exact required columns the same way Task 4's main gating test does (`information_schema.columns`) — the columns listed above are the ones this session already confirmed live for `po_lines`/`po_headers` (Task 2 and this session's earlier `addProcessPurchaseOrdersForGroup()` review); `production_groups.PONumber` was likewise confirmed live earlier this session. If any insert fails on a NOT NULL column not listed here, add it from the schema query's output.

- [ ] **Step 2: Run to verify failure**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportGatingTest`
Expected: FAIL — method `gate` not found.

- [ ] **Step 3: Add `gate()` and `send()` to `SubconOfficialReportService`**

```php
    /**
     * §5 of the spec: both checks must pass before a send/resend is allowed.
     * Re-run on every send/resend POST, not just hidden client-side.
     *
     * @return array{ok: bool, reason: ?string, cmt_order: ?\App\Models\SubconOrder, matched_po: ?array}
     */
    public function gate(string $productionGroup, string $operation): array
    {
        $cmtOrder = \App\Models\SubconOrder::where('production_group', $productionGroup)->first();

        if ($cmtOrder) {
            if (in_array($cmtOrder->workflow_stage, [
                \App\Models\SubconOrder::STAGE_CUTTING,
                \App\Models\SubconOrder::STAGE_CUTTING_REVIEW,
            ], true)) {
                return ['ok' => false, 'reason' => 'Cutting has not been approved yet for this work order.', 'cmt_order' => $cmtOrder, 'matched_po' => null];
            }
        } else {
            $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);
            $hasCutQty = collect($cutQuantities)->contains(fn ($c) => ($c['good'] ?? 0) > 0);
            if (! $hasCutQty) {
                return ['ok' => false, 'reason' => 'The D365 cut qty job sync is still blank for this in-house production group.', 'cmt_order' => null, 'matched_po' => null];
            }
        }

        $pos = $this->production->addProcessPurchaseOrdersForGroup($productionGroup);
        $matchedPo = $this->production->matchAddProcessPoForOperation($pos, $operation);

        if (! $matchedPo) {
            return ['ok' => false, 'reason' => "{$operation} has no confirmed purchase order yet.", 'cmt_order' => $cmtOrder, 'matched_po' => null];
        }

        if (strcasecmp($matchedPo['status'] ?? '', 'Invoiced') === 0) {
            return ['ok' => false, 'reason' => "{$operation}'s purchase order ({$matchedPo['po_number']}) is already invoiced — reporting is closed.", 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
        }

        return ['ok' => true, 'reason' => null, 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
    }

    /**
     * First send (no row exists yet) or resend (existing pending_ho row,
     * typically after a Director decline — revision_count is NOT
     * incremented here, only on decline; see applyDirectorDecline()).
     * Creates/updates the row directly in pending_ho and emails MD
     * Production. Throws \RuntimeException (message = gate()'s reason) when
     * blocked — callers turn that into a flash error.
     */
    public function send(string $productionGroup, string $operation, float $additionalDeduction, ?string $remarks, ?float $salesPriceOverride = null): \App\Models\SubconOfficialReport
    {
        $gate = $this->gate($productionGroup, $operation);
        if (! $gate['ok']) {
            throw new \RuntimeException($gate['reason']);
        }

        $matchedPo = $gate['matched_po'];
        $yield = $this->computeYield($productionGroup, $operation);

        $salesPrice = $salesPriceOverride;
        $salesPriceSource = $salesPrice !== null ? 'manual' : null;
        if ($salesPrice === null) {
            $groups = $this->production->forProductionGroup($productionGroup);
            $plmId = collect($groups)->pluck('plm_id')->filter()->first();
            $salesPrice = $plmId ? $this->d365->fetchSalesPrice((string) $plmId) : null;
            $salesPriceSource = $salesPrice !== null ? 'd365' : null;
        }

        $deduction = $this->computeDeduction($yield['cut_qty'], $yield['reject_qty'], (float) ($salesPrice ?? 0), $additionalDeduction);

        $report = \App\Models\SubconOfficialReport::updateOrCreate(
            ['production_group' => $productionGroup, 'operation' => $operation],
            [
                'status' => \App\Models\SubconOfficialReport::STATUS_PENDING_HO,
                'matched_po_number' => $matchedPo['po_number'] ?? null,
                'matched_po_vendor_code' => $matchedPo['vendor_code'] ?? null,
                'matched_po_vendor_name' => $matchedPo['vendor_name'] ?? null,
                'order_qty' => $yield['order_qty'],
                'cut_qty' => $yield['cut_qty'],
                'good_qty' => $yield['good_qty'],
                'reject_qty' => $yield['reject_qty'],
                'sales_price' => $salesPrice,
                'sales_price_source' => $salesPriceSource,
                'allowed_limit' => $deduction['allowed_limit'],
                'exceeding_reject_qty' => $deduction['exceeding_reject_qty'],
                'penalty_price' => $deduction['penalty_price'],
                'reject_penalty' => $deduction['reject_penalty'],
                'additional_deduction' => $additionalDeduction,
                'remarks' => $remarks,
            ]
        );

        return $report;
    }
```

Add `use App\Models\SubconOfficialReport;` and `use App\Models\SubconOrder;` to the top-of-file `use` block instead of the inline `\App\Models\...` if you prefer — either is fine, but stay consistent within the file (this codebase's other services mix both styles; pick one and use it throughout this file for anything you touch).

- [ ] **Step 4: Run the tests**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportGatingTest`
Expected: 4 passed.

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportSendTest`
Expected: 1 passed.

- [ ] **Step 5: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Services/SubconOfficialReportService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportGatingTest.php tests/Feature/SubconOfficialReportSendTest.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportGatingTest.php tests/Feature/SubconOfficialReportSendTest.php
git commit -m "subcon: add Official Report gating + send()"
```

---

## Task 5: RPA queueing methods

**Files:**
- Modify: `app/Services/RpaQueueService.php`
- Test: `tests/Unit/OfficialReportRpaQueueTest.php`

**Interfaces:**
- Consumes: `App\Models\SubconOfficialReport` (Task 1), its `totalDeduction()` method, `SubconProductionService::resolveAddProcessLineUnitPrice()` (Task 2).
- Produces: `RpaQueueService::queueOfficialReportInvoice(SubconOfficialReport $report, string $signedDoc): bool`; `RpaQueueService::queueOfficialReportDeduction(SubconOfficialReport $report, string $signedDoc): bool`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Models\SubconOfficialReport;
use App\Services\RpaQueueService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialReportRpaQueueTest extends TestCase
{
    private function makeReport(array $overrides = []): SubconOfficialReport
    {
        return SubconOfficialReport::create(array_merge([
            'production_group' => 'MPG/PRG/TEST/RPA01',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR,
            'matched_po_number' => 'MPG/PO/TEST/00099',
            'matched_po_vendor_code' => 'V0099',
            'matched_po_vendor_name' => 'Test Vendor',
            'good_qty' => 500,
            'reject_penalty' => 0,
            'additional_deduction' => 0,
        ], $overrides));
    }

    protected function tearDown(): void
    {
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice_addprocess', 'deduction_addprocess'])
            ->where('entity_id', 'like', 'MPG/PRG/TEST/RPA01%')
            ->delete();
        parent::tearDown();
    }

    public function test_queues_invoice_with_entity_id_and_rpa_type()
    {
        $report = $this->makeReport();

        $ok = app(RpaQueueService::class)->queueOfficialReportInvoice($report, 'data:application/pdf;base64,AAAA');

        $this->assertTrue($ok);
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Was',
            'rpa_type' => 'invoice_addprocess',
            'status' => 'pending',
        ], 'rpa');
    }

    public function test_deduction_is_queued_only_when_total_deduction_positive()
    {
        $zero = $this->makeReport(['operation' => 'ADD-Embro']);
        $service = app(RpaQueueService::class);

        $this->assertTrue($service->queueOfficialReportDeduction($zero, 'data:application/pdf;base64,AAAA'));
        $this->assertDatabaseMissing('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Embro',
            'rpa_type' => 'deduction_addprocess',
        ], 'rpa');

        $withDeduction = $this->makeReport(['operation' => 'ADD-Sab', 'reject_penalty' => 100000]);
        $this->assertTrue($service->queueOfficialReportDeduction($withDeduction, 'data:application/pdf;base64,AAAA'));
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/TEST/RPA01:ADD-Sab',
            'rpa_type' => 'deduction_addprocess',
            'status' => 'pending',
        ], 'rpa');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker exec vendor_portal_app php artisan test --filter=OfficialReportRpaQueueTest`
Expected: FAIL — method `queueOfficialReportInvoice` not found.

- [ ] **Step 3: Add the two methods to `RpaQueueService`**

Add near `queueAddProcessGoodsReceive()` (end of the class, before the closing brace):

```php
    /**
     * Invoice for one Official Report's own AddPro PO — spec §10. Separate
     * entity scheme/rpa_type from queueInvoice() (no qms `$project` object
     * here): entity_id = "{production_group}:{operation}", amount =
     * unit_price (matched PO line, resolveAddProcessLineUnitPrice()) *
     * good_qty. Best-effort/never-throwing, same contract as every other
     * method here.
     */
    public function queueOfficialReportInvoice(\App\Models\SubconOfficialReport $report, string $signedDoc): bool
    {
        try {
            $entityId = "{$report->production_group}:{$report->operation}";

            $unitPrice = app(SubconProductionService::class)->resolveAddProcessLineUnitPrice(
                (string) $report->matched_po_number,
                $report->operation
            ) ?? 0.0;

            $payload = [
                'PO' => (string) $report->matched_po_number,
                'vendor_name' => (string) $report->matched_po_vendor_name,
                'signed_doc' => $signedDoc,
                'amount' => $unitPrice * (float) $report->good_qty,
            ];

            $queuedPayload = $this->offloadSignedDocToS3('invoice_addprocess', $entityId, $payload);

            $this->upsertJob($entityId, 'invoice_addprocess', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA official-report invoice queue failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Deduction for one Official Report — only queued when
     * $report->totalDeduction() > 0, same "only when > 0" rule as
     * queueDeduction(). Separate rpa_type (`deduction_addprocess`) — spec §10.
     */
    public function queueOfficialReportDeduction(\App\Models\SubconOfficialReport $report, string $signedDoc): bool
    {
        try {
            $entityId = "{$report->production_group}:{$report->operation}";
            $amount = $report->totalDeduction();

            if ($amount <= 0) {
                return true;
            }

            $payload = [
                'PO' => (string) $report->matched_po_number,
                'vendor_name' => (string) $report->matched_po_vendor_name,
                'signed_doc' => $signedDoc,
                'amount' => $amount,
            ];

            $queuedPayload = $this->offloadSignedDocToS3('deduction_addprocess', $entityId, $payload);

            $this->upsertJob($entityId, 'deduction_addprocess', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA official-report deduction queue failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
```

Check that `SubconProductionService` is already `use`d at the top of `RpaQueueService.php`; if not, add `use App\Services\SubconProductionService;` (or fully qualify at the call site as written above — either is fine, match the file's existing style).

- [ ] **Step 4: Run the tests**

Run: `docker exec vendor_portal_app php artisan test --filter=OfficialReportRpaQueueTest`
Expected: 2 passed. (Requires the `rpa` connection to be reachable, same as every other `RpaQueueService` test already in this suite.)

- [ ] **Step 5: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Services/RpaQueueService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/RpaQueueService.php tests/Unit/OfficialReportRpaQueueTest.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Services/RpaQueueService.php tests/Unit/OfficialReportRpaQueueTest.php
git commit -m "subcon: add invoice_addprocess/deduction_addprocess RPA queueing"
```

---

## Task 6: PDF template + render method

**Files:**
- Create: `resources/views/subcon/pdf/official-report.blade.php`
- Modify: `app/Services/SubconOfficialReportService.php` (add `renderPdfDataUri`)
- Test: `tests/Feature/SubconOfficialReportPdfTest.php`

**Interfaces:**
- Produces: `SubconOfficialReportService::renderPdfDataUri(SubconOfficialReport $report): ?string` (`data:application/pdf;base64,...` or null on failure).

- [ ] **Step 1: Write the PDF Blade template**

Keep it simple/self-contained (no shared layout — DomPDF templates in this app don't extend the web layouts; confirm by checking `resources/views/vendor/pdf/packing-slip.blade.php`'s top few lines for the house style and match its basic `<style>`/table conventions):

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; }
        h1 { font-size: 15pt; text-align: center; margin-bottom: 2pt; }
        h2 { font-size: 10pt; text-align: center; color: #666; margin-top: 0; margin-bottom: 16pt; }
        h3 { font-size: 11pt; border-bottom: 1px solid #999; padding-bottom: 3pt; margin-top: 18pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        td, th { padding: 4pt 6pt; border: 1px solid #ccc; text-align: left; font-size: 9.5pt; }
        th { background: #f2f2f2; }
        .num { text-align: right; }
        .sig-block { width: 48%; display: inline-block; vertical-align: top; margin-top: 20pt; }
        .sig-title { font-weight: bold; font-size: 9pt; letter-spacing: 0.05em; color: #444; }
        .sig-name { margin-top: 30pt; font-weight: bold; }
        .sig-role { color: #666; font-size: 9pt; }
        .sig-pending { color: #999; font-style: italic; margin-top: 30pt; }
    </style>
</head>
<body>
    <h1>Official Report (Berita Acara)</h1>
    <h2>QUALITY CONTROL SYSTEM</h2>

    <h3>1. Overview</h3>
    <table>
        <tr><th>Vendor</th><td>{{ $report->matched_po_vendor_name ?? $report->matched_po_vendor_code ?? '—' }}</td>
            <th>PO Number</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
        <tr><th>Article Name</th><td>{{ $articleName ?? '—' }}</td>
            <th>Qty Order</th><td class="num">{{ number_format($report->order_qty) }}</td></tr>
        <tr><th>Available Qty</th><td class="num">{{ number_format($report->good_qty + $report->reject_qty) }}</td>
            <th>Delivery Plan</th><td>{{ $deliveryPlan ?? '—' }}</td></tr>
        <tr><th>Season</th><td>{{ $season ?? '—' }}</td>
            <th>PLM ID</th><td>{{ $plmId ?? '—' }}</td></tr>
        <tr><th>Production Group</th><td colspan="3">{{ $report->production_group }} — {{ $report->operation }}</td></tr>
    </table>

    <h3>2. Production Yield Matrix</h3>
    <table>
        <tr><th>Order Qty</th><th>Cut Qty</th><th>Good Qty</th><th>Reject Qty</th></tr>
        <tr>
            <td class="num">{{ number_format($report->order_qty) }}</td>
            <td class="num">{{ number_format($report->cut_qty) }}</td>
            <td class="num">{{ number_format($report->good_qty) }}</td>
            <td class="num">{{ number_format($report->reject_qty) }}</td>
        </tr>
    </table>

    <h3>3. Deduction &amp; Remarks</h3>
    <table>
        <tr><th>Cut Qty</th><td class="num">{{ number_format($report->cut_qty) }}</td>
            <th>Allowed Limit (1%)</th><td class="num">{{ number_format($report->allowed_limit) }}</td></tr>
        <tr><th>Reject Qty</th><td class="num">{{ number_format($report->reject_qty) }}</td>
            <th>Exceeding Qty</th><td class="num">{{ number_format($report->exceeding_reject_qty) }}</td></tr>
        <tr><th>Sales Price</th><td class="num">Rp {{ number_format($report->sales_price ?? 0, 0, ',', '.') }}</td>
            <th>Penalty Price (70%)</th><td class="num">Rp {{ number_format($report->penalty_price, 0, ',', '.') }}</td></tr>
        <tr><th>Reject Penalty</th><td class="num">Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td>
            <th>Additional Deduction</th><td class="num">Rp {{ number_format($report->additional_deduction, 0, ',', '.') }}</td></tr>
        <tr><th>Total Deduction</th><td class="num" colspan="3"><strong>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</strong></td></tr>
    </table>
    <p><strong>Remarks:</strong> {{ $report->remarks ?: '—' }}</p>

    <h3>4. Conclusions</h3>
    <div class="sig-block">
        <div class="sig-title">APPROVED BY</div>
        @if($report->ho_approval_signature)
            <div class="sig-name">{{ $report->ho_approval_signature }}</div>
            <div class="sig-role">MPG HO - MD Production</div>
            <div class="sig-role">{{ optional($report->ho_approved_at)->format('d-m-Y H:i:s') }}</div>
        @else
            <div class="sig-pending">Awaiting Approval</div>
        @endif
    </div>
    <div class="sig-block">
        <div class="sig-title">AUTHORIZED BY</div>
        @if($report->director_approval_signature)
            <div class="sig-name">{{ $report->director_approval_signature }}</div>
            <div class="sig-role">DIRECTOR</div>
            <div class="sig-role">{{ optional($report->director_approved_at)->format('d-m-Y H:i:s') }}</div>
        @else
            <div class="sig-pending">Awaiting Authorization</div>
        @endif
    </div>
</body>
</html>
```

- [ ] **Step 2: Add `renderPdfDataUri()` to `SubconOfficialReportService`**

```php
    /**
     * Renders the Official Report PDF as a `data:application/pdf;base64,...`
     * URI (spec §9) — the shape RpaQueueService's offloadSignedDocToS3()
     * expects (matches QcReportPdfService::renderDataUri()'s contract).
     * Overview fields (article name / delivery plan / season / PLM id) come
     * from forProductionGroup()'s own group metadata, best-effort — a
     * standalone/PLM-less group simply renders '—' for those, same as the
     * rest of this feature treats a missing PLM link.
     */
    public function renderPdfDataUri(\App\Models\SubconOfficialReport $report): ?string
    {
        try {
            $groups = $this->production->forProductionGroup($report->production_group);
            $first = $groups[0] ?? [];

            $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.official-report', [
                'report' => $report,
                'articleName' => $first['article_name'] ?? $first['style'] ?? null,
                'deliveryPlan' => $first['delivery_plan'] ?? null,
                'season' => $first['season'] ?? null,
                'plmId' => $first['plm_id'] ?? null,
            ])->setPaper('a4', 'portrait')->output();

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Official Report PDF render failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
```

Before finalizing, check `forProductionGroup()`'s actual return shape for group-level metadata keys (`article_name`/`style`/`delivery_plan`/`season`/`plm_id` are guesses based on the CLAUDE.md field list) with:
```
docker exec vendor_portal_app php artisan tinker --execute="print_r(app(App\Services\SubconProductionService::class)->forProductionGroup('MPG/PRG/2512/000014'));" | head -40
```
and correct the keys used in both this method and the Blade template above to match what the method actually returns.

- [ ] **Step 3: Write and run the test (real render against a seeded report + real `vsm` data)**

```php
<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Tests\TestCase;

class SubconOfficialReportPdfTest extends TestCase
{
    public function test_renders_a_pdf_data_uri_for_an_unsigned_report()
    {
        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/2512/000014', // real PRG verified reachable earlier this session
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'order_qty' => 2646, 'cut_qty' => 2600, 'good_qty' => 2500, 'reject_qty' => 20,
            'sales_price' => 100000, 'allowed_limit' => 26, 'exceeding_reject_qty' => 0,
            'penalty_price' => 70000, 'reject_penalty' => 0, 'additional_deduction' => 0,
        ]);

        $uri = app(SubconOfficialReportService::class)->renderPdfDataUri($report);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:application/pdf;base64,', $uri);
    }
}
```

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportPdfTest`
Expected: 1 passed.

- [ ] **Step 4: Lint and commit**

```bash
docker exec vendor_portal_app php -l app/Services/SubconOfficialReportService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportPdfTest.php
git add resources/views/subcon/pdf/official-report.blade.php app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportPdfTest.php
git commit -m "subcon: add Official Report PDF template + render method"
```

---

## Task 7: State transitions — `applyHoApproval()`, `applyDirectorApproval()`, `applyDirectorDecline()`

**Files:**
- Modify: `app/Services/SubconOfficialReportService.php`
- Test: `tests/Feature/SubconOfficialReportTransitionsTest.php`

**Interfaces:**
- Consumes: `RpaQueueService::queueOfficialReportInvoice()`, `::queueOfficialReportDeduction()` (Task 5); `SubconOfficialReportService::renderPdfDataUri()` (Task 6).
- Produces: `applyHoApproval(SubconOfficialReport $report, string $approver, ?float $additionalDeduction = null, ?string $remarks = null): void`; `applyDirectorApproval(SubconOfficialReport $report, string $approver): void`; `applyDirectorDecline(SubconOfficialReport $report, string $reason): void`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Services\RpaQueueService;
use App\Services\SubconOfficialReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubconOfficialReportTransitionsTest extends TestCase
{
    private function makeReport(array $overrides = []): SubconOfficialReport
    {
        return SubconOfficialReport::create(array_merge([
            'production_group' => 'MPG/PRG/2512/000014',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'matched_po_number' => 'MPG/PO/2601/00302',
            'matched_po_vendor_code' => 'V0061',
            'matched_po_vendor_name' => 'CV YOGA CIPTA PERKASA',
            'order_qty' => 2646, 'cut_qty' => 2600, 'good_qty' => 2500, 'reject_qty' => 20,
            'sales_price' => 100000, 'allowed_limit' => 26, 'exceeding_reject_qty' => 0,
            'penalty_price' => 70000, 'reject_penalty' => 0, 'additional_deduction' => 0,
        ], $overrides));
    }

    protected function tearDown(): void
    {
        DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice_addprocess', 'deduction_addprocess'])
            ->where('entity_id', 'like', 'MPG/PRG/2512/000014:%')
            ->delete();
        parent::tearDown();
    }

    private function service(): SubconOfficialReportService
    {
        return app(SubconOfficialReportService::class);
    }

    public function test_ho_approval_moves_to_pending_director_and_signs()
    {
        $report = $this->makeReport();

        $this->service()->applyHoApproval($report, 'Ika (MD Production)');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_DIRECTOR, $report->status);
        $this->assertStringContainsString('Ika', $report->ho_approval_signature);
        $this->assertNotNull($report->ho_approved_at);
    }

    public function test_ho_approval_can_revise_deduction_and_remarks_on_resend()
    {
        $report = $this->makeReport(['revision_count' => 1, 'additional_deduction' => 0]);

        $this->service()->applyHoApproval($report, 'Ika', additionalDeduction: 75000.0, remarks: 'Revised after director feedback');

        $report->refresh();
        $this->assertSame(75000.0, $report->additional_deduction);
        $this->assertSame('Revised after director feedback', $report->remarks);
    }

    public function test_director_approval_completes_and_queues_rpa()
    {
        $report = $this->makeReport(['status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR, 'ho_approval_signature' => 'Digitally Signed: Ika']);

        $this->service()->applyDirectorApproval($report, 'The Director');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_COMPLETED, $report->status);
        $this->assertStringContainsString('The Director', $report->director_approval_signature);
        $this->assertDatabaseHas('rpa_queues', [
            'entity_id' => 'MPG/PRG/2512/000014:ADD-Was',
            'rpa_type' => 'invoice_addprocess',
        ], 'rpa');
    }

    public function test_director_decline_returns_to_pending_ho_and_increments_revision_count()
    {
        $report = $this->makeReport(['status' => SubconOfficialReport::STATUS_PENDING_DIRECTOR, 'revision_count' => 0]);

        $this->service()->applyDirectorDecline($report, 'Deduction figures look off, please recheck.');

        $report->refresh();
        $this->assertSame(SubconOfficialReport::STATUS_PENDING_HO, $report->status);
        $this->assertSame(1, $report->revision_count);
        $this->assertSame('Deduction figures look off, please recheck.', $report->director_decline_reason);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportTransitionsTest`
Expected: FAIL — methods not found.

- [ ] **Step 3: Add the three methods**

```php
    /**
     * MD Production approves — first send or a post-decline resend (same
     * method either way, spec §6's "no HO-decline route" note). Deduction/
     * remarks are only overwritten when explicitly passed (the revision
     * case); recomputes reject_penalty/total off any new additional
     * deduction so the signed PDF always reflects the figures being signed.
     */
    public function applyHoApproval(\App\Models\SubconOfficialReport $report, string $approver, ?float $additionalDeduction = null, ?string $remarks = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($report, $approver, $additionalDeduction, $remarks) {
            if ($additionalDeduction !== null) {
                $report->additional_deduction = $additionalDeduction;
            }
            if ($remarks !== null) {
                $report->remarks = $remarks;
            }

            $report->ho_approval_signature = "Digitally Signed: {$approver}";
            $report->ho_approved_at = now();
            $report->status = \App\Models\SubconOfficialReport::STATUS_PENDING_DIRECTOR;
            $report->save();
        });

        $this->logResult($report, 'ho_approved', "HO approved by {$approver}.");
    }

    /**
     * Director approves — terminal. Renders the PDF once (signature is now
     * complete on the HO side, still pending on the Director side... but the
     * approval write happens first so the rendered PDF reflects BOTH
     * signatures), then queues Invoice + Deduction RPA (spec §10). RPA
     * queueing is best-effort and never blocks the approval itself — a
     * queueing failure is logged, not thrown.
     */
    public function applyDirectorApproval(\App\Models\SubconOfficialReport $report, string $approver): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($report, $approver) {
            $report->director_approval_signature = "Digitally Signed: {$approver}";
            $report->director_approved_at = now();
            $report->status = \App\Models\SubconOfficialReport::STATUS_COMPLETED;
            $report->save();
        });

        $signedDoc = $this->renderPdfDataUri($report);
        if ($signedDoc !== null) {
            $rpaQueue = app(\App\Services\RpaQueueService::class);
            $rpaQueue->queueOfficialReportInvoice($report, $signedDoc);
            $rpaQueue->queueOfficialReportDeduction($report, $signedDoc);
        } else {
            \Illuminate\Support\Facades\Log::warning("Official Report {$report->id}: director approved but PDF render failed — RPA not queued.");
        }

        $this->logResult($report, 'director_approved', "Director approved by {$approver}.");
    }

    /**
     * Director declines — back to pending_ho for MD Production to revise,
     * never back to a QC stage (there isn't one) and never a bare resend
     * (spec §4). revision_count increments so the UI can tell a first send
     * from a resend (SubconOfficialReport::needsRevision()).
     */
    public function applyDirectorDecline(\App\Models\SubconOfficialReport $report, string $reason): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($report, $reason) {
            $report->status = \App\Models\SubconOfficialReport::STATUS_PENDING_HO;
            $report->revision_count = $report->revision_count + 1;
            $report->director_decline_reason = $reason;
            $report->save();
        });

        $this->logResult($report, 'director_declined', "Director declined: {$reason}");
    }

    /** Audit trail via SubconJobLog, spec §3 — job_type "official_report:{operation}". */
    private function logResult(\App\Models\SubconOfficialReport $report, string $status, string $message): void
    {
        $order = \App\Models\SubconOrder::where('production_group', $report->production_group)->first();

        \App\Models\SubconJobLog::create([
            'order_id' => $order?->id,
            'order_number' => $order?->order_number ?? $report->production_group,
            'job_type' => "official_report:{$report->operation}",
            'status' => $status,
            'message' => $message,
        ]);
    }
```

- [ ] **Step 4: Run the tests**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportTransitionsTest`
Expected: 4 passed.

- [ ] **Step 5: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Services/SubconOfficialReportService.php
docker exec vendor_portal_app php vendor/bin/pint app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportTransitionsTest.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Services/SubconOfficialReportService.php tests/Feature/SubconOfficialReportTransitionsTest.php
git commit -m "subcon: add Official Report HO/Director approval + decline transitions"
```

---

## Task 8: Mailables

**Files:**
- Create: `app/Mail/SubconOfficialReportApprovalMailable.php`
- Create: `resources/views/emails/official-report-approval.blade.php`
- Create: `app/Mail/SubconOfficialReportDeclinedMailable.php`
- Create: `resources/views/emails/official-report-declined.blade.php`
- Test: `tests/Feature/SubconOfficialReportMailTest.php`

**Interfaces:**
- Produces: `new SubconOfficialReportApprovalMailable(SubconOfficialReport $report, string $stage)` where `$stage` is `'ho'|'director'`; `new SubconOfficialReportDeclinedMailable(SubconOfficialReport $report)`.
- Consumes: `URL::signedRoute()` for route names defined in Task 9 (`subcon.official-report.ho-approve`, `.director-approve`, `.director-decline`) — write this task assuming those route names exist; Task 9 wires the actual routes, but route *names* must match exactly.

- [ ] **Step 1: Write the HO/Director approval mailable**

```php
<?php

namespace App\Mail;

use App\Models\SubconOfficialReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent to MD Production (stage 'ho') or the Director (stage 'director') when
 * an Official Report is ready for their action. One mailable for both
 * stages, mirroring SubconApprovalRequestMailable's own $gate-param shape.
 */
class SubconOfficialReportApprovalMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOfficialReport $report;

    public string $stage;

    public string $approveUrl;

    public ?string $declineUrl = null;

    public function __construct(SubconOfficialReport $report, string $stage)
    {
        $this->report = $report;
        $this->stage = $stage;

        if ($stage === 'ho') {
            $this->approveUrl = url(URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false));
        } else {
            $this->approveUrl = url(URL::signedRoute('subcon.official-report.director-approve', ['report' => $report->id], absolute: false));
            $this->declineUrl = url(URL::signedRoute('subcon.official-report.director-decline', ['report' => $report->id], absolute: false));
        }
    }

    public function envelope(): Envelope
    {
        $label = $this->stage === 'ho' ? 'MD Production Approval' : 'Director Authorization';
        $ref = "{$this->report->production_group} — {$this->report->operation}";

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: "Official Report {$label} needed: {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.official-report-approval');
    }
}
```

- [ ] **Step 2: Write its email view**

```blade
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>
        @if($stage === 'ho')
            An Official Report (Berita Acara) is ready for your approval as MD Production.
        @else
            An Official Report (Berita Acara) has been approved by MD Production and needs your authorization.
        @endif
    </p>
    <table style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Production Group</td><td>{{ $report->production_group }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Operation</td><td>{{ $report->operation }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Purchase Order</td><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Total Deduction</td><td>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</td></tr>
        @if($report->needsRevision())
        <tr><td style="padding: 3px 10px 3px 0; color: #b45309;">Note</td><td style="color: #b45309;">This is a resend after a Director decline (revision #{{ $report->revision_count }}).</td></tr>
        @endif
    </table>
    <p>
        <a href="{{ $approveUrl }}" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">
            {{ $stage === 'ho' ? 'Review & Approve' : 'Review & Authorize' }}
        </a>
        @if($declineUrl)
            &nbsp;
            <a href="{{ $declineUrl }}" style="background:#dc2626;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Decline</a>
        @endif
    </p>
</body>
</html>
```

- [ ] **Step 3: Write the decline-notification mailable + view (to MD Production, after a Director decline)**

```php
<?php

namespace App\Mail;

use App\Models\SubconOfficialReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Notifies MD Production that the Director declined and a revision is needed. */
class SubconOfficialReportDeclinedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOfficialReport $report;

    public string $reviseUrl;

    public function __construct(SubconOfficialReport $report)
    {
        $this->report = $report;
        $this->reviseUrl = url(URL::signedRoute('subcon.official-report.ho-approve', ['report' => $report->id], absolute: false));
    }

    public function envelope(): Envelope
    {
        $ref = "{$this->report->production_group} — {$this->report->operation}";

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: "Official Report declined by Director — revision needed: {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.official-report-declined');
    }
}
```

```blade
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>The Director declined the Official Report below. Please revise and resend.</p>
    <table style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Production Group</td><td>{{ $report->production_group }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Operation</td><td>{{ $report->operation }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Reason</td><td>{{ $report->director_decline_reason }}</td></tr>
    </table>
    <p>
        <a href="{{ $reviseUrl }}" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Revise &amp; Resend</a>
    </p>
</body>
</html>
```

- [ ] **Step 4: Write and run a mail test (Mail::fake, mirroring `SubconCuttingReportTest`'s existing `Mail::fake()` usage)**

```php
<?php

namespace Tests\Feature;

use App\Mail\SubconOfficialReportApprovalMailable;
use App\Mail\SubconOfficialReportDeclinedMailable;
use App\Models\SubconOfficialReport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubconOfficialReportMailTest extends TestCase
{
    public function test_approval_mailable_renders_for_ho_and_director_stages()
    {
        Mail::fake();

        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/TEST/MAIL01',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
        ]);

        Mail::to('ho@test.com')->send(new SubconOfficialReportApprovalMailable($report, 'ho'));
        Mail::to('director@test.com')->send(new SubconOfficialReportApprovalMailable($report, 'director'));

        Mail::assertSent(SubconOfficialReportApprovalMailable::class, 2);
    }

    public function test_declined_mailable_sends()
    {
        Mail::fake();

        $report = SubconOfficialReport::create([
            'production_group' => 'MPG/PRG/TEST/MAIL02',
            'operation' => 'ADD-Was',
            'status' => SubconOfficialReport::STATUS_PENDING_HO,
            'director_decline_reason' => 'Please recheck deduction.',
        ]);

        Mail::to('ho@test.com')->send(new SubconOfficialReportDeclinedMailable($report));

        Mail::assertSent(SubconOfficialReportDeclinedMailable::class);
    }
}
```

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportMailTest`
Expected: 2 passed (`Mail::fake()` means the mailables never actually render/send, so this passes even before Task 9's routes exist — the signed-route calls inside the mailable constructors only need the route *names* to be registered, which happens in Task 9; if this task is done strictly before Task 9, temporarily skip this test file's run and come back to it after Task 9 — note that explicitly rather than silently letting it fail).

- [ ] **Step 5: Lint and commit**

```bash
docker exec vendor_portal_app php -l app/Mail/SubconOfficialReportApprovalMailable.php
docker exec vendor_portal_app php -l app/Mail/SubconOfficialReportDeclinedMailable.php
docker exec vendor_portal_app php vendor/bin/pint app/Mail/SubconOfficialReportApprovalMailable.php app/Mail/SubconOfficialReportDeclinedMailable.php tests/Feature/SubconOfficialReportMailTest.php
git add app/Mail/SubconOfficialReportApprovalMailable.php app/Mail/SubconOfficialReportDeclinedMailable.php resources/views/emails/official-report-approval.blade.php resources/views/emails/official-report-declined.blade.php tests/Feature/SubconOfficialReportMailTest.php
git commit -m "subcon: add Official Report approval/decline mailables"
```

---

## Task 9: Routes + Controller (send, HO approve, Director approve/decline — signed + in-app)

**Files:**
- Create: `app/Http/Controllers/SubconOfficialReportController.php`
- Create: `resources/views/subcon/official-report-ho-confirm.blade.php`
- Create: `resources/views/subcon/official-report-director-confirm.blade.php`
- Create: `resources/views/subcon/official-report-decline-confirm.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SubconOfficialReportFlowTest.php`

**Interfaces:**
- Consumes: `SubconOfficialReportService::send()`, `::gate()`, `::applyHoApproval()`, `::applyDirectorApproval()`, `::applyDirectorDecline()` (Tasks 4 & 7); `SubconOfficialReportApprovalMailable`, `SubconOfficialReportDeclinedMailable` (Task 8); `Setting::getValue('qc_ho_approver_email')`, `('qc_director_approver_email')` (existing).
- Produces: routes `subcon.official-report.ho-approve[.submit]`, `subcon.official-report.director-approve[.submit]`, `subcon.official-report.director-decline[.submit]` (signed, public); `subcon.admin.official-reports.send`, `.ho-approve`, `.director-approve`, `.director-decline` (in-app, admin-gated).

- [ ] **Step 1: Add the routes**

In `routes/web.php`, in the public signed-routes section (right after the existing `subcon.approve`/`subcon.decline` block, e.g. after line ~220 where `subcon.approve.submit` is defined):

```php
Route::get('/subcon/official-report/{report}/ho-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'hoApproveSigned'])->name('subcon.official-report.ho-approve')->middleware('signed:relative');
Route::post('/subcon/official-report/{report}/ho-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'hoApproveSubmit'])->name('subcon.official-report.ho-approve.submit')->middleware('signed:relative');
Route::get('/subcon/official-report/{report}/director-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'directorApproveSigned'])->name('subcon.official-report.director-approve')->middleware('signed:relative');
Route::post('/subcon/official-report/{report}/director-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'directorApproveSubmit'])->name('subcon.official-report.director-approve.submit')->middleware('signed:relative');
Route::get('/subcon/official-report/{report}/director-decline', [App\Http\Controllers\SubconOfficialReportController::class, 'directorDeclineSigned'])->name('subcon.official-report.director-decline')->middleware('signed:relative');
Route::post('/subcon/official-report/{report}/director-decline', [App\Http\Controllers\SubconOfficialReportController::class, 'directorDeclineSubmit'])->name('subcon.official-report.director-decline.submit')->middleware('signed:relative');
```

Inside the existing `Route::middleware(['auth', 'role:admin,subcon_admin'])->prefix('subcon/admin')->name('subcon.admin.')->group(...)` block (the same group `add-process`/`add-process.submit` live in), add:

```php
Route::post('/official-reports/send', [App\Http\Controllers\SubconOfficialReportController::class, 'send'])->name('official-reports.send');
Route::post('/official-reports/{id}/ho-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'hoApproveInApp'])->name('official-reports.ho-approve');
Route::post('/official-reports/{id}/director-approve', [App\Http\Controllers\SubconOfficialReportController::class, 'directorApproveInApp'])->name('official-reports.director-approve');
Route::post('/official-reports/{id}/director-decline', [App\Http\Controllers\SubconOfficialReportController::class, 'directorDeclineInApp'])->name('official-reports.director-decline');
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Mail\SubconOfficialReportApprovalMailable;
use App\Mail\SubconOfficialReportDeclinedMailable;
use App\Models\SubconOfficialReport;
use App\Models\Setting;
use App\Services\SubconOfficialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Official Report (Berita Acara) — send + HO/Director approve/decline, both
 * signed-email and in-app entry points. See
 * docs/superpowers/specs/2026-09-22-subcon-official-report-design.md.
 */
class SubconOfficialReportController extends Controller
{
    public function __construct(private readonly SubconOfficialReportService $reports) {}

    // --- Send (admin, in-app only — no email entry point for the first send) ---

    public function send(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'production_group' => 'required|string',
            'operation' => 'required|string',
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
            'sales_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $report = $this->reports->send(
                $data['production_group'],
                $data['operation'],
                (float) ($data['additional_deduction'] ?? 0),
                $data['remarks'] ?? null,
                isset($data['sales_price']) ? (float) $data['sales_price'] : null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->emailHo($report);

        return back()->with('success', "Official Report sent for {$data['operation']} — awaiting MD Production approval.");
    }

    // --- HO approve (signed + in-app) ---

    public function hoApproveSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingHo()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting MD Production approval.']);
        }

        return view('subcon.official-report-ho-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.ho-approve.submit', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function hoApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate([
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
            'approver' => 'required|string|max:255',
        ]);

        $this->reports->applyHoApproval($model, $data['approver'], isset($data['additional_deduction']) ? (float) $data['additional_deduction'] : null, $data['remarks'] ?? null);
        $this->emailDirector($model->fresh());

        return view('approvals.result', ['success' => true, 'message' => "Approved. Sent to the Director for authorization."]);
    }

    public function hoApproveInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $model = SubconOfficialReport::findOrFail($id);
        $data = $request->validate([
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $approver = Auth::user()->name ?? Auth::user()->email;
        $this->reports->applyHoApproval($model, $approver, isset($data['additional_deduction']) ? (float) $data['additional_deduction'] : null, $data['remarks'] ?? null);
        $this->emailDirector($model->fresh());

        return back()->with('success', 'Approved — sent to the Director.');
    }

    // --- Director approve (signed + in-app) ---

    public function directorApproveSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingDirector()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting Director authorization.']);
        }

        return view('subcon.official-report-director-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.director-approve.submit', ['report' => $model->id], absolute: false)),
            'declineUrl' => url(URL::signedRoute('subcon.official-report.director-decline', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function directorApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate(['approver' => 'required|string|max:255']);

        $this->reports->applyDirectorApproval($model, $data['approver']);

        return view('approvals.result', ['success' => true, 'message' => 'Authorized. Invoice/Deduction have been queued.']);
    }

    public function directorApproveInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $model = SubconOfficialReport::findOrFail($id);
        $approver = Auth::user()->name ?? Auth::user()->email;

        $this->reports->applyDirectorApproval($model, $approver);

        return back()->with('success', 'Authorized — Invoice/Deduction queued.');
    }

    // --- Director decline (signed + in-app) ---

    public function directorDeclineSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingDirector()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting Director authorization.']);
        }

        return view('subcon.official-report-decline-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.director-decline.submit', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function directorDeclineSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $this->reports->applyDirectorDecline($model, $data['reason']);
        $this->emailHoDeclined($model->fresh());

        return view('approvals.result', ['success' => true, 'message' => 'Declined. MD Production has been notified to revise and resend.']);
    }

    public function directorDeclineInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $model = SubconOfficialReport::findOrFail($id);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $this->reports->applyDirectorDecline($model, $data['reason']);
        $this->emailHoDeclined($model->fresh());

        return back()->with('success', 'Declined — MD Production notified.');
    }

    // --- Shared helpers ---

    private function authorizeAdmin(): void
    {
        if (! in_array(Auth::user()->role ?? '', ['admin', 'subcon_admin'], true)) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function emailHo(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_ho_approver_email');
        if (! $to) {
            Log::warning("Official Report {$report->id}: no qc_ho_approver_email configured, skipping HO email.");

            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportApprovalMailable($report, 'ho'));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: HO email failed: ".$e->getMessage());
        }
    }

    private function emailDirector(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_director_approver_email');
        if (! $to) {
            Log::warning("Official Report {$report->id}: no qc_director_approver_email configured, skipping Director email.");

            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportApprovalMailable($report, 'director'));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: Director email failed: ".$e->getMessage());
        }
    }

    private function emailHoDeclined(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_ho_approver_email');
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportDeclinedMailable($report));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: decline-notification email failed: ".$e->getMessage());
        }
    }
}
```

- [ ] **Step 3: Write the three confirm-page Blade views**

`resources/views/subcon/official-report-ho-confirm.blade.php` (model on `resources/views/subcon/approve-confirm.blade.php`'s layout — `@extends('layouts.app')`, `@section('bare', '1')`):

```blade
@extends('layouts.app')

@section('title', 'Official Report — MD Production Approval')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-1">Official Report — MD Production Approval</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    @if($report->needsRevision())
                        <div class="alert alert-warning">
                            This is a resend after a Director decline (revision #{{ $report->revision_count }}).
                            <div class="mt-1"><strong>Reason:</strong> {{ $report->director_decline_reason }}</div>
                        </div>
                    @endif

                    <table class="table table-sm">
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                        <tr><th>Cut / Good / Reject Qty</th><td>{{ number_format($report->cut_qty) }} / {{ number_format($report->good_qty) }} / {{ number_format($report->reject_qty) }}</td></tr>
                        <tr><th>Allowed Limit (1%)</th><td>{{ number_format($report->allowed_limit) }}</td></tr>
                        <tr><th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
                    </table>

                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Additional Deduction (Rp)</label>
                            <input type="number" step="0.01" min="0" name="additional_deduction" class="form-control" value="{{ old('additional_deduction', $report->additional_deduction) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $report->remarks) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="approver" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Approve &amp; Send to Director</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

`resources/views/subcon/official-report-director-confirm.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Official Report — Director Authorization')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-1">Official Report — Director Authorization</h4>
                    <p class="text-muted mb-4">{{ $report->production_group }} — {{ $report->operation }}</p>

                    <table class="table table-sm">
                        <tr><th>Purchase Order</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
                        <tr><th>Approved by (MD Production)</th><td>{{ $report->ho_approval_signature }}</td></tr>
                        <tr><th>Cut / Good / Reject Qty</th><td>{{ number_format($report->cut_qty) }} / {{ number_format($report->good_qty) }} / {{ number_format($report->reject_qty) }}</td></tr>
                        <tr><th>Reject Penalty</th><td>Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td></tr>
                        <tr><th>Additional Deduction</th><td>Rp {{ number_format($report->additional_deduction, 0, ',', '.') }}</td></tr>
                        <tr><th>Total Deduction</th><td><strong>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</strong></td></tr>
                        <tr><th>Remarks</th><td>{{ $report->remarks ?: '—' }}</td></tr>
                    </table>

                    <form method="POST" action="{{ $submitUrl }}" class="d-inline">
                        @csrf
                        <input type="text" name="approver" class="form-control mb-2" placeholder="Your Name" required>
                        <button type="submit" class="btn btn-primary">Authorize</button>
                    </form>
                    <a href="{{ $declineUrl }}" class="btn btn-outline-danger">Decline</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

`resources/views/subcon/official-report-decline-confirm.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Official Report — Decline')
@section('bare', '1')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-body p-4 p-md-5">
                    <h4 class="mb-4">Decline Official Report</h4>
                    <p class="text-muted">{{ $report->production_group }} — {{ $report->operation }}</p>
                    <form method="POST" action="{{ $submitUrl }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Decline &amp; Send Back for Revision</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

- [ ] **Step 4: Write the end-to-end flow test**

```php
<?php

namespace Tests\Feature;

use App\Models\SubconOfficialReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubconOfficialReportFlowTest extends TestCase
{
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

    public function test_full_send_to_director_approval_happy_path()
    {
        Mail::fake();
        \App\Models\Setting::setValue('qc_ho_approver_email', 'ho@test.com');
        \App\Models\Setting::setValue('qc_director_approver_email', 'director@test.com');

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
        \App\Models\Setting::setValue('qc_ho_approver_email', 'ho@test.com');
        \App\Models\Setting::setValue('qc_director_approver_email', 'director@test.com');
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
```

Check `Setting::setValue()`'s exact name/signature first (`grep -n "function setValue" app/Models/Setting.php`) and adjust the two calls above if it differs.

- [ ] **Step 5: Run the tests**

Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportFlowTest`
Expected: 3 passed.

Then re-run Task 8's mail test now that the route names exist:
Run: `docker exec vendor_portal_app php artisan test --filter=SubconOfficialReportMailTest`
Expected: 2 passed.

- [ ] **Step 6: Live-render check against the real app (mirrors how Add Process was verified earlier this session)**

```bash
docker exec vendor_portal_app php artisan route:list 2>&1 | grep -i "official-report"
```
Expected: all 10 routes listed (6 signed + 4 in-app).

- [ ] **Step 7: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Http/Controllers/SubconOfficialReportController.php
docker exec vendor_portal_app php -l routes/web.php
docker exec vendor_portal_app php vendor/bin/pint app/Http/Controllers/SubconOfficialReportController.php routes/web.php tests/Feature/SubconOfficialReportFlowTest.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Http/Controllers/SubconOfficialReportController.php routes/web.php resources/views/subcon/official-report-ho-confirm.blade.php resources/views/subcon/official-report-director-confirm.blade.php resources/views/subcon/official-report-decline-confirm.blade.php tests/Feature/SubconOfficialReportFlowTest.php
git commit -m "subcon: add Official Report send/approve/decline routes + controller"
```

---

## Task 10: Add Process page UI — "Send Report" per operation card

**Files:**
- Modify: `resources/views/subcon/admin/add-process/index.blade.php`
- Modify: `app/Http/Controllers/SubconAddProcessController.php` (pass report rows to the view)

**Interfaces:**
- Consumes: `SubconOfficialReportService::gate()` (Task 4); `SubconOfficialReport` model (Task 1).

- [ ] **Step 1: Load existing reports + gate results in `SubconAddProcessController::index()`**

In `index()`, after `$operations = array_values(array_filter(...))` (the existing confirmed-PO filter), add:

```php
        $officialReports = [];
        $officialReportGates = [];
        if ($prg !== '') {
            $officialReportsService = app(\App\Services\SubconOfficialReportService::class);
            $officialReports = \App\Models\SubconOfficialReport::where('production_group', $prg)
                ->get()->keyBy('operation');
            foreach ($allOperations as $operation) {
                $officialReportGates[$operation] = $officialReportsService->gate($prg, $operation);
            }
        }
```

Add `'officialReports', 'officialReportGates'` to the `compact(...)` call in the `return view(...)` line.

- [ ] **Step 2: Add the "Send Report" section to each operation's card**

In `resources/views/subcon/admin/add-process/index.blade.php`, inside the `@foreach($operations as $operation)` loop, right after the existing `<dl class="op-meta-row mb-0">...</dl>` block (still inside `.modern-card-header`), add:

```blade
                        @php
                            $existingOfficialReport = $officialReports[$operation] ?? null;
                            $officialGate = $officialReportGates[$operation] ?? ['ok' => false, 'reason' => 'Not checked.'];
                        @endphp
                        <div class="mt-3 pt-3 border-top">
                            @if($existingOfficialReport)
                                <span class="status-indicator status-indicator-{{ $existingOfficialReport->isCompleted() ? 'success' : 'info' }}">
                                    Official Report:
                                    {{ match(true) {
                                        $existingOfficialReport->isCompleted() => 'Completed',
                                        $existingOfficialReport->needsRevision() => 'Needs Revision (Director declined)',
                                        $existingOfficialReport->isPendingDirector() => 'Pending Director',
                                        default => 'Pending MD Production',
                                    } }}
                                </span>
                            @elseif($officialGate['ok'])
                                <form method="POST" action="{{ route('subcon.admin.official-reports.send') }}" class="d-flex flex-wrap gap-2 align-items-end">
                                    @csrf
                                    <input type="hidden" name="production_group" value="{{ $prg }}">
                                    <input type="hidden" name="operation" value="{{ $operation }}">
                                    <div>
                                        <label class="form-label small mb-1">Additional Deduction (Rp)</label>
                                        <input type="number" step="0.01" min="0" name="additional_deduction" class="form-control form-control-sm" style="width: 140px;" value="0">
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 200px;">
                                        <label class="form-label small mb-1">Remarks</label>
                                        <input type="text" name="remarks" class="form-control form-control-sm">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-signature me-1"></i> Send Official Report
                                    </button>
                                </form>
                            @else
                                <span class="text-muted small"><i class="fas fa-circle-info me-1"></i> Official Report unavailable: {{ $officialGate['reason'] }}</span>
                            @endif
                        </div>
```

- [ ] **Step 3: Verify it renders against live data**

```bash
docker exec vendor_portal_app php artisan tinker --execute="
use Illuminate\Http\Request;
\$user = App\Models\User::where('email','luki.rusli@megaperintis.co.id')->first();
Auth::login(\$user);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
\$request = Request::create('/subcon/admin/add-process', 'GET', ['prg' => 'MPG/PRG/2512/000014']);
app()->instance('request', \$request);
\$response = app(App\Http\Controllers\SubconAddProcessController::class)->index(\$request, app(App\Services\SubconProductionService::class));
echo 'Rendered length: '.strlen(\$response->render()).PHP_EOL;
echo 'Has Send Official Report button: '.(str_contains(\$response->render(), 'Send Official Report') ? 'yes' : 'no').PHP_EOL;
"
```
Expected: no error, `Has Send Official Report button: yes` (this PRG's `ADD-Was` operation was verified earlier this session to have a confirmed matched PO and — check via `docker exec vendor_portal_app php artisan tinker --execit="..."` whether a `SubconOrder` exists for it and what `workflow_stage` it's in, since the gate depends on that; if it's still in `cutting`/`cutting_review`, the button won't render and that's correct behavior, not a bug — note which case it landed in).

- [ ] **Step 4: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Http/Controllers/SubconAddProcessController.php
docker exec vendor_portal_app php vendor/bin/pint app/Http/Controllers/SubconAddProcessController.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Http/Controllers/SubconAddProcessController.php resources/views/subcon/admin/add-process/index.blade.php
git commit -m "subcon: add Send Official Report button to the Add Process page"
```

---

## Task 11: Merge into the Report Validation tab

**Files:**
- Modify: `app/Http/Controllers/SubconAdminController.php` (`reportValidations()`)
- Modify: `resources/views/subcon/admin/report-validations.blade.php`

**Interfaces:**
- Consumes: `SubconOfficialReport::where('status', 'pending_ho')->where('revision_count', '>', 0)` (Task 1).

- [ ] **Step 1: Union in Official Report rows inside `reportValidations()`**

After the existing `$pending = $this->pendingValidateSends(...)` line, add:

```php
        // Official Report rows needing MD Production's revision (Director
        // declined) — union alongside the qms-session rows, same page, per
        // the design's "one page, no redundant new list" decision (spec §7).
        $officialReportRevisions = \App\Models\SubconOfficialReport::where('status', \App\Models\SubconOfficialReport::STATUS_PENDING_HO)
            ->where('revision_count', '>', 0)
            ->get()
            ->map(fn ($r) => [
                'type' => 'official_report',
                'id' => $r->id,
                'order_number' => null,
                'style' => null,
                'vendor' => $r->matched_po_vendor_name,
                'production_group' => $r->production_group,
                'operation' => $r->operation,
                'total_deduction' => $r->totalDeduction(),
                'revision_count' => $r->revision_count,
                'action_url' => route('subcon.admin.official-reports.ho-approve', $r->id),
            ])->all();

        if ($search !== '') {
            $officialReportRevisions = self::filterPendingRows($officialReportRevisions, $search);
        }

        $pending = array_merge($pending, $officialReportRevisions);
```

Move this block so it runs after the existing `if ($search !== '') { $pending = self::filterPendingRows($pending, $search); }` line — actually simplest: apply the search filter to `$officialReportRevisions` on its own (as written above) before merging, so both sources get filtered consistently; then delete the now-redundant second filter pass on `$pending` for the qms rows if it would double-filter — check the existing method's exact line order first (`grep -n "filterPendingRows" app/Http/Controllers/SubconAdminController.php`) and place this block so each source is filtered exactly once before the merge.

- [ ] **Step 2: Mark each row's type in the view**

In `resources/views/subcon/admin/report-validations.blade.php`, wherever the row's "Approve"/action link is rendered, branch on `$row['type'] ?? 'qc'`:

```blade
@if(($row['type'] ?? 'qc') === 'official_report')
    <span class="badge bg-info-subtle text-info border">Add Process</span>
@else
    <span class="badge bg-secondary-subtle text-secondary border">QC/HO/Director</span>
@endif
```

and point the row's action button at `$row['action_url']` when `type === 'official_report'` instead of whatever token-based URL the existing qms rows use — check the current template's exact action-link markup first (`grep -n "approval_token\|approveUrl\|ho-approve" resources/views/subcon/admin/report-validations.blade.php`) and adapt in place rather than rewriting the whole table.

- [ ] **Step 3: Verify it renders**

```bash
docker exec vendor_portal_app php artisan tinker --execute="
use Illuminate\Http\Request;
\$user = App\Models\User::where('email','luki.rusli@megaperintis.co.id')->first();
Auth::login(\$user);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
\$request = Request::create('/subcon/admin/report-validations', 'GET', []);
app()->instance('request', \$request);
\$response = app(App\Http\Controllers\SubconAdminController::class)->reportValidations(\$request);
echo 'Rendered length: '.strlen(\$response->render()).PHP_EOL;
"
```
Expected: no error.

- [ ] **Step 4: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Http/Controllers/SubconAdminController.php
docker exec vendor_portal_app php vendor/bin/pint app/Http/Controllers/SubconAdminController.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Http/Controllers/SubconAdminController.php resources/views/subcon/admin/report-validations.blade.php
git commit -m "subcon: merge Official Report revisions into the Report Validation tab"
```

---

## Task 12: Merge into the Director Approvals tab

**Files:**
- Modify: `app/Http/Controllers/SubconAdminController.php` (`directorApprovals()`)
- Modify: `resources/views/subcon/admin/director-approvals.blade.php`

**Interfaces:**
- Consumes: `SubconOfficialReport::where('status', 'pending_director')` (Task 1).

- [ ] **Step 1: Union in Official Report rows inside `directorApprovals()`**

Same shape as Task 11, sourced from `pendingDirectorApprovals()`'s call site inside `directorApprovals()`:

```php
        $officialReportsPendingDirector = \App\Models\SubconOfficialReport::where('status', \App\Models\SubconOfficialReport::STATUS_PENDING_DIRECTOR)
            ->get()
            ->map(fn ($r) => [
                'type' => 'official_report',
                'id' => $r->id,
                'order_number' => null,
                'style' => null,
                'vendor' => $r->matched_po_vendor_name,
                'production_group' => $r->production_group,
                'operation' => $r->operation,
                'total_deduction' => $r->totalDeduction(),
                'action_url' => route('subcon.admin.official-reports.director-approve', $r->id),
            ])->all();
```

Check the exact variable name `directorApprovals()` merges its own `pendingDirectorApprovals()` result into (`grep -n "pendingDirectorApprovals\|finalApprovals" app/Http/Controllers/SubconAdminController.php`) and `array_merge` this array into the same one, applying `filterPendingRows` the same way as Task 11.

- [ ] **Step 2: Mark each row's type in the view**

Same badge/action-url pattern as Task 11, applied to `resources/views/subcon/admin/director-approvals.blade.php`.

- [ ] **Step 3: Verify it renders**

```bash
docker exec vendor_portal_app php artisan tinker --execute="
use Illuminate\Http\Request;
\$user = App\Models\User::where('email','luki.rusli@megaperintis.co.id')->first();
Auth::login(\$user);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
\$request = Request::create('/subcon/admin/director-approvals', 'GET', []);
app()->instance('request', \$request);
\$response = app(App\Http\Controllers\SubconAdminController::class)->directorApprovals(\$request);
echo 'Rendered length: '.strlen(\$response->render()).PHP_EOL;
"
```
Expected: no error.

- [ ] **Step 4: Lint, full Subcon regression, commit**

```bash
docker exec vendor_portal_app php -l app/Http/Controllers/SubconAdminController.php
docker exec vendor_portal_app php vendor/bin/pint app/Http/Controllers/SubconAdminController.php
docker exec vendor_portal_app php artisan test --filter=Subcon
git add app/Http/Controllers/SubconAdminController.php resources/views/subcon/admin/director-approvals.blade.php
git commit -m "subcon: merge Official Reports pending Director into the Director Approvals tab"
```

---

## Task 13: Full regression + Pint + manual smoke test

**Files:** none (verification only)

- [ ] **Step 1: Run the entire test suite**

```bash
docker exec vendor_portal_app php artisan test
```
Expected: same 11 pre-existing failures as the session baseline (all unrelated — `QcDirectorApprovalTest`, `SubconCuttingApprovalTest` x3, `SubconCuttingReportTest` label-generation/settings x6, `SyncD365SubconOrdersTest`), plus every new test from Tasks 1–12 passing. No new failures anywhere else in the suite.

- [ ] **Step 2: Pint across the whole feature's files**

```bash
docker exec vendor_portal_app php vendor/bin/pint --test \
  app/Models/SubconOfficialReport.php \
  app/Services/SubconOfficialReportService.php \
  app/Services/D365JobTransactionService.php \
  app/Services/SubconProductionService.php \
  app/Services/RpaQueueService.php \
  app/Http/Controllers/SubconOfficialReportController.php \
  app/Http/Controllers/SubconAddProcessController.php \
  app/Http/Controllers/SubconAdminController.php \
  app/Mail/SubconOfficialReportApprovalMailable.php \
  app/Mail/SubconOfficialReportDeclinedMailable.php \
  routes/web.php
```
Expected: `PASS` — if it reports style issues, run without `--test` to auto-fix, then re-run `--test` to confirm, then commit the fix.

- [ ] **Step 3: Manual smoke test against live data (mirrors this session's Add Process verification)**

```bash
docker exec vendor_portal_app php artisan route:list 2>&1 | grep -i "official-report"
```
Confirm all 10 routes list.

Then, using a real PRG+operation this session already verified has a confirmed matched PO (`MPG/PRG/2512/000014` / `ADD-Was`, matched to PO `MPG/PO/2601/00302`) and a `SubconOrder` whose `workflow_stage` is past `cutting_review` (check first; advance a test copy if needed rather than mutating real data), walk the full flow via `tinker` calls to `app(SubconOfficialReportService::class)` exactly as Tasks 4/6/7's own tests did, but against this real data instead of the sqlite test DB, and confirm:
  - `gate()` returns `ok: true`.
  - `send()` creates a `pending_ho` row with a non-null `sales_price` (or logs why it's null — a PLM-less standalone group is an expected null case, not a bug).
  - `renderPdfDataUri()` returns a non-empty data URI.
  - `applyHoApproval()` / `applyDirectorApproval()` transition correctly and a `rpa_queues` row appears for `invoice_addprocess` (check `deduction_addprocess` only appears when `total_deduction > 0`).

Delete the test row afterward: `SubconOfficialReport::where('production_group', 'MPG/PRG/2512/000014')->delete();` and the matching `rpa_queues` rows, so this manual smoke test leaves no residue in real data.

- [ ] **Step 4: Report findings back to the user, including anything skipped or deferred**

Explicitly confirm/deny before declaring done:
  - Automaton-side `invoice_addprocess`/`deduction_addprocess` listeners are out of scope (spec §13) — rows will sit `pending` in `rpa_queues` until that separate-repo work exists. State this plainly, don't imply it's wired end-to-end.
  - Confirm whether the smoke-tested PRG's `SubconOrder` was actually past cutting approval, or whether the gate check was only exercised via the in-house path / test DB — say which.
