<?php

namespace Database\Seeders;

use App\Models\ToleranceTemplate;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

/**
 * Seeds the WOVEN/KNIT shipment-tolerance tables (PC spec Appendix C10).
 * effective_from is backdated — see NoteTemplateSeeder's identical reasoning.
 */
class ToleranceTemplateSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('tolerance_templates.csv') as $row) {
            ToleranceTemplate::firstOrCreate(
                ['fabric_type' => $row['kind'], 'sort_order' => (int) $row['row_no'], 'effective_from' => '2020-01-01'],
                [
                    'row_label' => $row['label'],
                    'tolerance' => $row['tolerance'],
                    'highlight' => filter_var($row['highlight'], FILTER_VALIDATE_BOOLEAN),
                ]
            );
        }
    }
}
