<div class="line-row pc-card mb-3" data-index="{{ $index }}">
    <div class="pc-card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="pc-mono line-number-label fw-bold">Line {{ is_numeric($index) ? $index + 1 : '' }}</span>
            <div class="d-flex align-items-center gap-2">
                <span class="pc-mono small fw-bold line-total-badge text-primary"></span>
                <button type="button" class="btn btn-sm btn-outline-danger removeLineBtn" title="Remove line"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-5">
                <label class="form-label small fw-bold">Fabric Item <span class="text-danger">*</span></label>
                <select name="lines[{{ $index }}][fabric_item_id]" class="form-select ts-fabric-item" required>
                    @if ($line && $line->fabricItem)
                        <option value="{{ $line->fabric_item_id }}" selected>{{ $line->fabricItem->item_name }} ({{ $line->fabricItem->item_number }})</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">PLM ID / Article <span class="text-danger">*</span></label>
                <select name="lines[{{ $index }}][plm_ref_id]" class="form-select ts-plm-ref" required>
                    @if ($line && $line->plmRef)
                        <option value="{{ $line->plm_ref_id }}" selected>{{ $line->plmRef->plm_id }} — {{ $line->plmRef->article_name }}</option>
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">HS Code (10-digit) <span class="text-danger">*</span></label>
                <select name="lines[{{ $index }}][hs_code_id]" class="form-select searchable" required>
                    <option value="">Select HS Code…</option>
                    @foreach ($hsCodes as $h)
                        <option value="{{ $h->id }}" {{ ($line && $line->hs_code_id === $h->id) ? 'selected' : '' }}>
                            {{ $h->code }} {{ $h->description ? '— '.$h->description : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold qty-label">Quantity {{ $line?->uom_snapshot ? '('.$line->uom_snapshot.')' : ($line?->fabricItem?->uom_code ? '('.$line->fabricItem->uom_code.')' : '') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.001" min="0.001" name="lines[{{ $index }}][qty]" class="form-control line-qty-input" value="{{ $line?->qty }}" placeholder="0.00" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Unit Price <span class="text-danger">*</span></label>
                <input type="number" step="0.0001" min="0.0001" name="lines[{{ $index }}][unit_price]" class="form-control line-price-input" value="{{ $line?->unit_price }}" placeholder="0.0000" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Remarks (optional)</label>
                <input type="text" name="lines[{{ $index }}][remarks]" class="form-control" value="{{ $line?->remarks }}" placeholder="Free text remarks…">
            </div>
        </div>

        <div class="item-details-panel"></div>
    </div>
</div>
