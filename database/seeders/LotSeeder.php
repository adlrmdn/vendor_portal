<?php

namespace Database\Seeders;

use App\Models\Lot;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class LotSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('lots.csv') as $row) {
            Lot::firstOrCreate(['code' => $row['code']], ['label' => $row['label'], 'source' => 'seed']);
        }
    }
}
