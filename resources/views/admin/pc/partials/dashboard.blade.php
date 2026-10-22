{{-- PC Dashboard tab-pane content. Data from PcController::index(). --}}
<div class="d-flex justify-content-between align-items-baseline mb-4">
    <h1 class="pc-h1">Dashboard</h1>
    <span class="pc-meta">As of {{ now('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
</div>

<div class="pc-card mb-3">
    <div class="pc-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        Contracts
        <a href="{{ route('admin.pc.contracts.index') }}" class="small text-decoration-none">View all</a>
    </div>
    <div class="pc-card-body">
        <div class="row g-4">
            @foreach (['draft' => 'Draft', 'pending_vendor_review' => 'Awaiting vendor', 'pending_final_approval' => 'Awaiting approval', 'confirmed' => 'Confirmed'] as $key => $label)
                @php
                    // Only flag a count as "yours to act on" when it both has
                    // something in it and this viewer can actually move it:
                    // pending_final_approval is only actionable for an
                    // approver, pending_vendor_review isn't actionable by
                    // staff at all (it's sitting with the vendor).
                    $isActionable = $contractCounts[$key] > 0 && $key === 'pending_final_approval' && $isApprover;
                @endphp
                <div class="col-6 col-lg-3">
                    <div class="pc-stat {{ $isActionable ? 'pc-stat-actionable' : '' }}">
                        <div class="n">{{ $contractCounts[$key] }}</div>
                        <div class="l">{{ $label }}{{ $isActionable ? ' — needs you' : '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="pc-card mb-3">
    <div class="pc-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        Master Data
        <a href="{{ route('admin.pc.data.index') }}#master-data" class="small text-decoration-none">View all</a>
    </div>
    <div class="pc-card-body">
        <div class="row g-4">
            @foreach (['hs_codes' => 'HS Codes', 'lots' => 'Lots', 'fabric_vendors' => 'Fabric Vendors', 'fabric_items' => 'Fabric Items', 'plm_refs' => 'PLM Refs'] as $key => $label)
                <div class="col-6 col-lg">
                    <div class="pc-stat">
                        <div class="n">{{ $counts[$key] }}</div>
                        <div class="l">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="pc-card">
    <div class="pc-card-head">D365 / VSM Feed Status</div>
    <div class="pc-card-body pt-0">
        <table class="pc-table">
            <thead>
                <tr><th>Feed</th><th>Last synced</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Fabric Vendors <span class="pc-meta">(D365 VendorsV2 enrichment)</span></td>
                    <td class="pc-mono">{{ $lastSync['vendor_enrichment'] ? \Illuminate\Support\Carbon::parse($lastSync['vendor_enrichment'])->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '— never synced' }}</td>
                </tr>
                <tr>
                    <td>Fabric Items <span class="pc-meta">(D365 ReleasedProductsV2)</span></td>
                    <td class="pc-mono">{{ $lastSync['fabric_items'] ? \Illuminate\Support\Carbon::parse($lastSync['fabric_items'])->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '— never synced' }}</td>
                </tr>
                <tr>
                    <td>PLM Refs <span class="pc-meta">(VSM plm_activity)</span></td>
                    <td class="pc-mono">{{ $lastSync['plm_refs'] ? \Illuminate\Support\Carbon::parse($lastSync['plm_refs'])->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '— never synced' }}</td>
                </tr>
            </tbody>
        </table>
        <a href="{{ route('admin.pc.data.index') }}#sync" class="small text-decoration-none">Manage sync</a>
    </div>
</div>
