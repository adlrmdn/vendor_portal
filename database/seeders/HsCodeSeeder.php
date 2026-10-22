<?php

namespace Database\Seeders;

use App\Models\HsCode;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

/**
 * Seeds the 97 HS Codes known to have been used on legacy PC contracts
 * (PC spec Appendix H). Run manually: php artisan db:seed --class=HsCodeSeeder
 * — not auto-chained into DatabaseSeeder, matching FinanceAdminSeeder's
 * convention.
 */
class HsCodeSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('hs_codes.csv') as $row) {
            HsCode::firstOrCreate(
                ['code' => $row['hs_code']],
                ['description' => $row['typical_composition'] ?: null, 'source' => 'seed']
            );
        }
    }
}
