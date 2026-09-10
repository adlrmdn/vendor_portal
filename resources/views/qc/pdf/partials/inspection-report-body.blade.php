{{-- Body of the QC inspection report (PrintReport.tsx port). Extracted from
     inspection-report.blade.php so deduction-report.blade.php can prepend a
     debit-note page ahead of the same report without duplicating markup. --}}
@php
    $n = fn ($v) => ($v !== null && $v !== '') ? number_format((float) ($v ?? 0), 0, '.', ',') : '—';
    $rp = fn ($v) => ($v !== null && $v !== '') ? 'Rp '.number_format(round((float) ($v ?? 0)), 0, '.', ',') : '—';
    $pct = fn ($num, $den) => $den > 0 ? number_format($num / $den * 100, 2, '.', ',').'%' : '0.00%';
    $fmtCons = fn ($v) => ($v !== null && $v !== '') ? number_format((float) $v, 4, '.', ',') : '—';
    $fmtPercent = fn ($v) => ($v !== null && $v !== '') ? number_format((float) ($v * 100), 2, '.', ',').'%' : '—';
    $fmtDate = function ($v) {
        if (empty($v)) return '—';
        try { return \Carbon\Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable $e) { return (string) $v; }
    };
    $shortenFabric = fn ($s) => (! $s || strlen((string) $s) <= 48) ? (string) $s : trim(substr((string) $s, 0, 25)).' ... '.trim(substr((string) $s, -20));
    $shortLabel = fn ($s) => (! $s || strlen($s) <= 42) ? $s : rtrim(substr($s, 0, 28)).' ... '.ltrim(substr($s, -13));
    // Signature-box email: no wrapping room — truncate like 'fitri.yeni@megape...'.
    $shortEmail = fn ($e) => (! $e || strlen($e) <= 24) ? $e : substr($e, 0, 21).'...';

    // Defect summary grouped by (type, description) — active-cycle images only.
    $defectGroups = [];
    $totMajor = 0; $totMinor = 0;
    foreach ($defectImages as $img) {
        $type = $img->defect_type ?: 'General';
        $desc = trim((string) ($img->description ?? '')) ?: 'No description';
        $key = $type.'_'.$desc;
        $defectGroups[$key] ??= ['type' => $type, 'desc' => $desc, 'major' => 0, 'minor' => 0];
        $defectGroups[$key]['major'] += (int) ($img->major ?? 0);
        $defectGroups[$key]['minor'] += (int) ($img->minor ?? 0);
        $totMajor += (int) ($img->major ?? 0);
        $totMinor += (int) ($img->minor ?? 0);
    }

    $fabricDeductionLines = $fabricLines->filter(fn ($f) => ($f->deduction ?? 0) > 0)->values();
    $manualTotal = $deductionLines->sum('amount');
    $fabricDeductionTotal = $fabricDeductionLines->sum('deduction');
    $penaltyTotal = $deductions['rejectProduksiPenalty'] + $deductions['barangHilangPenalty'];
    $grandTotal = $penaltyTotal + $manualTotal + $fabricDeductionTotal;
    $hasAnyDeduction = $deductions['exceedingRejectQty'] > 0 || $deductions['sumBarangHilang'] > 0
        || $deductionLines->isNotEmpty() || $fabricDeductionLines->isNotEmpty();

    $embeddableImages = $defectImages->filter(fn ($i) => str_starts_with((string) ($i->image_path ?? ''), 'data:image'))->values();
    $result = strtoupper((string) ($session->result ?? 'PENDING')) ?: 'PENDING';
    $resultColor = $result === 'PASSED' ? '#059669' : ($result === 'FAILED' ? '#DC2626' : '#D97706');
@endphp

{{-- Header --}}
<table style="border-bottom: 1.5px solid #0F172A; margin-bottom: 5px;">
    <tr>
        <td style="width: 45%; vertical-align: top; padding-top: 0; padding-bottom: 12px;">
            @if ($logoData)
                <img src="{{ $logoData }}" height="90" style="height: 90px; margin-top: -12px; margin-bottom: 8px; display: block;">
            @endif
        </td>
        <td style="width: 55%; text-align: right; vertical-align: bottom; padding-bottom: 3px;">
            <div style="font-size: 12.5pt; font-weight: bold; color: #0F172A;">{{ $cycleName }} Inspection</div>
            <div style="font-size: 7.5pt; color: #0F172A; font-weight: bold; margin: 1px 0;">and</div>
            <div style="font-size: 12.5pt; font-weight: bold; color: #0F172A;">Official Report (Berita Acara)</div>
            <div style="font-size: 6.2pt; font-weight: bold;" class="muted">QUALITY CONTROL SYSTEM</div>
        </td>
    </tr>
</table>

{{-- Section 1: Overview --}}
<div class="section-title">1. Inspection Overview</div>
<table class="tbl">
    <colgroup>
        <col style="width: 12%;">
        <col style="width: 9%;">
        <col style="width: 7%;">
        <col style="width: 14%;">
        <col style="width: 7%;">
        <col style="width: 17%;">
        <col style="width: 17%;">
        <col style="width: 17%;">
    </colgroup>
    <tr>
        <th style="width: 12%;">Vendor</th><td colspan="5">{{ $project->po_vendor ?: '—' }}</td>
        <th style="width: 17%;">PO Number</th><td>{{ $project->po_info ?: '—' }}</td>
    </tr>
    <tr>
        <th>Article Name</th><td colspan="5">{{ $project->article_name ?: '—' }}</td>
        <th>Qty Order</th><td>{{ $project->po_qty ? $n($project->po_qty) : '—' }}</td>
    </tr>
    <tr>
        <th style="white-space: nowrap;">Inspection Date</th><td style="white-space: nowrap;">{{ $fmtDate($session->inspection_date) }}</td>
        <th style="white-space: nowrap;">Available Qty</th><td style="white-space: nowrap;">{{ $session->qty_available ? $n($session->qty_available) : '—' }}</td>
        <th style="white-space: nowrap;">Total Store</th><td style="white-space: nowrap;">{{ $session->total_store ?: '—' }}</td>
        <th style="white-space: nowrap;">Store Inspected</th><td style="white-space: nowrap;">{{ $session->store_inspected ?: '—' }}</td>
    </tr>
    <tr>
        <th style="white-space: nowrap;">Delivery Plan</th><td style="white-space: nowrap;">{{ $fmtDate($project->po_plan_date) }}</td>
        <th style="white-space: nowrap;">Season</th><td style="white-space: nowrap;">{{ $project->season ?: '—' }}</td>
        <th style="white-space: nowrap;">PLM ID</th><td style="white-space: nowrap;">{{ $project->plm_id ?: '—' }}</td>
        <th style="white-space: nowrap;">Production Group</th><td style="white-space: nowrap;">{{ $project->production_group ?: '—' }}</td>
    </tr>
