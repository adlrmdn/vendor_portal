<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #e7f5ff; color: #1c7ed6; font-size: 12px;
               font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        table.meta td.k { color: #868e96; width: 150px; }
        .btn { display: inline-block; text-decoration: none; font-weight: 700; font-size: 14px;
               padding: 12px 22px; border-radius: 8px; color: #fff !important; }
        .btn-ok { background: #2b8a3e; }
        .btn-no { background: #c92a2a; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
        table.data { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
        table.data th, table.data td { border: 1px solid #dee2e6; padding: 7px 10px; font-size: 13px; }
        table.data th { background: #f1f3f5; text-align: left; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; color: #495057; }
        table.data td.num { text-align: right; }
        table.data tfoot td { background: #f8f9fa; font-weight: 700; }
        .section-title { font-size: 13px; font-weight: 700; margin: 18px 0 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <span class="tag">Approval Needed</span>
            <h2>{{ $gateLabel }}</h2>
            <p style="margin:0;color:#495057;">A subcontractor has submitted the {{ strtolower($gateLabel) }} for your review.</p>

            <table class="meta">
                <tr><td class="k">Work Order</td><td><strong>{{ $order->order_number }}</strong></td></tr>
                <tr><td class="k">Style</td><td>{{ $order->title }}</td></tr>
                <tr><td class="k">Subcontractor</td><td>{{ $order->vendor?->name ?? '—' }} ({{ $order->vendor?->vendor_code ?? '—' }})</td></tr>
                @if($gate === 'gramasi' && $order->blister_capacity)
                    <tr><td class="k">Blister Capacity</td><td><strong>{{ number_format($order->blister_capacity) }}</strong> pcs / blister</td></tr>
                    <tr><td class="k">Sack (Karung) Capacity</td><td><strong>{{ number_format($order->sack_capacity ?? 50) }}</strong> pcs / sack</td></tr>
                @endif
            </table>

            @if(!empty($order->remarks))
                <div style="margin:14px 0; padding:12px 14px; background:#fff9db; border:1px solid #ffe066; border-radius:8px;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#7a5a00; margin-bottom:4px;">Vendor Remarks</div>
                    <div style="font-size:13px; color:#495057; white-space:pre-wrap;">{{ $order->remarks }}</div>
                </div>
            @endif

            @if(!empty($rows))
                <div class="section-title">
                    {{ $gate === 'gramasi' ? 'Submitted Gramasi (g) by size' : 'Submitted Cutting Quantities by size' }}
                </div>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Size</th>
                            @if($gate === 'gramasi')
                                <th style="text-align:right;">Gramasi (g)</th>
                            @else
                                <th style="text-align:right;">Order Qty</th>
                                <th style="text-align:right;">Qty Cut</th>
                                <th style="text-align:right;">Balance</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>{{ $row['size'] }}</td>
                                @if($gate === 'gramasi')
                                    <td class="num">{{ $row['gramasi'] !== null ? number_format($row['gramasi'], 2) : '—' }}</td>
                                @else
                                    <td class="num">{{ $row['order_qty'] !== null ? number_format($row['order_qty']) : '—' }}</td>
                                    <td class="num">{{ number_format($row['qty']) }}</td>
                                    <td class="num" style="color:{{ $row['balance'] === null ? '#868e96' : ($row['balance'] < 0 ? '#c92a2a' : '#2b8a3e') }};">
                                        {{ $row['balance'] === null ? '—' : ($row['balance'] > 0 ? '+'.number_format($row['balance']) : number_format($row['balance'])) }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if($gate !== 'gramasi')
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="num">{{ $totalOrder > 0 ? number_format($totalOrder) : '—' }}</td>
                                <td class="num">{{ number_format($totalQty) }}</td>
                                <td class="num">{{ $totalBalance === null ? '—' : ($totalBalance > 0 ? '+'.number_format($totalBalance) : number_format($totalBalance)) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif

            <p>
                <a href="{{ $approveUrl }}" class="btn btn-ok" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#2b8a3e;">{{ $gate === 'cutting' ? 'Review & Approve' : 'Approve' }}</a>
                &nbsp;&nbsp;
                <a href="{{ $declineUrl }}" class="btn btn-no" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#c92a2a;">Reject</a>
            </p>
            @if($gate === 'cutting')
                <p class="muted" style="font-size:13px;">Approving opens a quick page to enter the fabric consumption (Fabric Sent &amp; Cons. Plan) — no login required — then approves. Nothing else to install.</p>
            @endif

            <p style="font-size:13px;">Or review the full details in the portal:<br>
                <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>
            </p>

            <p class="muted">These approval links are signed and unique to this request. If you did not expect this email, you can ignore it.</p>
        </div>
    </div>
</body>
</html>
