<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\DeliveryTerm;
use App\Models\HsCode;
use App\Models\Lot;
use App\Models\NoteTemplate;
use App\Models\OrderType;
use App\Models\PaymentTerm;
use App\Models\PcContract;
use App\Models\PcFabricItem;
use App\Models\PcPlmRef;
use App\Models\Season;
use App\Models\Setting;
use App\Models\ToleranceTemplate;
use App\Models\TransportMode;
use App\Models\User;
use App\Models\VatTreatment;
use App\Models\Vendor;
use App\Services\PcContractNumberService;
use App\Services\TotpService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PcContractModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $preparer;

    private User $approver;

    private User $vendorUser;

    private Vendor $vendor;

    private PaymentTerm $paymentTerm;

    private Season $season;

    private OrderType $orderType;

    private TransportMode $transportMode;

    private DeliveryTerm $deliveryTerm;

    private VatTreatment $vatTreatment;

    private HsCode $hsCode;

    private Lot $lot;

    private PcFabricItem $fabricItem;

    private PcPlmRef $plmRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Mail::fake();
        Storage::fake('local');

        $currency = Currency::create(['code' => 'USD', 'd365_code' => 'USD', 'label' => 'UNITED STATES DOLLAR']);
        $this->paymentTerm = PaymentTerm::create(['code' => 'TT_90', 'd365_code' => 'N90', 'label' => '90 DAYS AFTER ETD', 'method' => 'TT']);
        $this->season = Season::create(['code' => "FALL'26", 'label' => "Fall 2026", 'is_open' => true, 'sort_key' => 1]);
        $this->orderType = OrderType::create(['code' => 'MAIN', 'label' => 'Main Order']);
        $this->transportMode = TransportMode::create(['code' => 'SEA', 'label' => 'SEA', 'source' => 'seed']);
        $this->deliveryTerm = DeliveryTerm::create(['code' => 'FOB', 'label' => 'FOB', 'source' => 'seed']);
        $this->vatTreatment = VatTreatment::create(['code' => 'EXCLUDE', 'label' => 'EXCLUDE']);
        $this->hsCode = HsCode::create(['code' => '5208599090', 'description' => '100% COTTON', 'source' => 'seed']);
        $this->lot = Lot::create(['code' => '1-2', 'label' => '1-2', 'source' => 'seed']);
        NoteTemplate::create(['clause_number' => 1, 'body' => 'Clause one.', 'emphasis' => false, 'effective_from' => '2020-01-01']);
        NoteTemplate::create(['clause_number' => 9, 'body' => 'Clause nine.', 'emphasis' => true, 'effective_from' => '2020-01-01']);
        ToleranceTemplate::create(['fabric_type' => 'WOVEN', 'row_label' => 'More than 3000 mtrs', 'tolerance' => '+2%', 'sort_order' => 1, 'effective_from' => '2020-01-01']);

        $this->fabricItem = PcFabricItem::create(['item_number' => '2606000000123', 'item_name' => 'WOVEN 100% COTTON', 'uom_code' => 'MTR', 'is_active' => true]);
        $this->plmRef = PcPlmRef::create(['plm_id' => 'PLM/26/06/00123', 'article_name' => 'Style A', 'season' => "FALL'26"]);

        $this->vendor = new Vendor([
            'name' => 'Example Textile Co.',
            'vendor_code' => 'V0001',
            'type' => 'fabric',
            'is_active' => true,
            'currency_code' => 'USD',
            'payment_term_id' => $this->paymentTerm->id,
        ]);
        $this->vendor->id = (string) Str::uuid();
        $this->vendor->save();

        $this->preparer = $this->makeUser('leon@mpg.test', 'fabric_admin');
        $this->approver = $this->makeUser('aslam@mpg.test', 'fabric_admin');
        $this->vendorUser = $this->makeUser('vendor@example.com', 'fabric_vendor', $this->vendor->id);

        Setting::updateOrCreate(['key' => 'pc_approver_emails'], ['value' => $this->approver->email]);

        $totp = app(TotpService::class);
        $this->approver->totp_secret = $totp->generateSecretKey();
        $this->approver->totp_confirmed_at = now();
        $this->approver->save();
    }

    private function makeUser(string $email, string $role, ?string $vendorId = null): User
    {
        $user = new User([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'vendor_id' => $vendorId,
        ]);
        $user->id = (string) Str::uuid();
        $user->save();

        return $user;
    }

    private function totpCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->totp_secret);
    }

    private function createDraftContract(): PcContract
    {
        $response = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.store'), [
            'vendor_id' => $this->vendor->id,
            'fabric_type' => 'WOVEN',
            'contract_date' => now()->toDateString(),
            'season_id' => $this->season->id,
            'order_type_id' => $this->orderType->id,
            'transport_mode_id' => $this->transportMode->id,
            'delivery_term_id' => $this->deliveryTerm->id,
            'vat_treatment_id' => $this->vatTreatment->id,
            'vat_rate' => '11.00',
            'deliveries' => [
                ['lot_id' => $this->lot->id, 'delivery_qty' => '1000', 'eta_date' => now()->addMonth()->toDateString()],
            ],
            'lines' => [
                [
                    'fabric_item_id' => $this->fabricItem->id,
                    'plm_ref_id' => $this->plmRef->id,
                    'hs_code_id' => $this->hsCode->id,
                    'qty' => '1000',
                    'unit_price' => '2.50',
                ],
            ],
        ]);

        $response->assertRedirect();

        preg_match('#/admin/pc/contracts/([^/]+)/edit#', $response->headers->get('Location'), $m);

        return PcContract::findOrFail($m[1]);
    }

    public function test_happy_path_draft_to_confirmed(): void
    {
        $contract = $this->createDraftContract();
        $this->assertDatabaseCount('pc_contracts', 1);
        $this->assertSame('draft', $contract->status);
        $this->assertSame(1, $contract->lines()->count());

        // 1. Leon submits draft for internal approval
        $submitResponse = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $submitResponse->assertRedirect(route('admin.pc.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('pending_approval', $contract->status);
        $this->assertMatchesRegularExpression('#^MPG/PC/PDV/\d{2}/[IVX]+/\d{3}$#', $contract->contract_number);

        // 2. Aslam approves with TOTP code
        $approveResponse = $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->approver),
        ]);
        $approveResponse->assertRedirect(route('admin.pc.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('approved', $contract->status);
        $this->assertCount(2, $contract->notes);
        $this->assertCount(1, $contract->tolerances);

        $file = $contract->files()->first();
        $this->assertNotNull($file);
        Storage::disk('local')->assertExists($file->path);

        // 3. Preparer sends the approved contract to supplier
        $sendResponse = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.send-to-supplier', $contract));
        $sendResponse->assertRedirect(route('admin.pc.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('sent_to_supplier', $contract->status);
        Mail::assertSent(\App\Mail\PcContractSentMailable::class);

        // 4. Supplier accepts contract in portal
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract))->assertRedirect();

        $contract->refresh();
        $this->assertSame('confirmed', $contract->status);
        $this->assertSame('accepted', $contract->vendor_decision);
        $this->assertDatabaseHas('pc_contract_vendor_responses', [
            'contract_id' => $contract->id,
            'decision' => 'accepted',
        ]);
    }

    public function test_vendor_query_transitions_to_supplier_queried(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->approver),
        ]);
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.send-to-supplier', $contract));

        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.query', $contract), [
            'reason' => 'Price is too low for this construction quality.',
        ])->assertRedirect(route('vendor.pc.contracts.index'));

        $contract->refresh();
        $this->assertSame('supplier_queried', $contract->status);
        $this->assertSame('queried', $contract->vendor_decision);
        $this->assertDatabaseHas('pc_contract_vendor_responses', [
            'contract_id' => $contract->id,
            'decision' => 'queried',
        ]);
    }

    public function test_approver_reject_sends_back_to_draft(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $this->actingAs($this->approver)->post(route('admin.pc.contracts.reject', $contract), [
            'reason' => 'Price looks incorrect, please verify with vendor.',
            'code' => $this->totpCode($this->approver),
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame('draft', $contract->status);
        $this->assertSame('rejected', $contract->approval_decision);
    }

    public function test_separation_of_duties_blocks_self_approval(): void
    {
        Setting::updateOrCreate(['key' => 'pc_approver_emails'], ['value' => $this->preparer->email]);
        $totp = app(TotpService::class);
        $this->preparer->totp_secret = $totp->generateSecretKey();
        $this->preparer->totp_confirmed_at = now();
        $this->preparer->save();

        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->preparer),
        ])->assertRedirect();

        $this->assertSame('pending_approval', $contract->fresh()->status);
    }

    public function test_number_uniqueness_under_repeated_calls(): void
    {
        $service = app(PcContractNumberService::class);
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $service->issue();
        }

        $this->assertCount(10, array_unique($numbers));
    }

    public function test_totp_gate_rejects_wrong_code(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => '000000',
        ])->assertRedirect();

        $this->assertSame('pending_approval', $contract->fresh()->status);
    }

    public function test_cancellation_by_creator_approver_and_admin(): void
    {
        $admin = $this->makeUser('admin@mpg.test', 'admin');

        foreach ([$this->preparer, $this->approver, $admin] as $actor) {
            $contract = $this->createDraftContract();
            $this->actingAs($actor)->post(route('admin.pc.contracts.cancel', $contract), [
                'reason' => 'No longer needed.',
            ])->assertRedirect();
            $this->assertSame('cancelled', $contract->fresh()->status);
        }

        $other = $this->makeUser('other@mpg.test', 'fabric_admin');
        $contract = $this->createDraftContract();
        $this->actingAs($other)->post(route('admin.pc.contracts.cancel', $contract), [
            'reason' => 'Trying anyway.',
        ])->assertForbidden();
    }

    public function test_revision_cycle_supersedes_original(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), ['code' => $this->totpCode($this->approver)]);

        $originalNumber = $contract->fresh()->contract_number;

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.revise', $contract))->assertRedirect();
        $revision = PcContract::where('previous_revision_id', $contract->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($originalNumber, $revision->contract_number);
        $this->assertSame(1, $revision->revision_no);

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $revision));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $revision), ['code' => $this->totpCode($this->approver)]);

        $this->assertSame('superseded', $contract->fresh()->status);
        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertSame($originalNumber, $revision->fresh()->contract_number);
    }

    public function test_vat_math_include_divides_by_rate(): void
    {
        $vatInclude = VatTreatment::create(['code' => 'INCLUDE', 'label' => 'INCLUDE']);

        $contract = PcContract::create([
            'vendor_id' => $this->vendor->id,
            'created_by' => $this->preparer->id,
            'vat_treatment_id' => $vatInclude->id,
            'vat_rate' => '11.00',
            'status' => 'draft',
        ]);
        $line = $contract->lines()->create([
            'line_no' => 1,
            'fabric_item_id' => $this->fabricItem->id,
            'item_number_snapshot' => $this->fabricItem->item_number,
            'hs_code_id' => $this->hsCode->id,
            'qty' => '10',
            'unit_price' => '11.10',
        ]);

        $this->assertSame('10.0000', $line->unit_price_ex_vat);
    }

    public function test_send_to_supplier_blocked_when_vendor_has_no_login(): void
    {
        $vendorNoLogin = new Vendor([
            'name' => 'No Login Vendor', 'vendor_code' => 'V0002', 'type' => 'fabric', 'is_active' => true,
            'currency_code' => 'USD', 'payment_term_id' => $this->paymentTerm->id,
        ]);
        $vendorNoLogin->id = (string) Str::uuid();
        $vendorNoLogin->save();

        $contract = $this->createDraftContract();
        $contract->vendor_id = $vendorNoLogin->id;
        $contract->save();

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->approver),
        ]);

        // Trying to send without vendor login fails (PC-A02)
        $response = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.send-to-supplier', $contract));
        $response->assertSessionHas('error');
        $this->assertSame('approved', $contract->fresh()->status);
    }

    public function test_price_band_rejects_extreme_unit_price(): void
    {
        $contract = PcContract::create([
            'vendor_id' => $this->vendor->id,
            'created_by' => $this->preparer->id,
            'fabric_type' => 'WOVEN',
            'contract_date' => now()->toDateString(),
            'season_id' => $this->season->id,
            'order_type_id' => $this->orderType->id,
            'transport_mode_id' => $this->transportMode->id,
            'delivery_term_id' => $this->deliveryTerm->id,
            'vat_treatment_id' => $this->vatTreatment->id,
            'currency_id' => Currency::where('code', 'USD')->value('id'),
            'payment_term_id' => $this->paymentTerm->id,
            'status' => 'draft',
        ]);
        $contract->deliveries()->create([
            'contract_id' => $contract->id, 'contract_line_id' => $contract->id,
            'lot_id' => $this->lot->id, 'delivery_qty' => '100', 'eta_date' => now()->addMonth(),
        ]);
        // USD per MTR acceptable band is 0.22 to 16.00. 150.00 is a magnitude error (e.g. entered IDR/RMB)
        $contract->lines()->create([
            'line_no' => 1, 'fabric_item_id' => $this->fabricItem->id, 'item_number_snapshot' => $this->fabricItem->item_number,
            'plm_ref_id' => $this->plmRef->id, 'hs_code_id' => $this->hsCode->id, 'qty' => '10', 'unit_price' => '150.00',
        ]);

        $response = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $response->assertSessionHas('errors');
        $this->assertSame('draft', $contract->fresh()->status);
    }

    public function test_pdf_preview_and_official_pdf_generation(): void
    {
        $contract = $this->createDraftContract();

        // 1. Preparer can preview PDF for draft
        $previewResp = $this->actingAs($this->preparer)->get(route('admin.pc.contracts.pdf', $contract));
        $previewResp->assertOk();
        $this->assertSame('application/pdf', $previewResp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $previewResp->getContent());

        // 2. Submit and Approve
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->approver),
        ]);

        // 3. Stored PDF file exists and can be retrieved
        $contract = $contract->fresh();
        $this->assertNotNull($contract->pdf_generated_at);
        $officialResp = $this->actingAs($this->preparer)->get(route('admin.pc.contracts.pdf', $contract));
        $officialResp->assertOk();
        $this->assertSame('application/pdf', $officialResp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $officialResp->getContent());

        // 4. Vendor can also download the official PDF
        $vendorResp = $this->actingAs($this->vendorUser)->get(route('vendor.pc.contracts.pdf', $contract));
        $vendorResp->assertOk();
        $this->assertSame('application/pdf', $vendorResp->headers->get('Content-Type'));
    }

    public function test_approvals_hub_renders_and_lists_pending_pc_contracts(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $response = $this->actingAs($this->approver)->get(route('admin.approvals'));
        $response->assertOk();
        $response->assertSee($contract->contract_number);
        $response->assertSee('Preliminary Contracts');
        $response->assertSee('Approve');
    }

    public function test_admin_sidebar_and_pc_views_render_successfully(): void
    {
        $contract = $this->createDraftContract();

        $indexResp = $this->actingAs($this->approver)->get(route('admin.pc.index'));
        $indexResp->assertOk();

        $showResp = $this->actingAs($this->approver)->get(route('admin.pc.contracts.show', $contract));
        $showResp->assertOk();
    }

    public function test_daily_analysis_export_generates_csvs_and_manifest(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $exportService = app(\App\Services\PcAnalysisExportService::class);
        $result = $exportService->generateExport();

        $this->assertGreaterThanOrEqual(1, $result['contracts_count']);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists('analysis/latest/pc_contract_lines.csv'));
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists('analysis/latest/pc_contracts.csv'));
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists('analysis/latest/manifest.json'));

        $manifestContent = Storage::disk(config('filesystems.default'))->get('analysis/latest/manifest.json');
        $manifest = json_decode($manifestContent, true);
        $this->assertSame('1.0', $manifest['schema_version']);
        $this->assertArrayHasKey('pc_contract_lines.csv', $manifest['files']);
        $this->assertArrayHasKey('pc_contracts.csv', $manifest['files']);
    }

    public function test_fabric_items_search_finds_items(): void
    {
        $response = $this->actingAs($this->preparer)->get(route('admin.pc.search.fabric-items', ['q' => 'COTTON']));
        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $numbers = collect($data)->pluck('item_number')->all();
        $this->assertContains($this->fabricItem->item_number, $numbers);
    }

    public function test_user_can_decide_currency_different_from_vendor_default(): void
    {
        $idr = Currency::firstOrCreate(['code' => 'IDR'], ['d365_code' => 'IDR', 'label' => 'INDONESIAN RUPIAH']);
        $usd = Currency::firstOrCreate(['code' => 'USD'], ['d365_code' => 'USD', 'label' => 'UNITED STATES DOLLAR']);

        // Vendor default is IDR
        $this->vendor->currency_code = 'IDR';
        $this->vendor->save();

        // Preparer creates a contract selecting USD
        $response = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.store'), [
            'vendor_id' => $this->vendor->id,
            'currency_id' => $usd->id,
            'fabric_type' => 'WOVEN',
            'contract_date' => now()->toDateString(),
            'season_id' => $this->season->id,
            'order_type_id' => $this->orderType->id,
            'transport_mode_id' => $this->transportMode->id,
            'delivery_term_id' => $this->deliveryTerm->id,
            'vat_treatment_id' => $this->vatTreatment->id,
            'vat_rate' => '11.00',
            'deliveries' => [
                ['lot_id' => $this->lot->id, 'delivery_qty' => '500', 'eta_date' => now()->addMonth()->toDateString()],
            ],
            'lines' => [
                [
                    'fabric_item_id' => $this->fabricItem->id,
                    'plm_ref_id' => $this->plmRef->id,
                    'hs_code_id' => $this->hsCode->id,
                    'qty' => '500',
                    'unit_price' => '3.50',
                ],
            ],
        ]);

        $response->assertRedirect();
        preg_match('#/admin/pc/contracts/([^/]+)/edit#', $response->headers->get('Location'), $m);
        $contract = PcContract::findOrFail($m[1]);

        $this->assertSame($usd->id, $contract->currency_id);

        // Submitting should succeed without PC-H03 blocking
        $submitResp = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $submitResp->assertRedirect();
        $this->assertSame(PcContract::STATUS_PENDING_APPROVAL, $contract->fresh()->status);
    }

    public function test_fabric_item_details_endpoint_returns_specs_and_history(): void
    {
        $this->fabricItem->item_name = 'DENIM 100% COTTON 7X6/68X48 57/58" CW 13 OZ TWILL 3/1 YARN DYED';
        $this->fabricItem->save();

        $response = $this->actingAs($this->preparer)->get(route('admin.pc.search.item-details', [
            'item_id' => $this->fabricItem->id,
            'vendor_id' => $this->vendor->id,
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertSame($this->fabricItem->item_number, $data['item_number']);
        $this->assertSame('MTR', $data['uom_code']);
        $this->assertArrayHasKey('specs', $data);
        $this->assertSame('100% COTTON', $data['specs']['composition'] ?? null);
        $this->assertSame('7X6/68X48', $data['specs']['construction'] ?? null);
        $this->assertSame('13 OZ', $data['specs']['weight'] ?? null);
    }

    public function test_plm_refs_search_excludes_finished_plms_and_prioritizes_active_style_for_fabric(): void
    {
        $po = new \App\Models\PurchaseOrder([
            'po_number' => 'PO-TEST-' . Str::random(4),
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'currency' => 'IDR',
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $activePlm = PcPlmRef::create([
            'plm_id' => 'PLM/26/TEST/ACTIVE',
            'article_name' => 'Active Shirt Blue',
            'brand' => 'Manzone',
            'season' => 'WINTER-26',
        ]);

        $finishedPlm = PcPlmRef::create([
            'plm_id' => 'PLM/26/TEST/FINISHED',
            'article_name' => 'Finished Shirt Red',
            'brand' => 'Manzone',
            'season' => 'WINTER-25',
        ]);

        $item1 = new \App\Models\PoItem([
            'po_id' => $po->id,
            'item_number' => $this->fabricItem->item_number,
            'description' => 'Fabric Desc',
            'batch' => 'B1',
            'plm_number' => $finishedPlm->plm_id,
            'status' => 'completed',
            'quantity' => 100,
            'unit' => 'YD',
            'unit_price' => 50000,
            'total_price' => 5000000,
        ]);
        $item1->id = (string) Str::uuid();
        $item1->save();

        $item2 = new \App\Models\PoItem([
            'po_id' => $po->id,
            'item_number' => $this->fabricItem->item_number,
            'description' => 'Fabric Desc',
            'batch' => 'B2',
            'plm_number' => $activePlm->plm_id,
            'status' => 'pending',
            'quantity' => 200,
            'unit' => 'YD',
            'unit_price' => 55000,
            'total_price' => 11000000,
        ]);
        $item2->id = (string) Str::uuid();
        $item2->save();

        // Search PLMs with fabric_item_id context
        $response = $this->actingAs($this->preparer)->get(route('admin.pc.search.plm-refs', [
            'fabric_item_id' => $this->fabricItem->id,
            'q' => 'TEST',
        ]));

        $response->assertOk();
        $data = $response->json();

        // Finished PLM should be moved out (excluded)
        $plmIds = collect($data)->pluck('plm_id')->all();
        $this->assertContains('PLM/26/TEST/ACTIVE', $plmIds);
        $this->assertNotContains('PLM/26/TEST/FINISHED', $plmIds);

        // Active PLM for this fabric item should be tagged as recommended
        $activeMatch = collect($data)->firstWhere('plm_id', 'PLM/26/TEST/ACTIVE');
        $this->assertTrue($activeMatch['is_recommended'] ?? false);

        // itemDetails should also return recommended_plm pointing to the active style
        $detailsRes = $this->actingAs($this->preparer)->get(route('admin.pc.search.item-details', [
            'item_id' => $this->fabricItem->id,
            'vendor_id' => $this->vendor->id,
        ]));
        $detailsRes->assertOk();
        $details = $detailsRes->json();
        $this->assertNotNull($details['recommended_plm']);
        $this->assertSame($activePlm->plm_id, $details['recommended_plm']['plm_number']);
        $this->assertFalse($details['recommended_plm']['is_completed']);
    }
}

