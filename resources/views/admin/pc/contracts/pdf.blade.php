<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $contract->contract_number ?? 'Preliminary Contract' }}</title>
    <style>
        @page {
            size: 1650pt 1275pt;
            margin: 36pt 40pt;
        }
        body {
            font-family: Calibri, 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.15;
            margin: 0;
            padding: 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        .tbl-grid th, .tbl-grid td {
            border: 0.75pt solid #000;
            padding: 2.5pt 3pt;
            vertical-align: top;
            box-sizing: border-box;
            word-wrap: break-word;
        }
        .bg-grey {
            background-color: #D9D9D9;
        }
        .bg-yellow {
            background-color: #FFFF00;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: bold; }
        .text-red { color: #D00000; font-weight: bold; }
        .header-cell {
            background-color: #D9D9D9;
            font-weight: bold;
            font-size: 9.5pt;
        }
        .sig-badge-signed {
            display: inline-block;
            border: 1.5pt solid #059669;
            border-radius: 4pt;
            background-color: #ECFDF5;
            color: #047857;
            font-size: 10pt;
            font-weight: bold;
            padding: 4pt 16pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .sig-badge-rejected {
            display: inline-block;
            border: 1.5pt solid #DC2626;
            border-radius: 4pt;
            background-color: #FEF2F2;
            color: #B91C1C;
            font-size: 10pt;
            font-weight: bold;
            padding: 4pt 16pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .sig-badge-pending {
            display: inline-block;
            border: 1.5pt solid #94A3B8;
            border-radius: 4pt;
            background-color: #F1F5F9;
            color: #1E293B;
            font-size: 9.5pt;
            font-weight: bold;
            padding: 4pt 16pt;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .sig-badge-queried {
            display: inline-block;
            border: 1.5pt solid #D97706;
            border-radius: 4pt;
            background-color: #FEF3C7;
            color: #B45309;
            font-size: 9.5pt;
            font-weight: bold;
            padding: 4pt 16pt;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .sig-email {
            font-size: 8.5pt;
            color: #334155;
            font-weight: 500;
            margin-top: 3pt;
            line-height: 1.2;
        }
        .sig-role {
            font-size: 8pt;
            color: #64748B;
            font-weight: normal;
            text-transform: uppercase;
            margin-top: 2pt;
            letter-spacing: 0.3pt;
        }
        .watermark {
            position: fixed;
            top: 35%;
            left: 20%;
            width: 60%;
            text-align: center;
            opacity: 0.12;
            font-size: 110pt;
            font-weight: bold;
            color: #D00000;
            transform: rotate(-25deg);
            z-index: -1000;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @php
        $snap = $contract->header_snapshot ?? [];
        $isApprovedOrConfirmed = in_array($contract->status, [
            \App\Models\PcContract::STATUS_APPROVED,
            \App\Models\PcContract::STATUS_SENT_TO_SUPPLIER,
            \App\Models\PcContract::STATUS_CONFIRMED,
        ]);
    @endphp

    @if(! $isApprovedOrConfirmed)
        <div class="watermark">
            {{ str_replace('_', ' ', strtoupper($contract->status ?? 'DRAFT')) }}
        </div>
    @endif

    {{-- Top Header Block --}}
    <table style="width: 1570pt; margin-bottom: 8pt;">
        <tr>
            {{-- Left: Logo and Address --}}
            <td style="width: 520pt; vertical-align: top;">
                <table>
                    <tr>
                        @if($logoBase64)
                            <td style="width: 165pt; vertical-align: top; padding-right: 10pt;">
                                <img src="{{ $logoBase64 }}" style="width: 155pt; height: 64pt;" alt="Mega Putra Garment Logo">
                            </td>
                        @endif
                        <td style="vertical-align: top;">
                            <div style="font-size: 13.5pt; font-weight: bold; color: #000;">PT MEGA PUTRA GARMENT</div>
                            <div style="font-size: 9.5pt; color: #000; line-height: 1.25; margin-top: 2pt;">
                                JL. LINGKAR UTARA NO. 245<br>
                                DESA WANAREJAN, KEC. TAMAN<br>
                                KAB. PEMALANG, JAWA TENGAH<br>
                                PHONE : 028-4329055
                            </div>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- Center: Document Title --}}
            <td style="width: 530pt; text-align: center; vertical-align: middle;">
                <div style="font-size: 15pt; font-weight: bold; letter-spacing: 0.05em;">PRELIMINARY CONTRACT</div>
            </td>

            {{-- Right: Contract Number Box --}}
            <td style="width: 520pt; vertical-align: top;">
                <table class="tbl-grid" style="width: 320pt; float: right; font-size: 9.5pt;">
                    <tr>
                        <td class="header-cell" style="width: 110pt;">Contract Number</td>
                        <td class="fw-bold" style="font-size: 10pt;">
                            {{ $contract->contract_number ?? 'DRAFT' }}@if($contract->revision_no > 0) (REV {{ $contract->revision_no }})@endif
                        </td>
                    </tr>
                    <tr>
                        <td class="header-cell">Date</td>
                        <td>
                            {{ $contract->contract_date ? \Carbon\Carbon::parse($contract->contract_date)->format('d-m-Y') : now()->format('d-m-Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="header-cell">Season</td>
                        <td class="fw-bold">
                            {{ $snap['season_label'] ?? $contract->season?->name ?? $contract->season?->code ?? '' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Parties & Delivery Block --}}
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 6pt;">
        <tr>
            <td class="header-cell" style="width: 800pt;">SUPPLIER NAME AND ADDRESS</td>
            <td class="header-cell" style="width: 770pt;">FABRIC DELIVERY</td>
        </tr>
        <tr>
            <td style="height: 48pt;">
                @php
                    $vendorCode = $snap['vendor_code'] ?? $contract->vendor?->code ?? '';
                    $vendorName = $snap['vendor_name'] ?? $contract->vendor?->name ?? '';
                    $vendorAddress = $snap['vendor_address'] ?? $contract->vendor?->address ?? '';
                @endphp
                <div class="fw-bold" style="font-size: 10.5pt;">
                    {{ $vendorCode ? $vendorCode . ' - ' : '' }}{{ $vendorName }}
                </div>
                @if ($vendorAddress)
                    <div style="font-size: 8.5pt; margin-top: 2pt; color: #333; line-height: 1.25;">
                        {{ $vendorAddress }}
                    </div>
                @endif
            </td>
            <td style="padding: 0; vertical-align: top;">
                <table style="width: 100%;">
                    @forelse ($contract->deliveries as $del)
                        <tr>
                            <td style="padding: 2pt 5pt; border-bottom: 0.5pt solid #ddd; width: 450pt;">
                                LOT {{ $del->lot?->label ?? $del->lot?->code ?? $loop->iteration }}
                            </td>
                            <td style="padding: 2pt 5pt; border-bottom: 0.5pt solid #ddd; text-align: right;">
                                {{ $del->eta_date ? \Carbon\Carbon::parse($del->eta_date)->format('d-m-Y') : '' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td style="padding: 4pt 6pt; color: #888;">As scheduled in purchase order</td>
                        </tr>
                    @endforelse
                </table>
            </td>
        </tr>
        <tr>
            <td class="header-cell">Contact Person</td>
            <td class="header-cell">Terms of Delivery</td>
        </tr>
        <tr>
            <td>{{ $snap['vendor_contact_person'] ?? $contract->vendor?->contact_person ?? '—' }}</td>
            <td class="fw-bold">{{ $snap['delivery_term_label'] ?? $contract->deliveryTerm?->code ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-cell">Mode of Transport</td>
            <td class="header-cell">Terms of Payment</td>
        </tr>
        <tr>
            <td class="fw-bold">{{ $snap['transport_mode_label'] ?? $contract->transportMode?->code ?? '' }}</td>
            <td class="fw-bold">{{ $snap['payment_term_label'] ?? $contract->paymentTerm?->code ?? $contract->vendor?->paymentTerm?->code ?? '' }}</td>
        </tr>
    </table>

    {{-- Currency Block --}}
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 6pt;">
        <tr>
            <td class="header-cell" style="width: 200pt;">CURRENCY</td>
            <td class="fw-bold" style="width: 1370pt; font-size: 10pt;">
                {{ $currencyName }} ({{ $currencySymbol }})
            </td>
        </tr>
    </table>

    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 0; table-layout: fixed;">
        <colgroup>
            <col style="width: 1.90%;">
            <col style="width: 10.00%;">
            <col style="width: 6.50%;">
            <col style="width: 1.80%;">
            <col style="width: 19.00%;">
            <col style="width: 5.50%;">
            <col style="width: 5.50%;">
            <col style="width: 3.20%;">
            <col style="width: 3.30%;">
            <col style="width: 3.30%;">
            <col style="width: 3.40%;">
            <col style="width: 3.40%;">
            <col style="width: 3.00%;">
            <col style="width: 4.40%;">
            <col style="width: 4.20%;">
            <col style="width: 4.60%;">
            <col style="width: 2.20%;">
            <col style="width: 6.50%;">
            <col style="width: 8.30%;">
        </colgroup>
        <thead>
            <tr class="header-cell text-center" style="font-size: 9pt;">
                <th style="width: 1.90%;">No.</th>
                <th style="width: 10.00%;">Brand/Style</th>
                <th style="width: 6.50%;">PLM ID<br>Item No</th>
                <th style="width: 1.80%;">LOT</th>
                <th style="width: 19.00%;">Item Name</th>
                <th style="width: 5.50%;">Composition</th>
                <th style="width: 5.50%;">Construction</th>
                <th style="width: 3.20%;">Weave</th>
                <th style="width: 3.30%;">Full<br>Width</th>
                <th style="width: 3.30%;">Cutt<br>Width</th>
                <th style="width: 3.40%;">Weight</th>
                <th style="width: 3.40%;">Process</th>
                <th style="width: 3.00%;">Finish</th>
                <th style="width: 4.40%;">HS Code</th>
                <th style="width: 4.20%;">Quantity</th>
                <th style="width: 4.60%;">Price</th>
                <th style="width: 2.20%;">UOM</th>
                <th style="width: 6.50%;">TTL Value</th>
                <th style="width: 8.30%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php
                $uomTotals = [];
                $totalQty = 0;
                $totalVal = 0;
            @endphp
            @foreach ($contract->lines as $line)
                @php
                    $q = (float) $line->qty;
                    $p = (float) $line->unit_price;
                    $lineTotal = $q * $p;
                    $totalQty += $q;
                    $totalVal += $lineTotal;
                    $uom = $line->uom_snapshot ?? $line->fabricItem?->uom ?? 'MTR';
                    $uomTotals[$uom] = ($uomTotals[$uom] ?? 0) + $q;
                @endphp
                <tr style="font-size: 8pt;">
                    <td class="text-center">{{ $line->line_no }}</td>
                    <td style="word-break: break-word;">{{ $line->plmRef?->article_name ?? $line->plm_article_name_snapshot ?? '' }}</td>
                    <td class="text-center" style="white-space: nowrap; font-size: 7.5pt; padding: 2pt 1pt;">
                        <div>{{ $line->plm_id_snapshot }}</div>
                        <div class="fw-bold" style="margin-top: 2pt;">{{ $line->item_number_snapshot }}</div>
                    </td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->lot_snapshot ? str_replace('Lot ', '', $line->lot_snapshot) : ($line->lot?->label ?? $line->lot?->code ?? ($line->deliveries->first()?->lot?->label ?? $line->deliveries->first()?->lot?->code ?? '')) }}</td>
                    <td style="word-break: break-word;">{{ $line->item_name_snapshot }}</td>
                    <td class="text-center" style="word-break: break-word;">{{ $line->resolved_composition ?? $line->fabricItem?->composition }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->resolved_construction ?? $line->fabricItem?->construction }}</td>
                    <td class="text-center">{{ $line->resolved_weave ?? $line->fabricItem?->weave }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->resolved_full_width ?? $line->fabricItem?->full_width }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->resolved_cut_width ?? $line->fabricItem?->cut_width }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->resolved_weight ?? ($line->fabricItem?->weight_gsm ? $line->fabricItem->weight_gsm . ' GSM' : '') }}</td>
                    <td class="text-center">{{ $line->resolved_process ?? $line->fabricItem?->process }}</td>
                    <td class="text-center">{{ $line->resolved_finish ?? $line->fabricItem?->finish }}</td>
                    <td class="text-center" style="white-space: nowrap;">{{ $line->hs_code_snapshot ? \App\Models\HsCode::formatCode($line->hs_code_snapshot) : '—' }}</td>
                    <td class="text-right fw-bold">{{ number_format($q, $q == (int)$q ? 0 : 2) }}</td>
                    <td class="text-right" style="white-space: nowrap;">{{ $currencySymbol }}&nbsp;{{ number_format($p, 2) }}</td>
                    <td class="text-center">{{ $uom }}</td>
                    <td class="text-right fw-bold" style="white-space: nowrap; font-size: 7.5pt;">{{ $currencySymbol }}&nbsp;{{ number_format($lineTotal, 2) }}</td>
                    <td style="word-break: break-word; font-size: 7.5pt;">{{ $line->remarks }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals Block --}}
    @php
        $qtyBreakdown = [];
        foreach ($uomTotals as $uom => $sumQty) {
            $qtyBreakdown[] = number_format($sumQty, $sumQty == (int)$sumQty ? 0 : 2) . ' ' . $uom;
        }
        $qtySummaryText = count($qtyBreakdown) > 1 ? implode('; ', $qtyBreakdown) : number_format($totalQty, $totalQty == (int)$totalQty ? 0 : 2);
        $vatCode = $snap['vat_treatment_code'] ?? $contract->vatTreatment?->code ?? 'EXCLUDE';
        $vatLabel = $vatCode === 'INCLUDE' ? "INCLUDE (PPN " . ($snap['vat_rate'] ?? $contract->vat_rate ?? 11) . "%)" : "EXCLUDE";
    @endphp
    <table class="tbl-grid" style="width: 1570pt; margin-top: -0.75pt; margin-bottom: 8pt; table-layout: fixed;">
        <colgroup>
            <col style="width: 74.20%;">
            <col style="width: 4.20%;">
            <col style="width: 6.80%;">
            <col style="width: 6.50%;">
            <col style="width: 8.30%;">
        </colgroup>
        <tr>
            <td class="header-cell text-right fw-bold" style="width: 74.20%;">TOTAL QUANTITY</td>
            <td class="text-right fw-bold" style="width: 4.20%; font-size: 9pt;">{{ $qtySummaryText }}</td>
            <td style="width: 6.80%;"></td>
            <td style="width: 6.50%;"></td>
            <td style="width: 8.30%;"></td>
        </tr>
        <tr>
            <td class="header-cell text-right fw-bold" style="width: 74.20%;">TOTAL VALUE</td>
            <td style="width: 4.20%;"></td>
            <td style="width: 6.80%;"></td>
            <td class="text-right fw-bold" style="width: 6.50%; font-size: 9pt; white-space: nowrap;">{{ $currencySymbol }}&nbsp;{{ number_format($totalVal, 2) }}</td>
            <td style="width: 8.30%;"></td>
        </tr>
        <tr>
            <td class="header-cell text-right fw-bold" style="width: 74.20%;">VAT/PPN</td>
            <td colspan="4" class="fw-bold" style="font-size: 9pt; width: 25.80%;">{{ $vatLabel }}</td>
        </tr>
    </table>

    {{-- Standard Additional Notes --}}
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 8pt;">
        <tr>
            <td class="header-cell" style="font-size: 10pt;">ADDITIONAL NOTES</td>
        </tr>
        <tr>
            <td style="padding: 5pt 8pt; font-size: 8.5pt; line-height: 1.3;">
                @forelse ($contract->notes->sortBy('clause_number') as $note)
                    <div style="margin-bottom: 2pt;" class="{{ in_array($note->clause_number, [7, 8, 9]) || $note->emphasis ? 'text-red' : '' }}">
                        {{ $note->clause_number }}. {{ $note->body }}
                    </div>
                @empty
                    <div style="margin-bottom: 2pt;">1. THIS CONTRACT IS FOR BOOKING YARN ONLY. DO NOT DYE YARN UNLESS YOU HAVE THE PO IN HAND FROM PURCHASING WITH COLOR BREAKDOWN</div>
                    <div style="margin-bottom: 2pt;">2. PRODUCTION OF FINISH FABRIC SHOULD PROCEED BASED ON QUANTITY ,COLOR BREAKDOWN AND APPROVAL IN PURCHASE ORDER.</div>
                    <div style="margin-bottom: 2pt;">3. MILL HAS TO PROVIDE FOLLOWING DOCS PRIOR TO SHIPMENT : *INTERNAL FABRIC TEST REPORT *SHADEBAND/SHADELOT *INTERNAL 100% FABRIC INSPECTION REPORT</div>
                    <div style="margin-bottom: 2pt;">4. For Import &amp; overseas shipment it is mandatory for all goods to go through a SGS Inspection at factory before shipment cost of which is borne by the Buyer.</div>
                    <div style="margin-bottom: 2pt;">5. The Specification and HS code of the fabric in this contract are stated as declared to us by the supplier.</div>
                    <div style="margin-bottom: 2pt;">6. If SGS inspection finds non-conformity with the quantity , specification and HS of the goods the non-conformity will result in failure of shipment and extra cost will be incurred for shipment for which these costs are to be borne by the Supplier.</div>
                    <div style="margin-bottom: 2pt;" class="text-red">7. ANY LATE TIMELINE/DELIVERY FROM COMMITMENT - AIR SHIPMENT MAY APPLY</div>
                    <div style="margin-bottom: 2pt;" class="text-red">8. SHIPMENT TOLERANCE TO BE FOLLOWED. SHORTAGE MORE THAN DECLARED % - ACCESSORIES AND OR OTHER RELATED TRIMMING CLAIM WILL APPLY</div>
                    <div style="margin-bottom: 2pt;" class="text-red">9. THIS CONTRACT IS ONLY VALID AFTER QUALITY IS APPROVED ONLY</div>
                    @if($contract->additional_notes)
                        <div style="margin-bottom: 2pt;">10. {{ trim($contract->additional_notes) }}</div>
                    @endif
                @endforelse
                @if($contract->notes->isNotEmpty() && $contract->additional_notes && !$contract->notes->contains('body', trim($contract->additional_notes)))
                    <div style="margin-bottom: 2pt;">{{ ($contract->notes->max('clause_number') ?? 9) + 1 }}. {{ trim($contract->additional_notes) }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Shipment Tolerance Tables (Woven & Knit Side-by-Side) --}}
    <table style="width: 1570pt; margin-bottom: 10pt;">
        <tr>
            {{-- Woven Tolerance Table --}}
            <td style="width: 520pt; vertical-align: top;">
                <table class="tbl-grid" style="font-size: 8.5pt;">
                    <tr class="bg-yellow fw-bold">
                        <td style="width: 330pt;">Woven</td>
                        <td style="width: 190pt;">Shipment Tolerance</td>
                    </tr>
                    <tr><td>More than 3000 mtrs/yds</td><td>+2%</td></tr>
                    <tr><td>2000-3000 mtrs/yds</td><td>+3%</td></tr>
                    <tr><td>1000-1999 mtrs/yds</td><td>+5%</td></tr>
                    <tr><td>500-999 mtrs/yds</td><td>+7%</td></tr>
                    <tr><td>&lt;=499 meters/yards</td><td>+10%</td></tr>
                </table>
            </td>
            <td style="width: 40pt;"></td>
            {{-- Knit Tolerance Table --}}
            <td style="width: 520pt; vertical-align: top;">
                <table class="tbl-grid" style="font-size: 8.5pt;">
                    <tr class="bg-yellow fw-bold">
                        <td style="width: 330pt;">Knit</td>
                        <td style="width: 190pt;">Shipment Tolerance</td>
                    </tr>
                    <tr><td>Above and equal to 300 kgs</td><td>+3%</td></tr>
                    <tr><td>100-299 kgs</td><td>+5%</td></tr>
                    <tr><td>&lt;= 99 kgs</td><td>+10%</td></tr>
                    <tr class="bg-yellow fw-bold"><td>Rib + Flat Knit Collar + Cuff</td><td>+3%</td></tr>
                </table>
            </td>
            <td style="width: 490pt;"></td>
        </tr>
    </table>

    {{-- Electronic Signatures Block --}}
    @php
        $preparedName = $contract->creator?->name ?? 'Fabric Admin';
        $preparedEmail = $contract->creator?->email ?? '';
        $preparedAt = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : '—';

        $isApproved = !empty($contract->approval_decision_at) || $isApprovedOrConfirmed;
        $approvedName = $contract->approver?->name ?? ($isApproved ? 'Aslam Akbany' : 'Pending Approval');
        $approvedEmail = $contract->approver?->email ?? '';
        $approvedAt = $contract->approval_decision_at ? \Carbon\Carbon::parse($contract->approval_decision_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : ($isApproved ? 'Approved' : '—');

        $isConfirmed = $contract->status === \App\Models\PcContract::STATUS_CONFIRMED;
        $vendorName = $snap['vendor_name'] ?? $contract->vendor?->name ?? 'Supplier';
        $vendorEmail = $contract->vendorUser?->email ?? (is_array($contract->vendor?->contact_info) ? ($contract->vendor->contact_info['email'] ?? '') : '');
        $vendorAt = $contract->vendor_decision_at ?? $contract->confirmed_at;
        $vendorConfirmedAt = $vendorAt ? \Carbon\Carbon::parse($vendorAt)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : ($isConfirmed ? 'Confirmed' : '—');
    @endphp
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 8pt; font-size: 9pt; table-layout: fixed;">
        <colgroup>
            <col style="width: 8.28%;">
            <col style="width: 30.57%;">
            <col style="width: 30.57%;">
            <col style="width: 30.58%;">
        </colgroup>
        <tr class="header-cell text-center">
            <th style="width: 8.28%;"></th>
            <th style="width: 30.57%;">Prepared</th>
            <th style="width: 30.57%;">Approved</th>
            <th style="width: 30.58%;">Supplier</th>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="height: 52pt; vertical-align: middle;">SIGNATURE</td>
            <td class="text-center" style="vertical-align: middle; height: 52pt; padding: 5pt 6pt;">
                <div class="sig-badge-signed">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==" style="width: 9pt; height: 9pt; margin-right: 3.5pt; vertical-align: middle; margin-top: -1.5pt; display: inline-block;">
                    Digitally Signed
                </div>
                @if($preparedEmail)
                    <div class="sig-email">{{ $preparedEmail }}</div>
                @endif
            </td>
            <td class="text-center" style="vertical-align: middle; height: 52pt; padding: 5pt 6pt;">
                @if($contract->approval_decision_at || $isApprovedOrConfirmed)
                    <div class="sig-badge-signed">
                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==" style="width: 9pt; height: 9pt; margin-right: 3.5pt; vertical-align: middle; margin-top: -1.5pt; display: inline-block;">
                        Digitally Signed
                    </div>
                    @if($approvedEmail)
                        <div class="sig-email">{{ $approvedEmail }}</div>
                    @endif
                @elseif($contract->status === \App\Models\PcContract::STATUS_REJECTED)
                    <div class="sig-badge-rejected">
                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAY0lEQVR4nJWQ0Q0AIQhDxQluKJnaoWSDd18mnKDxSPigtKRFgPKn6i3RVAmCCe7IpkrNwN2Rp3epfjiJ517W0JktfyyE9stsDoJThiDIPK94+JInZ6JPaFNl9RxsAVc9WgMoL8rWaChiYc43AAAAAElFTkSuQmCC" style="width: 9pt; height: 9pt; margin-right: 3.5pt; vertical-align: middle; margin-top: -1.5pt; display: inline-block;">
                        Rejected
                    </div>
                @else
                    <div class="sig-badge-pending">Awaiting Approval</div>
                @endif
            </td>
            <td class="text-center" style="vertical-align: middle; height: 52pt; padding: 5pt 6pt;">
                @if($isConfirmed)
                    <div class="sig-badge-signed">
                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==" style="width: 9pt; height: 9pt; margin-right: 3.5pt; vertical-align: middle; margin-top: -1.5pt; display: inline-block;">
                        Digitally Signed
                    </div>
                    @if($vendorEmail)
                        <div class="sig-email">{{ $vendorEmail }}</div>
                    @endif
                @elseif($contract->status === \App\Models\PcContract::STATUS_SUPPLIER_QUERIED)
                    <div class="sig-badge-queried">Queried by Supplier</div>
                @else
                    <div class="sig-badge-pending">Pending Supplier Acceptance</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="vertical-align: middle; padding: 5pt 4pt;">NAME</td>
            <td class="text-center" style="padding: 5pt 4pt; vertical-align: middle;">
                <div class="fw-bold" style="font-size: 9.5pt; color: #0F172A;">{{ $preparedName }}</div>
                <div class="sig-role">Fabric Merchandiser</div>
            </td>
            <td class="text-center" style="padding: 5pt 4pt; vertical-align: middle;">
                @if($contract->approval_decision_at || $isApprovedOrConfirmed)
                    <div class="fw-bold" style="font-size: 9.5pt; color: #0F172A;">{{ $approvedName }}</div>
                @else
                    <div style="font-size: 9pt; color: #475569; font-style: italic; font-weight: 600;">Pending Approval</div>
                @endif
                <div class="sig-role">Head of Fabric</div>
            </td>
            <td class="text-center" style="padding: 5pt 4pt; vertical-align: middle;">
                <div class="fw-bold" style="font-size: 9.5pt; color: #0F172A;">{{ $vendorName }}</div>
                <div class="sig-role">Authorized Supplier Representative</div>
            </td>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="vertical-align: middle; padding: 4pt;">DATE</td>
            <td class="text-center" style="padding: 4pt; font-size: 9pt; color: #1E293B;">{{ $preparedAt }}</td>
            <td class="text-center" style="padding: 4pt; font-size: 9pt; color: #1E293B;">{{ $approvedAt }}</td>
            <td class="text-center" style="padding: 4pt; font-size: 9pt; color: #1E293B;">{{ $vendorConfirmedAt }}</td>
        </tr>
    </table>

    {{-- Bottom Verification Code Bar --}}
    <table style="width: 1570pt; font-size: 8.5pt; color: #555; margin-top: 4pt;">
        <tr>
            <td style="text-align: left; width: 500pt;">
                {{ $contract->contract_number ?? 'DRAFT' }}@if($contract->revision_no > 0) (REV {{ $contract->revision_no }})@endif
            </td>
            <td style="text-align: center; width: 570pt;">
                @if($verificationCode)
                    Verification code: <span style="font-family: monospace; font-weight: bold; color: #000;">{{ $verificationCode }}</span>
                @endif
            </td>
            <td style="text-align: right; width: 500pt;">
                Page 1 of 1 &nbsp;|&nbsp; PT Mega Putra Garment
            </td>
        </tr>
    </table>
</body>
</html>
