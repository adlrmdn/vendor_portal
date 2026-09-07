<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 10px;
               border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        .tag-ok { background: #ebfbee; color: #2b8a3e; }
        .tag-no { background: #fff5f5; color: #c92a2a; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; }
        table.meta td.k { color: #868e96; width: 150px; }
        .btn { display: inline-block; text-decoration: none; font-weight: 700; font-size: 14px;
               padding: 12px 22px; border-radius: 8px; color: #fff !important; background: #1c7ed6; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @if($outcome === 'approved')
                <span class="tag tag-ok">Approved</span>
            @else
                <span class="tag tag-no">Returned for changes</span>
            @endif

            <h2>{{ $gateLabel }}</h2>

            @if($outcome === 'approved')
                @if($labelsUnlocked)
                    <p style="margin:0;color:#495057;">Your gramasi &amp; blister capacity have been approved. Please generate the packing labels to proceed with printing.</p>
                @elseif($gate === 'cutting' && $order->cutting_partial)
                    <p style="margin:0;color:#495057;">Your <strong>partial</strong> cutting report has been approved. The work order stays at the cutting-report stage — you can continue entering the remaining quantities and submit again (untick "partial" on the final submission).</p>
                @else
                    <p style="margin:0;color:#495057;">Your cutting report has been approved. You may now enter <strong>gramasi &amp; blister capacity</strong> for this work order.</p>
                @endif
            @else
                <p style="margin:0;color:#495057;">Your {{ strtolower($gateLabel) }} was returned. Please review and resubmit in the portal.</p>
                @if($order->reject_reason)
                    <p style="margin:12px 0 0;padding:12px;background:#fff5f5;border-radius:8px;color:#c92a2a;white-space:pre-wrap;"><strong>Reason:</strong> {{ $order->reject_reason }}</p>
                @endif
            @endif

            <table class="meta">
                <tr><td class="k">Work Order</td><td><strong>{{ $order->order_number }}</strong></td></tr>
                <tr><td class="k">Style</td><td>{{ $order->title }}</td></tr>
            </table>

            @if($labelsUnlocked)
                <p><a href="{{ $portalUrl }}" class="btn" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#1c7ed6;">Generate Packing Labels</a></p>
            @else
                <p><a href="{{ $portalUrl }}" class="btn" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#1c7ed6;">Open Work Order</a></p>
            @endif

            <p class="muted">Mega Perintis Subcontractor Portal</p>
        </div>
    </div>
</body>
</html>
