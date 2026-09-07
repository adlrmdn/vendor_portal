<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #212529; line-height: 1.5; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { border: 1px solid #e9ecef; border-radius: 10px; padding: 24px; }
        .tag { display: inline-block; background: #e7f0ff; color: #1c4ed8; font-size: 12px;
               font-weight: 700; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { margin: 14px 0 4px; font-size: 20px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.meta td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        table.meta td.k { color: #868e96; width: 150px; }
        .muted { color: #868e96; font-size: 12px; margin-top: 20px; }
        table.data { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
        table.data th, table.data td { border: 1px solid #dee2e6; padding: 7px 10px; font-size: 13px; }
        table.data th { background: #f1f3f5; text-align: left; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; color: #495057; }
        table.data td.num { text-align: right; }
        table.data tfoot td { background: #f8f9fa; font-weight: 700; }
        .section-title { font-size: 13px; font-weight: 700; margin: 18px 0 4px; }
        .remark-box { margin:14px 0; padding:12px 14px; background:#fff9db; border:1px solid #ffe066; border-radius:8px; }
        .remark-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #7a5a00; margin-bottom: 4px; }
        .remark-text { font-size: 13px; color: #495057; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <span class="tag">Director Authorization Needed</span>
            <h2>Director Authorization</h2>
            <p style="margin:0;color:#495057;">This packaging inspection has been confirmed by the factory representative and approved by MD Production. It now needs your <strong>final authorization</strong>. The current report is attached; authorizing completes the project and queues the Invoice/Deduction RPA jobs.</p>

            <table class="meta">
                @if($orderNumber)
                    <tr><td class="k">Work Order</td><td><strong>{{ $orderNumber }}</strong></td></tr>
                @endif
                @if($productionGroup)
                    <tr><td class="k">Production Group</td><td>{{ $productionGroup }}</td></tr>
                @endif
                <tr><td class="k">Project</td><td>{{ $projectId ?? '—' }}</td></tr>
                <tr><td class="k">Session</td><td>{{ $sessionId ?? '—' }}</td></tr>
                <tr>
                    <td class="k">Deduction</td>
                    <td>
                        @if(($deductionTotal ?? 0) > 0)
                            <span style="display:inline-block;background:#ffe3e3;color:#c92a2a;font-weight:700;font-size:13px;padding:3px 10px;border-radius:10px;">Rp {{ number_format($deductionTotal, 0, ',', '.') }}</span>
                        @else
                            <span style="display:inline-block;background:#ebfbee;color:#2b8a3e;font-weight:700;font-size:13px;padding:3px 10px;border-radius:10px;">None</span>
                        @endif
                    </td>
                </tr>
            </table>

            @foreach([
                ['label' => 'Vendor Remarks', 'text' => $vendorRemarks ?? null],
                ['label' => 'QC Remarks', 'text' => $qcRemarks ?? null],
                ['label' => 'MD Production Remarks', 'text' => $hoRemarks ?? null],
            ] as $r)
                @if(!empty($r['text']))
                    <div class="remark-box">
                        <div class="remark-label">{{ $r['label'] }}</div>
                        <div class="remark-text">{{ $r['text'] }}</div>
                    </div>
                @endif
            @endforeach

            @php
                $cuttPlanTotal = collect($fabricLines ?? [])->pluck('cutt_plan')->filter(fn ($v) => $v !== null)->min();
            @endphp
            @if(!empty($productionGroups))
                <div class="section-title">Cutting Quantities by Size</div>
                @foreach($productionGroups as $g)
                    @php $lines = $g['lines'] ?? []; @endphp
                    @if(!empty($lines))
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th style="text-align:right;">Order Qty</th>
                                    <th style="text-align:right;">Cut Plan</th>
                                    <th style="text-align:right;">Cut Qty</th>
                                    <th style="text-align:right;">Balance</th>
                                    <th style="text-align:right;">Gramasi (g)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalOrderQty = collect($lines)->sum('Qty'); $totalCutQty = 0; @endphp
                                @foreach($lines as $line)
                                    @php
                                        $orderQty = (int) $line->Qty;
                                        $cutQty = ($cuttingReports ?? collect())->get($line->ProdId)?->cutting_qty;
                                        $totalCutQty += (int) ($cutQty ?? 0);
                                        $cutPlanQty = ($cuttPlanTotal !== null && $totalOrderQty > 0)
                                            ? (int) round($orderQty / $totalOrderQty * $cuttPlanTotal) : null;
                                        $balanceBase = $cutPlanQty ?? $orderQty;
                                        $balance = $cutQty !== null ? ((int) $cutQty - $balanceBase) : null;
                                        $gramasi = ($cuttingReports ?? collect())->get($line->ProdId)?->gramasi;
                                    @endphp
                                    <tr>
                                        <td>{{ $line->Size ?: '—' }}</td>
                                        <td class="num">{{ number_format($orderQty) }}</td>
                                        <td class="num">{{ $cutPlanQty !== null ? number_format($cutPlanQty) : '—' }}</td>
                                        <td class="num">{{ $cutQty !== null ? number_format($cutQty) : '—' }}</td>
                                        <td class="num" style="color:{{ $balance === null ? '#868e96' : ($balance < 0 ? '#c92a2a' : '#2b8a3e') }};">
                                            {{ $balance === null ? '—' : ($balance > 0 ? '+'.number_format($balance) : number_format($balance)) }}
                                        </td>
                                        <td class="num">{{ $gramasi !== null ? number_format($gramasi, 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    <td class="num">{{ number_format($totalOrderQty) }}</td>
                                    <td class="num">{{ $cuttPlanTotal !== null ? number_format($cuttPlanTotal) : '—' }}</td>
                                    <td class="num">{{ number_format($totalCutQty) }}</td>
                                    <td class="num" colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                @endforeach
            @endif

            @if(!empty($fabricLines))
                <div class="section-title">Fabric Consumption</div>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Fabric</th>
                            <th style="text-align:right;">Fabric Sent</th>
                            <th style="text-align:right;">Cons. Plan</th>
                            <th style="text-align:right;">Actual Cons.</th>
                            <th style="text-align:right;">Overconsumption</th>
                            <th style="text-align:right;">Deduction</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fabricLines as $fl)
                            <tr>
                                <td>{{ $fl['label'] ?? '—' }}</td>
                                <td class="num">{{ $fl['fabric_sent'] !== null ? number_format($fl['fabric_sent'], 2) : '—' }}</td>
                                <td class="num">{{ $fl['consumption_plan'] !== null ? number_format($fl['consumption_plan'], 4) : '—' }}</td>
                                <td class="num">{{ $fl['actual_consumption'] !== null ? number_format($fl['actual_consumption'], 4) : '—' }}</td>
                                <td class="num">{{ $fl['overconsumption'] !== null ? number_format($fl['overconsumption'] * 100, 2).'%' : '—' }}</td>
                                <td class="num">{{ $fl['deduction'] !== null ? 'Rp '.number_format($fl['deduction'], 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="muted" style="font-size:11px; margin-top:4px;">
                    Cutt Plan = ROUNDDOWN((Fabric Sent &minus; Retur Kain) &divide; Cons. Plan). Actual Cons. = (Fabric Sent &minus; Retur Kain) &divide; Total Qty Cut.
                    Overconsumption = (Actual Cons. &minus; Cons. Plan) &divide; Cons. Plan. Deduction = MAX(0, Actual Cons. &minus; Cons. Plan &times; 1.03) &times; Total Qty Cut &times; Fabric Price
                    &mdash; charged only when Overconsumption exceeds 3%.
                </p>
            @endif

            <p>
                <a href="{{ $url }}" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#1a1a2e;">Review &amp; Authorize</a>
                &nbsp;&nbsp;
                <a href="{{ $declineUrl }}" style="display:inline-block;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;color:#ffffff !important;background:#c92a2a;">Reject</a>
            </p>
            <p class="muted" style="font-size:13px;">Authorizing opens a read-only summary page for sign-off — no login required. Rejecting returns the inspection to Report Validation for MD Production to re-validate and send (not to the factory, and MD Production does not have to re-enter the consumption figures). Or paste this link into your browser:<br>
                <a href="{{ $url }}">{{ $url }}</a>
            </p>

            <p class="muted">This link is unique to this inspection. If you did not expect this email, you can ignore it.</p>
        </div>
    </div>
</body>
</html>
