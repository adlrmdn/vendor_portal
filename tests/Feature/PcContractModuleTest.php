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
            'lines' => [
                [
                    'fabric_item_id' => $this->fabricItem->id,
                    'plm_ref_id' => $this->plmRef->id,
                    'hs_code_id' => $this->hsCode->id,
                    'qty' => '1000',
                    'unit_price' => '12.50',
                    'deliveries' => [
                        ['lot_id' => $this->lot->id, 'delivery_qty' => '1000', 'eta_date' => now()->addMonth()->toDateString()],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        // Pull the id out of the edit-route redirect rather than guessing via
        // created_at ordering — timestamps have second-level granularity and
        // this helper is called several times per test in tight loops.
        preg_match('#/admin/pc/contracts/([^/]+)/edit#', $response->headers->get('Location'), $m);

        return PcContract::findOrFail($m[1]);
    }

    public function test_happy_path_draft_to_confirmed(): void
    {
        $contract = $this->createDraftContract();
        $this->assertDatabaseCount('pc_contracts', 1);
        $this->assertSame(1, $contract->lines()->count());

        $submitResponse = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $submitResponse->assertRedirect(route('admin.pc.contracts.show', $contract));
        $this->assertSame('pending_vendor_review', $contract->fresh()->status);

        Mail::assertSent(\App\Mail\PcContractSentMailable::class);

        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract))->assertRedirect();
        $this->assertSame('pending_final_approval', $contract->fresh()->status);

        $approveResponse = $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->approver),
        ]);
        $approveResponse->assertRedirect(route('admin.pc.contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('confirmed', $contract->status);
        $this->assertMatchesRegularExpression('#^MPG/PC/PDV/\d{2}/[IVX]+/\d{3}$#', $contract->contract_number);
        $this->assertCount(2, $contract->notes);
        $this->assertCount(1, $contract->tolerances);

        $file = $contract->files()->first();
        $this->assertNotNull($file);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_vendor_query_sends_back_to_draft(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));

        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.query', $contract), [
            'reason' => 'Price is too low for this quality.',
        ])->assertRedirect(route('vendor.pc.contracts.index'));

        $contract->refresh();
        $this->assertSame('draft', $contract->status);
        $this->assertSame('queried', $contract->vendor_decision);
        $this->assertDatabaseHas('pc_contract_vendor_responses', [
            'contract_id' => $contract->id,
            'decision' => 'queried',
        ]);
    }

    public function test_approver_reject_sends_back_to_draft_not_vendor_review(): void
    {
        $contract = $this->createDraftContract();
        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract));

        $this->actingAs($this->approver)->post(route('admin.pc.contracts.reject', $contract), [
            'reason' => 'Price looks off, please double-check.',
            'code' => $this->totpCode($this->approver),
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame('draft', $contract->status);
        $this->assertNotSame('pending_vendor_review', $contract->status);
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
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract));

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => $this->totpCode($this->preparer),
        ])->assertRedirect();

        $this->assertSame('pending_final_approval', $contract->fresh()->status);
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
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract));

        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), [
            'code' => '000000',
        ])->assertRedirect();

        $this->assertSame('pending_final_approval', $contract->fresh()->status);
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
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $contract));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $contract), ['code' => $this->totpCode($this->approver)]);

        $originalNumber = $contract->fresh()->contract_number;

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.revise', $contract))->assertRedirect();
        $revision = PcContract::where('previous_revision_id', $contract->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($originalNumber, $revision->contract_number);
        $this->assertSame(1, $revision->revision_no);

        $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $revision));
        $this->actingAs($this->vendorUser)->post(route('vendor.pc.contracts.accept', $revision));
        $this->actingAs($this->approver)->post(route('admin.pc.contracts.approve', $revision), ['code' => $this->totpCode($this->approver)]);

        $this->assertSame('superseded', $contract->fresh()->status);
        $this->assertSame('confirmed', $revision->fresh()->status);
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

    public function test_submit_blocked_when_vendor_has_no_login(): void
    {
        $vendorNoLogin = new Vendor([
            'name' => 'No Login Vendor', 'vendor_code' => 'V0002', 'type' => 'fabric', 'is_active' => true,
            'currency_code' => 'USD', 'payment_term_id' => $this->paymentTerm->id,
        ]);
        $vendorNoLogin->id = (string) Str::uuid();
        $vendorNoLogin->save();

        $contract = PcContract::create([
            'vendor_id' => $vendorNoLogin->id,
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
        $line = $contract->lines()->create([
            'line_no' => 1, 'fabric_item_id' => $this->fabricItem->id, 'item_number_snapshot' => $this->fabricItem->item_number,
            'plm_ref_id' => $this->plmRef->id, 'hs_code_id' => $this->hsCode->id, 'qty' => '10', 'unit_price' => '1',
        ]);
        $line->deliveries()->create(['contract_id' => $contract->id, 'lot_id' => $this->lot->id, 'delivery_qty' => '10', 'eta_date' => now()->addMonth()]);

        $response = $this->actingAs($this->preparer)->post(route('admin.pc.contracts.submit', $contract));
        $response->assertRedirect(route('admin.pc.contracts.edit', $contract));
        $response->assertSessionHas('errors');
        $this->assertSame('draft', $contract->fresh()->status);
    }
}
