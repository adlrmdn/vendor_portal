@php
    $deliveries = $line ? $line->deliveries : collect();
@endphp
<div class="line-row pc-card mb-3" data-index="{{ $index }}">
    <div class="pc-card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="pc-mono line-number-label">Line {{ is_numeric($index) ? $index + 1 : '' }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger removeLineBtn"><i class="fas fa-trash"></i></button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Fabric Item</label>
                <select name="lines[{{ $index }}][fabric_item_id]" class="form-select ts-fabric-item">
                    @if ($line && $line->fabricItem)
                        <option value="{{ $line->fabric_item_id }}" selected>{{ $line->fabricItem->item_name }} ({{ $line->fabricItem->item_number }})</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">PLM ID</label>
                <select name="lines[{{ $index }}][plm_ref_id]" class="form-select ts-plm-ref">
                    @if ($line && $line->plmRef)
                        <option value="{{ $line->plm_ref_id }}" selected>{{ $line->plmRef->plm_id }} — {{ $line->plmRef->article_name }}</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">HS Code</label>
                <select name="lines[{{ $index }}][hs_code_id]" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($hsCodes as $h)
                        <option value="{{ $h->id }}" {{ ($line && $line->hs_code_id === $h->id) ? 'selected' : '' }}>{{ $h->code }} {{ $h->description ? '— '.$h->description : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Quantity</label>
                <input type="number" step="0.001" min="0.001" name="lines[{{ $index }}][qty]" class="form-control" value="{{ $line?->qty }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Unit Price</label>
                <input type="number" step="0.0001" min="0.0001" name="lines[{{ $index }}][unit_price]" class="form-control" value="{{ $line?->unit_price }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Remarks</label>
                <input type="text" name="lines[{{ $index }}][remarks]" class="form-control" value="{{ $line?->remarks }}">
            </div>
        </div>
        <div class="fdr-panel"></div>

        <hr>
        <div class="pc-meta mb-2">Delivery Schedule</div>
        <div class="delivery-rows">
            @forelse ($deliveries as $di => $delivery)
                @include('admin.pc.contracts.partials.delivery-row', ['lineIndex' => $index, 'dIndex' => $di, 'delivery' => $delivery, 'lots' => $lots])
            @empty
                @include('admin.pc.contracts.partials.delivery-row', ['lineIndex' => $index, 'dIndex' => 0, 'delivery' => null, 'lots' => $lots])
            @endforelse
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary addDeliveryBtn mt-1">+ Add delivery row</button>
    </div>
</div>
