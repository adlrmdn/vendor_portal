{{-- Modern Executive Dashboard for Preliminary Contracts --}}
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="pc-h1">Preliminary Contracts Overview</h1>
        <div class="pc-meta mt-1">
            <i class="fas fa-sync-alt me-1 text-muted"></i> Live pipeline & integration status · As of {{ now('Asia/Jakarta')->format('d M Y, H:i') }} WIB
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.pc.contracts.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-list me-1"></i> View All Contracts
        </a>
        <a href="{{ route('admin.pc.contracts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Contract
        </a>
    </div>
</div>

{{-- Pipeline Stat Cards (Clickable) --}}
<div class="row g-3 mb-4">
    @php
        $pipelineConfig = [
            'draft' => [
                'label' => 'Drafts',
                'sub' => 'In preparation',
                'class' => 'pc-stat-draft',
                'icon' => 'fas fa-pencil-alt',
                'color' => '#64748b'
            ],
            'pending_approval' => [
                'label' => 'Awaiting Approval',
                'sub' => 'Review by Approver',
                'class' => 'pc-stat-pending',
                'icon' => 'fas fa-user-clock',
                'color' => '#f59e0b'
            ],
            'approved' => [
                'label' => 'Approved',
                'sub' => 'Ready to send to vendor',
                'class' => 'pc-stat-approved',
                'icon' => 'fas fa-paper-plane',
                'color' => '#6366f1'
            ],
            'sent_to_supplier' => [
                'label' => 'With Vendor',
                'sub' => 'Pending vendor review',
                'class' => 'pc-stat-sent',
                'icon' => 'fas fa-building',
                'color' => '#0ea5e9'
            ],
            'confirmed' => [
                'label' => 'Confirmed',
                'sub' => 'Signed & locked',
                'class' => 'pc-stat-confirmed',
                'icon' => 'fas fa-check-circle',
                'color' => '#10b981'
            ],
            'supplier_queried' => [
                'label' => 'Vendor Queried',
                'sub' => 'Changes requested',
                'class' => 'pc-stat-queried',
                'icon' => 'fas fa-exclamation-circle',
                'color' => '#ef4444'
            ],
        ];
    @endphp

    @foreach ($pipelineConfig as $statusKey => $cfg)
        @php
            $count = $contractCounts[$statusKey] ?? 0;
            $isActionable = ($count > 0 && (
                ($statusKey === 'pending_approval' && $isApprover) ||
                ($statusKey === 'approved' && ! $isApprover) ||
                ($statusKey === 'supplier_queried')
            ));
        @endphp
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.pc.contracts.index', ['status' => $statusKey]) }}" 
               class="pc-stat-card {{ $cfg['class'] }}" title="Filter by {{ $cfg['label'] }}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="stat-label text-truncate">{{ $cfg['label'] }}</span>
                    <i class="{{ $cfg['icon'] }}" style="color: {{ $cfg['color'] }}; font-size: 1.1rem; opacity: 0.85;"></i>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <div class="stat-num">{{ $count }}</div>
                    @if($isActionable)
                        <span class="badge bg-warning text-dark small" style="font-size: 0.68rem;">Action</span>
                    @endif
                </div>
                <div class="small text-muted text-truncate mt-1" style="font-size: 0.75rem;">
                    {{ $cfg['sub'] }}
                </div>
            </a>
        </div>
    @endforeach
</div>

