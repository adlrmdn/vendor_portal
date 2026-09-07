<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #e7f5ff; color: #1971c2; font-size: 12px;
               font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        table.meta td.k { color: #868e96; width: 180px; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <span class="tag">Fabric Vendor Accounts</span>
            <h2>Vendor account roster</h2>
            <p style="margin:0;color:#495057;">
                Attached is the full fabric vendor portal account list.
            </p>

            <table class="meta">
                <tr><td class="k">Total fabric vendors</td><td><strong>{{ $totalVendors }}</strong></td></tr>
                <tr><td class="k">Newly registered accounts</td><td><strong>{{ $newAccounts }}</strong></td></tr>
                @if($resetAccounts > 0)
                    <tr><td class="k">Passwords reset</td><td><strong>{{ $resetAccounts }}</strong></td></tr>
                @endif
            </table>

            @if($resetAccounts > 0)
                <p style="margin:0;color:#495057;">
                    Every fabric vendor account's password has been reset to the value shown in the sheet.
                </p>
            @else
                <p style="margin:0;color:#495057;">
                    Newly registered accounts include a generated login password in the sheet. Existing accounts
                    keep their current password unchanged — since only a hashed password is stored, it cannot be
                    shown here.
                </p>
            @endif

            <p class="muted">This is an automated notification from the vendor portal.</p>
        </div>
    </div>
</body>
</html>
