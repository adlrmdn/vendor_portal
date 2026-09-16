<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet, every style on the board stacked vertically as its own
 * mini-table — not one shared table across all styles, because different
 * styles use different Cutting ratio/marker columns (same reason the
 * on-screen board gives each style its own <table>, not one shared table;
 * see style-progress-rows.blade.php). Figures are cumulative totals to
 * date (matching the "X of Y made" figures already on screen), not a
 * day-by-day ledger.
 *
 * $boldRows are the 1-based row numbers SubconDailyProductionController
 * tracked while building $rows: each style's title row, its table's header
 * row, and its table's Total row.
 */
class SubconDailyProductionExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<int, string|int|float>>  $rows
     * @param  array<int, int>  $boldRows
     */
    public function __construct(protected array $rows, protected array $boldRows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Daily Production';
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        foreach ($this->boldRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
