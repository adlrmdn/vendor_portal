<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes for Rolls</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .page-break {
            page-break-before: always;
        }
        .qr-container {
            display: inline-block;
            width: 200px;
            height: 250px;
            border: 1px solid #ddd;
            margin: 10px;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 0 auto 10px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ccc;
        }
        .roll-info {
            font-size: 12px;
            line-height: 1.4;
        }
        .roll-number {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <h1>QR Codes for Rolls</h1>
    <p>Generated: {{ date('F d, Y H:i:s') }}</p>
    <p>PO: {{ $rolls->first()->item->purchaseOrder->po_number ?? 'N/A' }}</p>
    
    <div>
        @foreach($rolls as $roll)
            @if($loop->iteration % 8 == 1 && $loop->iteration > 1)
                <div class="page-break"></div>
            @endif
            
            <div class="qr-container">
                <div class="qr-code">
                    <!-- QR Code would be generated here -->
                    <div style="font-size: 24px; color: #666;">QR</div>
                </div>
                <div class="roll-info">
                    <div class="roll-number">{{ $roll->roll_number }}</div>
                    <div>PO: {{ $roll->item->purchaseOrder->po_number }}</div>
                    <div>Item: {{ $roll->item->item_number }}</div>
                    <div>Fabric: {{ $roll->item->fabric_type }}</div>
                    <div>Color: {{ $roll->item->color }}</div>
                    <div>Length: {{ $roll->length ?? 'N/A' }} {{ $roll->unit }}</div>
                    @if($roll->grade)
                        <div>Grade: {{ $roll->grade }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>