</table>

{{-- Section 2: QC Status --}}
<div class="section-title">2. Quality Control Status</div>
<table style="margin-bottom: 6px; width: 100%; border-collapse: collapse; border: none;">
    <tr>
        @if (count($checklist))
            <td style="width: 40%; vertical-align: top; padding-right: 8px; border: none;">
                <table style="width: 100%; border: 0.6px solid #E2E8F0; border-radius: 6px; background: #F8FAFC; border-collapse: separate; border-spacing: 0;">
                    <tr>
                        <td colspan="2" style="font-size: 6.2pt; font-weight: 700; color: #0F172A; text-transform: uppercase; border-bottom: 0.6px solid #E2E8F0; padding: 3.5px 7px; letter-spacing: 0.02em; border-top-left-radius: 6px; border-top-right-radius: 6px;">
                            Garment Checklist
                        </td>
                    </tr>
                    @foreach (array_chunk($checklist, 2) as $pair)
                        <tr>
                            @foreach ($pair as $item)
                                <td style="font-size: 5.1pt; padding: 2px 6px; color: {{ $item['checked'] ? '#0F172A' : '#64748B' }}; font-weight: {{ $item['checked'] ? 'bold' : 'normal' }}; border: none; vertical-align: middle;">
                                    @if ($item['checked'])
                                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==" style="width: 8px; height: 8px; margin-right: 4px; display: inline-block; vertical-align: middle; margin-top: -1.5px;">
                                    @else
                                        <span style="width: 6px; height: 6px; border: 0.8px solid #CBD5E1; border-radius: 1.5px; background: #FFFFFF; display: inline-block; vertical-align: middle; margin-right: 4.5px; margin-top: -1.5px;"></span>
                                    @endif
                                    {{ $item['label'] }}
                                </td>
                            @endforeach
                            @if (count($pair) === 1)
                                <td style="border: none;"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </td>
        @endif
        <td style="vertical-align: top; border: none;">
            <table style="width: 100%; border: 0.6px solid #E2E8F0; border-radius: 6px; background: #F8FAFC; border-collapse: separate; border-spacing: 0; padding: 3px 6px; font-size: 5.3pt; font-weight: 600; color: #0F172A; margin-bottom: 4px;">
                <tr>
                    <td style="border: none; padding: 0;">SAMPLING: <strong>{{ $session->sampling_pcs ?: 0 }}</strong></td>
                    <td style="border: none; padding: 0; text-align: center;">AQL: <strong>{{ $session->aql ?: '—' }}</strong></td>
                    <td style="border: none; padding: 0; text-align: right;">LEVEL: <strong>{{ $session->level_val ?: '—' }}</strong></td>
                </tr>
            </table>
            <table class="tbl">
                <tr><th style="width: 80%;">Defect Type &amp; Description</th><th class="center">Major</th><th class="center">Minor</th></tr>
                @forelse ($defectGroups as $d)
                    <tr>
                        <td>[{{ $d['type'] }}] {{ $d['desc'] }}</td>
                        <td class="center {{ $d['major'] > 0 ? 'bold' : '' }}">{{ $d['major'] }}</td>
                        <td class="center {{ $d['minor'] > 0 ? 'bold' : '' }}">{{ $d['minor'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="center muted" style="padding: 6px;">No defects logged in this session.</td></tr>
                @endforelse
                <tr style="background: #F8FAFC;" class="bold">
                    <td>TOTAL</td>
                    <td class="center" style="color: {{ $totMajor > 0 ? '#DC2626' : '#0F172A' }};">{{ $totMajor }}</td>
                    <td class="center" style="color: {{ $totMinor > 0 ? '#D97706' : '#0F172A' }};">{{ $totMinor }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Section 3: Yield Matrix --}}
<div class="section-title">3. Sizing &amp; Production Yield Matrix</div>
<table style="border: 0.6px solid #E2E8F0; background: #F8FAFC; margin-bottom: 3px;">
    <tr>
        <td style="font-size: 5.4pt; font-weight: bold; padding: 2.5px 5px;">CUTTING: {{ $n($session->cutting_pcs) }} PCS</td>
        <td style="font-size: 5.4pt; font-weight: bold; padding: 2.5px 5px;" class="center">SEWING: {{ $n($session->sewing_pcs) }} PCS</td>
        <td style="font-size: 5.4pt; font-weight: bold; padding: 2.5px 5px;" class="center">FINISHING: {{ $n($session->finishing_pcs) }} PCS</td>
        <td style="font-size: 5.4pt; font-weight: bold; padding: 2.5px 5px;" class="right">PACKING: {{ $n($session->packing_pcs) }} PCS</td>
    </tr>
</table>
<table class="tbl">
    <tr>
        <th rowspan="3">Size</th>
        <th rowspan="3">Order<br>Qty</th>
        <th rowspan="3">Cut<br>Plan</th>
        <th rowspan="3">Cut<br>Qty</th>
        <th rowspan="3">Good<br>Qty</th>
        <th colspan="10">Reject Category</th>
        <th rowspan="3">Total<br>Reject</th>
        <th rowspan="3">WIP</th>
        <th colspan="3">Partial Delivery</th>
        <th rowspan="3">Total<br>Delivery FG</th>
        <th rowspan="3">Goods Receive<br>Delivery</th>
    </tr>
    <tr>
        <th colspan="7">Produksi</th>
        <th rowspan="2">Bahan</th>
        <th rowspan="2">BTJ</th>
        <th rowspan="2">Barang<br>Hilang</th>
        <th rowspan="2">I</th><th rowspan="2">II</th><th rowspan="2">III</th>
    </tr>
    <tr>
        <th>Cut</th><th>Sew</th><th>Print</th><th>Embro</th><th>Wash</th><th>Finish</th><th style="background:#E2E8F0;">Total</th>
    </tr>
    @foreach ($rows as $r)
        <tr class="center">
            <td class="bold">{{ $r['size'] }}</td>
            <td>{{ $n($r['orderQty']) }}</td>
            <td>{{ $r['cutPlan'] !== null ? $n($r['cutPlan']) : '—' }}</td>
            <td>{{ $n($r['cuttingQty']) }}</td>
            <td>{{ $n($r['goodGarments']) }}</td>
            <td>{{ $r['rejectCutting'] }}</td>
            <td>{{ $r['rejectSewing'] }}</td>
            <td>{{ $r['rejectPrinting'] }}</td>
            <td>{{ $r['rejectEmbro'] }}</td>
            <td>{{ $r['rejectWashing'] }}</td>
            <td>{{ $r['rejectFinishing'] }}</td>
            <td class="bold" style="background: #F8FAFC;">{{ $n($r['rejectProduksi']) }}</td>
            <td>{{ $r['rejectBahan'] }}</td>
            <td>{{ $r['rejectBtj'] }}</td>
            <td>{{ $r['barangHilang'] }}</td>
            <td class="bold" style="color: {{ $r['totalReject'] > 0 ? '#DC2626' : '#0F172A' }};">{{ $n($r['totalReject']) }}</td>
            <td>{{ $n($r['wip']) }}</td>
            <td>{{ $r['qtyI'] > 0 ? $n($r['qtyI']) : '—' }}</td>
            <td>{{ $r['qtyII'] > 0 ? $n($r['qtyII']) : '—' }}</td>
            <td>{{ $r['qtyIII'] > 0 ? $n($r['qtyIII']) : '—' }}</td>
            <td class="bold" style="color: #059669;">{{ $n($r['totalDeliveryFG']) }}</td>
            <td class="bold" style="color: #2563EB;">{{ $n($r['goodsReceiveDelivery']) }}</td>
        </tr>
    @endforeach
    <tr class="center bold" style="background: #F8FAFC;">
        <td>TOTAL</td>
        <td>{{ $n($totals['orderQty']) }}</td>
        <td>{{ $totals['cutPlan'] !== null ? $n($totals['cutPlan']) : '—' }}</td>
        <td>{{ $n($totals['cuttingQty']) }}</td>
        <td>{{ $n($totals['goodGarments']) }}<br><span class="muted" style="font-size: 4.2pt;">({{ $pct($totals['goodGarments'], $totals['cuttingQty']) }})</span></td>
        <td>{{ $totals['rejectCutting'] }}</td>
        <td>{{ $totals['rejectSewing'] }}</td>
        <td>{{ $totals['rejectPrinting'] }}</td>
        <td>{{ $totals['rejectEmbro'] }}</td>
        <td>{{ $totals['rejectWashing'] }}</td>
        <td>{{ $totals['rejectFinishing'] }}</td>
        <td style="background: #E2E8F0;">{{ $n($totals['rejectProduksi']) }}<br><span class="muted" style="font-size: 4.2pt;">({{ $pct($totals['rejectProduksi'], $totals['cuttingQty']) }})</span></td>
        <td>{{ $totals['rejectBahan'] }}</td>
        <td>{{ $totals['rejectBtj'] }}</td>
        <td>{{ $totals['barangHilang'] }}</td>
        <td style="color: {{ $totals['totalReject'] > 0 ? '#DC2626' : '#0F172A' }};">{{ $n($totals['totalReject']) }}<br><span class="muted" style="font-size: 4.2pt;">({{ $pct($totals['totalReject'], $totals['cuttingQty']) }})</span></td>
        <td>{{ $n($totals['wip']) }}</td>
        <td>{{ $totals['qtyI'] > 0 ? $n($totals['qtyI']) : '—' }}</td>
        <td>{{ $totals['qtyII'] > 0 ? $n($totals['qtyII']) : '—' }}</td>
        <td>{{ $totals['qtyIII'] > 0 ? $n($totals['qtyIII']) : '—' }}</td>
        <td style="color: #059669;">{{ $n($totals['totalDeliveryFG']) }}<br><span style="font-size: 4.2pt; color: #059669;">({{ $pct($totals['totalDeliveryFG'], $totals['cuttingQty']) }})</span></td>
        <td style="color: #2563EB;">{{ $n($totals['goodsReceiveDelivery']) }}</td>
    </tr>
</table>

{{-- Fabric summary lines --}}
@if ($fabricLines->isNotEmpty())
    <table style="margin-top: 4px;">
        @foreach ($fabricLines as $f)
            @php
                // Display unit: length fabric (M/YD) is always entered/converted to
                // YARDS regardless of the PO's raw unit; KG (weight) fabric keeps its
                // own unit. See SubconProductionService::displayUnit().
                $unitRaw = \App\Services\SubconProductionService::displayUnit((string) $f->label);
                $unit = $unitRaw ? ' '.$unitRaw : '';
                $unitPerPc = $unitRaw ? ' '.$unitRaw.'/pc' : '';
            @endphp
            @php
                $deliveryPct = $f->delivery_pct ?? null;
                $inventoryGroup = trim((string) ($f->inventory_group ?? ''));
            @endphp
            <tr>
                <td style="font-size: 4.9pt; color: #334155; border-top: {{ $loop->first ? '0.6px solid #E2E8F0' : 'none' }}; padding: 2px 1px; line-height: 1.5;">
                    <div>
                        @if ($f->label)<span style="font-weight: 700; color: #0F172A; margin-right: 4px;">{{ $shortenFabric($f->label) }}</span>@endif
                        @if ($inventoryGroup !== '')<span class="muted bold" style="text-transform: uppercase;">{{ $inventoryGroup }}</span>@endif
                    </div>
                    <div style="margin-top: 1px;">
                        @if ($deliveryPct !== null)
                            <span class="muted bold">{{ $deliveryPct >= 0 ? 'Overdelivery' : 'Underdelivery' }}:</span>
                            <span style="color: {{ abs($deliveryPct) > 3 ? '#DC2626' : '#0F172A' }}; font-weight: {{ abs($deliveryPct) > 3 ? '700' : '500' }};">{{ number_format(abs($deliveryPct), 2) }}%</span> &nbsp;
                        @endif
                        <span class="muted bold">Goods Receive:</span> <span style="color: #0F172A;">{{ isset($f->goods_receive) && $f->goods_receive !== null ? $n($f->goods_receive).$unit : '—' }}</span> &nbsp;
                        <span class="muted bold">Fabric Sent:</span> <span style="color: #0F172A;">{{ $f->fabric_sent !== null ? $n($f->fabric_sent).$unit : '0' }}</span> &nbsp;
                        <span class="muted bold">Short Roll:</span> <span style="color: #0F172A;">{{ $f->short_roll !== null ? $n($f->short_roll).$unit : '0' }}</span> &nbsp;
                        <span class="muted bold">Sisa Kain (utuh):</span> <span style="color: #0F172A;">{{ $f->sisa_kain !== null ? $n($f->sisa_kain).$unit : '0' }}</span> &nbsp;
                        <span class="muted bold">Kepala Kain:</span> <span style="color: #0F172A;">{{ $f->kepala_kain !== null ? $n($f->kepala_kain).$unit : '0' }}</span> &nbsp;
                        <span class="muted bold">Retur Kain:</span> <span style="color: #0F172A;">{{ $f->return_kain !== null ? $n($f->return_kain).$unit : '0' }}</span>
                    </div>
                    <div style="margin-top: 1px; padding-left: 8px;">
                        <span style="display: inline-block; width: 3px; height: 5px; border-left: 0.6px solid #94A3B8; border-bottom: 0.6px solid #94A3B8; margin-right: 3.5px; margin-left: 2px; vertical-align: middle; margin-top: -3.5px;"></span>
                        <span class="muted bold">Cutt Plan:</span> <span style="color: #0F172A;">{{ $f->cutt_plan !== null ? $n($f->cutt_plan).' Pcs' : '—' }}</span> &nbsp;
                        <span class="muted bold">Plan Cons:</span> <span style="color: #0F172A;">{{ $f->consumption_plan !== null ? $fmtCons($f->consumption_plan).$unitPerPc : '—' }}</span> &nbsp;
                        <span class="muted bold">Actual Cons:</span> <span style="color: #0F172A;">{{ $f->actual_consumption !== null ? $fmtCons($f->actual_consumption).$unitPerPc : '—' }}</span> &nbsp;
                        <span class="muted bold">Budget Cap (3%):</span> <span style="color: #0F172A;">{{ $f->consumption_plan !== null ? $fmtCons($f->consumption_plan * 1.03).$unitPerPc : '—' }}</span> &nbsp;
                        <span class="muted bold">Overconsumption:</span> <span style="color: {{ $f->overconsumption !== null && $f->overconsumption > 0.03 ? '#DC2626' : '#0F172A' }}; font-weight: {{ $f->overconsumption !== null && $f->overconsumption > 0.03 ? '700' : '500' }};">{{ $f->overconsumption !== null ? (number_format($f->overconsumption * 100, 2) . '%') : '—' }}</span>
                    </div>
                </td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Section 4: Deductions & Remarks --}}
