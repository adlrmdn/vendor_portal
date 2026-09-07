{{-- Debit note page — layout port of "DEBIT NOTE - CMT.xlsx". Prepended ahead
     of the QC inspection report by deduction-report.blade.php.

     Document No / Invoice Date are blank at queue time (RPA hasn't run yet —
     see QcReportPdfService::renderDeductionDataUri). The debit_note RPA bot
     (automaton/pw_service/debit_note/pdf_fill.py) later overlays the real
     values onto this exact rendered PDF at fixed (x, y) points measured
     against this layout — do NOT reposition/reflow the "Document No"/
     "Invoice Date" value cells without re-measuring and updating
     pdf_fill.py's DOCUMENT_NO_POS / INVOICE_DATE_POS to match. --}}
<div style="position: relative; font-size: 8pt; line-height: 1.5;">
    {{-- Logo on its own full-width row — kept separate from the two-column
         info row below on purpose: this logo is a wide wordmark (~2.9:1), and
         sizing it inside a 50%-width cell alongside "DEBIT NOTE"/Document No
         forced dompdf to squeeze that column and shift the Document No/
         Invoice Date position. On its own row it can be sized freely without
         touching those coordinates. --}}
    @if ($logoData)
        <div style="margin-bottom: 10pt;">
            <img src="{{ $logoData }}" style="height: 66px;">
        </div>
    @endif
    <table style="width: 100%; margin-bottom: 14pt;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                JL. Karet Pedurenan No.240.<br>
                Karet Kuningan, Jakarta Selatan 12940<br>
                IDN
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <div style="font-size: 16pt; font-weight: bold; letter-spacing: 1pt; margin-bottom: 8pt;">DEBIT NOTE</div>
                <table style="width: 100%; font-size: 8pt;">
                    <tr>
                        <td style="width: 45%; text-align: right; padding-right: 4pt;">Document No</td>
                        <td style="width: 5%; text-align: center;">:</td>
                        <td style="width: 50%; text-align: left; font-weight: bold;">{{ $debitDocumentNo ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; padding-right: 4pt;">Invoice Date</td>
                        <td style="text-align: center;">:</td>
                        <td style="text-align: left; font-weight: bold;">{{ $debitInvoiceDate ?: '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 60%; margin-bottom: 14pt;">
        <tr>
            <td style="width: 30%;">Customer</td>
            <td style="width: 3%;">:</td>
            <td style="width: 67%; font-weight: bold;">{{ $debitVendor ?: '—' }}</td>
        </tr>
        <tr>
            <td>NPWP</td>
            <td>:</td>
            <td></td>
        </tr>
        <tr>
            <td>Faktur Pajak</td>
            <td>:</td>
            <td></td>
        </tr>
        <tr>
            <td>Payment Term</td>
            <td>:</td>
            <td></td>
        </tr>
        <tr>
            <td>Customer Ref</td>
            <td>:</td>
            <td>{{ $debitCustomerRef ?: '—' }}</td>
        </tr>
    </table>

    {{-- Same itemization as the inspection report's "4. Deductions & Remarks"
         (Production Reject Penalty / Lost Items Penalty / Fabric
         Overconsumption / manual lines) — this claim mirrors that section
         rather than showing one lump sum. --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 4pt;">
        <tr style="border-bottom: 1pt solid #333;">
            <th style="width: 46%; text-align: left; padding: 4pt 2pt;">Description</th>
            <th style="width: 10%; text-align: center; padding: 4pt 2pt;">QTY</th>
            <th style="width: 10%; text-align: center; padding: 4pt 2pt;">Satuan</th>
            <th style="width: 17%; text-align: right; padding: 4pt 2pt;">Unit Price</th>
            <th style="width: 17%; text-align: right; padding: 4pt 2pt;">Total</th>
        </tr>
        @if (! $debitHasAnyDeduction)
            <tr><td colspan="5" style="text-align: center; padding: 6pt; color: #666;">No itemized deductions available — see total below.</td></tr>
        @else
            @if ($deductions['exceedingRejectQty'] > 0)
                <tr>
                    <td style="padding: 4pt 2pt;">Production Reject Penalty (Exceeding 1% Limit) — {{ $debitPo ?: '—' }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">{{ $deductions['exceedingRejectQty'] }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">PCS</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($deductions['penaltyPrice'], 0, '.', ',') }}</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($deductions['rejectProduksiPenalty'], 0, '.', ',') }}</td>
                </tr>
            @endif
            @if ($deductions['sumBarangHilang'] > 0)
                <tr>
                    <td style="padding: 4pt 2pt;">Lost Items Penalty (Barang Hilang) — {{ $debitPo ?: '—' }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">{{ $deductions['sumBarangHilang'] }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">PCS</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($deductions['penaltyPrice'], 0, '.', ',') }}</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($deductions['barangHilangPenalty'], 0, '.', ',') }}</td>
                </tr>
            @endif
            @foreach ($debitFabricDeductionLines as $f)
                <tr>
                    <td style="padding: 4pt 2pt;">Fabric Overconsumption{{ $f->label ? ' — '.$f->label : '' }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">—</td>
                    <td style="text-align: center; padding: 4pt 2pt;">-</td>
                    <td style="text-align: right; padding: 4pt 2pt;">{{ $f->fabric_price !== null ? 'Rp '.number_format($f->fabric_price, 0, '.', ',') : '—' }}</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($f->deduction, 0, '.', ',') }}</td>
                </tr>
            @endforeach
            @foreach ($deductionLines as $line)
                <tr>
                    <td style="padding: 4pt 2pt;">{{ $line->description }}</td>
                    <td style="text-align: center; padding: 4pt 2pt;">—</td>
                    <td style="text-align: center; padding: 4pt 2pt;">-</td>
                    <td style="text-align: right; padding: 4pt 2pt;">—</td>
                    <td style="text-align: right; padding: 4pt 2pt;">Rp {{ number_format($line->amount, 0, '.', ',') }}</td>
                </tr>
            @endforeach
        @endif
    </table>

    <table style="width: 100%; margin-bottom: 10pt;">
        <tr>
            <td style="width: 66%;"></td>
            <td style="width: 17%; text-align: right; padding: 2pt;">Total</td>
            <td style="width: 17%; text-align: right; padding: 2pt;">Rp {{ number_format($debitAmount, 0, '.', ',') }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="text-align: right; padding: 2pt;">Discount</td>
            <td style="text-align: right; padding: 2pt;">Rp 0</td>
        </tr>
        <tr style="font-weight: bold;">
            <td></td>
            <td style="text-align: right; padding: 2pt; border-top: 0.6pt solid #333;">Grand Total</td>
            <td style="text-align: right; padding: 2pt; border-top: 0.6pt solid #333;">Rp {{ number_format($debitAmount, 0, '.', ',') }}</td>
        </tr>
        <tr style="font-weight: bold;">
            <td></td>
            <td style="text-align: right; padding: 2pt;">Pay This Amount</td>
            <td style="text-align: right; padding: 2pt;">Rp {{ number_format($debitAmount, 0, '.', ',') }}</td>
        </tr>
    </table>

    <div style="margin-bottom: 14pt;">Say : <span style="font-weight: bold;">{{ $debitAmountWords }}</span></div>

    <div style="font-weight: bold; border-bottom: 0.6pt solid #333; margin-bottom: 6pt; padding-bottom: 2pt;">PAYMENT DETAIL</div>
    <table style="width: 60%; margin-bottom: 14pt;">
        <tr>
            <td style="width: 25%;">Name</td>
            <td style="width: 3%;">:</td>
            <td style="width: 72%;">PT Mega Putra Garment</td>
        </tr>
        <tr>
            <td>Account</td>
            <td>:</td>
            <td>8161677728</td>
        </tr>
        <tr>
            <td>Branch</td>
            <td>:</td>
            <td>BANK CENTRAL ASIA<br>KCU - BOROBUDUR MALANG</td>
        </tr>
    </table>

    <div style="font-size: 7pt; color: #666;">
        This is a system generated note and requires no signature<br>
        Nota ini hasil cetakan sistem dan tidak memerlukan tanda tangan
    </div>
</div>
