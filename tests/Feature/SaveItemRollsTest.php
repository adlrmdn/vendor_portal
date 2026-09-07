<?php

namespace Tests\Feature;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Roll;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaveItemRollsTest extends TestCase
{
    private User $user;

    private Vendor $vendor;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        // The in-memory DB persists across tests in one process — clean our fixtures.
        foreach (Vendor::where('vendor_code', 'V_TEST_ROLLS')->get() as $vendor) {
            Roll::whereIn('item_id', PoItem::whereIn('po_id', PurchaseOrder::where('vendor_id', $vendor->id)->pluck('id'))->pluck('id'))->delete();
            PoItem::whereIn('po_id', PurchaseOrder::where('vendor_id', $vendor->id)->pluck('id'))->delete();
            PurchaseOrder::where('vendor_id', $vendor->id)->delete();
            User::where('vendor_id', $vendor->id)->delete();
            $vendor->delete();
        }

        $this->vendor = new Vendor([
            'name' => 'Test Fabric Vendor',
            'vendor_code' => 'V_TEST_ROLLS',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $this->vendor->id = (string) Str::uuid();
        $this->vendor->save();

        $this->user = new User([
            'name' => 'Vendor User',
            'email' => 'rolls-vendor@test.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_vendor',
            'vendor_id' => $this->vendor->id,
        ]);
        $this->user->id = (string) Str::uuid();
        $this->user->save();

        $this->po = new PurchaseOrder([
            'po_number' => 'PO-ROLLS-TEST',
            'vendor_id' => $this->vendor->id,
            'status' => 'processing',
            'order_date' => now()->format('Y-m-d'),
        ]);
        $this->po->id = (string) Str::uuid();
        $this->po->save();
    }

    private function makeItem(string $unit, float $quantity = 100.0): PoItem
    {
        $item = new PoItem([
            'po_id' => $this->po->id,
            'item_number' => 'ITEM-'.strtoupper(Str::random(4)),
            'description' => 'Test Fabric',
            'batch' => 'BATCH-A',
            'plm_number' => 'PLM-TEST',
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => 5.00,
            'total_price' => $quantity * 5.00,
            'status' => 'pending',
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        return $item;
    }

    public function test_yd_item_saves_all_metrics_and_extra_fields()
    {
        $item = $this->makeItem('YD');

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    [
                        'length_yd' => '50.00',
                        'weight' => '12.50',
                        'vendor_roll_no' => 'VR-01',
                        'bale_no' => 'B-7',
                        'internal_id' => 'LOT-1',
                        'color' => 'Navy',
                    ],
                ],
            ]);

        $response->assertRedirect(route('vendor.item.process', $item->id));

        $roll = Roll::where('item_id', $item->id)->firstOrFail();
        $this->assertSame('YD', $roll->unit);
        $this->assertEquals(50.00, (float) $roll->length_yd);
        // M derived from YD (50 × 0.9144)
        $this->assertEquals(45.72, (float) $roll->length_m);
        $this->assertEquals(12.50, (float) $roll->weight);
        $this->assertSame('VR-01', $roll->vendor_roll_no);
        $this->assertSame('B-7', $roll->bale_no);
        $this->assertSame('LOT-1', $roll->internal_id);
        $this->assertSame('Navy', $roll->color);
        $this->assertSame('PO-ROLLS-TEST-'.$item->item_number.'-001', $roll->roll_number);

        // Delivered qty counts only the order-metric column — no double counting
        // even though YD, M and KG are all stored.
        $this->assertEquals(50.00, $item->fresh()->totalDeliveredQuantity());
    }

