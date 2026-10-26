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
        }
        .tbl-grid th, .tbl-grid td {
            border: 0.75pt solid #000;
            padding: 2.5pt 4pt;
            vertical-align: top;
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
                <div class="fw-bold" style="font-size: 10.5pt;">{{ $snap['vendor_name'] ?? $contract->vendor?->name ?? '' }}</div>
                <div style="font-size: 9pt; margin-top: 2pt;">
                    {{ $snap['vendor_address'] ?? $contract->vendor?->address ?? '' }}
                </div>
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
            <td class="fw-bold">{{ $snap['payment_term_label'] ?? $contract->paymentTerm?->code ?? '' }}</td>
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

    {{-- 20-Column Line Items Table --}}
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 0;">
        <thead>
            <tr class="header-cell text-center" style="font-size: 9pt;">
                <th style="width: 28pt;">No.</th>
                <th style="width: 130pt;">Brand/Style</th>
                <th style="width: 82pt;">PLM ID</th>
                <th style="width: 40pt;">LOT</th>
                <th style="width: 98pt;">Item No</th>
                <th style="width: 160pt;">Item Name</th>
                <th style="width: 138pt;">Composition</th>
                <th style="width: 120pt;">Construction</th>
                <th style="width: 70pt;">Weave</th>
                <th style="width: 52pt;">Full<br>Width</th>
                <th style="width: 52pt;">Cutt<br>Width</th>
                <th style="width: 48pt;">Weight</th>
                <th style="width: 72pt;">Process</th>
                <th style="width: 72pt;">Finish</th>
                <th style="width: 78pt;">HS Code</th>
                <th style="width: 70pt;">Quantity</th>
                <th style="width: 66pt;">Price</th>
                <th style="width: 38pt;">UOM</th>
                <th style="width: 92pt;">TTL Value</th>
                <th style="width: 64pt;">Remarks</th>
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
                <tr style="font-size: 8.5pt;">
                    <td class="text-center">{{ $line->line_no }}</td>
                    <td>{{ $line->plmRef?->article_name ?? $line->plm_article_name_snapshot ?? '' }}</td>
                    <td>{{ $line->plm_id_snapshot }}</td>
                    <td class="text-center">{{ $line->deliveries->first()?->lot?->label ?? $line->deliveries->first()?->lot?->code ?? '' }}</td>
                    <td class="fw-bold">{{ $line->item_number_snapshot }}</td>
                    <td>{{ $line->item_name_snapshot }}</td>
                    <td>{{ $line->fabricItem?->composition }}</td>
                    <td>{{ $line->fabricItem?->construction }}</td>
                    <td>{{ $line->fabricItem?->weave }}</td>
                    <td class="text-center">{{ $line->fabricItem?->full_width }}</td>
                    <td class="text-center">{{ $line->fabricItem?->cut_width }}</td>
                    <td class="text-center">{{ $line->fabricItem?->weight_gsm ? $line->fabricItem->weight_gsm . ' GSM' : '' }}</td>
                    <td>{{ $line->fabricItem?->process }}</td>
                    <td>{{ $line->fabricItem?->finish }}</td>
                    <td>{{ $line->hs_code_snapshot }}</td>
                    <td class="text-right fw-bold">{{ number_format($q, $q == (int)$q ? 0 : 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }} {{ number_format($p, 2) }}</td>
                    <td class="text-center">{{ $uom }}</td>
                    <td class="text-right fw-bold">{{ $currencySymbol }} {{ number_format($lineTotal, 2) }}</td>
                    <td>{{ $line->remarks }}</td>
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
    <table class="tbl-grid" style="width: 1570pt; margin-top: -0.75pt; margin-bottom: 8pt;">
        <tr>
            <td class="header-cell text-right" style="width: 1210pt;">TOTAL QUANTITY</td>
            <td class="text-right fw-bold" style="width: 70pt; font-size: 9.5pt;">
                {{ $qtySummaryText }}
            </td>
            <td style="width: 104pt;"></td>
            <td class="text-right fw-bold" style="width: 92pt; font-size: 9.5pt;">
                {{ $currencySymbol }} {{ number_format($totalVal, 2) }}
            </td>
            <td style="width: 94pt;"></td>
        </tr>
        <tr>
            <td class="header-cell text-right">TOTAL VALUE</td>
            <td colspan="4" class="fw-bold" style="font-size: 10pt;">
                {{ $currencySymbol }} {{ number_format($totalVal, 2) }}
            </td>
        </tr>
        <tr>
            <td class="header-cell text-right">VAT/PPN</td>
            <td colspan="4" class="fw-bold">
                {{ $vatLabel }}
            </td>
        </tr>
    </table>

    {{-- Standard Additional Notes --}}
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 8pt;">
        <tr>
            <td class="header-cell" style="font-size: 10pt;">ADDITIONAL NOTES</td>
        </tr>
        <tr>
            <td style="padding: 5pt 8pt; font-size: 8.5pt; line-height: 1.3;">
                @forelse ($contract->notes as $note)
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
                @endforelse
                @if($contract->internal_remarks)
                    <div style="margin-top: 4pt; font-style: italic;">
                        Special Instructions: {{ $contract->internal_remarks }}
                    </div>
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
        $preparedName = $contract->creator?->name ?? 'Leon Oscar';
        $preparedAt = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : '—';
        $approvedName = $contract->approver?->name ?? ($isApprovedOrConfirmed ? 'Aslam Akbany' : 'Pending Approval');
        $approvedAt = $contract->approval_decision_at ? \Carbon\Carbon::parse($contract->approval_decision_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : ($isApprovedOrConfirmed ? 'Approved' : '—');
        $isConfirmed = $contract->status === \App\Models\PcContract::STATUS_CONFIRMED;
        $vendorName = $snap['vendor_name'] ?? $contract->vendor?->name ?? 'Supplier';
        $vendorAt = $contract->vendor_decision_at ?? $contract->confirmed_at;
        $vendorConfirmedAt = $vendorAt ? \Carbon\Carbon::parse($vendorAt)->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB' : ($isConfirmed ? 'Confirmed' : 'Pending Supplier');
    @endphp
    <table class="tbl-grid" style="width: 1570pt; margin-bottom: 8pt; font-size: 9pt;">
        <tr class="header-cell text-center">
            <th style="width: 90pt;"></th>
            <th style="width: 490pt;">Prepared</th>
            <th style="width: 490pt;">Approved</th>
            <th style="width: 500pt;">Supplier</th>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="height: 48pt; vertical-align: middle;">SIGNATURE</td>
            <td class="text-center" style="vertical-align: middle;">
                <div style="color: #198754; font-weight: bold; font-size: 9.5pt;">[ Prepared electronically ]</div>
            </td>
            <td class="text-center" style="vertical-align: middle;">
                @if($contract->approval_decision_at || $isApprovedOrConfirmed)
                    <div style="color: #198754; font-weight: bold; font-size: 9.5pt;">[ Approved electronically ]</div>
                @else
                    <div style="color: #999; font-style: italic;">[ Awaiting Approval ]</div>
                @endif
            </td>
            <td class="text-center" style="vertical-align: middle;">
                @if($isConfirmed)
                    <div style="color: #198754; font-weight: bold; font-size: 9.5pt;">[ Accepted electronically ]</div>
                @else
                    <div style="color: #999; font-style: italic;">[ Pending Supplier Acceptance ]</div>
                @endif
            </td>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="vertical-align: middle;">NAME</td>
            <td class="text-center fw-bold">{{ $preparedName }}</td>
            <td class="text-center fw-bold">{{ $approvedName }}</td>
            <td class="text-center fw-bold">{{ $vendorName }}</td>
        </tr>
        <tr>
            <td class="header-cell fw-bold text-center" style="vertical-align: middle;">DATE</td>
            <td class="text-center">{{ $preparedAt }}</td>
            <td class="text-center">{{ $approvedAt }}</td>
            <td class="text-center">{{ $vendorConfirmedAt }}</td>
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
