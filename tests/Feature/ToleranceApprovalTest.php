<?php

namespace Tests\Feature;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Roll;
use App\Models\ToleranceAmendmentRequest;
use App\Models\Vendor;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the signed email approve/decline links: a plain GET
 * used to mutate the request immediately, so a link-prescanning bot fetching
 * the DECLINE button from the email (Outlook Safe Links, mail gateways, spam
 * filters) would silently reject a vendor's request before a human saw it.
 * GET must now only render a confirm page; POST performs the mutation.
 */
class ToleranceApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Vendor::where('vendor_code', 'V_TOLAPPR')->get() as $vendor) {
            $itemIds = PoItem::whereIn('po_id', PurchaseOrder::where('vendor_id', $vendor->id)->pluck('id'))->pluck('id');
            Roll::whereIn('item_id', $itemIds)->delete();
            ToleranceAmendmentRequest::whereIn('po_item_id', $itemIds)->delete();
            PoItem::whereIn('po_id', PurchaseOrder::where('vendor_id', $vendor->id)->pluck('id'))->delete();
            PurchaseOrder::where('vendor_id', $vendor->id)->delete();
            $vendor->delete();
        }
    }

    private function makeRequest(): ToleranceAmendmentRequest
    {
        $vendor = new Vendor([
            'name' => 'Tolerance Approval Vendor',
            'vendor_code' => 'V_TOLAPPR',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        $po = new PurchaseOrder([
            'po_number' => 'PO-TOLAPPR-'.Str::random(5),
            'vendor_id' => $vendor->id,
            'status' => 'processing',
            'order_date' => now()->format('Y-m-d'),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $item = new PoItem([
            'po_id' => $po->id,
            'item_number' => 'ITEM-'.strtoupper(Str::random(4)),
            'description' => 'Test Fabric',
            'batch' => 'BATCH-A',
            'plm_number' => 'PLM-TEST',
            'quantity' => 100.0,
            'unit' => 'YD',
            'unit_price' => 5.00,
            'total_price' => 500.00,
            'status' => 'pending',
            'underdelivery' => 3.00,
            'overdelivery' => 3.00,
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        $request = new ToleranceAmendmentRequest([
            'po_item_id' => $item->id,
            'type' => 'tolerance',
            'old_underdelivery' => 3.00,
            'old_overdelivery' => 3.00,
            'new_underdelivery' => 10.00,
            'new_overdelivery' => 10.00,
            'reason' => 'Test reason',
            'status' => 'pending',
        ]);
        $request->id = (string) Str::uuid();
        $request->save();

        return $request;
    }

    public function test_signed_decline_link_get_does_not_mutate()
    {
        $request = $this->makeRequest();

        $url = URL::signedRoute('tolerance.decline', ['request' => $request->id], absolute: false);

        // No actingAs — this must work without a login, and must NOT decline.
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.confirm');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_signed_approve_link_get_does_not_mutate()
    {
        $request = $this->makeRequest();

        $url = URL::signedRoute('tolerance.approve', ['request' => $request->id], absolute: false);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.confirm');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('3.00', $request->fresh()->poItem->underdelivery);
    }

    public function test_decline_submit_post_actually_declines()
    {
        $request = $this->makeRequest();

        $url = URL::signedRoute('tolerance.decline.submit', ['request' => $request->id], absolute: false);

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');

        $this->assertSame('declined', $request->fresh()->status);
    }

    public function test_approve_submit_post_actually_approves_and_updates_tolerance()
    {
        $request = $this->makeRequest();

        $url = URL::signedRoute('tolerance.approve.submit', ['request' => $request->id], absolute: false);

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');

        $fresh = $request->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('10.00', $fresh->poItem->underdelivery);
        $this->assertSame('10.00', $fresh->poItem->overdelivery);
    }

    /**
     * Approving a partial_shipment request used to only flip its status to
     * 'approved' and leave the actual split to a separate vendor-triggered
     * button — vendors routinely never came back to press it, leaving the
     * item stuck. Approval must now execute the split immediately.
     */
    public function test_approve_submit_post_executes_partial_shipment_split_immediately()
    {
        $vendor = new Vendor([
            'name' => 'Tolerance Approval Vendor',
            'vendor_code' => 'V_TOLAPPR',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        $po = new PurchaseOrder([
            'po_number' => 'PO-TOLAPPR-'.Str::random(5),
            'vendor_id' => $vendor->id,
            'status' => 'processing',
            'order_date' => now()->format('Y-m-d'),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $item = new PoItem([
            'po_id' => $po->id,
            'item_number' => 'ITEM-'.strtoupper(Str::random(4)),
            'description' => 'Test Fabric',
            'batch' => 'BATCH-A',
            'plm_number' => 'PLM-TEST',
            'quantity' => 100.0,
            'unit' => 'YD',
            'unit_price' => 5.00,
            'total_price' => 500.00,
            'status' => 'processing',
            'underdelivery' => 3.00,
            'overdelivery' => 3.00,
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        $roll = new Roll([
            'item_id' => $item->id,
            'roll_number' => 'ROLL-1',
            'sequence' => 1,
            'length_yd' => 50.0,
            'unit' => 'YD',
        ]);
        $roll->id = (string) Str::uuid();
        $roll->save();

        $request = new ToleranceAmendmentRequest([
            'po_item_id' => $item->id,
            'type' => 'partial_shipment',
            'requested_qty' => 50.0,
            'reason' => 'Under-delivery test',
            'status' => 'pending',
        ]);
        $request->id = (string) Str::uuid();
        $request->save();

        $url = URL::signedRoute('tolerance.approve.submit', ['request' => $request->id], absolute: false);

        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');

        // The request is 'implemented', not left at 'approved' — the split
        // already ran, so the old manual button can't re-run it.
        $this->assertSame('implemented', $request->fresh()->status);

        $original = $item->fresh();
        $this->assertSame('completed', $original->status);
        $this->assertEquals(50.0, (float) $original->quantity);

        $shadow = PoItem::where('po_id', $po->id)
            ->where('item_number', $item->item_number)
            ->where('id', '!=', $item->id)
            ->first();

        $this->assertNotNull($shadow, 'Expected a shadow -P2 item holding the remaining balance.');
        $this->assertSame('pending', $shadow->status);
        $this->assertEquals(50.0, (float) $shadow->quantity);
        $this->assertSame('BATCH-A-P2', $shadow->batch);
    }

    public function test_decline_link_on_already_actioned_request_shows_result_not_confirm()
    {
        $request = $this->makeRequest();
        $request->update(['status' => 'approved', 'actioned_at' => now()]);

        $url = URL::signedRoute('tolerance.decline', ['request' => $request->id], absolute: false);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('approvals.result');
        $response->assertSee('already been approved');
    }
}
