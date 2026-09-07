<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR - {{ $roll->roll_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f2f2f2;
        }

        .qr-card {
            border: 1px dashed #333;
            background: #fff;
            padding: 24px 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-card img {
            width: 260px;
            height: 260px;
        }

        .qr-card .roll-code {
            font-size: 15px;
            font-weight: bold;
            margin-top: 12px;
        }

        .qr-card .meta {
            font-size: 12px;
            line-height: 1.5;
            margin-top: 4px;
            color: #555;
        }
    </style>
</head>

<body>
    @php
        $stripBrand = fn ($desc) => (($s = trim(preg_replace('/^\s*\S+\s*/u', '', (string) $desc))) !== '' ? $s : trim((string) $desc));
    @endphp
    <div class="qr-card">
        <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR Code">
        <div class="roll-code">{{ $roll->roll_number }}</div>
        <div class="meta">
            {{ $roll->item->batch ? $stripBrand($roll->item->batch) : 'N/A' }}<br>
            Lot-ID {{ $roll->internal_id ?? 'N/A' }} | Bale No. {{ $roll->bale_no ?? 'N/A' }}<br>
            {{ $qtyCaption }}
        </div>
    </div>
</body>

</html>
