<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rows produced by RpaQueueReadService::rows(), as shown on the Finance
 * Admin Invoices tab and its Subcon Admin read-only mirror. Shared by both
 * controllers — the table columns are identical except Finance's view shows
 * a Portal column (Fabric/Subcon), which the Subcon admin's view omits since
 * it's already scoped to portal='Subcon'.
 */
class RpaInvoiceExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $rows,
        protected string $sheetTitle,
        protected bool $includePortal = true,
    ) {}

    public function array(): array
    {
        return $this->rows->map(function ($r) {
            $row = [
                $r->po ?: '-',
                $r->vendor_name ?: '-',
            ];

            if ($this->includePortal) {
                $row[] = $r->portal;
            }

            $row[] = $r->style ?: '-';
            $row[] = $r->status === 'processing' ? 'Processing RPA' : ucfirst((string) $r->status);
            $row[] = $r->reason ?: '';
            $row[] = \Illuminate\Support\Carbon::parse($r->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i');
            $row[] = \Illuminate\Support\Carbon::parse($r->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i');
            $row[] = $r->checked ? 'Yes' : 'No';

            return $row;
        })->all();
    }

    public function headings(): array
    {
        $headings = ['PO', 'Vendor'];

        if ($this->includePortal) {
            $headings[] = 'Portal';
        }

        $headings = array_merge($headings, ['Style', 'Status', 'Reason', 'Submission (WIB)', 'Updated (WIB)', 'Checked']);

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
