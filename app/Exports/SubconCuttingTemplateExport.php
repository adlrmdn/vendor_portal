<?php

namespace App\Exports;

use App\Models\SubconOrder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cutting / gramasi report template. When built for a specific order it is
 * pre-filled with the production lines (Production ID, Size, Order Qty) so the
 * vendor only fills the Qty Cut / Gramasi columns — matching the importer's
 * header detection exactly.
 *
 * A per-order "Fabric Reconciliation" reference block (short roll / sisa kain /
 * kepala kain / retur kain, in meters) is placed above the size table. These are
 * entered in the portal form, not this sheet — the block is reference only and
 * the importer skips it by scanning for the "Production ID" header row.
 */
class SubconCuttingTemplateExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @var array<int, array<int, string|int|float>> */
    protected array $rows;

    /** 1-based row of the size-table header (bolded, and where the importer starts). */
    protected int $headerRow;

    /**
     * @param  array<int, array{prod_id:string,size:string,order_qty:int|float}>  $lines
     */
    public function __construct(array $lines = [], ?SubconOrder $order = null)
    {
        $rows = [];

        // Per-fabric reconciliation (reference — entered in the portal form, one
        // block per fabric by description). Empty if none recorded yet.
        if ($order) {
            $rows[] = ['Fabric Reconciliation (meters — entered in the portal)'];
            try {
                $reconciliations = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)
                    ->orderBy('label')
                    ->get();
            } catch (\Throwable $e) {
                report($e);
                $reconciliations = collect();
            }
            if ($reconciliations->isEmpty()) {
                $rows[] = ['(none recorded yet)'];
            } else {
                foreach ($reconciliations as $rec) {
                    $rows[] = [$rec->label];
                    $rows[] = ['Short Roll (m)', number_format((float) $rec->short_roll, 2)];
                    $rows[] = ['Sisa Kain (Utuh) (m)', number_format((float) $rec->sisa_kain, 2)];
                    $rows[] = ['Kepala Kain (m)', number_format((float) $rec->kepala_kain, 2)];
                    $rows[] = ['Retur Kain (m)', number_format((float) $rec->retur_kain, 2)];
                }
            }
            $rows[] = ['']; // spacer — a single empty cell so the writer keeps the row
        }

        $this->headerRow = count($rows) + 1;
        $rows[] = ['Production ID', 'Size', 'Order Qty', 'Qty Cut', 'Gramasi (g)'];

        foreach ($lines as $l) {
            $rows[] = [
                $l['prod_id'] ?? '',
                $l['size'] ?? '',
                $l['order_qty'] ?? '',
                '', // Qty Cut — vendor fills
                '', // Gramasi (g) — vendor fills
            ];
        }

        $this->rows = $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Cutting Report';
    }

    public function styles(Worksheet $sheet): array
    {
        // Bold the reconciliation title (row 1) and the size-table header so the
        // columns to fill are obvious.
        return [
            1 => ['font' => ['bold' => true]],
            $this->headerRow => ['font' => ['bold' => true]],
        ];
    }
}
