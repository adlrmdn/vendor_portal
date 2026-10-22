<?php

namespace Database\Seeders;

use App\Models\Currency;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('currencies.csv') as $row) {
            Currency::firstOrCreate(['code' => $row['code']], [
                'label' => $row['name'],
                'd365_code' => $row['d365_code'],
            ]);
        }
    }
}
