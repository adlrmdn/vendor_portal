{{-- PC D365 Sync tab-pane content. Data from PcController::index(). --}}
<div class="mb-4">
    <h1 class="pc-h1 mb-0"><i class="fas fa-rotate me-2"></i>D365 / VSM Sync</h1>
    <small class="text-muted">Fabric vendor enrichment and fabric items refresh every 4 hours; PLM refs refresh hourly. Use "Refresh now" for anything more urgent.</small>
</div>

<div class="pc-card">
    <div class="pc-card-body">
        <table class="pc-table mb-0" id="pcSyncTable">
            <thead>
                <tr>
                    <th>Feed</th>
                    <th>Status</th>
                    <th>Last Result</th>
                    <th>Finished At</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['vendor-enrichment' => 'Fabric Vendors (D365 VendorsV2 enrichment)', 'fabric-items' => 'Fabric Items (D365 ReleasedProductsV2)', 'plm-refs' => 'PLM Refs (VSM plm_activity)'] as $feed => $label)
                    @php $state = $feeds[$feed] ?? ['running' => false, 'last' => null]; @endphp
                    <tr data-feed="{{ $feed }}">
                        <td>{{ $label }}</td>
                        <td class="feed-status">
                            @if ($state['running'])
                                <span class="pc-pill pc-pill-waiting">Running…</span>
                            @else
                                <span class="pc-pill pc-pill-neutral">Idle</span>
                            @endif
                        </td>
                        <td class="feed-last-result">{{ $state['last']['result'] ?? '—' }}</td>
                        <td class="feed-finished-at">{{ $state['last']['finished_at'] ?? '—' }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary feed-refresh-btn" data-feed="{{ $feed }}" {{ $state['running'] ? 'disabled' : '' }}>
                                Refresh now
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
