<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cutting report for a single subcon work order, for the admin's records.
 *
 * Works at any workflow stage — it exports whatever cutting-qty / gramasi has
 * been entered so far (cells stay blank where a size hasn't been filled in),
 * so the same button is useful both before and after approval.
 *
 * The controller builds the full 2D grid (meta block + fabric reconciliation
 * table + size table) and the 1-based row numbers of each section; this class
 * only renders and styles it as an actual formatted report — real table grids
 * with borders/fills/number formats, not a vertical label:value dump. Values
 * arrive as raw numeric types (not pre-formatted strings) so Excel can still
 * sum/sort/filter them; display formatting is applied here as native cell
 * number formats instead.
 *
 * @param  array<int, array<int, mixed>>  $rows  Full sheet grid (1 row per array entry)
 * @param  array{
 *   titleRow: int, metaRows: array<int,int>, reconSectionRow: int,
 *   reconHeaderRow: ?int, reconDataRows: array<int,int>, reconTotalRow: ?int,
 *   sizeHeaderRow: int, sizeDataRows: array<int,int>, totalRow: int,
 *   balanceColors: array<int,string>,
 * }  $sections
 */
class SubconCuttingReportExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Last column letter used by the widest table (the 12-column fabric reconciliation table). */
    private const LAST_COL = 'L';

    public function __construct(
        protected array $rows,
        protected array $sections = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Cutting Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34, 'B' => 13, 'C' => 15, 'D' => 12, 'E' => 12,
            'F' => 13, 'G' => 11, 'H' => 10, 'I' => 11, 'J' => 15,
            'K' => 15, 'L' => 15,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->style($event->sheet->getDelegate());
            },
        ];
    }

    private function style(Worksheet $sheet): void
    {
        $s = $this->sections;

        // Print/page setup: landscape + fit-to-width so the 12-column fabric
        // table doesn't paginate mid-row if this is ever printed or exported to PDF.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        // Title band.
        $sheet->mergeCells('A'.$s['titleRow'].':'.self::LAST_COL.$s['titleRow']);
        $sheet->getStyle('A'.$s['titleRow'])->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($s['titleRow'])->setRowHeight(24);

        // Meta info block: bold labels, thin box around the whole block.
        if (! empty($s['metaRows'])) {
            $first = min($s['metaRows']);
            $last = max($s['metaRows']);
            $sheet->getStyle('A'.$first.':A'.$last)->getFont()->setBold(true);
            $sheet->getStyle('A'.$first.':B'.$last)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
        }

        // Fabric Reconciliation & Consumption — section band + real table.
        $sheet->mergeCells('A'.$s['reconSectionRow'].':'.self::LAST_COL.$s['reconSectionRow']);
        $sheet->getStyle('A'.$s['reconSectionRow'])->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF7']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($s['reconSectionRow'])->setRowHeight(20);

        if ($s['reconHeaderRow']) {
            $this->headerRow($sheet, $s['reconHeaderRow'], self::LAST_COL);

            $numberFormats = [
                'B' => '#,##0.00', 'C' => '#,##0.00', 'D' => '#,##0.00', 'E' => '#,##0.00',
                'F' => '#,##0.00', 'G' => '0.00##', 'H' => '#,##0', 'I' => '0.00##',
                'J' => '0.00%', 'K' => '#,##0.00', 'L' => '"Rp" #,##0.00',
            ];
            foreach ($s['reconDataRows'] as $row) {
                $sheet->getStyle('A'.$row.':'.self::LAST_COL.$row)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                foreach ($numberFormats as $col => $format) {
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode($format);
                    $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                // Fixed row height for the wrapped fabric name (~3 lines at column
                // A's width) — without it, LibreOffice/PDF export doesn't always
                // auto-grow the row and the wrapped text bleeds into the row below.
                $sheet->getRowDimension($row)->setRowHeight(48);
            }

            if ($s['reconTotalRow']) {
                $this->totalRow($sheet, $s['reconTotalRow'], self::LAST_COL);
                $sheet->getStyle('L'.$s['reconTotalRow'])->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
                $sheet->getStyle('L'.$s['reconTotalRow'])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        } else {
            $sheet->getStyle('A'.($s['reconSectionRow'] + 1))->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        }

        // "Exported" stamp — small, muted, no borders.
        $exportedRow = $s['sizeHeaderRow'] - 2;
        if ($exportedRow >= 1) {
            $sheet->getStyle('A'.$exportedRow.':B'.$exportedRow)->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
            ]);
        }

        // Cutting progress by size — table header + grid + colored Balance column.
        $sizeLastCol = 'G';
        $this->headerRow($sheet, $s['sizeHeaderRow'], $sizeLastCol);

        $sizeFormats = ['D' => '#,##0', 'E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0.00'];
        foreach ($s['sizeDataRows'] as $row) {
            $sheet->getStyle('A'.$row.':'.$sizeLastCol.$row)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
            foreach ($sizeFormats as $col => $format) {
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode($format);
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $this->totalRow($sheet, $s['totalRow'], $sizeLastCol);
        foreach (['D', 'E', 'F'] as $col) {
            $sheet->getStyle($col.$s['totalRow'])->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($col.$s['totalRow'])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Balance column (F) font colour: red = shortfall, green = exact/surplus —
        // matches the in-app production table and the approval email.
        foreach ($s['balanceColors'] as $row => $rgb) {
            $sheet->getStyle('F'.$row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($rgb));
        }
    }

    private function headerRow(Worksheet $sheet, int $row, string $lastCol): void
    {
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    private function totalRow(Worksheet $sheet, int $row, string $lastCol): void
    {
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '9CA3AF']]],
        ]);
    }
}
