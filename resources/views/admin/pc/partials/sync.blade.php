{{-- PC D365 Sync tab-pane content --}}
<div class="apple-card mb-4">
    <div class="apple-card-header">
        <div class="apple-card-title">
            <i class="fas fa-rotate text-success"></i>
            <span>D365 / VSM Synchronization</span>
        </div>
        <span class="small text-muted">Enrichment refreshes every 4h; PLM refs refresh hourly</span>
    </div>
    <div class="table-responsive">
        <table class="table apple-table mb-0" id="pcSyncTable">
            <thead>
                <tr>
                    <th class="ps-3">Feed</th>
                    <th>Status</th>
                    <th>Last Result</th>
                    <th>Finished At</th>
                    <th class="pe-3 text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['vendor-enrichment' => 'Fabric Vendors (D365 VendorsV2 enrichment)', 'fabric-items' => 'Fabric Items (D365 ReleasedProductsV2)', 'plm-refs' => 'PLM Refs (VSM plm_activity)'] as $feed => $label)
                    @php $state = $feeds[$feed] ?? ['running' => false, 'last' => null]; @endphp
                    <tr class="apple-table-row" data-feed="{{ $feed }}">
                        <td class="ps-3 fw-semibold">{{ $label }}</td>
                        <td class="feed-status">
                            @if ($state['running'])
                                <span class="apple-pill-status pill-warning"><span class="status-dot dot-warning"></span>Running…</span>
                            @else
                                <span class="apple-pill-status pill-success"><span class="status-dot dot-success"></span>Idle</span>
                            @endif
                        </td>
                        <td class="feed-last-result text-muted">{{ $state['last']['result'] ?? '—' }}</td>
                        <td class="feed-finished-at text-muted">{{ $state['last']['finished_at'] ?? '—' }}</td>
                        <td class="pe-3 text-end">
                            <button type="button" class="apple-btn-secondary feed-refresh-btn" data-feed="{{ $feed }}" {{ $state['running'] ? 'disabled' : '' }}>
                                <i class="fas fa-sync-alt me-1"></i> Refresh now
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
