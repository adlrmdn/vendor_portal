<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #ebfbee; color: #2b8a3e; font-size: 12px;
               font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        table.meta td.k { color: #868e96; width: 150px; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <span class="tag">Stage Approved</span>
            <h2>Approved by {{ $stage }}</h2>
            <p style="margin:0;color:#495057;">
                Your packaging inspection has been <strong>approved at the {{ $stage }} stage</strong>.
                {{ $nextStep }}
            </p>

            <table class="meta">
                @if($orderNumber)
                    <tr><td class="k">Work Order</td><td><strong>{{ $orderNumber }}</strong></td></tr>
                @endif
                @if($productionGroup)
                    <tr><td class="k">Production Group</td><td>{{ $productionGroup }}</td></tr>
                @endif
                <tr><td class="k">Project</td><td>{{ $projectId ?? '—' }}</td></tr>
                <tr><td class="k">Session</td><td>{{ $sessionId ?? '—' }}</td></tr>
                <tr><td class="k">Approved by</td><td>{{ $actor }}</td></tr>
            </table>

            <p class="muted">This is an automated notification from the vendor portal. The current inspection report is attached.</p>
        </div>
    </div>
</body>
</html>
