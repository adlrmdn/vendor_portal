<?php

namespace Database\Seeders\Concerns;

/**
 * Tiny shared CSV reader for the PC module's seed files under
 * database/seeders/data/pc/ (sourced from the PC spec package's lampiran
 * C/H attachments). Strips the UTF-8 BOM those files carry on their header
 * row — plain fgetcsv() would otherwise fold it into the first column name.
 */
trait ReadsPcSeedCsv
{
    /**
     * Uses fgetcsv() (not a manual fgets()+str_getcsv() split) specifically
     * because some seed rows (e.g. note_templates.csv clause 3) carry a
     * quoted field with embedded newlines — fgets() would cut that field
     * mid-way and corrupt the row; fgetcsv() keeps reading until its
     * quotes balance.
     *
     * @return array<int, array<string, string>>
     */
    protected function readPcCsv(string $filename): array
    {
        $path = database_path("seeders/data/pc/{$filename}");
        $rows = [];

        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        // Skip a UTF-8 BOM if present, so it doesn't fold into the first header name.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = null;
        while (($fields = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $fields;

                continue;
            }

            if (count($fields) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $fields);
        }

        fclose($handle);

        return $rows;
    }
}
