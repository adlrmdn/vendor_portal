<div class="delivery-row row g-2 align-items-end mb-2">
    <div class="col-md-3">
        {{-- Not .searchable: "+Add delivery row" clones this row's DOM
             wholesale (see form.blade.php), and cloning an already
             TomSelect-enhanced <select> duplicates its generated wrapper
             markup instead of giving the clone a clean widget. Lots is a
             short seeded list, so a plain select is a fine tradeoff here. --}}
        <select name="lines[{{ $lineIndex }}][deliveries][{{ $dIndex }}][lot_id]" class="form-select form-select-sm">
            <option value="">Lot…</option>
            @foreach ($lots as $lot)
                <option value="{{ $lot->id }}" {{ ($delivery && $delivery->lot_id === $lot->id) ? 'selected' : '' }}>{{ $lot->label ?? $lot->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <input type="number" step="0.001" min="0" placeholder="Qty" name="lines[{{ $lineIndex }}][deliveries][{{ $dIndex }}][delivery_qty]" class="form-control form-control-sm" value="{{ $delivery?->delivery_qty }}">
    </div>
    <div class="col-md-3">
        <input type="date" name="lines[{{ $lineIndex }}][deliveries][{{ $dIndex }}][eta_date]" class="form-control form-control-sm" value="{{ $delivery?->eta_date?->toDateString() }}">
    </div>
    <div class="col-md-2">
        <input type="text" placeholder="Remarks" name="lines[{{ $lineIndex }}][deliveries][{{ $dIndex }}][remarks]" class="form-control form-control-sm" value="{{ $delivery?->remarks }}">
    </div>
    <div class="col-md-1">
        <button type="button" class="btn btn-sm btn-outline-danger removeDeliveryBtn"><i class="fas fa-times"></i></button>
    </div>
</div>
