<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #fff4e6; color: #e8590c; font-size: 12px;
               font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        table.meta td.k { color: #868e96; width: 150px; }
        .btn-ok { display: inline-block; text-decoration: none; font-weight: 700; font-size: 14px;
                  padding: 12px 22px; border-radius: 8px; color: #ffffff !important; background: #2b8a3e; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <span class="tag">Final Approval Needed</span>
            <h2>Final Approval</h2>
            <p style="margin:0;color:#495057;">The vendor has confirmed this packaging inspection. It now needs final sign-off. Open the form to enter <strong>Fabric Sent</strong> and <strong>Consumption Plan</strong>, then approve.</p>

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
                <a href="{{ $url }}" class="btn btn-ok" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#2b8a3e;">Review &amp; Approve</a>
                &nbsp;&nbsp;
                <a href="{{ $declineUrl }}" class="btn btn-no" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#c92a2a;">Reject</a>
            </p>
            <p class="muted" style="font-size:13px;">Approving opens a quick page to enter <strong>Fabric Sent</strong> &amp; <strong>Consumption Plan</strong>, review the calculated figures, then sign off — no login required. Or paste this link into your browser:<br>
                <a href="{{ $url }}">{{ $url }}</a>
            </p>

            <p class="muted">This link is unique to this inspection. If you did not expect this email, you can ignore it.</p>
        </div>
    </div>
</body>
</html>
