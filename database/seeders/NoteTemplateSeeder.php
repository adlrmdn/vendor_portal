<?php

namespace Database\Seeders;

use App\Models\NoteTemplate;
use Database\Seeders\Concerns\ReadsPcSeedCsv;
use Illuminate\Database\Seeder;

/**
 * Seeds the 9 standard contract clauses (PC spec Appendix C9). effective_from
 * is backdated well before any real contract_date so these are treated as
 * already in force — there is no earlier version to supersede.
 */
class NoteTemplateSeeder extends Seeder
{
    use ReadsPcSeedCsv;

    public function run(): void
    {
        foreach ($this->readPcCsv('note_templates.csv') as $row) {
            NoteTemplate::firstOrCreate(
                ['clause_number' => (int) $row['note_no'], 'effective_from' => '2020-01-01'],
                [
                    'body' => $row['body'],
                    'emphasis' => filter_var($row['emphasis_red'], FILTER_VALIDATE_BOOLEAN),
                ]
            );
        }
    }
}
