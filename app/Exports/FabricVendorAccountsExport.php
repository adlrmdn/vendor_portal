<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Full fabric vendor account roster, produced by vendors:register-fabric-accounts.
 * Covers every fabric vendor, not just newly registered ones; existing accounts
 * carry no recoverable password since only a bcrypt hash is stored.
 *
 * @param  array<int, array{code:string,name:string,group:?string,contact_email:?string,phone:?string,login_email:?string,password:?string,status:string,is_active:bool}>  $rows
 */
class FabricVendorAccountsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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
            $r['login_email'] ?? '(no account)',
            $r['password'] ?? '',
            $r['status'],
            $r['is_active'] ? 'Active' : 'Inactive',
        ], $this->rows);
    }

    public function headings(): array
    {
        return [
            'Vendor Code',
            'Vendor Name',
            'Pool',
            'Contact Email',
            'Phone',
            'Login Email',
            'Login Password',
            'Account Status',
            'Vendor Status',
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
        return 'Fabric Vendor Accounts';
    }
}
