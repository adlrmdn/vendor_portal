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

        th:nth-child(4), td:nth-child(4), /* Order Qty */
        th:nth-child(5), td:nth-child(5), /* Actual Qty */
        th:nth-child(6), td:nth-child(6)  /* Rolls */
        {
            width: 110px;
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
    @php
        // Item descriptions carry a leading brand word (e.g. "Minimal Fiona", "Manzone Classic")
        // that isn't meaningful on the printed slip.
        $stripBrand = fn ($desc) => (($s = trim(preg_replace('/^\s*\S+\s*/u', '', (string) $desc))) !== '' ? $s : trim((string) $desc));
    @endphp
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
                <h2>PACKING SLIP</h2>
                <p><strong>Slip #:</strong> {{ $packingSlip->slip_number }}</p>
                <p><strong>PO #:</strong> {{ $packingSlip->purchaseOrder->po_number }}</p>
                <p><strong>Date:</strong> {{ $packingSlip->created_at->format('m/d/Y') }}</p>
            </div>
            <div class="clear"></div>
        </div>

        <div class="buyer-info" style="margin-bottom: 25px; font-size: 13px; min-height: 85px;">
            <div style="float: left; width: 60%;">
                <h3 style="margin: 0 0 5px 0; color: #003366; text-transform: uppercase;">PT Mega Putra Garment</h3>
                <p style="margin: 0; line-height: 1.3;">
                    Jl. Nasional 1 No. 245, Slatri, Wanarejan Utara<br>
                    Kecamatan Taman, Kabupaten Pemalang, Jawa Tengah 52361<br>
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
                    <th>Order Qty</th>
                    <th>Actual Qty</th>
                    <th>Rolls</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalRolls = 0;
                    $totalQty = 0;
                    $totalActualQty = 0;
                @endphp
                @foreach($selectedItems as $item)
                    @php
                        $itemRollCount = $item->rolls->count();
                        $itemActualQty = $item->totalDeliveredQuantity();
                        $totalRolls += $itemRollCount;
                        $totalQty += $item->quantity;
                        $totalActualQty += $itemActualQty;
                    @endphp
                    <tr>
                        <td>{{ $item->item_number }}</td>
                        <td>{{ $stripBrand($item->description) }}</td>
                        <td>{{ $item->batch ? $stripBrand($item->batch) : '-' }}</td>
                        <td>{{ number_format($item->quantity, 2) }} {{ ucfirst($item->unit) }}</td>
                        <td>{{ number_format($itemActualQty, 2) }} {{ ucfirst($item->unit) }}</td>
                        <td>{{ $itemRollCount }} rolls</td>
                    </tr>
                @endforeach
                <!-- Summary Row -->
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">Total:</td>
                    <td style="text-align: center;">{{ number_format($totalQty, 2) }} {{ $selectedItems->isNotEmpty() ? ucfirst($selectedItems->first()->unit) : '' }}</td>
                    <td style="text-align: center;">{{ number_format($totalActualQty, 2) }} {{ $selectedItems->isNotEmpty() ? ucfirst($selectedItems->first()->unit) : '' }}</td>
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
            
            $totalPrimary = 0;
            $totalSecondary = 0;
            $hasSecondary = false;
            $primaryUnitName = $sortedRolls->first()->unit ?? '';
            $secondaryUnitName = '';

            foreach ($sortedRolls as $roll) {
                $pQty = 0;
                $sQty = 0;
                $sUnit = null;

                if ($roll->unit == 'YD') {
                    $pQty = $roll->length_yd ?? 0;
                    $sUnit = 'M';
                    $sQty = $roll->length_m ?? 0;
                    if ($sQty == 0 && $pQty > 0) {
                        $sQty = $pQty * 0.9144;
                    }
                } elseif ($roll->unit == 'M') {
                    $pQty = $roll->length_m ?? 0;
                    $sUnit = 'YD';
                    $sQty = $roll->length_yd ?? 0;
                    if ($sQty == 0 && $pQty > 0) {
                        $sQty = $pQty / 0.9144;
                    }
                } else {
                    $pQty = $roll->weight ?? 0;
                    if (($roll->length_yd ?? 0) > 0 || ($roll->length_m ?? 0) > 0) {
                        if (isset($reverseUnits) && $reverseUnits) {
                            $sUnit = 'M';
                            $sQty = $roll->length_m ?? 0;
                            if ($sQty == 0 && ($roll->length_yd ?? 0) > 0) {
                                $sQty = $roll->length_yd * 0.9144;
                            }
                        } else {
                            $sUnit = 'YD';
                            $sQty = $roll->length_yd ?? 0;
                            if ($sQty == 0 && ($roll->length_m ?? 0) > 0) {
                                $sQty = $roll->length_m / 0.9144;
                            }
                        }
                    }
                }

                if (isset($reverseUnits) && $reverseUnits && ($roll->unit == 'YD' || $roll->unit == 'M')) {
                    $tempQty = $pQty;
                    $pQty = $sQty;
                    $sQty = $tempQty;
                    $primaryUnitName = ($roll->unit == 'YD') ? 'M' : 'YD';
                    $secondaryUnitName = $roll->unit;
                } else {
                    $secondaryUnitName = $sUnit;
                }

                $totalPrimary += $pQty;
                if ($sUnit !== null) {
                    $totalSecondary += $sQty;
                    $hasSecondary = true;
                }
            }

            $totalQtyText = number_format($totalPrimary, 2) . ' ' . $primaryUnitName;
            if (isset($showSecondary) && $showSecondary && $hasSecondary) {
                $totalQtyText .= ' (' . number_format($totalSecondary, 2) . ' ' . $secondaryUnitName . ')';
            }
        @endphp
        @if($sortedRolls->count() > 0)
            <div style="page-break-before: always;">
                <h3 style="margin-bottom: 5px;">Rolls Details: (Item: {{ $item->item_number }})</h3>
                <p style="margin: 0 0 10px 0; font-size: 13px;">
                    <strong>Total Rolls:</strong> {{ $sortedRolls->count() }}<br>
                    <strong>Total Qty:</strong> {{ $totalQtyText }}
                </p>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4;">Roll Number</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: center;">Lot-ID</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: right;">Quantity</th>
                            <th style="border: 1px solid #ddd; padding: 6px; background-color: #f4f4f4; text-align: center;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sortedRolls as $roll)
                            @php
                                $pQty = 0;
                                $sQty = 0;
                                $sUnit = null;

                                if ($roll->unit == 'YD') {
                                    $pQty = $roll->length_yd ?? 0;
                                    $sUnit = 'M';
                                    $sQty = $roll->length_m ?? 0;
                                    if ($sQty == 0 && $pQty > 0) {
                                        $sQty = $pQty * 0.9144;
                                    }
                                } elseif ($roll->unit == 'M') {
                                    $pQty = $roll->length_m ?? 0;
                                    $sUnit = 'YD';
                                    $sQty = $roll->length_yd ?? 0;
                                    if ($sQty == 0 && $pQty > 0) {
                                        $sQty = $pQty / 0.9144;
                                    }
                                } else {
                                    $pQty = $roll->weight ?? 0;
                                    if (($roll->length_yd ?? 0) > 0 || ($roll->length_m ?? 0) > 0) {
                                        if (isset($reverseUnits) && $reverseUnits) {
                                            $sUnit = 'M';
                                            $sQty = $roll->length_m ?? 0;
                                            if ($sQty == 0 && ($roll->length_yd ?? 0) > 0) {
                                                $sQty = $roll->length_yd * 0.9144;
                                            }
                                        } else {
                                            $sUnit = 'YD';
                                            $sQty = $roll->length_yd ?? 0;
                                            if ($sQty == 0 && ($roll->length_m ?? 0) > 0) {
                                                $sQty = $roll->length_m / 0.9144;
                                            }
                                        }
                                    }
                                }

                                $pUnit = $roll->unit;
                                if (isset($reverseUnits) && $reverseUnits && ($roll->unit == 'YD' || $roll->unit == 'M')) {
                                    $tempQty = $pQty;
                                    $pQty = $sQty;
                                    $sQty = $tempQty;
                                    
                                    $pUnit = ($roll->unit == 'YD') ? 'M' : 'YD';
                                    $sUnit = $roll->unit;
                                }

                                $qtyVal = number_format($pQty, 2);
                                $unitVal = $pUnit;
                                if (isset($showSecondary) && $showSecondary && $sUnit !== null) {
                                    $qtyVal .= ' (' . number_format($sQty, 2) . ')';
                                    $unitVal .= ' (' . $sUnit . ')';
                                }
                            @endphp
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 6px;">{{ $roll->roll_number }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">{{ $roll->internal_id ?? '-' }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: right;">{{ $qtyVal }}</td>
                                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">{{ $unitVal }}</td>
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
                    <div style="margin: 0; border-bottom: 1px solid #ccc; font-weight: bold; font-size: 10px; padding-bottom: 2px;">Item {{ $loop->iteration }}: {{ $item->item_number }} - {{ $stripBrand($item->description) }} (Total: {{ $item->rolls->count() }} Rolls)</div>
                </td>
            </tr>
            @foreach($item->rolls->chunk(4) as $chunk)
                <tr>
                    @foreach($chunk as $roll)
                        @php
                            $pQty = 0;
                            $sQty = 0;
                            $sUnit = null;

                            if ($roll->unit == 'YD') {
                                $pQty = $roll->length_yd ?? 0;
                                $sUnit = 'M';
                                $sQty = $roll->length_m ?? 0;
                                if ($sQty == 0 && $pQty > 0) {
                                    $sQty = $pQty * 0.9144;
                                }
                            } elseif ($roll->unit == 'M') {
                                $pQty = $roll->length_m ?? 0;
                                $sUnit = 'YD';
                                $sQty = $roll->length_yd ?? 0;
                                if ($sQty == 0 && $pQty > 0) {
                                    $sQty = $pQty / 0.9144;
                                }
                            } else {
                                $pQty = $roll->weight ?? 0;
                                if (($roll->length_yd ?? 0) > 0 || ($roll->length_m ?? 0) > 0) {
                                    if (isset($reverseUnits) && $reverseUnits) {
                                        $sUnit = 'M';
                                        $sQty = $roll->length_m ?? 0;
                                        if ($sQty == 0 && ($roll->length_yd ?? 0) > 0) {
                                            $sQty = $roll->length_yd * 0.9144;
                                        }
                                    } else {
                                        $sUnit = 'YD';
                                        $sQty = $roll->length_yd ?? 0;
                                        if ($sQty == 0 && ($roll->length_m ?? 0) > 0) {
                                            $sQty = $roll->length_m / 0.9144;
                                        }
                                    }
                                }
                            }

                            $pUnit = $roll->unit;
                            if (isset($reverseUnits) && $reverseUnits && ($roll->unit == 'YD' || $roll->unit == 'M')) {
                                $tempQty = $pQty;
                                $pQty = $sQty;
                                $sQty = $tempQty;
                                
                                $pUnit = ($roll->unit == 'YD') ? 'M' : 'YD';
                                $sUnit = $roll->unit;
                            }

                            // The code must scan to the roll's identity, not a
                            // description of it — lot-id/qty stay as printed
                            // text only, so they're never mistaken for the id.
                            $rollCode = \App\Models\Roll::qrSafeName($roll->roll_number);
                            $qtyCaption = number_format($pQty, 2) . ' ' . $pUnit;
                            if (isset($showSecondary) && $showSecondary && $sUnit !== null) {
                                $qtyCaption .= ' (' . number_format($sQty, 2) . ' ' . $sUnit . ')';
                            }
                        @endphp
                        <td class="qr-cell">
                            <div style="margin-bottom: 2px; height: 130px; display: flex; align-items: center; justify-content: center;">
                                <img src="data:image/svg+xml;base64, {{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->generate($rollCode)) }}" style="max-width: 100%; max-height: 100%;">
                            </div>
                            <div style="font-size: 9px; line-height: 1.1; overflow: hidden;">
                                <strong style="font-size: 8px;">{{ $rollCode }}</strong><br>
                                {{ $item->batch ? $stripBrand($item->batch) : 'N/A' }}<br>
                                Lot-ID {{ $roll->internal_id ?? 'N/A' }} | Bale No. {{ $roll->bale_no ?? 'N/A' }}<br>
                                {{ $qtyCaption }}
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
