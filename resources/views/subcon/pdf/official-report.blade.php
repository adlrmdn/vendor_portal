<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; }
        h1 { font-size: 15pt; text-align: center; margin-bottom: 2pt; }
        h2 { font-size: 10pt; text-align: center; color: #666; margin-top: 0; margin-bottom: 16pt; }
        h3 { font-size: 11pt; border-bottom: 1px solid #999; padding-bottom: 3pt; margin-top: 18pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        td, th { padding: 4pt 6pt; border: 1px solid #ccc; text-align: left; font-size: 9.5pt; }
        th { background: #f2f2f2; }
        .num { text-align: right; }
        .sig-block { width: 48%; display: inline-block; vertical-align: top; margin-top: 20pt; }
        .sig-title { font-weight: bold; font-size: 9pt; letter-spacing: 0.05em; color: #444; }
        .sig-name { margin-top: 30pt; font-weight: bold; }
        .sig-role { color: #666; font-size: 9pt; }
        .sig-pending { color: #999; font-style: italic; margin-top: 30pt; }
    </style>
</head>
<body>
    <h1>Official Report (Berita Acara)</h1>
    <h2>QUALITY CONTROL SYSTEM</h2>

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
        <tr><th>Order Qty</th><th>Cut Qty</th><th>Good Qty</th><th>Reject Qty</th></tr>
        <tr>
            <td class="num">{{ number_format($report->order_qty) }}</td>
            <td class="num">{{ number_format($report->cut_qty) }}</td>
            <td class="num">{{ number_format($report->good_qty) }}</td>
            <td class="num">{{ number_format($report->reject_qty) }}</td>
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
    <div class="sig-block">
        <div class="sig-title">APPROVED BY</div>
        @if($report->ho_approval_signature)
            <div class="sig-name">{{ $report->ho_approval_signature }}</div>
            <div class="sig-role">MPG HO - MD Production</div>
            <div class="sig-role">{{ optional($report->ho_approved_at)->format('d-m-Y H:i:s') }}</div>
        @else
            <div class="sig-pending">Awaiting Approval</div>
        @endif
    </div>
    <div class="sig-block">
        <div class="sig-title">AUTHORIZED BY</div>
        @if($report->director_approval_signature)
            <div class="sig-name">{{ $report->director_approval_signature }}</div>
            <div class="sig-role">DIRECTOR</div>
            <div class="sig-role">{{ optional($report->director_approved_at)->format('d-m-Y H:i:s') }}</div>
        @else
            <div class="sig-pending">Awaiting Authorization</div>
        @endif
    </div>
</body>
</html>
