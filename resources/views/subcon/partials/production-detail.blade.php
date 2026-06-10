{{--
    Garment production detail for a subcon CMT PO.
    Expects: $productionGroups (array from App\Services\SubconProductionService::forPo)
    Read-only enrichment — renders nothing intrusive when the PO has no PLM link.
--}}
@php
    $prodBadge = fn ($s) => match ($s) {
        'Completed', 'ReportedFinished' => 'success',
        'StartedUp'                     => 'warning',
        'Released'                      => 'info',
        'CostEstimated', 'Created'      => 'secondary',
        default                         => 'secondary',
    };
@endphp

<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="fas fa-industry me-1"></i> Production Detail</h6>
        @if(!empty($productionGroups))
            <span class="badge bg-light text-dark">{{ count($productionGroups) }} article{{ count($productionGroups) === 1 ? '' : 's' }}</span>
        @endif
    </div>
    <div class="card-body">
        @forelse($productionGroups as $g)
            <div class="@if(!$loop->first) mt-4 pt-4 border-top @endif">
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">
                            {{ $g['article_name'] ?: 'Unnamed article' }}
                            @if($g['colour']) <span class="text-muted">— {{ $g['colour'] }}</span> @endif
                        </div>
                        <div class="small text-muted">
                            @if($g['article_code']) <code>{{ $g['article_code'] }}</code> @endif
                            @if($g['brand']) · {{ $g['brand'] }} @endif
                            @if($g['group_name']) · {{ $g['group_name'] }} @endif
                        </div>
                        <div class="small text-muted">
                            <span title="PLM activity">{{ $g['plm_id'] }}</span>
                            @if($g['production_group']) · <span title="Production group">{{ $g['production_group'] }}</span> @endif
                        </div>
                    </div>
                    <div class="text-end">
                        @if($g['plm_status'])
                            <span class="badge bg-{{ $prodBadge($g['plm_status']) }}">{{ $g['plm_status'] }}</span>
                        @endif
                        <div class="small text-muted mt-1">Total: <strong>{{ number_format($g['total_qty']) }}</strong> pcs</div>
                    </div>
                </div>

                @if(!empty($g['lines']))
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Size</th>
                                    <th class="text-end">Qty</th>
                                    <th>Status</th>
                                    <th>Site / Location</th>
                                    <th>Production ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($g['lines'] as $line)
                                    <tr>
                                        <td><span class="badge bg-secondary">{{ $line->Size ?: '—' }}</span></td>
                                        <td class="text-end">{{ number_format($line->Qty) }}</td>
                                        <td><span class="badge bg-{{ $prodBadge($line->ProdStatus) }}">{{ $line->ProdStatus ?: '—' }}</span></td>
                                        <td class="small">{{ $line->InventSiteId }}@if($line->InventLocationId) / {{ $line->InventLocationId }} @endif</td>
                                        <td class="small"><code>{{ $line->ProdId }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="small text-muted mb-0">No production-group lines found for this article yet.</p>
                @endif
            </div>
        @empty
            <p class="text-muted small mb-0">
                <i class="fas fa-info-circle me-1"></i>
                No linked production detail. This order isn't yet tied to a PLM activity in the production system.
            </p>
        @endforelse
    </div>
</div>
