<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #1a1a1a; }
        h1 { font-size: 14pt; text-align: center; margin-bottom: 2pt; }
        h2 { font-size: 9pt; text-align: center; color: #666; margin-top: 0; margin-bottom: 8pt; }
        h3 { font-size: 10pt; border-bottom: 1px solid #999; padding-bottom: 2pt; margin-top: 10pt; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 4pt; }
        td, th { padding: 2pt 5pt; border: 1px solid #ccc; text-align: left; font-size: 8pt; line-height: 1.3; }
        th { background: #f2f2f2; }
        .num { text-align: right; }
        p { margin: 4pt 0; }
        .sig-badge { font-weight: bold; }
        .sig-badge.signed { color: #15803d; }
        .sig-badge.rejected { color: #b91c1c; }
        .sig-badge.pending { color: #999; font-style: italic; }
        .sig-badge img { width: 7px; height: 7px; margin-right: 2px; vertical-align: middle; margin-top: -1px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .header-logo { width: 30%; text-align: left; }
        .header-logo img { height: 42px; }
        .header-title { width: 70%; text-align: center; }
        .header-title h1 { margin: 0; }
        .header-title h2 { margin: 2pt 0 0; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if($logoData)
                    <img src="{{ $logoData }}">
                @endif
            </td>
            <td class="header-title">
                <h1>Official Report (Berita Acara)</h1>
                <h2>Additional Process - {{ $report->operation }}</h2>
            </td>
        </tr>
    </table>

    <h3>1. Overview</h3>
    <table>
        <tr><th>Vendor</th><td>{{ $report->matched_po_vendor_name ?? $report->matched_po_vendor_code ?? '—' }}</td>
            <th>PO Number</th><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
        <tr><th>Article Name</th><td>{{ $articleName ?? '—' }}</td>
            <th>Qty Order</th><td class="num">{{ number_format($report->order_qty) }}</td></tr>
        <tr><th>Available Qty</th><td class="num">{{ number_format($report->good_qty + $report->reject_qty) }}</td>
            <th>Delivery Plan</th><td>{{ $deliveryPlan ?? '—' }}</td></tr>
        <tr><th>Season</th><td>{{ $season ?? '—' }}</td>
            <th>PLM ID</th><td>{{ $plmId ?? '—' }}</td></tr>
        <tr><th>Production Group</th><td colspan="3">{{ $report->production_group }} — {{ $report->operation }}</td></tr>
    </table>

    <h3>2. Production Yield Matrix</h3>
    <table>
        <tr><th>Size</th><th>Order Qty</th><th>Cut Qty</th><th>Good Qty</th><th>Reject Qty</th><th>Balance</th></tr>
        @foreach($yield['rows'] as $row)
            <tr>
                <td>{{ $row['size'] }}</td>
                <td class="num">{{ number_format($row['order_qty']) }}</td>
                <td class="num">{{ number_format($row['cut_qty']) }}</td>
                <td class="num">{{ number_format($row['good_qty']) }}</td>
                <td class="num">{{ number_format($row['reject_qty']) }}</td>
                <td class="num">{{ number_format($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold; background: #f2f2f2;">
            <td>TOTAL</td>
            <td class="num">{{ number_format($yield['total']['order_qty']) }}</td>
            <td class="num">{{ number_format($yield['total']['cut_qty']) }}</td>
            <td class="num">{{ number_format($yield['total']['good_qty']) }}</td>
            <td class="num">{{ number_format($yield['total']['reject_qty']) }}</td>
            <td class="num">{{ number_format($yield['total']['balance']) }}</td>
        </tr>
    </table>

    <h3>3. Deduction &amp; Remarks</h3>
    <table>
        <tr><th>Cut Qty</th><td class="num">{{ number_format($report->cut_qty) }}</td>
            <th>Allowed Limit (1%)</th><td class="num">{{ number_format($report->allowed_limit) }}</td></tr>
        <tr><th>Reject Qty</th><td class="num">{{ number_format($report->reject_qty) }}</td>
            <th>Exceeding Qty</th><td class="num">{{ number_format($report->exceeding_reject_qty) }}</td></tr>
        <tr><th>Sales Price</th><td class="num">Rp {{ number_format($report->sales_price ?? 0, 0, ',', '.') }}</td>
            <th>Penalty Price (70%)</th><td class="num">Rp {{ number_format($report->penalty_price, 0, ',', '.') }}</td></tr>
        <tr><th>Reject Penalty</th><td class="num">Rp {{ number_format($report->reject_penalty, 0, ',', '.') }}</td>
            <th>Additional Deduction</th><td class="num">Rp {{ number_format($report->additional_deduction, 0, ',', '.') }}</td></tr>
        <tr><th>Total Deduction</th><td class="num" colspan="3"><strong>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</strong></td></tr>
    </table>
    <p><strong>Remarks:</strong> {{ $report->remarks ?: '—' }}</p>

    <h3>4. Conclusions</h3>
    {{-- Same nested-table-as-box technique as the base CMT inspection report's
         own signature boxes (QcReportPdfService's partial, Section 5): the
         box is itself a <table> with border/radius/background/height set
         directly on it (border-collapse: separate + box-sizing: border-box
         is what lets padding+border still fit inside that fixed height
         without it growing), not a <td> or <div> wrapping one — that's what
         keeps both boxes the exact same height regardless of how much each
         one has to say. The outer <td>'s padding-right (0 on the last one)
         is the gap between boxes, not a separate spacer column. --}}
    <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 6pt;">
        <tr style="height: 85pt;">
            @foreach([
                ['label' => 'Approved By', 'sig' => $signatures['ho'], 'role' => 'MPG HO - MD Production'],
                ['label' => 'Authorized By', 'sig' => $signatures['director'], 'role' => 'Director'],
            ] as $box)
                <td style="width: 50%; vertical-align: top; border: none; padding: {{ $loop->last ? '0' : '0 10pt 0 0' }}; height: 85pt;">
                    <table style="width: 100%; border: 0.6px solid #ccc; border-radius: 6px; background-color: #f8fafc; height: 85pt; border-collapse: separate; border-spacing: 0; margin: 0; padding: 8pt; box-sizing: border-box;">
                        <tr style="height: 60pt;">
                            <td style="vertical-align: top; border: none; padding: 0; text-align: center; height: 60pt;">
                                <div style="font-size: 7.5pt; font-weight: bold; color: #444; text-transform: uppercase; letter-spacing: 0.05em;">{{ $box['label'] }}</div>
                                {{-- Unicode check/cross glyphs (&#10003;/&#10007;) aren't in
                                     dompdf's Helvetica metrics and render as "?" — same tiny
                                     inline icon the base CMT report uses instead. --}}
                                @if($box['sig']['state'] === 'signed')
                                    <div class="sig-badge signed" style="margin-top: 4pt;"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==">Digitally Signed</div>
                                @elseif($box['sig']['state'] === 'rejected')
                                    <div class="sig-badge rejected" style="margin-top: 4pt;"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAY0lEQVR4nJWQ0Q0AIQhDxQluKJnaoWSDd18mnKDxSPigtKRFgPKn6i3RVAmCCe7IpkrNwN2Rp3epfjiJ517W0JktfyyE9stsDoJThiDIPK94+JInZ6JPaFNl9RxsAVc9WgMoL8rWaChiYc43AAAAAElFTkSuQmCC">Rejected</div>
                                @else
                                    <div class="sig-badge pending" style="margin-top: 4pt;">{{ $box['label'] === 'Authorized By' ? 'Awaiting Authorization' : 'Awaiting Approval' }}</div>
                                @endif
                                @if($box['sig']['email'])<div style="color: #666; font-size: 7.5pt; margin-top: 2pt;">{{ $box['sig']['email'] }}</div>@endif
                                @if($box['sig']['date'])<div style="color: #666; font-size: 7.5pt; margin-top: 2pt;">{{ $box['sig']['date'] }}</div>@endif
                            </td>
                        </tr>
                        <tr style="height: 25pt;">
                            <td style="vertical-align: bottom; border: none; border-top: 0.6px dashed #ccc; padding: 4pt 0 0 0; text-align: center; height: 25pt;">
                                @if($box['sig']['name'])
                                    <div style="font-weight: bold; font-size: 8.5pt; line-height: 1.1;">{{ $box['sig']['name'] }}</div>
                                @endif
                                <div style="color: #666; font-size: 7.5pt; text-transform: uppercase;">{{ $box['role'] }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
