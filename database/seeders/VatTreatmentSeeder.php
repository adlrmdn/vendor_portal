<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\VatTreatment;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed EXCLUDE/INCLUDE rows plus the default PPN rate — per PC
 * spec 6.3, the default rate (11.00) lives in Settings, not on the
 * vat_treatments row itself, since a contract snapshots its own vat_rate
 * and the default can change independently later.
 */
class VatTreatmentSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('vat_treatments.csv') as $row) {
            VatTreatment::firstOrCreate(['code' => $row['code']], ['label' => $row['label']]);

            if (! empty($row['default_rate_percent'])) {
                Setting::updateOrCreate(
                    ['key' => 'pc_default_vat_rate'],
                    [
                        'value' => $row['default_rate_percent'],
                        'group' => 'pc',
                        'type' => 'string',
                        'description' => 'Default PPN rate (%) applied to a new PC contract when VAT/PPN = INCLUDE',
                    ]
                );
            }
        }
    }
}