    public function test_yd_item_accepts_meters_only_and_derives_yards()
    {
        $item = $this->makeItem('YD');

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    ['length_m' => '45.72'],
                ],
            ]);

        $response->assertRedirect(route('vendor.item.process', $item->id));

        $roll = Roll::where('item_id', $item->id)->firstOrFail();
        $this->assertEquals(45.72, (float) $roll->length_m);
        $this->assertEquals(50.00, (float) $roll->length_yd);
        $this->assertNull($roll->weight);
    }

    public function test_kg_item_requires_weight()
    {
        $item = $this->makeItem('KG');

        // Lengths alone do not satisfy a KG order
        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    ['length_yd' => '50.00'],
                ],
            ]);
        $response->assertSessionHasErrors();
        $this->assertSame(0, Roll::where('item_id', $item->id)->count());

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    ['weight' => '25.00', 'length_yd' => '50.00'],
                ],
            ]);
        $response->assertRedirect(route('vendor.item.process', $item->id));

        $roll = Roll::where('item_id', $item->id)->firstOrFail();
        $this->assertSame('KG', $roll->unit);
        $this->assertEquals(25.00, (float) $roll->weight);
        $this->assertEquals(50.00, (float) $roll->length_yd);
        // M derived from the optional YD
        $this->assertEquals(45.72, (float) $roll->length_m);
        $this->assertEquals(25.00, $item->fresh()->totalDeliveredQuantity());
    }

    public function test_process_item_page_renders_all_metric_columns()
    {
        $item = $this->makeItem('YD');

        $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    ['length_yd' => '50.00', 'vendor_roll_no' => 'VR-99', 'bale_no' => 'B-1', 'color' => 'Red'],
                ],
            ]);

        $response = $this->actingAs($this->user)->get(route('vendor.item.process', $item->id));

        $response->assertOk();
        $response->assertSee('Original Order Metric');
        $response->assertSee('Roll No.');
        $response->assertSee('Bale No.');
        $response->assertSee('Color');
        $response->assertSee('Length (YD)');
        $response->assertSee('Length (M)');
        $response->assertSee('Weight (KG)');
        $response->assertSee('VR-99');
    }

    public function test_admin_process_item_page_renders_roll_no_and_saves_it()
    {
        $item = $this->makeItem('YD');

        $admin = new User([
            'name' => 'Admin User',
            'email' => 'rolls-admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_admin',
        ]);
        $admin->id = (string) Str::uuid();
        $admin->save();

        $response = $this->actingAs($admin)->get(route('admin.item.process', $item->id));
        $response->assertOk();
        $response->assertSee('Roll No.');
        $response->assertSee('vendor_roll_no');

        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.item.save-rolls', $item->id), [
                'rolls' => [
                    ['length_yd' => '50.00', 'vendor_roll_no' => 'VR-ADMIN'],
                ],
            ]);
        $response->assertRedirect(route('admin.item.process', $item->id));

        $this->assertSame('VR-ADMIN', Roll::where('item_id', $item->id)->firstOrFail()->vendor_roll_no);

        $admin->delete();
    }

    public function test_rolls_template_download()
    {
        $item = $this->makeItem('YD');

        $response = $this->actingAs($this->user)->get(route('vendor.item.rolls-template', $item->id));

        $response->assertOk();
        $response->assertDownload();
    }

    public function test_partial_shipment_sibling_item_gets_non_colliding_roll_numbers()
    {
        // Simulates PoItem::splitToPartialShipment(): a second po_items row for
        // the same item_number on the same PO, holding the undelivered remainder.
        $original = $this->makeItem('YD');
        $original->item_number = 'ITEM-SHARED';
        $original->save();

        $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $original->id), [
                'rolls' => [
                    ['length_yd' => '50.00'],
                    ['length_yd' => '30.00'],
                ],
            ])->assertRedirect(route('vendor.item.process', $original->id));

        $shadow = new PoItem([
            'po_id' => $this->po->id,
            'item_number' => 'ITEM-SHARED',
            'description' => 'Test Fabric',
            'batch' => 'BATCH-A-P2',
            'plm_number' => 'PLM-TEST',
            'quantity' => 20.0,
            'unit' => 'YD',
            'unit_price' => 5.00,
            'total_price' => 100.00,
            'status' => 'pending',
        ]);
        $shadow->id = (string) Str::uuid();
        $shadow->save();

        $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $shadow->id), [
                'rolls' => [
                    ['length_yd' => '20.00'],
                ],
            ])->assertRedirect(route('vendor.item.process', $shadow->id));

        $originalNumbers = Roll::where('item_id', $original->id)->pluck('roll_number');
        $shadowNumbers = Roll::where('item_id', $shadow->id)->pluck('roll_number');

        $this->assertEmpty($originalNumbers->intersect($shadowNumbers), 'sibling po_items must not produce colliding roll_number values');
        $this->assertSame(['PO-ROLLS-TEST-ITEM-SHARED-003'], $shadowNumbers->all());
    }

    public function test_pcs_item_stores_quantity_in_weight_column()
    {
        $item = $this->makeItem('PCS');

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('vendor.item.save-rolls', $item->id), [
                'rolls' => [
                    ['weight' => '40'],
                ],
            ]);
        $response->assertRedirect(route('vendor.item.process', $item->id));

        $roll = Roll::where('item_id', $item->id)->firstOrFail();
        $this->assertSame('PCS', $roll->unit);
        $this->assertEquals(40.00, (float) $roll->weight);
        $this->assertEquals(40.00, $item->fresh()->totalDeliveredQuantity());
    }
}
