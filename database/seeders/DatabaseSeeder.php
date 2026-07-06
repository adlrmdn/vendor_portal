<?php

namespace Database\Seeders;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use DateTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent: keyed on each row's natural unique column via firstOrCreate(),
     * so this is safe to re-run (including `db:seed` / `migrate --seed`) against an
     * existing database without duplicate-key errors. Existing rows are left as-is
     * (passwords / manual edits are not clobbered).
     */
    public function run()
    {
        // Temporarily disable model events to avoid UUID generation issues
        \App\Models\User::flushEventListeners();
        \App\Models\Vendor::flushEventListeners();
        \App\Models\PurchaseOrder::flushEventListeners();
        \App\Models\PoItem::flushEventListeners();

        // Root admin
        User::firstOrCreate(
            ['email' => 'admin@mp.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Root Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // Fabric admin
        User::firstOrCreate(
            ['email' => 'fabric.admin@mp.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Fabric Admin',
                'password' => Hash::make('password'),
                'role' => 'fabric_admin',
            ]
        );

        // Fabric vendors
        $vendor = Vendor::firstOrCreate(
            ['vendor_code' => 'V0095'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'HUZHOU ANGYE DIGITAL INDUSTRY CO LTD',
                'group' => 'Import',
                'type' => 'fabric',
                'contact_info' => [
                    'phone' => '572-3152-209 / 135-1572-1175',
                    'address' => 'No. 188 Dagang Road Zhili - Huzhou SHANGHAI CHN',
                    'email' => 'None',
                ],
                'is_active' => true,
            ]
        );
        $vendorId = $vendor->id;

        $vendor1 = Vendor::firstOrCreate(
            ['vendor_code' => 'V0216'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'PT GOLDEN TEKSTIL INDONESIA',
                'group' => 'Local',
                'type' => 'fabric',
                'contact_info' => [
                    'phone' => '0271825251',
                    'address' => 'Jl. Komp Kw Industri Kendal, Jawa Tengah KENDAL, IDN',
                    'email' => 'allen.yang@goldenteks.id',
                ],
                'is_active' => true,
            ]
        );
        $vendorId1 = $vendor1->id;

        // Fabric vendor users
        User::firstOrCreate(
            ['email' => 'vendor@angye.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Vendor Angye',
                'password' => Hash::make('password'),
                'role' => 'fabric_vendor',
                'vendor_id' => $vendorId,
            ]
        );

        User::firstOrCreate(
            ['email' => 'vendor@goldenteks.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Vendor Goldenteks',
                'password' => Hash::make('password'),
                'role' => 'fabric_vendor',
                'vendor_id' => $vendorId1,
            ]
        );

        // Create sample purchase orders
        $po = PurchaseOrder::firstOrCreate(
            ['po_number' => 'MPG/PO/2512/00926'],
            [
                'id' => Str::uuid()->toString(),
                'vendor_id' => $vendorId,
                'status' => 'pending',
                'total_amount' => 70972.00,
                'currency' => 'CNY',
                'order_date' => DateTime::createFromFormat('m/d/Y', '12/30/2025'),
                'delivery_date' => DateTime::createFromFormat('m/d/Y', '1/12/2026'),
            ]
        );
        $poId = $po->id;

        $po1 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'MPG/PO/2512/00927'],
            [
                'id' => Str::uuid()->toString(),
                'vendor_id' => $vendorId,
                'status' => 'pending',
                'total_amount' => 59942.83,
                'currency' => 'CNY',
                'order_date' => DateTime::createFromFormat('m/d/Y', '12/30/2025'),
                'delivery_date' => DateTime::createFromFormat('m/d/Y', '12/30/2025'),
            ]
        );
        $poId1 = $po1->id;

        $po2 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'MPG/PO/2512/00928'],
            [
                'id' => Str::uuid()->toString(),
                'vendor_id' => $vendorId,
                'status' => 'pending',
                'total_amount' => 60083.13,
                'currency' => 'CNY',
                'order_date' => DateTime::createFromFormat('m/d/Y', '12/30/2025'),
                'delivery_date' => DateTime::createFromFormat('m/d/Y', '1/12/2026'),
            ]
        );
        $poId2 = $po2->id;

        $po3 = PurchaseOrder::firstOrCreate(
            ['po_number' => 'MPG/PO/2512/00638'],
            [
                'id' => Str::uuid()->toString(),
                'vendor_id' => $vendorId1,
                'status' => 'pending',
                'total_amount' => 148036960.00,
                'currency' => 'IDR',
                'order_date' => DateTime::createFromFormat('m/d/Y', '12/19/2025'),
                'delivery_date' => DateTime::createFromFormat('m/d/Y', '12/22/2025'),
            ]
        );
        $poId3 = $po3->id;

        // Create sample PO items
        PoItem::firstOrCreate(
            ['po_id' => $poId, 'item_number' => '2508000000018'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'WOVEN 100% COTTON 40X40/150X82 56/57" CW 128 GSM DOBBY PRINT SOFT AS SHAKA SM26-MAGN WHITE',
                'batch' => 'Manzone Magnar White - SUMMER-26',
                'plm_number' => 'PLM/25/07/00241',
                'quantity' => 4032.50,
                'unit' => 'M',
                'unit_price' => 17.60,
                'total_price' => 70972.00,
                'color' => 'WHITE',
                'status' => 'pending',
            ]
        );

        PoItem::firstOrCreate(
            ['po_id' => $poId1, 'item_number' => '2508000000020'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'WOVEN 100% COTTON 40X40/144X96 56/57\" CW 128 GSM DOBBY PRINT SOFT AS UGRASENA SM26-SAPT KHAKI',
                'batch' => 'Manzone Sapta Khaki - SUMMER-26',
                'plm_number' => 'PLM-002',
                'quantity' => 3793.85,
                'unit' => 'M',
                'unit_price' => 15.80,
                'total_price' => 59942.83,
                'color' => 'KHAKI',
                'status' => 'pending',
            ]
        );

        PoItem::firstOrCreate(
            ['po_id' => $poId2, 'item_number' => '2508000000019'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'WOVEN 100% COTTON 40X40/144X96 56/57\" CW 128 GSM DOBBY PRINT SOFT AS UGRASENA SM26-SADA LIGHT GREY',
                'batch' => 'Manzone Sadawira Grey_Light - SUMMER-26',
                'plm_number' => 'PLM-002',
                'quantity' => 3802.73,
                'unit' => 'M',
                'unit_price' => 15.80,
                'total_price' => 60083.13,
                'color' => 'LIGHT GREY',
                'status' => 'pending',
            ]
        );

        PoItem::firstOrCreate(
            ['po_id' => $poId3, 'item_number' => '2509000000035'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'WOVEN 98% COTTON 2% SPANDEX 21X16+70D/157X48 56/57\" CW 260 GSM TWILL 3/1 SOLID PEACHED 2ADS01514-1 THYR DARK NAVY',
                'batch' => 'Edwin Thayer Chinos - Dark Navy Navy_Dark - SPRING',
                'plm_number' => 'PLM-003',
                'quantity' => 1824.23,
                'unit' => 'M',
                'unit_price' => 43000.00,
                'total_price' => 78441890.00,
                'color' => 'DARK NAVY',
                'status' => 'pending',
            ]
        );

        PoItem::firstOrCreate(
            ['po_id' => $poId3, 'item_number' => '2511000000030'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'WOVEN 98% COTTON 2% SPANDEX 21X16+70D/157X48 56/57\" CW 260 GSM TWILL 3/1 SOLID PEACHED 2ADS01514-1 FRND DARK NAVY',
                'batch' => 'Edwin Ferando Chinos - Dark Navy Navy_Dark - SPRIN',
                'plm_number' => 'PLM-004',
                'quantity' => 1618.49,
                'unit' => 'M',
                'unit_price' => 43000.00,
                'total_price' => 69595070.00,
                'color' => 'DARK NAVY',
                'status' => 'pending',
            ]
        );

        // Re-enable model events
        \App\Models\User::boot();
        \App\Models\Vendor::boot();
        \App\Models\PurchaseOrder::boot();
        \App\Models\PoItem::boot();
    }
}
