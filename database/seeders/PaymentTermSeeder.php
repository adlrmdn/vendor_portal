<?php

namespace Database\Seeders;

use App\Models\PaymentTerm;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('payment_terms.csv') as $row) {
            PaymentTerm::firstOrCreate(['code' => $row['code']], [
                'd365_code' => $row['d365_code'] ?: null,
                'label' => $row['label'],
                'method' => $row['method'] ?: null,
                'days' => $row['days'] !== '' ? (int) $row['days'] : null,
                'basis' => $row['basis'] ?: null,
            ]);
        }
    }
}
