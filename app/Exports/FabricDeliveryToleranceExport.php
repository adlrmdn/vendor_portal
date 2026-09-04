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
 * Rows produced by FabricDeliveryToleranceService::outsideOriginalToleranceRows(),
 * as shown on the Finance Admin "Fabric Delivery" tab.
 */
class FabricDeliveryToleranceExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected Collection $rows) {}

    public function array(): array
    {
        return $this->rows->map(fn ($r) => [
            $r->po_number,
            $r->vendor_name,
            $r->style,
            $r->item_number,
            $r->unit,
            $r->shipments,
            $r->ordered,
            $r->delivered,
            $r->pct_vs_ordered,
            $r->orig_underdelivery_pct,
            $r->orig_overdelivery_pct,
            $r->min_allowed,
            $r->max_allowed,
            $r->was_split_shipment ? 'Yes' : 'No',
            $r->tolerance_was_amended ? 'Yes' : 'No',
            $r->type,
        ])->all();
    }

    public function headings(): array
    {
        return [
            'PO Number', 'Vendor', 'Style', 'Item Number', 'Unit', 'Shipments in Group',
            'Ordered Qty', 'Delivered Qty', 'Diff % vs Ordered',
            'Original Underdelivery %', 'Original Overdelivery %', 'Min Allowed', 'Max Allowed',
            'Was Partial-Shipment Split', 'Tolerance Was Later Amended', 'Type',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Fabric Delivery';
    }
}
