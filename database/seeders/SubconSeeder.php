<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Adds ONLY the subcon accounts to an existing (production) database.
 *
 * Safe to run against live data:
 *  - Uses firstOrCreate (keyed on natural unique columns), so it never throws
 *    duplicate-key errors, never overwrites the PK/id of existing rows, and
 *    leaves existing passwords / manual edits untouched.
 *  - Touches nothing on the fabric side.
 *
 * Run AFTER `php artisan migrate`:
 *  php artisan db:seed --class=SubconSeeder
 */
class SubconSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Subcon dev/test vendor — PT WORLD KNK SURYA ANUGERAH (V0246)
        $vendor = Vendor::firstOrCreate(
            ['vendor_code' => 'V0246'],
            [
                'id'           => (string) Str::uuid(),
                'name'         => 'PT WORLD KNK SURYA ANUGERAH',
                'group'        => 'Subcon',
                'type'         => 'subcon',
                'contact_info' => [
                    'phone'   => null,
                    'email'   => 'vendor@worldknk.id',
                    'address' => null,
                ],
                'is_active'    => true,
            ]
        );

        // 2. Subcon admin (no vendor scope)
        User::firstOrCreate(
            ['email' => 'subcon.admin@mp.com'],
            [
                'id'       => (string) Str::uuid(),
                'name'     => 'Subcon Admin',
                'password' => Hash::make('password'),
                'role'     => 'subcon_admin',
            ]
        );

        // 3. Subcon vendor user (scoped to the subcon vendor above)
        User::firstOrCreate(
            ['email' => 'vendor@worldknk.id'],
            [
                'id'        => (string) Str::uuid(),
                'name'      => 'World KNK Surya Anugerah',
                'password'  => Hash::make('password'),
                'role'      => 'subcon_vendor',
                'vendor_id' => $vendor->id,
            ]
        );

        // 4. Root admin (sees BOTH fabric + subcon panels).
        //    The old admin@mp.com is left as `fabric_admin` by the migration;
        //    this is a separate, dedicated root account.
        User::firstOrCreate(
            ['email' => 'root@mp.com'],
            [
                'id'       => (string) Str::uuid(),
                'name'     => 'Root Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // NOTE: Work orders are NOT seeded with fake data. They are real POs
        // pulled from D365 — identical source to the fabric pipeline. After
        // seeding the vendor/users above, populate real work orders with:
        //
        //   php artisan d365:sync-subcon-orders --vendor=V0246 --days=60
        //
        // (see App\Console\Commands\SyncD365SubconOrders)
    }
}
