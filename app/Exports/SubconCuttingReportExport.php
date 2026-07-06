<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cutting report for a single subcon work order, for the admin's records.
 *
 * Works at any workflow stage — it exports whatever cutting-qty / gramasi has
 * been entered so far (cells stay blank where a size hasn't been filled in),
 * so the same button is useful both before and after approval.
 *
 * The full 2D grid (meta header block + size table + total row) is built by the
 * controller, which holds the VSM production lines and the local reports; this
 * class only renders and styles it.
 *
 * @param  array<int, array<int, mixed>>  $rows  Full sheet grid (1 row per array entry)
 * @param  array<int, int>  $boldRows  1-based row numbers to render bold
 * @param  array<int, string>  $balanceColors  1-based row => hex RGB for the Balance cell (col F)
 */
class SubconCuttingReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected array $rows,
        protected array $boldRows = [],
        protected array $balanceColors = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        foreach ($this->boldRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        // Per-cell font colour on the Balance column (F): red = shortfall,
        // green = exact/surplus. Cell-level keys merge with the row-level bold
        // above, so the total row stays bold and coloured.
        foreach ($this->balanceColors as $row => $rgb) {
            $styles['F'.$row] = ['font' => ['color' => ['rgb' => $rgb]]];
        }

        return $styles;
    }

    public function title(): string
    {
        return 'Cutting Report';
    }
}
