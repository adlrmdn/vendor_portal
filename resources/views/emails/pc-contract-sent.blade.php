<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
        .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
        .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .details-table th, .details-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .details-table th { background-color: #f8f9fa; width: 40%; }
        .button-group { text-align: center; margin-top: 30px; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .btn-view { background-color: #0d6efd; color: #ffffff !important; text-decoration: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Preliminary Contract — Review Requested</h2>
        </div>
        <div class="content">
            <p>A Preliminary Contract has been prepared for your company. Please log into the Vendor Portal to review it and either Accept or Query it.</p>

            <table class="details-table">
                <tr>
                    <th>Reference</th>
                    <td>{{ $contract->contract_number ?? 'Draft — number issued once confirmed' }}</td>
                </tr>
                <tr>
                    <th>Lines</th>
                    <td>{{ $contract->lines->count() }}</td>
                </tr>
                @if($contract->fabric_type)
                <tr>
                    <th>Fabric Type</th>
                    <td>{{ $contract->fabric_type }}</td>
                </tr>
                @endif
            </table>

            <div class="button-group">
                <a href="{{ $viewUrl }}" class="btn btn-view">View in Vendor Portal</a>
            </div>
        </div>
        <div class="footer">
            This is an automated notification from the Vendor Portal.
        </div>
    </div>
</body>
</html>
