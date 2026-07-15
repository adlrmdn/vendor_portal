<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #e7f0ff; color: #1c4ed8; font-size: 12px;
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
            <span class="tag">Director Authorization Needed</span>
            <h2>Director Authorization</h2>
            <p style="margin:0;color:#495057;">This packaging inspection has been confirmed by the factory representative and approved by MD Production. It now needs your <strong>final authorization</strong>. The current report is attached; authorizing completes the project and queues the Invoice/Deduction RPA jobs.</p>

            <table class="meta">
                @if($orderNumber)
                    <tr><td class="k">Work Order</td><td><strong>{{ $orderNumber }}</strong></td></tr>
                @endif
                @if($productionGroup)
                    <tr><td class="k">Production Group</td><td>{{ $productionGroup }}</td></tr>
                @endif
                <tr><td class="k">Project</td><td>{{ $projectId ?? '—' }}</td></tr>
                <tr><td class="k">Session</td><td>{{ $sessionId ?? '—' }}</td></tr>
            </table>

            @if(!empty($remarks ?? null))
                <div style="margin:14px 0; padding:12px 14px; background:#fff9db; border:1px solid #ffe066; border-radius:8px;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#7a5a00; margin-bottom:4px;">Vendor Remarks</div>
                    <div style="font-size:13px; color:#495057; white-space:pre-wrap;">{{ $remarks }}</div>
                </div>
            @endif

            <p>
                <a href="{{ $url }}" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#1a1a2e;">Review &amp; Authorize</a>
                &nbsp;&nbsp;
                <a href="{{ $declineUrl }}" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#c92a2a;">Reject</a>
            </p>
            <p class="muted" style="font-size:13px;">Authorizing opens a read-only summary page for sign-off — no login required. Rejecting returns the inspection to MD Production (not to the factory). Or paste this link into your browser:<br>
                <a href="{{ $url }}">{{ $url }}</a>
            </p>

            <p class="muted">This link is unique to this inspection. If you did not expect this email, you can ignore it.</p>
        </div>
    </div>
</body>
</html>