{{-- Recent Contracts Card --}}
<div class="apple-card apple-table-container mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom" style="border-color: #ebebf0 !important;">
        <div>
            <h5 class="mb-0 fw-bold fs-6 text-dark">Recent Preliminary Contracts</h5>
            <span class="small text-muted">Latest procurement contracts across all fabric suppliers</span>
        </div>
        <a href="{{ route('admin.pc.contracts.index') }}" class="apple-header-link">
            <span>View All Contracts</span>
            <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table apple-table mb-0">
            <thead>
                <tr>
                    <th style="width: 28%;">Contract Details</th>
                    <th style="width: 25%;">Vendor</th>
                    <th style="width: 15%;">Season</th>
                    <th class="text-center" style="width: 10%;">Lines</th>
                    <th style="width: 14%; text-align: center;">Status</th>
                    <th class="text-end pe-3" style="width: 8%;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentContracts as $contract)
                    <tr class="apple-table-row">
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div class="fw-bold text-dark">
                                    <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="apple-po-number">
                                        {{ $contract->contract_number ?? 'Draft (Not Assigned)' }}
                                    </a>
                                    @if($contract->revision_no > 0)
                                        <span class="badge bg-secondary-subtle text-secondary ms-1 px-2 py-0" style="font-size: 0.72rem; border-radius: 4px;">REV {{ $contract->revision_no }}</span>
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    Created {{ $contract->created_at ? $contract->created_at->format('d M Y, H:i') : '—' }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $contract->vendor->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $contract->vendor->code ?? '' }}</div>
                        </td>
                        <td>
                            <span class="apple-sub-tag pc-tag">
                                {{ $contract->season->name ?? $contract->season->code ?? '—' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="apple-count-pill" style="padding: 2px 8px; font-size: 0.75rem;">{{ $contract->lines_count ?? 0 }} lines</span>
                        </td>
                        <td class="text-center">
                            @include('admin.pc.partials.status-pill', ['status' => $contract->status])
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('admin.pc.contracts.show', $contract) }}" class="apple-action-btn apple-action-view" title="View Contract">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center">
                            <div class="apple-empty-state">
                                <div class="apple-empty-icon-circle mb-3">
                                    <i class="fas fa-folder-open fa-2x"></i>
                                </div>
                                <h4 class="apple-empty-title">No Contracts Recorded Yet</h4>
                                <p class="apple-empty-desc">
                                    There are no preliminary contracts in the pipeline yet.
                                </p>
                                <a href="{{ route('admin.pc.contracts.create') }}" class="apple-btn-primary mt-2">
                                    <i class="fas fa-plus me-1"></i> Create First Contract
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Two-Column Bottom: Master Data & Feed Sync Status --}}
<div class="row g-4">
    {{-- Master Data Summary --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold fs-6">
                    <i class="fas fa-database text-info me-2"></i>Master Data Cache
                </h5>
                <a href="{{ route('admin.pc.data.index') }}#master-data" class="small text-decoration-none">
                    Manage Data <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @php
                        $dataStats = [
                            ['label' => 'HS Codes', 'val' => $counts['hs_codes'] ?? 0, 'icon' => 'fas fa-barcode'],
                            ['label' => 'Lots', 'val' => $counts['lots'] ?? 0, 'icon' => 'fas fa-layer-group'],
                            ['label' => 'Fabric Vendors', 'val' => $counts['fabric_vendors'] ?? 0, 'icon' => 'fas fa-industry'],
                            ['label' => 'Fabric Items', 'val' => $counts['fabric_items'] ?? 0, 'icon' => 'fas fa-scroll'],
                            ['label' => 'PLM Activity', 'val' => $counts['plm_refs'] ?? 0, 'icon' => 'fas fa-tag'],
                        ];
                    @endphp
                    @foreach ($dataStats as $ds)
                        <div class="col-4 col-sm">
                            <div class="p-2 border rounded bg-light text-center h-100">
                                <i class="{{ $ds['icon'] }} text-secondary mb-1" style="font-size: 1.1rem;"></i>
                                <div class="fs-5 fw-bold text-dark">{{ number_format($ds['val']) }}</div>
                                <div class="small text-muted" style="font-size: 0.75rem;">{{ $ds['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- D365 / VSM Feed Sync --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold fs-6">
                    <i class="fas fa-network-wired text-success me-2"></i>D365 / VSM Feed Integration
                </h5>
                <a href="{{ route('admin.pc.data.index') }}#sync" class="small text-decoration-none">
                    Sync Center <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Integration Feed</th>
                                <th class="pe-3 text-end">Last Synchronized</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">Fabric Vendors</div>
                                    <div class="small text-muted">D365 VendorsV2 entity</div>
                                </td>
                                <td class="pe-3 text-end">
                                    @if($lastSync['vendor_enrichment'])
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            {{ \Carbon\Carbon::parse($lastSync['vendor_enrichment'])->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Never synced</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">Fabric Items</div>
                                    <div class="small text-muted">D365 ReleasedProductsV2 entity</div>
                                </td>
                                <td class="pe-3 text-end">
                                    @if($lastSync['fabric_items'])
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            {{ \Carbon\Carbon::parse($lastSync['fabric_items'])->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Never synced</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">PLM Activity Refs</div>
                                    <div class="small text-muted">VSM plm_activity database feed</div>
                                </td>
                                <td class="pe-3 text-end">
                                    @if($lastSync['plm_refs'])
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            {{ \Carbon\Carbon::parse($lastSync['plm_refs'])->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Never synced</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
