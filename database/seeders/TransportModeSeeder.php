<?php

namespace Database\Seeders;

use App\Models\TransportMode;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class TransportModeSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('transport_modes.csv') as $row) {
            TransportMode::firstOrCreate(['code' => $row['code']], ['label' => $row['label'], 'source' => 'seed']);
        }
    }
}
