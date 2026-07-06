<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Subcon CMT vendor list + portal login credentials, produced by
 * d365:sync-subcon-vendors. Written to the project root so the rollout sheet
 * lives next to the app (subcon_vendors_credentials.xlsx).
 *
 * @param  array<int, array{code:string,name:string,group:?string,contact_email:?string,phone:?string,login_email:string,password:string,po_count:int}>  $rows
 */
class SubconVendorCredentialsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected array $rows = []) {}

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['code'],
            $r['name'],
            $r['group'] ?? '',
            $r['contact_email'] ?? '',
            $r['phone'] ?? '',
            $r['login_email'],
            $r['password'],
            $r['po_count'] ?? 0,
        ], $this->rows);
    }

    public function headings(): array
    {
        return [
            'Vendor Code',
            'Vendor Name',
            'Group',
            'Contact Email',
            'Phone',
            'Login Email',
            'Login Password',
            'CMT POs (12 mo)',
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
        return 'Subcon Vendors';
    }
}
