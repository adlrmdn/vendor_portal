<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Calibri, Helvetica, sans-serif; font-size: 11px; color: #111; }
    .header-box { border: 1px solid #000; padding: 6px; text-align: right; float: right; width: 220px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #333; padding: 4px 6px; font-size: 10px; }
    th { background: #eee; }
    .red { color: #c00; font-weight: bold; }
    .highlight { background: #fff3b0; }
    .section-title { font-weight: bold; margin-top: 14px; }
    .sig-table td { text-align: center; height: 50px; vertical-align: bottom; }
</style>
</head>
<body>
    @php $snap = $contract->header_snapshot ?? []; @endphp

    <div class="header-box">
        <strong>{{ $contract->contract_number }}</strong><br>
        {{ $contract->contract_date?->format('d-m-Y') }}<br>
        {{ $snap['season_label'] ?? '' }}
    </div>
    <h2>PRELIMINARY CONTRACT</h2>
    <div style="clear:both"></div>

    <table>
        <tr>
            <td style="width:50%">
                <strong>{{ $snap['vendor_name'] ?? '' }}</strong><br>
                {{ $snap['vendor_contact_person'] ?? '' }}<br>
                Mode of Transport: {{ $snap['transport_mode_label'] ?? '' }}
            </td>
            <td>
                Terms of Delivery: {{ $snap['delivery_term_label'] ?? '' }}<br>
                Terms of Payment: {{ $snap['payment_term_label'] ?? '' }}
            </td>
        </tr>
    </table>

    <p><strong>{{ strtoupper($snap['currency_label'] ?? '') }}</strong></p>

    <table>
        <thead>
            <tr>
                <th>No.</th><th>Brand/Style</th><th>PLM ID</th><th>Lot</th><th>Item No</th><th>Item Name</th>
                <th>Composition</th><th>Construction</th><th>Weave</th><th>Full Width</th><th>Cutt Width</th>
                <th>Weight</th><th>Process</th><th>Finish</th><th>HS Code</th><th>Qty</th><th>Price</th><th>UOM</th><th>TTL Value</th><th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contract->lines as $line)
            <tr>
                <td>{{ $line->line_no }}</td>
                <td>{{ $line->plmRef?->article_name }}</td>
                <td>{{ $line->plm_id_snapshot }}</td>
                <td>{{ $line->deliveries->first()?->lot?->label }}</td>
                <td>{{ $line->item_number_snapshot }}</td>
                <td>{{ $line->item_name_snapshot }}</td>
                <td>{{ $line->fabricItem?->composition }}</td>
                <td>{{ $line->fabricItem?->construction }}</td>
                <td>{{ $line->fabricItem?->weave }}</td>
                <td>{{ $line->fabricItem?->full_width }}</td>
                <td>{{ $line->fabricItem?->cut_width }}</td>
                <td>{{ $line->fabricItem?->weight_gsm }}</td>
                <td>{{ $line->fabricItem?->process }}</td>
                <td>{{ $line->fabricItem?->finish }}</td>
                <td>{{ $line->hs_code_snapshot }}</td>
                <td>{{ $line->qty }}</td>
                <td>{{ $line->unit_price }}</td>
                <td>{{ $line->uom_snapshot }}</td>
                <td>{{ $line->line_total }}</td>
                <td>{{ $line->remarks }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td><strong>TOTAL QUANTITY:</strong> {{ $contract->lines->sum('qty') }}</td>
            <td><strong>TOTAL VALUE:</strong> {{ $contract->lines->sum('line_total') }}</td>
            <td><strong>VAT/PPN:</strong> {{ $snap['vat_treatment_code'] ?? '' }}@if(($snap['vat_treatment_code'] ?? '') === 'INCLUDE') (PPN {{ $snap['vat_rate'] ?? '' }}%)@endif</td>
        </tr>
    </table>

    <div class="section-title">Additional Notes</div>
    @foreach ($contract->notes as $note)
        <p class="{{ $note->emphasis ? 'red' : '' }}">{{ $note->clause_number }}. {{ $note->body }}</p>
    @endforeach

    <div class="section-title">Shipment Tolerance</div>
    <table>
        <thead><tr><th>Band</th><th>Tolerance</th></tr></thead>
        <tbody>
            @foreach ($contract->tolerances as $t)
                <tr class="{{ $t->highlight ? 'highlight' : '' }}"><td>{{ $t->row_label }}</td><td>{{ $t->tolerance }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="sig-table">
        <tr>
            <td style="width:33%">Prepared electronically<br>{{ $contract->creator?->name }}<br>{{ $contract->created_at?->format('d-m-Y H:i') }} WIB</td>
            <td style="width:33%">Approved electronically<br>{{ $contract->approver?->name }}<br>{{ $contract->approval_decision_at?->format('d-m-Y H:i') }} WIB</td>
            <td>Supplier<br>&nbsp;<br>&nbsp;</td>
        </tr>
    </table>

    <p style="font-size:9px; margin-top:10px;">
        {{ $contract->contract_number }} &nbsp; | &nbsp;
        Verification: {{ substr(hash('sha256', $contract->id.$contract->contract_number.$contract->confirmed_at), 0, 12) }}
    </p>
</body>
</html>
