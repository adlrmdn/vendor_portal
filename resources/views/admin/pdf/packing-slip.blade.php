<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slip - {{ $packingSlip->slip_number }}</title>
    <style>
        @page {
            margin: 30px;
            margin-top: 35px; /* Increased by 15% */
            margin-bottom: 2.5px; /* Decreased by 50% */
        }
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }


        .header img {
            max-height: 120px;
            width: auto;
        }

        .header h1 { margin: 0; font-size: 24px; line-height: 1.2; }
        .header h2 { margin: 0; font-size: 24px; line-height: 1.2; text-transform: uppercase; }
        .company-info p, .slip-info p { margin: 2px 0; font-size: 13px; }

        .company-info {
            float: left;
            width: 50%;
        }

        .slip-info {
            float: right;
            width: 40%;
            text-align: right;
        }

        .clear {
            clear: both;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 13px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        
        /* Column Widths */
        th:nth-child(3), td:nth-child(3) /* Batch */
        {
            width: 80px;
            text-align: center;
        }

        th:nth-child(4), td:nth-child(4), /* Quantity */
        th:nth-child(5), td:nth-child(5)  /* Rolls */
        {
            width: 120px;
            text-align: center;
        }

        .page-one-container {
            position: relative;
            height: 1030px; /* Increased to push footer to the very edge/rim */
        }

        .footer {
            position: absolute;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 200px; 
            padding-top: 10px;
            font-size: 10px;
            background-color: white;
        }

        .signature-box {
            width: 250px;
            height: 140px; /* 16:9 ratio approx */
            border: 1px solid #333;
            padding: 10px;
            position: relative;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div class="page-one-container">
        <div class="header">
            <div class="company-info">
                <h1 style="font-size: 20px; font-weight: bold; margin: 0 0 5px 0;">{{ $packingSlip->vendor->name }}</h1>
                <p style="margin: 0; line-height: 1.3;">
                    {!! nl2br(e($packingSlip->vendor->contact_info['address'] ?? 'Address not specified')) !!}<br>
                    Phone: {{ $packingSlip->vendor->contact_info['phone'] ?? 'N/A' }}
                </p>
            </div>

            <div class="slip-info">
                <h2>PACKING SLIP (ADMIN COPY)</h2>
                <p><strong>Slip #:</strong> {{ $packingSlip->slip_number }}</p>
                <p><strong>PO #:</strong> {{ $packingSlip->purchaseOrder->po_number }}</p>
                <p><strong>Date:</strong> {{ $packingSlip->created_at->format('m/d/Y') }}</p>
            </div>
            <div class="clear"></div>
        </div>

        <div class="buyer-info" style="margin-bottom: 25px; font-size: 13px; min-height: 85px;">
            <div style="float: left; width: 60%;">
                <h3 style="margin: 0 0 5px 0; color: #003366; text-transform: uppercase;">PT. MEGA PUTRA GARMENT</h3>
                <p style="margin: 0; line-height: 1.3;">
                    JL.KARET PEDURENAN NO. 240 RT.002<br>
                    RW.006 KEL. KARET KUNINGAN,<br>
                    KEC.SETIABUDI, JAKARTA SELATAN 12940<br>
                    INDONESIA
                </p>
            </div>
            <div style="float: right; width: 35%; text-align: right;">
                @if($packingSlip->delivery_note)
                    <div style="margin-bottom: 5px;">
                        <img src="data:image/svg+xml;base64, {{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(65)->generate($packingSlip->delivery_note)) }}" style="max-height: 65px;">
                    </div>
                    <strong style="font-size: 11px;">Delivery: {{ $packingSlip->delivery_note }}</strong>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <h3>Items Included</h3>
        <table>
            <thead>
                <tr>
                    <th>Item #</th>
                    <th>Description</th>
                    <th>Batch</th>
                    <th>Quantity</th>
                    <th>Rolls</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $totalRolls = 0; 
                    $totalQty = 0;
                @endphp
                @foreach($selectedItems as $item)
                    @php 
                        $itemRollCount = $item->rolls->count();
                        $totalRolls += $itemRollCount; 
                        $totalQty += $item->quantity;
                    @endphp
                    <tr>
                        <td>{{ $item->item_number }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->batch ?? '-' }}</td>
                        <td>{{ number_format($item->quantity, 2) }} {{ ucfirst($item->unit) }}</td>
                        <td>{{ $itemRollCount }} rolls</td>
                    </tr>
                @endforeach
                <!-- Summary Row -->
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">Total:</td>
                    <td style="text-align: center;">{{ number_format($totalQty, 2) }} {{ $selectedItems->isNotEmpty() ? ucfirst($selectedItems->first()->unit) : '' }}</td>
                    <td style="text-align: center;">{{ $totalRolls }} rolls</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div style="width: 45%; float: left;">
                <p style="margin: 0 0 5px 0;"><strong>Shipping Instructions:</strong></p>
                <p style="margin: 0;">1. Attach one QR code to each corresponding roll<br>
                    2. Include this packing slip with shipment<br>
                    3. Keep one copy for your records</p>
            </div>

            <div style="width: 45%; float: right;">
                <div class="signature-box">
                    <p style="margin: 0 0 15px 0;"><strong>Buyer:</strong></p>
                    <p style="margin: 0;"><strong>Received By:</strong></p>
                    <div style="position: absolute; bottom: 20px; left: 10px; right: 10px; border-bottom: 1px dashed #000; display: flex; align-items: flex-end;">
                        <span style="background: white; padding-right: 5px; font-size: 14px;">Name:</span>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
            
            <div style="text-align: center; margin-top: 10px; font-size: 9px; color: #999;">
                <p style="margin: 2px;">Generated by Vendor Portal System on {{ date('F d, Y H:i:s') }}</p>
                <p style="margin: 0;">Slip printed {{ $packingSlip->printed_count + 1 }} times</p>
            </div>
        </div>
    </div>

    @foreach($selectedItems as $item)
        @php
            $sortedRolls = $item->rolls->sortBy('sequence');
            $totalQuantity = 0;
            foreach ($sortedRolls as $roll) {
                if ($roll->unit == 'YD')
                    $totalQuantity += $roll->length_yd;
                elseif ($roll->unit == 'M')
                    $totalQuantity += $roll->length_m;
                else
                    $totalQuantity += $roll->weight;
            }
        @endphp
        @if($sortedRolls->count() > 0)
            <div style="page-break-before: always;">
                <h3 style="margin-bottom: 5px;">Rolls Details: (Item: {{ $item->item_number }})</h3>
                <p style="margin: 0 0 10px 0; font-size: 13px;">
                    <strong>Total Rolls:</strong> {{ $sortedRolls->count() }}<br>
                    <strong>Total Qty:</strong> {{ number_format($totalQuantity, 2) }} {{ $sortedRolls->first()->unit ?? '' }}
                </p>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4;">Roll Number</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: center;">Sequence Order</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: right;">Quantity</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: center;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sortedRolls as $roll)
                            @php
                                $displayQty = 0;
                                if ($roll->unit == 'YD')
                                    $displayQty = $roll->length_yd;
                                elseif ($roll->unit == 'M')
                                    $displayQty = $roll->length_m;
                                else
                                    $displayQty = $roll->weight;
                            @endphp
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 6px;">{{ $roll->roll_number }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">{{ $roll->sequence }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: right;">{{ number_format($displayQty, 2) }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">{{ $roll->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    <style>
        .qr-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-before: always; /* Force start on new page */
            margin-top: -35px; /* Counteract page margin to remove upper gap */
        }
        .qr-cell {
            width: 25%;
            height: 190px; /* Reduced height by 5% */
            border: 1px dashed #333;
            vertical-align: middle;
            text-align: center;
            padding: 2px; /* Minimized padding */
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .qr-card-content {
             height: 100%;
             display: flex;
             flex-direction: column;
             justify-content: center;
             align-items: center;
        }
        .qr-header-cell {
            border: none !important;
            padding: 10px 0 5px 0 !important;
            text-align: left !important;
        }
    </style>

    <table class="qr-table">
        @foreach($selectedItems as $item)
            <tr>
                {{-- Ensure page break happens before the table, but continuous rows inside --}}
                <td colspan="4" class="qr-header-cell">
                    <div style="margin: 0; border-bottom: 1px solid #ccc; font-weight: bold; font-size: 10px; padding-bottom: 2px;">Item {{ $loop->iteration }}: {{ $item->item_number }} - {{ $item->description }} (Total: {{ $item->rolls->count() }} Rolls)</div>
                </td>
            </tr>
            @foreach($item->rolls->chunk(4) as $chunk)
                <tr>
                    @foreach($chunk as $roll)
                        <td class="qr-cell">
                            <div style="margin-bottom: 2px; height: 130px; display: flex; align-items: center; justify-content: center;">
                                <img src="data:image/svg+xml;base64, {{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->generate($roll->roll_number)) }}" style="max-width: 100%; max-height: 100%;">
                            </div>
                            <div style="font-size: 9px; line-height: 1.1; overflow: hidden;">
                                <strong style="font-size: 8px;">{{ $roll->roll_number }}</strong><br>
                                {{ $item->batch ?? 'N/A' }}<br>
                                {{ $roll->length_yd > 0 ? number_format($roll->length_yd, 2) . ' YD' : ($roll->length_m > 0 ? number_format($roll->length_m, 2) . ' M' : number_format($roll->weight, 2) . ' KG') }}
                            </div>
                        </td>
                    @endforeach
                    @for($i = $chunk->count(); $i < 4; $i++)
                        <td class="qr-cell" style="border: none;"></td>
                    @endfor
                </tr>
            @endforeach
        @endforeach
    </table>
</body>
</html>
