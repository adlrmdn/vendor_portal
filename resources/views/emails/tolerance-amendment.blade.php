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
        .reason-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; font-style: italic; }
        .button-group { text-align: center; margin-top: 30px; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 0 10px; }
        .btn-approve { background-color: #198754; color: #ffffff !important; text-decoration: none !important; }
        .btn-decline { background-color: #dc3545; color: #ffffff !important; text-decoration: none !important; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-info { background-color: #e3f2fd; color: #0d47a1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $request->type === 'partial_shipment' ? 'Partial Shipment' : 'Tolerance Amendment' }} Request</h2>
        </div>
        <div class="content">
            <p>
                @if($request->type === 'partial_shipment')
                    A vendor has requested permission to split a Purchase Order item into a partial shipment.
                @else
                    A vendor has requested to amend the delivery tolerance for a Purchase Order item.
                @endif
            </p>
            
            <table class="details-table">
                <tr>
                    <th>PO Number</th>
                    <td>{{ $request->poItem->purchaseOrder->po_number }}</td>
                </tr>
                <tr>
                    <th>Item Number</th>
                    <td>{{ $request->poItem->item_number }}</td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td>{{ $request->poItem->description }}</td>
                </tr>
                <tr>
                    <th>Order Quantity</th>
                    <td>{{ number_format($request->poItem->quantity, 2) }} {{ strtoupper($request->poItem->unit) }}</td>
                </tr>
                
                @if($request->type === 'partial_shipment')
                <tr>
                    <th>Intended Qty</th>
                    <td style="color: #0d6efd; font-weight: bold;">{{ number_format($request->requested_qty, 2) }} {{ strtoupper($request->poItem->unit) }}</td>
                </tr>
                <tr>
                    <th>Remaining Balance</th>
                    <td style="color: #6c757d;">{{ number_format($request->poItem->quantity - $request->requested_qty, 2) }} {{ strtoupper($request->poItem->unit) }}</td>
                </tr>
                @else
                <tr>
                    <td colspan="2" style="padding: 0;">
                        <table style="width: 100%; border-collapse: collapse; margin-top: 15px; border: 1px solid #eee;">
                            <thead>
                                <tr style="background-color: #f8f9fa;">
                                    <th style="padding: 10px; border: 1px solid #eee; font-size: 11px; text-align: center;">METRIC</th>
                                    <th style="padding: 10px; border: 1px solid #eee; font-size: 11px; text-align: center;">PREVIOUS</th>
                                    <th style="padding: 10px; border: 1px solid #eee; font-size: 11px; text-align: center; color: #0d6efd;">REQUESTED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #eee;"><strong>Underdelivery</strong></td>
                                    <td style="padding: 10px; border: 1px solid #eee; text-align: center;">{{ number_format($request->old_underdelivery, 2) }}%</td>
                                    <td style="padding: 10px; border: 1px solid #eee; text-align: center; color: #0d6efd; font-weight: bold;">{{ number_format($request->new_underdelivery, 2) }}%</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #eee;"><strong>Overdelivery</strong></td>
                                    <td style="padding: 10px; border: 1px solid #eee; text-align: center;">{{ number_format($request->old_overdelivery, 2) }}%</td>
                                    <td style="padding: 10px; border: 1px solid #eee; text-align: center; color: #0d6efd; font-weight: bold;">{{ number_format($request->new_overdelivery, 2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                @endif
            </table>

            <p><strong>Reason for request:</strong></p>
            <div class="reason-box">
                "{{ $request->reason }}"
            </div>

            <p>Please review and choose an action below:</p>
            
            <div class="button-group">
                <a href="{{ $approveUrl }}" class="btn btn-approve">APPROVE</a>
                <a href="{{ $declineUrl }}" class="btn btn-decline">DECLINE</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated request from the Vendor Portal System.</p>
        </div>
    </div>
</body>
</html>
