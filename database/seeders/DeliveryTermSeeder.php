<?php

namespace Database\Seeders;

use App\Models\DeliveryTerm;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class DeliveryTermSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('delivery_terms.csv') as $row) {
            DeliveryTerm::firstOrCreate(['code' => $row['code']], ['label' => $row['label'], 'source' => 'seed']);
        }
    }
}