<div class="section-title">4. Deductions &amp; Remarks</div>
<table class="tbl">
    <tr>
        <th style="width: 35%;">Description</th>
        <th style="width: 15%;" class="center">Price/Pcs</th>
        <th style="width: 27%;">Reason</th>
        <th style="width: 8%;" class="center">Qty</th>
        <th style="width: 15%;" class="center">Amount</th>
    </tr>
    @if (! $hasAnyDeduction)
        <tr><td colspan="5" class="center muted" style="padding: 6px;">No deductions &amp; remarks logged for this session.</td></tr>
    @else
        @if ($deductions['exceedingRejectQty'] > 0)
            <tr>
                <td>Production Reject Penalty (Exceeding 1% Limit)</td>
                <td class="center">{{ $rp($deductions['penaltyPrice']) }}</td>
                <td>Total reject {{ $deductions['sumRejectProduksi'] }} exceeds 1% of cutting qty ({{ $deductions['sumCuttingQty'] }}) by {{ $deductions['exceedingRejectQty'] }} pcs</td>
                <td class="center">{{ $deductions['exceedingRejectQty'] }}</td>
                <td class="center">{{ $rp($deductions['rejectProduksiPenalty']) }}</td>
            </tr>
        @endif
        @if ($deductions['sumBarangHilang'] > 0)
            <tr>
                <td>Lost Items Penalty (Barang Hilang)</td>
                <td class="center">{{ $rp($deductions['penaltyPrice']) }}</td>
                <td>Lost {{ $deductions['sumBarangHilang'] }} pcs during production</td>
                <td class="center">{{ $deductions['sumBarangHilang'] }}</td>
                <td class="center">{{ $rp($deductions['barangHilangPenalty']) }}</td>
            </tr>
        @endif
        @foreach ($fabricDeductionLines as $f)
            @php
                preg_match('/\(([A-Z]+)\)\s*$/', (string) $f->label, $unitMatch);
                $unit = strtolower($unitMatch[1] ?? 'unit');
                $formattedLabel = $shortenFabric($f->label);
            @endphp
            <tr>
                <td>Fabric Overconsumption{{ $f->label ? ' — '.$formattedLabel : '' }}</td>
                <td class="center">{{ $f->fabric_price !== null ? $rp($f->fabric_price).' /'.$unit : '—' }}</td>
                <td>{{ $f->overconsumption !== null ? number_format($f->overconsumption * 100, 2).'% overconsumption' : '—' }}</td>
                <td class="center">—</td>
                <td class="center">{{ $rp($f->deduction) }}</td>
            </tr>
        @endforeach
        @foreach ($deductionLines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="center">—</td>
                <td>{{ $line->created_by ? 'Added by '.$line->created_by : '—' }}</td>
                <td class="center">—</td>
                <td class="center">{{ $rp($line->amount) }}</td>
            </tr>
        @endforeach
        <tr class="bold" style="background: #F8FAFC;">
            <td colspan="4" class="right" style="padding-right: 10px;">Total Deductions</td>
            <td class="center">{{ $rp($grandTotal) }}</td>
        </tr>
    @endif
</table>
@php
    $remarksList = collect([
        'Subcon' => trim((string) $remarks),
        'QC' => trim((string) $session->remarks),
        'MD Prod' => trim((string) ($session->ho_remarks ?? '')),
    ])->map(fn ($r) => strtolower($r) === 'none' ? '' : $r)->filter();
@endphp
<div style="font-size: 5.3pt; margin: 4px 0 6px;">
    <div class="bold" style="margin-bottom: 1px;">Remarks</div>
    @if ($remarksList->isNotEmpty())
        <table style="width: 100%; border-collapse: collapse; border: none;">
            @foreach ($remarksList as $label => $r)
                <tr>
                    <td style="border: none; padding: 0 3px 0 0; width: 8px; vertical-align: top; line-height: 1.25;">-</td>
                    <td style="border: none; padding: 0 4px 0 0; width: 42px; vertical-align: top; line-height: 1.25;">({{ $label }})</td>
                    <td style="border: none; padding: 0; vertical-align: top; line-height: 1.25;">{{ $r }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <span style="color: #94A3B8; font-style: italic;">none</span>
    @endif
</div>

{{-- Section 5: Conclusions --}}
<div class="section-title">5. Conclusions</div>
<table style="width: 100%; border-collapse: collapse; border: none; margin-top: 3px;">
    <tr style="height: 68px;">
        <td style="width: 20%; vertical-align: top; border: none; padding: 0 5px 0 0; height: 68px;">
            <table style="width: 100%; border: 0.6px solid #CBD5E1; border-radius: 6px; background-color: #F8FAFC; height: 68px; border-collapse: separate; border-spacing: 0; margin: 0; padding: 3px; box-sizing: border-box;">
                <tr style="height: 12px;">
                    <td style="vertical-align: top; border: none; padding: 1px 0 0 0; text-align: center; height: 12px;">
                        <div style="font-size: 4.5pt; font-weight: bold; color: #0F172A; text-transform: uppercase; line-height: 1.1;">
                            <span style="font-family: 'DejaVu Sans', sans-serif; font-size: 5.5pt; font-weight: bold; margin-right: 2px;">&#8756;</span>Overall Inspection Result
                        </div>
                    </td>
                </tr>
                <tr style="height: 50px;">
                    <td style="vertical-align: middle; border: none; padding: 0; text-align: center; height: 50px;">
                        <div style="font-size: 11pt; font-weight: bold; color: {{ $resultColor }}; line-height: 1;">
                            {{ $result }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        @foreach ([
            ['label' => 'Inspected By', 'sig' => $signatures['inspector'], 'role' => 'Inspector', 'name' => $signatures['inspector']['name']],
            ['label' => 'Confirmed By', 'sig' => $signatures['factory'], 'role' => 'Factory Representative', 'name' => $signatures['factory']['name']],
            ['label' => 'Approved By', 'sig' => $signatures['ho'], 'role' => 'MPG HO - MD Production', 'name' => $signatures['ho']['name']],
            ['label' => 'Authorized By', 'sig' => $signatures['director'], 'role' => 'Director', 'name' => $signatures['director']['name']],
        ] as $box)
            <td style="width: 20%; vertical-align: top; border: none; padding: {{ $loop->last ? '0' : '0 5px 0 0' }}; height: 68px;">
                <table style="width: 100%; border: 0.6px solid #CBD5E1; border-radius: 6px; background-color: #F8FAFC; height: 68px; border-collapse: separate; border-spacing: 0; margin: 0; padding: 3px; box-sizing: border-box;">
                    <tr style="height: 44px;">
                        <td style="vertical-align: top; border: none; padding: 1px 0 0 0; text-align: center; height: 44px;">
                            <div style="font-size: 4.8pt; text-transform: uppercase; margin-bottom: 2px; line-height: 1.1;" class="muted bold">{{ $box['label'] }}</div>
                            @if ($box['sig']['state'] === 'signed')
                                <div class="sig-badge" style="display: inline-flex; align-items: center; vertical-align: middle; line-height: 1;"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAXklEQVR4nGP8//8/AymAiSTVxGoQ3NUEdwZBDTDFMJpoJ713q2MkqAHZKVidhKwAmQ0znYGBgYERFqzYTENXjGIDugQuMSZCCjDA////MbDAzsb/2MT///+P8AOxAAA4NkZ/eAqFBQAAAABJRU5ErkJggg==" style="width: 7px; height: 7px; margin-right: 2px; vertical-align: middle; margin-top: -1px; display: inline-block;"> Digitally Signed</div>
                                @php
                                    $stampText = !empty($box['sig']['email']) ? $shortEmail($box['sig']['email']) : '';
                                    if (empty($stampText) && $box['label'] !== 'Inspected By') {
                                        $stampText = $box['sig']['name'];
                                    }
                                @endphp
                                @if (!empty($stampText))<div class="muted" style="font-size: 4.2pt; line-height: 1; margin-top: 1px;">{{ $stampText }}</div>@endif
                                @if ($box['sig']['date'])<div class="muted" style="font-size: 4.2pt; line-height: 1; margin-top: 1px;">{{ $box['sig']['date'] }}</div>@endif
                            @elseif ($box['sig']['state'] === 'rejected')
                                <div class="sig-badge rejected" style="display: inline-flex; align-items: center; vertical-align: middle; line-height: 1;"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAY0lEQVR4nJWQ0Q0AIQhDxQluKJnaoWSDd18mnKDxSPigtKRFgPKn6i3RVAmCCe7IpkrNwN2Rp3epfjiJ517W0JktfyyE9stsDoJThiDIPK94+JInZ6JPaFNl9RxsAVc9WgMoL8rWaChiYc43AAAAAElFTkSuQmCC" style="width: 7px; height: 7px; margin-right: 2px; vertical-align: middle; margin-top: -1px; display: inline-block;"> Rejected</div>
                                @php
                                    $stampText = !empty($box['sig']['email']) ? $shortEmail($box['sig']['email']) : '';
                                    if (empty($stampText) && $box['label'] !== 'Inspected By') {
                                        $stampText = $box['sig']['name'];
                                    }
                                @endphp
                                @if (!empty($stampText))<div class="muted" style="font-size: 4.2pt; line-height: 1; margin-top: 1px;">{{ $stampText }}</div>@endif
                                @if ($box['sig']['date'])<div class="muted" style="font-size: 4.2pt; line-height: 1; margin-top: 1px;">{{ $box['sig']['date'] }}</div>@endif
                            @else
                                <div class="sig-badge pending" style="margin-top: 4px; line-height: 1;">{{ $box['label'] === 'Authorized By' ? 'Awaiting Authorization' : 'Awaiting Approval' }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr style="height: 17px;">
                        <td style="vertical-align: bottom; border: none; border-top: 0.6px dashed #CBD5E1; padding: 1px 0 0 0; text-align: center; height: 17px;">
                            <div>
                                @if ($box['name'])
                                    <div style="font-size: 5.3pt; font-weight: bold; line-height: 1.1; margin-bottom: 0px;">{{ $box['name'] }}</div>
                                @endif
                                <div class="muted" style="font-size: 4.4pt; text-transform: uppercase; line-height: 1;">{{ $box['role'] }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>

<table style="margin-top: 6px; border-top: 0.6px solid #CBD5E1; padding-top: 4px; width: 100%;">
    <tr>
        <td style="font-size: 5.6pt; line-height: 1.35;" class="muted">
            Distribution by email: 1. Factory&nbsp; 2. MD Prod&nbsp; 3. PPIC/Finance&nbsp; 4. QA MP
            <br>
            *MPG HO – MD Production approval makes this document eligible for shipping.
        </td>
        <td style="font-size: 5.6pt; text-align: right; vertical-align: top;" class="muted">&raquo; This is an auto-generated document. Final Inspection QC [{{ $generatedAt }} WIB]</td>
    </tr>
</table>

{{-- Section 6: Granular Breakdown — full Material Recon + Consumption detail
     behind the compact fabric-summary lines above. Both Fabric and Accessory
     follow the same left-to-right narrative: what came IN (Goods Receive) →
     what went OUT to the vendor (Fabric Sent / Mats Sent) → what was actually
     consumed (real D365-posted figures, cross-checked against the admin
     figures that drive billing) → what came back (declared Retur vs. an
     independently-computed Expected Retur). --}}
@php
    $totalCuttingQtyForBreakdown = (float) ($totals['cuttingQty'] ?? 0);
    // Quantities in this section (Goods Receive, Mats Sent, Consumption, Retur
    // Qty, Fabric Sent, waste columns...) come straight off D365/VSM source
    // data and are frequently fractional (e.g. a thread consumption of
    // 230.44 YD) — $n() above rounds to 0 decimals and silently hides that.
    // Fixed 2 decimals, dot as the decimal point (same convention as $n/$rp),
    // matches how these quantities actually appear in the source systems.
    $n2 = fn ($v) => ($v !== null && $v !== '') ? number_format((float) $v, 2, '.', ',') : '—';
    // Same, but trims a trailing ".00" — a whole-number quantity (most PCS
    // counts) doesn't need decimal noise, while a genuinely fractional one
    // (a YD/CM/KG remainder, or an odd PCS journal artifact — both real,
    // seen in this data) stays fully visible. Only for QUANTITIES; rate
    // cells (.../pc) keep $n2's fixed 2 decimals so they read as rates, not
    // counts.
    $nSmart = fn ($v) => ($v !== null && $v !== '') ? preg_replace('/\.00$/', '', number_format((float) $v, 2, '.', ',')) : '—';
@endphp
<div style="page-break-before: always;"></div>
<div class="section-title" style="margin-top: 0; margin-bottom: 6px;">6. Granular Breakdown</div>

<div style="font-size: 5.6pt; font-weight: bold; color: #0F172A; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 3px;">Fabric — Reconciliation &amp; Consumption</div>
@if ($fabricLines->isEmpty())
    <div class="muted" style="font-size: 5.6pt; margin-bottom: 8px;">No fabric reconciliation data recorded for this order.</div>
@else
    @foreach ($fabricLines as $f)
        @php
            $unitRawB = \App\Services\SubconProductionService::displayUnit((string) $f->label);
            $unitB = $unitRawB ? ' '.$unitRawB : '';
            $unitPerPcB = $unitRawB ? ' '.$unitRawB.'/pc' : '';
            $netUsable = ($f->fabric_sent !== null) ? ((float) $f->fabric_sent - (float) ($f->return_kain ?? 0)) : null;
            $diffCons = ($f->actual_consumption !== null && $f->consumption_plan !== null)
                ? ((float) $f->actual_consumption - (float) $f->consumption_plan) : null;
            $budgetCap = $f->consumption_plan !== null ? (float) $f->consumption_plan * 1.03 : null;
            $hasConsData = $f->fabric_sent !== null && $f->consumption_plan !== null && $totalCuttingQtyForBreakdown > 0;
            $issueDiff = ($f->issue_consumption !== null && $netUsable !== null) ? $f->issue_consumption - $netUsable : null;
            $issueDiffNotable = $issueDiff !== null && abs($issueDiff) > 0.5;
            $sentIsFromIssue = $f->fabric_sent !== null && (float) $f->fabric_sent === 0.0 && $f->issue_proposal !== null && $f->issue_proposal > 0;
            $sentDiff = ($f->issue_proposal !== null && $f->fabric_sent !== null) ? $f->issue_proposal - (float) $f->fabric_sent : null;
            $sentDiffNotable = $sentDiff !== null && abs($sentDiff) > 0.5;
            // Total waste/retur qty (Step 4) — the four columns, one sum.
            $returTotal = (float) ($f->short_roll ?? 0) + (float) ($f->sisa_kain ?? 0)
                + (float) ($f->kepala_kain ?? 0) + (float) ($f->return_kain ?? 0);
            // Overconsumption expressed as actual fabric QUANTITY, not just a
            // rate/percentage — two versions: vs. the plan (informational —
            // can be negative, meaning under-consumption) and vs. the budget
            // cap (the BILLABLE excess, i.e. exactly the qty the Deduction
            // formula charges for — always ≥ 0, since under the 3% tolerance
            // nothing is charged).
            $overQtyVsPlan = ($diffCons !== null) ? $diffCons * $totalCuttingQtyForBreakdown : null;
            $overQtyBillable = ($f->actual_consumption !== null && $budgetCap !== null)
                ? max(0, (float) $f->actual_consumption - $budgetCap) * $totalCuttingQtyForBreakdown
                : null;
        @endphp
        <table style="width: 100%; border: 0.6px solid #E2E8F0; border-radius: 6px; background-color: #FFFFFF; border-collapse: separate; border-spacing: 0; margin-bottom: 6px; page-break-inside: avoid;">
            <tr>
                <td style="border: none; border-bottom: 0.6px solid #F1F5F9; padding: 4px 6px; font-size: 5.8pt; font-weight: bold; color: #0F172A;">
                    {{ $shortenFabric($f->label) }}
                    @if (! empty($f->item_number))<span class="muted" style="font-size: 4.8pt; font-weight: 600; margin-left: 5px;">Item {{ $f->item_number }}</span>@endif
                    @if (! empty($f->inventory_group))<span class="muted" style="font-size: 4.8pt; text-transform: uppercase; font-weight: 600; margin-left: 5px;">{{ $f->inventory_group }}</span>@endif
                </td>
            </tr>
            <tr>
                <td style="border: none; padding: 4px 7px 5px;">

                    {{-- GIVEN — the raw inputs, stated once before any derivation touches them. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px;">Given</div>
                    <div style="font-size: 5.2pt; line-height: 1.65; margin-bottom: 5px; padding-left: 4px;">
                        Order Qty (PO) = {{ $f->ordered_qty !== null ? $nSmart($f->ordered_qty).$unitB : '—' }}<br>
                        Goods Receive = {{ isset($f->goods_receive) && $f->goods_receive !== null ? $nSmart($f->goods_receive).$unitB : '—' }}@if (! empty($f->goods_receive_date))<span class="muted" style="font-size: 4.6pt;"> (as of {{ \Carbon\Carbon::parse($f->goods_receive_date)->format('d M Y') }})</span>@endif<br>
                        Fabric Sent (to vendor) = {{ $f->fabric_sent !== null ? $nSmart($f->fabric_sent).$unitB : '—' }}
                        @if ($sentIsFromIssue)
                            <span style="color: #D97706; font-style: italic;"> (not yet entered — Material Issue suggests {{ $nSmart($f->issue_proposal).$unitB }})</span>
                        @elseif ($f->issue_proposal !== null)
                            <span class="muted" style="font-style: italic;"> (Material Issue: {{ $nSmart($f->issue_proposal).$unitB }}{{ $sentDiffNotable ? ', Δ '.($sentDiff > 0 ? '+' : '').$nSmart($sentDiff) : '' }})</span>
                        @endif
                        <br>
                        Waste — Short Roll = {{ $nSmart($f->short_roll ?? 0) }}{{ $unitB }}, Sisa Kain = {{ $nSmart($f->sisa_kain ?? 0) }}{{ $unitB }}, Kepala Kain = {{ $nSmart($f->kepala_kain ?? 0) }}{{ $unitB }}, Retur Kain = {{ $nSmart($f->return_kain ?? 0) }}{{ $unitB }}
                        <span class="muted" style="font-size: 4.6pt;">(total waste {{ $nSmart($returTotal) }}{{ $unitB }}; only Retur Kain feeds Step 1 below)</span><br>
                        Actual Cutting Qty = {{ $n($totalCuttingQtyForBreakdown) }} pcs <span class="muted" style="font-size: 4.6pt;">(Cutt Plan: {{ $f->cutt_plan !== null ? $n($f->cutt_plan).' pcs' : '—' }})</span><br>
                        Consumption Plan (admin-entered at cutting approval — unlike Accessory's Cons. Plan, NOT sourced from BOM Final/D365) = {{ $f->consumption_plan !== null ? $fmtCons($f->consumption_plan).$unitPerPcB : '—' }}<br>
                        Fabric Price = {{ $f->fabric_price !== null ? $rp($f->fabric_price).'/'.trim($unitB) : '—' }}
                    </div>

                    {{-- STEP 1 — Net for Consumption. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 1 — Net for Consumption</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; margin-bottom: 4px; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Net = Fabric Sent &minus; Retur Kain</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $nSmart($f->fabric_sent) }} &minus; {{ $nSmart($f->return_kain ?? 0) }}<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold">{{ $netUsable !== null ? $nSmart($netUsable).$unitB : '—' }}</span>
                    </div>

                    {{-- STEP 2 — Actual Consumption. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 2 — Actual Consumption</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; margin-bottom: 4px; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Actual Cons. = Net &divide; Cutting Qty</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $nSmart($netUsable) }} &divide; {{ $nSmart($totalCuttingQtyForBreakdown) }}<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold">{{ $f->actual_consumption !== null ? $fmtCons($f->actual_consumption).$unitPerPcB : '—' }}</span>
                        <div class="muted" style="font-size: 4.6pt; margin-top: 1px;">Cross-check — Material Issue (real posted, D365): {{ $f->issue_consumption !== null ? $nSmart($f->issue_consumption).$unitB : '—' }}
                            @if ($issueDiffNotable)<span style="color: #DC2626;"> (&Delta; {{ $issueDiff > 0 ? '+' : '' }}{{ $nSmart($issueDiff) }})</span>@endif
                            @if ($f->issue_consumption !== null && ! $f->issue_all_posted)<span style="font-style: italic;"> (unposted lines included)</span>@endif
                        </div>
                    </div>

                    {{-- STEP 3 — Diff & Overconsumption %. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 3 — Diff &amp; Overconsumption</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; margin-bottom: 4px; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Diff = Actual Cons. &minus; Plan</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $fmtCons($f->actual_consumption) }} &minus; {{ $fmtCons($f->consumption_plan) }}<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold" style="{{ $diffCons !== null && $diffCons > 0 ? 'color: #DC2626;' : '' }}">{{ $diffCons !== null ? ($diffCons > 0 ? '+' : '').$fmtCons($diffCons).$unitPerPcB : '—' }}</span><br>
                        <span class="muted" style="font-style: italic;">Overconsumption = Diff &divide; Plan</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $fmtCons($diffCons) }} &divide; {{ $fmtCons($f->consumption_plan) }}
                        = <span class="bold" style="{{ $f->overconsumption !== null && $f->overconsumption > 0.03 ? 'color: #DC2626;' : '' }}">{{ $f->overconsumption !== null ? number_format($f->overconsumption * 100, 2).'%' : '—' }}</span>
                    </div>

                    {{-- STEP 4 — Budget Cap. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 4 — Budget Cap (3% tolerance)</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; margin-bottom: 4px; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Budget Cap = Plan &times; 1.03</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $fmtCons($f->consumption_plan) }} &times; 1.03<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold">{{ $budgetCap !== null ? $fmtCons($budgetCap).$unitPerPcB : '—' }}</span>
                    </div>

                    {{-- STEP 5 — Overconsumption qty, in actual fabric, not just a rate. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 5 — Overconsumption, as Fabric Quantity</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; margin-bottom: 4px; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Overconsumption Qty = max(0, Actual Cons. &minus; Budget Cap) &times; Cutting Qty</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= max(0, {{ $fmtCons($f->actual_consumption) }} &minus; {{ $fmtCons($budgetCap) }}) &times; {{ $nSmart($totalCuttingQtyForBreakdown) }}<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold" style="{{ ($overQtyBillable ?? 0) > 0 ? 'color: #DC2626;' : '' }}">{{ $overQtyBillable !== null ? $nSmart($overQtyBillable).$unitB : '—' }}</span>
                        <span class="muted" style="font-size: 4.6pt;"> — this is how much fabric is actually being charged for</span>
                    </div>

                    {{-- STEP 6 — Deduction, the final result. --}}
                    <div style="font-size: 4.9pt; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px;">Step 6 — Deduction</div>
                    <div style="font-size: 5.2pt; line-height: 1.6; padding-left: 6px;">
                        <span class="muted" style="font-style: italic;">Deduction = Overconsumption Qty &times; Fabric Price</span><br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= {{ $overQtyBillable !== null ? $nSmart($overQtyBillable) : '—' }}{{ $unitB }} &times; {{ $rp($f->fabric_price ?? 0) }}<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;= <span class="bold" style="font-size: 6pt; {{ ($f->deduction ?? 0) > 0 ? 'color: #DC2626;' : '' }}">{{ $rp($f->deduction ?? 0) }}</span>
                        <span class="muted" style="font-size: 4.6pt;"> (only charged when overconsumption &gt; 3% — otherwise Step 5 is already 0)</span>
                    </div>

                </td>
            </tr>
        </table>
    @endforeach
@endif

<div style="font-size: 5.6pt; font-weight: bold; color: #0F172A; text-transform: uppercase; letter-spacing: 0.02em; margin: 6px 0 1px;">Accessory — Reconciliation &amp; Consumption</div>
@if (empty($accessoryBreakdown))
    <div class="muted" style="font-size: 5.6pt;">No accessory data found (PO lines or BOM Final) for this order.</div>
@else
    <div class="muted" style="font-size: 4.6pt; font-style: italic; margin-bottom: 3px;">
        "BOM only" = in BOM Final but no PO line. Cons. Plan / Actual Cons. are both per-pc rates (BOM design vs. real D365 usage). Expected Retur = Mats Sent &minus; Estimated Consumption (Plan &times; Cutting Qty).
    </div>
    <table class="tbl">
        <tr>
            <th style="width: 21%;">Item</th>
            <th style="width: 9.5%;" class="center">Goods Receive</th>
            <th style="width: 9.5%;" class="center">Mats Sent</th>
            <th style="width: 10.5%;" class="center">Cons. Plan (BOM/pc)</th>
            <th style="width: 10.5%;" class="center">Actual Cons. (pc)</th>
            <th style="width: 9%;" class="center">Retur Qty</th>
            <th style="width: 9.5%;" class="center">Expected Retur</th>
            <th style="width: 9.5%;" class="center">Price</th>
            <th style="width: 11%;" class="center">Value (ref)</th>
        </tr>
        @foreach ($accessoryBreakdown as $a)
            <tr>
                <td>
                    {{ $a['display_label'] }}
                    @if (! empty($a['item_number']))<span class="muted" style="font-size: 4.6pt; font-weight: 600; margin-left: 4px;">Item {{ $a['item_number'] }}</span>@endif
                    @if ($a['from_bom_only'])<span class="bold" style="color: #DC2626; font-size: 4.4pt; text-transform: uppercase; margin-left: 4px;">BOM only</span>@endif
                </td>
                <td class="center">{{ $a['goods_receive'] !== null ? $nSmart($a['goods_receive']).' '.$a['unit'] : '—' }}</td>
                <td class="center">
                    {{ $a['mats_sent'] !== null ? $nSmart($a['mats_sent']).' '.$a['unit'] : '—' }}
                    @if ($a['mats_sent_source'] === 'd365')<span class="muted" style="font-style: italic;">(Material Issue)</span>@endif
                </td>
                <td class="center">
                    {{ $a['cons_plan_bom'] !== null ? $n2($a['cons_plan_bom']).'/pc' : '—' }}
                    @if ($a['cons_plan_bom'] !== null && ! $a['cons_plan_consistent'])<span class="muted" style="font-style: italic;">(avg)</span>@endif
                    @if ($a['est_consumption'] !== null)
                        <div class="muted" style="font-size: 4.4pt;">Est. {{ $nSmart($a['est_consumption']) }} {{ $a['unit'] }}</div>
                    @endif
                </td>
                <td class="center">
                    {{ $a['actual_cons'] !== null ? $n2($a['actual_cons']).'/pc' : '—' }}
                    @if ($a['consumption'] !== null)
                        <div class="muted" style="font-size: 4.4pt;">
                            {{ $nSmart($a['consumption']) }} {{ $a['unit'] }}
                            @if (! $a['consumption_all_posted'])(unposted)@endif
                        </div>
                    @endif
                </td>
                <td class="center">{{ $a['qty'] !== null ? $nSmart($a['qty']).' '.$a['unit'] : '—' }}</td>
                <td class="center bold">{{ $a['expected_retur'] !== null ? $nSmart($a['expected_retur']).' '.$a['unit'] : '—' }}</td>
                <td class="center">{{ $a['price'] !== null ? $rp($a['price']) : '—' }}</td>
                <td class="center muted" style="font-style: italic;">{{ $a['value'] !== null ? $rp($a['value']) : '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Page 2: defect photos --}}
@if ($embeddableImages->isNotEmpty())
    <div style="page-break-before: always;"></div>
    <div class="section-title" style="margin-top: 0; margin-bottom: 10px;">7. Defect Photos Attachments</div>
    <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 6px;">
        @foreach ($embeddableImages->chunk(3) as $chunk)
            <tr>
                @foreach ($chunk as $img)
                    <td style="width: 33.33%; vertical-align: top; border: none; padding: 5px;">
                        <table style="width: 100%; border: 0.6px solid #E2E8F0; border-radius: 6px; background-color: #FFFFFF; border-collapse: separate; border-spacing: 0; padding: 6px; text-align: left;">
                            <tr>
                                <td style="border: none; padding: 0;">
                                    <div style="width: 100%; height: 95px; border-radius: 4px; border: 0.6px solid #F1F5F9; background-color: #F8FAFC; text-align: center; line-height: 95px; vertical-align: middle; overflow: hidden;">
                                        <img src="{{ $img->image_path }}" style="max-width: 100%; max-height: 95px; border: none; display: inline-block; vertical-align: middle;">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 0; padding-top: 4px;">
                                    <div style="font-size: 5.8pt; font-weight: bold; color: #0F172A; text-transform: uppercase; letter-spacing: 0.02em; margin-top: 2px;">
                                        Defect #{{ $loop->parent->index * 3 + $loop->iteration }} - {{ $img->defect_type ?: 'General' }}
                                    </div>
                                    <div style="font-size: 5.5pt; color: #475569; line-height: 1.3; height: 2.6em; overflow: hidden; margin-top: 2px;" title="{{ trim((string) ($img->description ?? '')) }}">
                                        {{ trim((string) ($img->description ?? '')) ?: 'No description provided.' }}
                                    </div>
                                    <div style="font-size: 5.5pt; font-weight: 600; border-top: 0.6px solid #F1F5F9; padding-top: 4px; margin-top: 4px; color: #0F172A;">
                                        <span style="color: #DC2626;">MAJ: {{ $img->major ?? 0 }}</span> &nbsp;&nbsp;
                                        <span style="color: #D97706;">MIN: {{ $img->minor ?? 0 }}</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                @for ($i = count($chunk); $i < 3; $i++)
                    <td style="width: 33.33%; border: none;"></td>
                @endfor
            </tr>
        @endforeach
    </table>
@endif

{{-- Material Flow return attachments — one page per delivery-note file. --}}
@if ($attachments->isNotEmpty())
    @foreach ($attachments as $att)
        <div style="page-break-before: always;"></div>
        <div class="section-title" style="margin-top: 0; margin-bottom: 10px;">
            8. Attachment{{ $attachments->count() > 1 ? ' ('.$loop->iteration.' of '.$attachments->count().')' : '' }}
        </div>
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr>
                <td style="border: none; padding: 0; text-align: center;">
                    @if ($att['is_image'] && $att['image_data'])
                        <img src="{{ $att['image_data'] }}" style="max-width: 100%; max-height: 640px; border: 0.6px solid #E2E8F0; border-radius: 4px;">
                    @else
                        <div style="width: 100%; padding: 60px 0; border: 0.6px solid #E2E8F0; border-radius: 6px; background-color: #F8FAFC; color: #64748B; font-style: italic; font-size: 6.5pt;">
                            @if (! $att['is_image'])
                                This delivery-note attachment is a PDF file ({{ $att['filename'] ?? 'file' }}) — not embedded inline; open the original file to view its content.
                            @else
                                Image could not be loaded ({{ $att['filename'] ?? 'file' }}).
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border: none; padding-top: 6px; font-size: 5.6pt; line-height: 1.6;">
                    <span class="muted bold">File:</span> <span style="color: #0F172A;">{{ $att['filename'] ?? '—' }}</span> &nbsp;
                    <span class="muted bold">Attached By:</span> <span style="color: #0F172A;">{{ $att['role'] === 'vendor' ? 'Vendor' : 'MD Prod' }}{{ $att['name'] ? ' — '.$att['name'] : '' }}</span> &nbsp;
                    <span class="muted bold">Date:</span> <span style="color: #0F172A;">{{ $att['uploaded_at'] ? \Carbon\Carbon::parse($att['uploaded_at'])->timezone('Asia/Jakarta')->format('d M Y H:i') : '—' }}</span>
                    @if (! empty($att['note']))
                        <div style="margin-top: 2px;"><span class="muted bold">Note:</span> <span style="color: #0F172A;">{{ $att['note'] }}</span></div>
                    @endif
                </td>
            </tr>
        </table>
    @endforeach
@endif
