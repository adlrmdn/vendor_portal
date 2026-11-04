<div class="line-row apple-line-item-card mb-3" data-index="{{ $index }}">
    <div class="p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: #e5e5ea !important;">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill bg-dark text-white px-2 py-1 line-number-label fw-semibold" style="font-size: 0.76rem; letter-spacing: 0.02em;">Line {{ is_numeric($index) ? $index + 1 : 1 }}</span>
                <span class="pc-mono small fw-bold line-total-badge text-primary"></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger removeLineBtn" style="border-radius: 8px; padding: 4px 9px;" title="Remove line"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">Fabric Item <span class="text-danger">*</span></label>
                <select name="lines[{{ $index }}][fabric_item_id]" class="form-select ts-fabric-item" required>
                    @if ($line && $line->fabricItem)
                        <option value="{{ $line->fabric_item_id }}" selected>{{ $line->fabricItem->item_name }} ({{ $line->fabricItem->item_number }})</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">PLM ID / Article <span class="text-danger">*</span></label>
                <select name="lines[{{ $index }}][plm_ref_id]" class="form-select ts-plm-ref" required>
                    @if ($line && $line->plmRef)
                        <option value="{{ $line->plm_ref_id }}" selected>{{ $line->plmRef->plm_id }} — {{ $line->plmRef->article_name }}</option>
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">HS Code <span class="text-danger hs-code-asterisk">*</span></label>
                <select name="lines[{{ $index }}][hs_code_id]" class="form-select searchable hs-code-select">
                    <option value="">Select HS Code…</option>
                    @foreach ($hsCodes as $h)
                        <option value="{{ $h->id }}" {{ ($line && $line->hs_code_id === $h->id) ? 'selected' : '' }}>
                            {{ $h->formatted_code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">Lot</label>
                <select name="lines[{{ $index }}][lot_id]" class="form-select line-lot-select">
                    <option value="">Lot…</option>
                    @if(isset($lots))
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" {{ ($line && $line->lot_id === $lot->id) ? 'selected' : '' }}>
                                Lot {{ $lot->label ?? $lot->code }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary text-uppercase qty-label" style="font-size: 0.72rem; letter-spacing: 0.03em;">Quantity {{ $line?->uom_snapshot ? '('.$line->uom_snapshot.')' : ($line?->fabricItem?->uom_code ? '('.$line->fabricItem->uom_code.')' : '') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.001" min="0.001" name="lines[{{ $index }}][qty]" class="form-control line-qty-input" value="{{ $line?->qty }}" placeholder="0.00" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">Unit Price <span class="text-danger">*</span></label>
                <input type="number" step="0.0001" min="0.0001" name="lines[{{ $index }}][unit_price]" class="form-control line-price-input" value="{{ $line?->unit_price }}" placeholder="0.0000" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">Line Total</label>
                <input type="text" class="form-control bg-light pc-mono fw-bold line-subtotal-display" value="0.00" readonly tabindex="-1" style="background-color: #f2f2f7 !important;">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">Remarks (optional)</label>
                <input type="text" name="lines[{{ $index }}][remarks]" class="form-control" value="{{ $line?->remarks }}" placeholder="Free text remarks…">
            </div>
        </div>

        <div class="item-details-panel"></div>
    </div>
</div>
