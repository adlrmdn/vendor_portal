<?php

namespace Tests\Feature;

use App\Models\PackingSlip;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Roll;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackingSlipPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cleanup
        Roll::query()->delete();
        PoItem::query()->delete();
        PackingSlip::query()->delete();
        PurchaseOrder::query()->delete();
        User::query()->where('email', 'like', '%@test.com')->delete();
        Vendor::query()->where('vendor_code', 'like', 'V_TEST%')->delete();
    }

    public function test_packing_slip_pdf_contains_lot_id_and_barcode_data()
    {
        // 1. Create a fabric vendor
        $vendor = new Vendor([
            'name' => 'Test Fabric Vendor',
            'vendor_code' => 'V_TEST_FABRIC',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        // 2. Create a fabric vendor user
        $user = new User([
            'name' => 'Vendor User',
            'email' => 'vendor@test.com',
            'password' => bcrypt('password'),
            'role' => 'fabric_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) Str::uuid();
        $user->save();

        // 3. Create a purchase order
        $po = new PurchaseOrder([
            'po_number' => 'PO-FABRIC-TEST-123',
            'vendor_id' => $vendor->id,
            'status' => 'processing',
            'order_date' => now()->format('Y-m-d'),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        // 4. Create po item
        $item = new PoItem([
            'po_id' => $po->id,
            'item_number' => 'ITEM-001',
            'description' => 'Cotton Blue Fabric',
            'batch' => 'BATCH-A',
            'plm_number' => 'PLM-9988',
            'quantity' => 1000.00,
            'unit' => 'M',
            'unit_price' => 5.00,
            'total_price' => 5000.00,
            'status' => 'processing',
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        // 5. Create roll with lot id (internal_id)
        $roll = new Roll([
            'item_id' => $item->id,
            'roll_number' => 'PO-FABRIC-TEST-123-ITEM-001-001',
            'internal_id' => 'LOT-XYZ-789', // This is the Lot-ID!
            'sequence' => 1,
            'length_m' => 187.00,
            'unit' => 'M',
            'is_printed' => false,
        ]);
        $roll->id = (string) Str::uuid();
        $roll->save();

        // 6. Create packing slip
        $packingSlip = PackingSlip::create([
            'slip_number' => 'SLIP-FABRIC-TEST-001',
            'po_id' => $po->id,
            'vendor_id' => $vendor->id,
            'items' => [$item->id],
        ]);

        // 7. Verify the view renders HTML with Lot-ID and new barcode content
        $selectedItems = PoItem::whereIn('id', $packingSlip->items)
            ->with(['rolls' => function ($q) {
                $q->orderBy('sequence');
            }])
            ->get();

        $html = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems'))->render();

        // Assert Lot-ID table column header exists
        $this->assertStringContainsString('Lot-ID', $html);

        // Assert specific roll lot id is displayed in the rolls details table
        $this->assertStringContainsString('LOT-XYZ-789', $html);

        // Assert that the barcode text contains the combined Lot-ID | Bale No.
        $expectedBarcodeText = 'Lot-ID LOT-XYZ-789 | Bale No. N/A';
        $this->assertStringContainsString($expectedBarcodeText, $html);

        // 7b. Verify the view renders HTML with secondary metric enabled (showSecondary = 1)
        $showSecondary = 1;
        $reverseUnits = 0;
        $htmlSecondary = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'))->render();
        // 187.00 M in YD is 187.00 / 0.9144 = 204.51
        $this->assertStringContainsString('204.51', $htmlSecondary);

        // 7c. Verify the view renders HTML with units reversed (reverseUnits = 1)
        $showSecondary = 0;
        $reverseUnits = 1;
        $htmlReversed = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'))->render();
        // Since units are reversed, M becomes primary YD (204.51 YD)
        $this->assertStringContainsString('204.51', $htmlReversed);

        // 8. Act (call print controller route as vendor)
        $response = $this->actingAs($user)
            ->get(route('vendor.packing-slip.print', $packingSlip->id));

        // Assert response is successful PDF
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());

        // Test with query params
        $responseWithParams = $this->actingAs($user)
            ->get(route('vendor.packing-slip.print', [
                'id' => $packingSlip->id,
                'show_secondary' => 1,
                'reverse_units' => 1,
            ]));
        $responseWithParams->assertStatus(200);
        $responseWithParams->assertHeader('Content-Type', 'application/pdf');

        // 9. Act as Admin and test printing
        $admin = new User([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $admin->id = (string) Str::uuid();
        $admin->save();

        $responseAdmin = $this->actingAs($admin)
            ->get(route('admin.packing-slip.print', $packingSlip->id));

        $responseAdmin->assertStatus(200);
        $responseAdmin->assertHeader('Content-Type', 'application/pdf');

        // Admin with query params
        $responseAdminWithParams = $this->actingAs($admin)
            ->get(route('admin.packing-slip.print', [
                'id' => $packingSlip->id,
                'show_secondary' => 1,
                'reverse_units' => 1,
            ]));
        $responseAdminWithParams->assertStatus(200);
        $responseAdminWithParams->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_packing_slip_with_pieces_and_optional_lengths()
    {
        $vendor = new Vendor([
            'name' => 'Test Vendor 2',
            'vendor_code' => 'V_TEST_FABRIC_2',
            'type' => 'fabric',
            'is_active' => true,
        ]);
        $vendor->id = (string) Str::uuid();
        $vendor->save();

        $po = new PurchaseOrder([
            'po_number' => 'PO-FABRIC-TEST-456',
            'vendor_id' => $vendor->id,
            'status' => 'processing',
            'order_date' => now()->format('Y-m-d'),
        ]);
        $po->id = (string) Str::uuid();
        $po->save();

        $item = new PoItem([
            'po_id' => $po->id,
            'item_number' => 'ITEM-002',
            'description' => 'Wool Red Fabric',
            'batch' => 'BATCH-B',
            'plm_number' => 'PLM-1122',
            'quantity' => 100.00,
            'unit' => 'PCS',
            'unit_price' => 10.00,
            'total_price' => 1000.00,
            'status' => 'processing',
        ]);
        $item->id = (string) Str::uuid();
        $item->save();

        $roll = new Roll([
            'item_id' => $item->id,
            'roll_number' => 'PO-FABRIC-TEST-456-ITEM-002-001',
            'internal_id' => 'LOT-PCS-123',
            'sequence' => 1,
            'weight' => 5.00, // primary for PCS
            'length_yd' => 100.00,
            'length_m' => 91.44,
            'unit' => 'PCS',
            'is_printed' => false,
        ]);
        $roll->id = (string) Str::uuid();
        $roll->save();

        $packingSlip = PackingSlip::create([
            'slip_number' => 'SLIP-FABRIC-TEST-002',
            'po_id' => $po->id,
            'vendor_id' => $vendor->id,
            'items' => [$item->id],
        ]);

        $selectedItems = PoItem::whereIn('id', $packingSlip->items)
            ->with(['rolls' => function ($q) {
                $q->orderBy('sequence');
            }])
            ->get();

        // 1. Without secondary metrics
        $html = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems'))->render();
        $this->assertStringContainsString('Lot-ID LOT-PCS-123 | Bale No. N/A', $html);

        // 2. With secondary metrics enabled (showSecondary = 1)
        $showSecondary = 1;
        $reverseUnits = 0;
        $htmlSecondary = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'))->render();
        // Since primary is PCS, length is YD (100.00 YD)
        $this->assertStringContainsString('5.00 PCS (100.00 YD)', $htmlSecondary);

        // 3. With reverse units enabled (reverseUnits = 1, showSecondary = 1)
        $showSecondary = 1;
        $reverseUnits = 1;
        $htmlReversed = view('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'))->render();
        // Since reverseUnits = 1, length is M (91.44 M)
        $this->assertStringContainsString('5.00 PCS (91.44 M)', $htmlReversed);
    }
}
