<?php

namespace Database\Seeders;

use App\Models\OrderType;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class OrderTypeSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('order_types.csv') as $row) {
            OrderType::firstOrCreate(['code' => $row['code']], ['label' => $row['label']]);
        }
    }
}
