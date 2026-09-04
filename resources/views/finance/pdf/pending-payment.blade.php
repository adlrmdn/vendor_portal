<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28pt; }
        body { font-family: Arial, sans-serif; color: #0F172A; font-size: 8.5pt; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.6px solid #CBD5E1; padding: 4px 6px; font-size: 8pt; }
        th { background-color: #F8FAFC; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.02em; }
        .right { text-align: right; }
        .header { border-bottom: 1.5px solid #0F172A; margin-bottom: 10pt; padding-bottom: 6pt; }
        .title { font-size: 14pt; font-weight: bold; }
        .muted { color: #64748B; font-size: 7.5pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Pending Payment &mdash; Subcon CMT POs Received Without an Invoice</div>
        <div class="muted">
            Generated {{ $generatedAt }}
            @if ($search) &mdash; filtered by "{{ $search }}" @endif
            &mdash; {{ $rows->count() }} row(s)
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>PO</th>
                <th>Vendor</th>
                <th>Item Batch</th>
                <th>PLM ID</th>
                <th class="right">Qty</th>
                <th class="right">Amount</th>
                <th>Received (D365)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->po ?: '-' }}</td>
                    <td>{{ $row->vendor_name ?: '-' }}</td>
                    <td>{{ $row->item_batch ?: '-' }}</td>
                    <td>{{ $row->plm_id ?: '-' }}</td>
                    <td class="right">{{ is_numeric($row->qty) ? number_format((float) $row->qty, 0) : '-' }}</td>
                    <td class="right">{{ is_numeric($row->amount) ? number_format((float) $row->amount, 2) : '-' }}</td>
                    <td>{{ $row->received_at ? \Illuminate\Support\Carbon::parse($row->received_at)->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align: center; color: #64748B;">No received CMT POs are missing an invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
