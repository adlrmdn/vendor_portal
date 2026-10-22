<?php

namespace Database\Seeders;

use App\Models\Season;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('seasons.csv') as $row) {
            Season::firstOrCreate(['code' => $row['code']], [
                'label' => $row['label'],
                'is_open' => filter_var($row['is_open'], FILTER_VALIDATE_BOOLEAN),
                'sort_key' => (int) $row['sort_key'],
            ]);
        }
    }
}
