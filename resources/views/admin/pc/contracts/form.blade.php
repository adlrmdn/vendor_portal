@php
    $isEdit = isset($contract);
    $lines = $isEdit ? $contract->lines : collect();
    $deliveries = $isEdit ? $contract->deliveries : collect();
@endphp

@include('partials.searchable-select')

<form method="POST" action="{{ $isEdit ? route('admin.pc.contracts.update', $contract) : route('admin.pc.contracts.store') }}" id="pcContractForm">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if (session('errors') && is_array(session('errors')))
        <div class="alert alert-danger mb-3">
            <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i>Cannot submit yet:</h6>
            <ul class="mb-0">
                @foreach (session('errors') as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($errors->any())
        <div class="alert alert-danger mb-3">
            <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i>Validation Errors:</h6>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('warnings') && count(session('warnings')))
        <div class="alert alert-warning mb-3">
            <h6 class="alert-heading fw-bold mb-1"><i class="fas fa-info-circle me-1"></i>Notices & Warnings:</h6>
            <ul class="mb-0">
                @foreach (session('warnings') as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Section --}}
    <div class="pc-card mb-3">
        <div class="pc-card-head d-flex justify-content-between align-items-center">
            <span>Contract Terms & Header</span>
            <span class="pc-meta small">* Required for submission</span>
        </div>
        <div class="pc-card-body row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold">Supplier / Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vendor_id" class="form-select searchable" required data-no-empty-option {{ $isEdit ? 'data-no-clear' : '' }}>
                    <option value="">Search supplier by name or V-code…</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}"
                                data-currency="{{ $v->currency_code }}"
                                data-payment-term="{{ $v->paymentTerm?->label ?? '' }}"
                                {{ (old('vendor_id', $contract->vendor_id ?? '')) === $v->id ? 'selected' : '' }}>
                            {{ $v->name }} ({{ $v->vendor_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Currency <span class="text-danger">*</span></label>
                <select name="currency_id" id="currencySelect" class="form-select" required>
                    <option value="">Select currency…</option>
                    @foreach ($currencies as $curr)
                        <option value="{{ $curr->id }}"
                                data-code="{{ $curr->code }}"
                                {{ (old('currency_id', $contract->currency_id ?? '')) === $curr->id ? 'selected' : '' }}>
                            {{ $curr->code }} - {{ $curr->label ?? $curr->name ?? $curr->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Terms of Payment (from supplier)</label>
                <input type="text" class="form-control bg-light" id="vendorPaymentTermDisplay"
                       value="{{ $contract->paymentTerm?->label ?? 'Auto-assigned from supplier' }}" readonly>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Fabric Type <span class="text-danger">*</span></label>
                <select name="fabric_type" class="form-select" required>
                    <option value="">Select…</option>
                    @foreach (['WOVEN','KNIT'] as $ft)
                        <option value="{{ $ft }}" {{ old('fabric_type', $contract->fabric_type ?? 'WOVEN') === $ft ? 'selected' : '' }}>{{ $ft }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Contract Date <span class="text-danger">*</span></label>
                <input type="date" name="contract_date" class="form-control" max="{{ now()->toDateString() }}"
                    value="{{ old('contract_date', isset($contract) && $contract->contract_date ? $contract->contract_date->toDateString() : now()->toDateString()) }}" required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Season <span class="text-danger">*</span></label>
                <select name="season_id" class="form-select" required>
                    <option value="">Select season…</option>
                    @foreach ($seasons as $s)
                        <option value="{{ $s->id }}" {{ old('season_id', $contract->season_id ?? '') === $s->id ? 'selected' : '' }}>{{ $s->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Order Type <span class="text-danger">*</span></label>
                <select name="order_type_id" class="form-select" required>
                    <option value="">Select order type…</option>
                    @foreach ($orderTypes as $o)
                        <option value="{{ $o->id }}" {{ old('order_type_id', $contract->order_type_id ?? '') === $o->id ? 'selected' : '' }}>{{ $o->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Mode of Transport <span class="text-danger">*</span></label>
                <select name="transport_mode_id" class="form-select" required>
                    <option value="">Select transport…</option>
                    @foreach ($transportModes as $t)
                        <option value="{{ $t->id }}" {{ old('transport_mode_id', $contract->transport_mode_id ?? '') === $t->id ? 'selected' : '' }}>{{ $t->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Terms of Delivery <span class="text-danger">*</span></label>
                <select name="delivery_term_id" class="form-select" required>
                    <option value="">Select delivery term…</option>
                    @foreach ($deliveryTerms as $d)
                        <option value="{{ $d->id }}" {{ old('delivery_term_id', $contract->delivery_term_id ?? '') === $d->id ? 'selected' : '' }}>{{ $d->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">VAT / PPN <span class="text-danger">*</span></label>
                <select name="vat_treatment_id" id="vat_treatment_id" class="form-select" required>
                    <option value="">Select treatment…</option>
                    @foreach ($vatTreatments as $v)
                        <option value="{{ $v->id }}" data-code="{{ $v->code }}" {{ old('vat_treatment_id', $contract->vat_treatment_id ?? '') === $v->id ? 'selected' : '' }}>{{ $v->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3" id="vatRateWrapper">
                <label class="form-label fw-bold">VAT Rate (%)</label>
                <input type="number" step="0.01" name="vat_rate" id="vat_rate" class="form-control"
                    value="{{ old('vat_rate', $contract->vat_rate ?? \App\Models\Setting::getValue('pc_default_vat_rate', '11.00')) }}">
            </div>

            <div class="col-12">
                <label class="form-label">Internal Remarks (not printed in contract)</label>
                <textarea name="internal_remarks" class="form-control" rows="2" placeholder="Internal negotiation or background notes…">{{ old('internal_remarks', $contract->internal_remarks ?? '') }}</textarea>
            </div>

            {{-- Fabric Delivery Schedule (Spec S4 Header) --}}
            <div class="col-12 border-top pt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold mb-0"><i class="fas fa-truck me-1"></i>Fabric Delivery Schedule <span class="text-danger">*</span></label>
                    <button type="button" class="btn btn-sm btn-outline-secondary addDeliveryBtn">+ Add delivery date</button>
                </div>
                <div id="deliveriesContainer" class="row g-2">
                    @forelse($deliveries as $di => $del)
                        <div class="col-md-4 delivery-item">
                            <div class="input-group">
                                <select name="deliveries[{{ $di }}][lot_id]" class="form-select" required>
                                    <option value="">Lot…</option>
                                    @foreach($lots as $lot)
                                        <option value="{{ $lot->id }}" {{ $del->lot_id === $lot->id ? 'selected' : '' }}>Lot {{ $lot->label ?? $lot->code }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="deliveries[{{ $di }}][eta_date]" class="form-control" value="{{ $del->eta_date?->toDateString() }}" required>
                                <button type="button" class="btn btn-outline-danger removeDeliveryBtn"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    @empty
                        <div class="col-md-4 delivery-item">
                            <div class="input-group">
                                <select name="deliveries[0][lot_id]" class="form-select" required>
                                    <option value="">Lot…</option>
                                    @foreach($lots as $lot)
                                        <option value="{{ $lot->id }}">Lot {{ $lot->label ?? $lot->code }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="deliveries[0][eta_date]" class="form-control" value="{{ now()->addMonth()->toDateString() }}" required>
                                <button type="button" class="btn btn-outline-danger removeDeliveryBtn"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Section --}}
    <div class="pc-card mb-3">
        <div class="pc-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span>Ordered Lines</span>
                <span class="pc-meta ms-2 small" id="linesCountTally">({{ count($lines) }} line{{ count($lines) === 1 ? '' : 's' }})</span>
            </div>
            <button type="button" class="btn btn-sm pc-btn-primary addLineBtn"><i class="fas fa-plus me-1"></i>Add Line</button>
        </div>
        <div class="pc-card-body">
            <div id="linesContainer">
                @foreach ($lines as $i => $line)
                    @include('admin.pc.contracts.partials.line-row', ['index' => $i, 'line' => $line])
                @endforeach
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary addLineBtn"><i class="fas fa-plus me-1"></i>Add Another Line</button>
                <div class="pc-mono fw-bold fs-6" id="runningTotalDisplay">
                    Total: <span id="summaryTotalQty">0.00</span> | <span id="summaryTotalValue">0.00</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Submit Bar --}}
    <div class="d-flex justify-content-between align-items-center mt-4">
        <a href="{{ $isEdit ? route('admin.pc.contracts.show', $contract) : route('admin.pc.contracts.index') }}" class="btn btn-outline-secondary px-4">
            Cancel
        </a>
        <div class="d-flex gap-2">
            <button type="submit" class="btn pc-btn-primary px-4 fw-bold">
                <i class="fas fa-save me-1"></i>Save Draft
            </button>
        </div>
    </div>
</form>

<template id="lineRowTemplate">
    @include('admin.pc.contracts.partials.line-row', ['index' => '__INDEX__', 'line' => null])
</template>

<template id="deliveryTemplate">
    <div class="col-md-4 delivery-item">
        <div class="input-group">
            <select name="deliveries[__DINDEX__][lot_id]" class="form-select" required>
                <option value="">Lot…</option>
                @foreach($lots as $lot)
                    <option value="{{ $lot->id }}">Lot {{ $lot->label ?? $lot->code }}</option>
                @endforeach
            </select>
            <input type="date" name="deliveries[__DINDEX__][eta_date]" class="form-control" value="{{ now()->addMonth()->toDateString() }}" required>
            <button type="button" class="btn btn-outline-danger removeDeliveryBtn"><i class="fas fa-times"></i></button>
        </div>
    </div>
</template>

@push('scripts')
<script>
    (function () {
        let lineCounter = {{ count($lines) }};
        let deliveryCounter = {{ max(count($deliveries), 1) }};

        // Vendor Currency & Payment Term auto-reflection
        function syncVendorDetails() {
            const vendorSel = document.getElementById('vendor_id');
            const currencySel = document.getElementById('currencySelect');
            const paymentDisp = document.getElementById('vendorPaymentTermDisplay');
            if (!vendorSel) return;

            const opt = vendorSel.options[vendorSel.selectedIndex];
            if (opt && opt.value) {
                let cur = (opt.dataset.currency || '').toUpperCase();
                if (cur === 'CNY') cur = 'RMB';
                if (currencySel && (!currencySel.value || !currencySel.dataset.userModified)) {
                    for (let i = 0; i < currencySel.options.length; i++) {
                        if (currencySel.options[i].dataset.code === cur) {
                            currencySel.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (paymentDisp && opt.dataset.paymentTerm) {
                    paymentDisp.value = opt.dataset.paymentTerm;
                }
            } else {
                if (paymentDisp) paymentDisp.value = 'Auto-assigned from supplier';
            }
        }
        document.getElementById('vendor_id')?.addEventListener('change', function () {
            const curSel = document.getElementById('currencySelect');
            if (curSel) delete curSel.dataset.userModified;
            syncVendorDetails();
        });
        document.getElementById('currencySelect')?.addEventListener('change', function () {
            this.dataset.userModified = 'true';
        });
        setTimeout(syncVendorDetails, 100);

        // VAT Rate field toggle
        function syncVatRateField() {
            const treatmentSelect = document.getElementById('vat_treatment_id');
            const rateInput = document.getElementById('vat_rate');
            const wrapper = document.getElementById('vatRateWrapper');
            if (!treatmentSelect || !rateInput || !wrapper) return;
            const option = treatmentSelect.options[treatmentSelect.selectedIndex];
            const applies = !!option && option.dataset.code === 'INCLUDE';
            rateInput.disabled = !applies;
            wrapper.classList.toggle('opacity-50', !applies);
        }
        document.getElementById('vat_treatment_id')?.addEventListener('change', syncVatRateField);
        syncVatRateField();

        // Live calculation of line totals and running totals
        function recalculateTotals() {
            let totalQty = 0;
            let totalVal = 0;
            const rows = document.querySelectorAll('.line-row');

            rows.forEach(function (row, idx) {
                const qtyInput = row.querySelector('.line-qty-input');
                const priceInput = row.querySelector('.line-price-input');
                const badge = row.querySelector('.line-total-badge');

                const q = parseFloat(qtyInput?.value || 0);
                const p = parseFloat(priceInput?.value || 0);
                const lineTotal = (q > 0 && p > 0) ? (q * p) : 0;

                if (badge) {
                    badge.textContent = lineTotal > 0 ? ('= ' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '';
                }

                totalQty += q;
                totalVal += lineTotal;
            });

            const tally = document.getElementById('linesCountTally');
            if (tally) tally.textContent = '(' + rows.length + ' line' + (rows.length === 1 ? '' : 's') + ')';

            const dispQty = document.getElementById('summaryTotalQty');
            const dispVal = document.getElementById('summaryTotalValue');
            if (dispQty) dispQty.textContent = totalQty.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (dispVal) dispVal.textContent = totalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.getElementById('linesContainer')?.addEventListener('input', function (e) {
            if (e.target.classList.contains('line-qty-input') || e.target.classList.contains('line-price-input')) {
                recalculateTotals();
            }
        });
        setTimeout(recalculateTotals, 200);

        // TomSelect Pickers Initialization
        function initPickers(scope) {
            scope.querySelectorAll('select.ts-fabric-item').forEach(function (el) {
                if (el.tomselect) return;
                const ts = new TomSelect(el, {
                    valueField: 'id', labelField: 'text', searchField: 'text',
                    placeholder: 'Type to search fabric item name or number…',
                    maxOptions: 50,
                    preload: 'focus',
                    openOnFocus: true,
                    load: function (query, callback) {
                        fetch(@json(route('admin.pc.search.fabric-items')) + '?q=' + encodeURIComponent(query))
                            .then(r => r.json()).then(data => callback(data)).catch(() => callback());
                    },
                });
                ts.on('change', function () {
                    const row = el.closest('.line-row');
                    if (row) {
                        fetchItemDetails(row);
                        const plmSel = row.querySelector('select.ts-plm-ref');
                        if (plmSel && plmSel.tomselect) {
                            plmSel.tomselect.clearOptions();
                        }
                    }
                });
            });
            scope.querySelectorAll('select.ts-plm-ref').forEach(function (el) {
                if (el.tomselect) return;
                new TomSelect(el, {
                    valueField: 'id', labelField: 'text', searchField: 'text',
                    placeholder: 'Type to search PLM ID or Article…',
                    maxOptions: 50,
                    preload: 'focus',
                    openOnFocus: true,
                    load: function (query, callback) {
                        const row = el.closest('.line-row');
                        const itemSel = row ? row.querySelector('select.ts-fabric-item') : null;
                        const fabricItemId = itemSel && itemSel.tomselect ? itemSel.tomselect.getValue() : (itemSel ? itemSel.value : '');
                        const cached = itemSel && itemSel.tomselect ? (itemSel.tomselect.options[fabricItemId] || {}) : {};
                        const itemNumber = cached.item_number || '';

                        let url = @json(route('admin.pc.search.plm-refs')) + '?q=' + encodeURIComponent(query);
                        if (fabricItemId) url += '&fabric_item_id=' + encodeURIComponent(fabricItemId);
                        if (itemNumber) url += '&item_number=' + encodeURIComponent(itemNumber);

                        fetch(url)
                            .then(r => r.json()).then(data => callback(data)).catch(() => callback());
                    },
                });
            });
            scope.querySelectorAll('select.searchable').forEach(function (el) {
                if (el.tomselect) return;
                let placeholder = el.getAttribute('placeholder');
                let hasEmptyOption = false;
                if (!placeholder && el.options.length > 0 && el.options[0].value === '') {
                    placeholder = el.options[0].text;
                    hasEmptyOption = true;
                }
                const isRequired = el.hasAttribute('required') || el.hasAttribute('data-no-clear');
                new TomSelect(el, {
                    allowEmptyOption: !el.hasAttribute('data-no-empty-option') && !hasEmptyOption,
                    maxOptions: null,
                    placeholder: placeholder || undefined,
                    plugins: isRequired ? [] : ['clear_button'],
                });
            });
        }

        function fetchItemDetails(row) {
            const itemSel = row.querySelector('select.ts-fabric-item');
            const vendorSel = document.getElementById('vendor_id');
            const panel = row.querySelector('.item-details-panel') || row.querySelector('.fdr-panel');
            if (!itemSel || !itemSel.tomselect || !panel) return;

            const value = itemSel.tomselect.getValue();
            if (!value) {
                panel.innerHTML = '';
                const qtyLabel = row.querySelector('.qty-label');
                if (qtyLabel) qtyLabel.innerHTML = 'Quantity <span class="text-danger">*</span>';
                return;
            }

            const cached = itemSel.tomselect.options[value] || {};
            const itemNumber = cached.item_number || '';
            const vendorId = vendorSel ? vendorSel.value : '';

            panel.innerHTML = '<div class="text-muted small py-1"><i class="fas fa-spinner fa-spin me-1"></i>Fetching item specifications and pricing history…</div>';

            const url = @json(route('admin.pc.search.item-details')) + 
                '?item_id=' + encodeURIComponent(value) + 
                '&item_number=' + encodeURIComponent(itemNumber) + 
                '&vendor_id=' + encodeURIComponent(vendorId);

            fetch(url)
                .then(r => r.json())
                .then(function (data) {
                    if (!data || !data.success) {
                        panel.innerHTML = '';
                        return;
                    }

                    // Update Quantity label with UOM
                    const uom = data.uom_code || '';
                    const qtyLabel = row.querySelector('.qty-label');
                    if (qtyLabel && uom) {
                        qtyLabel.innerHTML = 'Quantity (' + uom + ') <span class="text-danger">*</span>';
                    }

                    // Auto-suggest / Auto-populate recommended active PLM if current line has no PLM selected
                    const plmSel = row.querySelector('select.ts-plm-ref');
                    if (data.recommended_plm && !data.recommended_plm.is_completed && plmSel && plmSel.tomselect && !plmSel.tomselect.getValue()) {
                        const rec = data.recommended_plm;
                        if (!plmSel.tomselect.options[rec.plm_id]) {
                            plmSel.tomselect.addOption({ id: rec.plm_id, text: rec.text });
                        }
                        plmSel.tomselect.setValue(rec.plm_id);
                    }

                    // Build Specs Badges
                    let badgesHtml = '<span class="badge bg-dark pc-mono"><i class="fas fa-barcode me-1"></i>' + data.item_number + '</span> ';
                    if (uom) {
                        badgesHtml += '<span class="badge bg-primary"><i class="fas fa-ruler me-1"></i>UOM: ' + uom + '</span> ';
                    }
                    if (data.specs) {
                        if (data.specs.composition) badgesHtml += '<span class="badge bg-white text-dark border"><i class="fas fa-layer-group me-1 text-primary"></i>' + data.specs.composition + '</span> ';
                        if (data.specs.construction) badgesHtml += '<span class="badge bg-white text-dark border"><i class="fas fa-th me-1 text-primary"></i>' + data.specs.construction + '</span> ';
                        if (data.specs.width) badgesHtml += '<span class="badge bg-white text-dark border"><i class="fas fa-arrows-alt-h me-1 text-primary"></i>' + data.specs.width + '</span> ';
                        if (data.specs.weight) badgesHtml += '<span class="badge bg-white text-dark border"><i class="fas fa-weight-hanging me-1 text-primary"></i>' + data.specs.weight + '</span> ';
                        if (data.specs.weave) badgesHtml += '<span class="badge bg-white text-dark border"><i class="fas fa-stream me-1 text-primary"></i>' + data.specs.weave + '</span> ';
                    }

                    // Recommended Active PLM Style Banner
                    let recPlmHtml = '';
                    if (data.recommended_plm && !data.recommended_plm.is_completed) {
                        const rec = data.recommended_plm;
                        recPlmHtml = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 pt-2 border-top small bg-light-subtle p-2 rounded border">' +
                                     '<div class="d-flex align-items-center gap-2">' +
                                     '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Latest Active Style</span> ' +
                                     '<strong class="text-dark">' + rec.text + '</strong>' +
                                     '</div>' +
                                     '<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fill-recommended-plm-btn" data-plm-id="' + rec.plm_id + '" data-plm-text="' + rec.text + '">' +
                                     '<i class="fas fa-check me-1"></i>Apply Active PLM</button>' +
                                     '</div>';
                    }

                    // Last PO pricing & PLM suggestion
                    let historyHtml = '';
                    if (data.last_po) {
                        const po = data.last_po;
                        const statusBadge = po.is_completed 
                            ? '<span class="badge bg-secondary-subtle text-secondary border ms-1"><i class="fas fa-flag-checkered me-1"></i>PO Batch Finished</span>'
                            : '<span class="badge bg-success-subtle text-success border ms-1"><i class="fas fa-clock me-1"></i>Active</span>';

                        historyHtml += '<div class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-2 border-top small">';
                        historyHtml += '<span><i class="fas fa-history me-1 text-primary"></i><strong>Last PO (' + po.po_number + '):</strong> ' + 
                                       '<span class="fw-bold text-success">' + po.unit_price_formatted + ' ' + po.currency + '</span> / ' + po.uom + statusBadge + '</span>';

                        historyHtml += '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fill-price-btn" data-price="' + po.unit_price + '">' +
                                       '<i class="fas fa-magic me-1"></i>Apply Price (' + po.unit_price_formatted + ')</button>';

                        if (po.plm_id && po.plm_text && !data.recommended_plm) {
                            historyHtml += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fill-plm-btn" data-plm-id="' + po.plm_id + '" data-plm-text="' + po.plm_text + '">' +
                                           '<i class="fas fa-tag me-1"></i>Apply PLM (' + po.plm_text + ')</button>';
                        }
                        historyHtml += '</div>';
                    }

                    // FDR panel if available
                    let fdrHtml = '';
                    if (data.fdr) {
                        const fdr = data.fdr;
                        fdrHtml += '<div class="text-secondary mt-1 small"><i class="fas fa-file-alt me-1 text-warning"></i><strong>FDR (' + fdr.fdr_number + '):</strong> ' +
                                   [fdr.composition, fdr.construction, fdr.hs_code ? ('HS ' + fdr.hs_code) : null,
                                    (fdr.price ? (fdr.price + ' ' + (fdr.currency || '')) : null)].filter(Boolean).join(' · ') +
                                   '</div>';
                    }

                    // Item Name Header on top of badges
                    let nameHtml = '';
                    if (data.item_name) {
                        nameHtml = '<div class="fw-bold text-dark mb-1 pb-1 border-bottom d-flex align-items-center gap-2 small">' +
                                   '<i class="fas fa-cube text-primary"></i>' +
                                   '<span>' + data.item_name + '</span>' +
                                   '</div>';
                    }

                    panel.innerHTML = '<div class="alert alert-light border shadow-sm p-2 mb-0 mt-2 rounded">' +
                                      nameHtml +
                                      '<div class="d-flex flex-wrap gap-1 align-items-center mt-1">' + badgesHtml + '</div>' +
                                      recPlmHtml +
                                      historyHtml + 
                                      fdrHtml + 
                                      '</div>';

                    // Attach handlers for auto-fill buttons
                    panel.querySelector('.fill-price-btn')?.addEventListener('click', function () {
                        const priceInput = row.querySelector('.line-price-input');
                        if (priceInput) {
                            priceInput.value = this.dataset.price;
                            priceInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });

                    panel.querySelector('.fill-recommended-plm-btn')?.addEventListener('click', function () {
                        const plmSel = row.querySelector('select.ts-plm-ref');
                        if (plmSel && plmSel.tomselect) {
                            const plmId = this.dataset.plmId;
                            const plmText = this.dataset.plmText;
                            if (!plmSel.tomselect.options[plmId]) {
                                plmSel.tomselect.addOption({ id: plmId, text: plmText });
                            }
                            plmSel.tomselect.setValue(plmId);
                        }
                    });

                    panel.querySelector('.fill-plm-btn')?.addEventListener('click', function () {
                        const plmSel = row.querySelector('select.ts-plm-ref');
                        if (plmSel && plmSel.tomselect) {
                            const plmId = this.dataset.plmId;
                            const plmText = this.dataset.plmText;
                            if (!plmSel.tomselect.options[plmId]) {
                                plmSel.tomselect.addOption({ id: plmId, text: plmText });
                            }
                            plmSel.tomselect.setValue(plmId);
                        }
                    });
                })
                .catch(() => {
                    panel.innerHTML = '';
                });
        }

        document.getElementById('linesContainer').querySelectorAll('.line-row').forEach(function (row) {
            initPickers(row);
            const itemSel = row.querySelector('select.ts-fabric-item');
            if (itemSel && itemSel.value) {
                setTimeout(() => fetchItemDetails(row), 200);
            }
        });

        document.querySelectorAll('.addLineBtn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tpl = document.getElementById('lineRowTemplate').innerHTML.replace(/__INDEX__/g, lineCounter++);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = tpl;
                const row = wrapper.firstElementChild;
                document.getElementById('linesContainer').appendChild(row);
                initPickers(row);
                recalculateTotals();
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        document.getElementById('linesContainer').addEventListener('click', function (e) {
            if (e.target.closest('.removeLineBtn')) {
                const row = e.target.closest('.line-row');
                row.remove();
                recalculateTotals();
            }
        });

        // Deliveries management
        document.querySelector('.addDeliveryBtn')?.addEventListener('click', function () {
            const tpl = document.getElementById('deliveryTemplate').innerHTML.replace(/__DINDEX__/g, deliveryCounter++);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = tpl;
            document.getElementById('deliveriesContainer').appendChild(wrapper.firstElementChild);
        });

        document.getElementById('deliveriesContainer')?.addEventListener('click', function (e) {
            if (e.target.closest('.removeDeliveryBtn')) {
                const items = document.querySelectorAll('.delivery-item');
                if (items.length > 1) {
                    e.target.closest('.delivery-item').remove();
                } else {
                    alert('At least one delivery schedule row is required.');
                }
            }
        });

        document.getElementById('linesContainer').addEventListener('change', function (e) {
            if (e.target.matches('select.ts-fabric-item') || e.target.tomselect) {
                const row = e.target.closest('.line-row');
                if (row) setTimeout(() => fetchItemDetails(row), 50);
            }
        });
    })();
</script>
@endpush
