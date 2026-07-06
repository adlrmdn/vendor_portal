{{--
    Fabric reconciliation for the cutting report — single per-order values that
    apply across all sizes (like blister capacity). Stored as decimal meters,
    default 0. Editable during the cutting stage; read-only afterwards, where
    "Sisa Kain" is shown labelled "Sisa Kain (Utuh)".
    Params: $order, $editable (bool)
--}}
@php($editable = $editable ?? false)
@php($fields = [
    'short_roll'  => 'Short Roll',
    'sisa_kain'   => $editable ? 'Sisa Kain' : 'Sisa Kain (Utuh)',
    'kepala_kain' => 'Kepala Kain',
    'retur_kain'  => 'Retur Kain',
])
<div class="card mt-3 shadow-sm border-0" style="border-radius:12px; border:1px solid rgba(0,0,0,0.08);">
    <div class="card-body p-3">
        <div class="fw-semibold mb-2 small text-secondary text-uppercase" style="letter-spacing:.05em;">
            <i class="fas fa-ruler-horizontal me-1"></i> Fabric Reconciliation
            <span class="text-muted fw-normal text-lowercase">(meters — applies across all sizes)</span>
        </div>
        <div class="d-flex align-items-end flex-wrap gap-3">
            @foreach($fields as $name => $label)
                <div>
                    <label for="{{ $name }}" class="form-label small mb-1 text-secondary">{{ $label }}</label>
                    @if($editable)
                        <div class="input-group input-group-sm" style="width: 150px;">
                            <input type="number" id="{{ $name }}" name="{{ $name }}" min="0" step="0.01" inputmode="decimal"
                                   class="form-control"
                                   value="{{ old($name, $order->$name ?? 0) }}" placeholder="0">
                            <span class="input-group-text">m</span>
                        </div>
                    @else
                        <div class="fw-semibold">{{ number_format($order->$name ?? 0, 2) }} <span class="text-muted small fw-normal">m</span></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
