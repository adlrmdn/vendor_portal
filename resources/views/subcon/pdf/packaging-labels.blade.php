<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Packaging Labels - {{ $order->order_number }}</title>
    <style>
        @page {
            size: 288pt 432pt;
            margin: 0;
        }
        * {
            box-sizing: content-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
        }
        /* Explicitly size the label to fit exactly on 288pt x 432pt media with no margins and no borders (fullscreen).
           Outer width: 256pt + 32pt padding = 288pt
           Outer height: 400pt + 32pt padding = 432pt */
        .label {
            width: 256pt;
            height: 400pt;
            padding: 16pt;
            border: none;
            margin: 0;
        }
        .barcode-box {
            text-align: center;
            margin-top: 0pt;
            margin-bottom: 8pt;
        }
        .packing-code {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: bold;
            margin-top: 3pt;
        }
        .meta-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }
        .meta-info td {
            border: none;
            padding: 1pt 0;
            font-size: 11px;
            vertical-align: middle;
        }
        .meta-label {
            width: 45pt;
        }
        .meta-sep {
            width: 15pt;
        }
        .meta-val {
            font-weight: normal;
        }
        .store-box {
            margin-bottom: 10pt;
            line-height: 1.35;
        }
        .store-name {
            font-size: 12px;
            font-weight: bold;
        }
        .store-address {
            font-size: 11px;
        }
        .style-name {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 8pt;
        }
        .page-info-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            margin-bottom: 4pt;
        }
        .page-info-table td {
            border: none;
            padding: 3pt 0;
            font-size: 12px;
        }
        .page-info-left {
            text-align: left;
            font-weight: bold;
        }
        .page-info-center {
            text-align: center;
            font-weight: bold;
        }
        .page-info-right {
            text-align: right;
            font-weight: normal;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            border: none;
            padding: 4pt 0;
            font-size: 11px;
            font-weight: normal;
        }
        .items-table td {
            border: none;
            padding: 4pt 0;
            font-size: 11px;
        }
        .items-table tfoot td {
            border: none;
            border-top: 1px solid #000;
            padding: 5pt 0;
            font-size: 11px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    @php $groupCount = count($grouped); @endphp
    @foreach($grouped as $gi => $group)
    @php $pageCount = max(1, (int) ($group['page_count'] ?? 1)); @endphp
    @for($pageNo = 1; $pageNo <= $pageCount; $pageNo++)
        @php $isLastLabel = ($gi === $groupCount - 1) && ($pageNo === $pageCount); @endphp
        <div class="label" style="{{ !$isLastLabel ? 'page-break-after: always;' : '' }}">
            <div class="barcode-box">
                {!! \App\Support\Code39::html($group['packing_code'], 65, 265) !!}
                <div class="packing-code">{{ $group['packing_code'] }}</div>
            </div>
            
            <table class="meta-info">
                <tr>
                    <td class="meta-label">Date</td>
                    <td class="meta-sep">:</td>
                    <td class="meta-val">{{ $printDate }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Source</td>
                    <td class="meta-sep">:</td>
                    <td class="meta-val">PT MEGA PUTRA GARMENT - {{ $order->vendor?->vendor_code ?? '7788' }}</td>
                </tr>
            </table>
            
            <div class="store-box">
                <div class="store-name">{{ $group['store_id'] }} {{ $group['store_name'] }}</div>
                <div class="store-address">
                    {{ $group['address']['line1'] }}<br>
                    {{ $group['address']['line2'] }}<br>
                    {{ $group['address']['country'] }}
                </div>
            </div>
            
            <div class="style-name">{{ $styleName }}</div>
            
            <table class="page-info-table">
                <tr>
                    <td class="page-info-left" style="width: 35%;">Page {{ $pageNo }} of {{ $pageCount }}</td>
                    <td class="page-info-center" style="width: 30%;">{{ $group['status'] }}</td>
                    <td class="page-info-right" style="width: 35%;">{{ $group['store_province'] }}</td>
                </tr>
            </table>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 35%; text-align: left;">Item</th>
                        <th class="text-center" style="width: 30%;">Size</th>
                        <th class="text-right" style="width: 35%;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['items'] as $item)
                        <tr>
                            <td style="width: 35%;">{{ $item['item_id'] }}</td>
                            <td class="text-center" style="width: 30%;">{{ $item['size'] }}</td>
                            <td class="text-right" style="width: 35%;">{{ $item['qty'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td style="width: 35%; text-align: left; font-weight: bold;">Berat(kg)</td>
                        <td class="text-center" style="width: 30%; font-weight: bold;">{{ number_format($group['total_weight'], 2) }}</td>
                        <td class="text-right" style="width: 35%; font-weight: normal;">{{ $group['total_qty'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endfor
    @endforeach
</body>
</html>
