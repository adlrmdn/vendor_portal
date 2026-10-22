{{--
    Shared create/edit form. One <form>, header fields + dynamic line
    rows, one submit — same single-form shape as admin/process-item.blade.php.
    Unlike that page's roll rows, contract lines have no physical/printed
    identity while in draft, so rows are plain add/remove (no soft-delete
    -with-undo complexity needed) and the server replaces the whole line
    set on every save (see PcContractController::syncLines()).
--}}
@php
    $isEdit = isset($contract);
    $lines = $isEdit ? $contract->lines : collect();
@endphp

{{-- Turns Vendor / HS Code / the header reference selects into type-to-
     search comboboxes — load-bearing for Vendor especially, a plain
     scroll-list is a rough way to find one of ~100+ fabric vendors. --}}
@include('partials.searchable-select')

<form method="POST" action="{{ $isEdit ? route('admin.pc.contracts.update', $contract) : route('admin.pc.contracts.store') }}" id="pcContractForm">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if (session('errors'))
        <div class="alert alert-danger">
            <strong>Cannot submit yet:</strong>
            <ul class="mb-0">
                @foreach (session('errors') as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('warnings') && count(session('warnings')))
        <div class="alert alert-warning">
            <ul class="mb-0">
                @foreach (session('warnings') as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pc-card mb-3">
        <div class="pc-card-head">Header</div>
        <div class="pc-card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Vendor</label>
                <select name="vendor_id" id="vendor_id" class="form-select searchable" required data-no-empty-option {{ $isEdit ? 'data-no-clear' : '' }}>
                    <option value="">Select vendor…</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}" {{ (old('vendor_id', $contract->vendor_id ?? '')) === $v->id ? 'selected' : '' }}>{{ $v->name }} ({{ $v->vendor_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Fabric Type</label>
                <select name="fabric_type" class="form-select" required>
                    <option value="">Select…</option>
                    @foreach (['WOVEN','KNIT'] as $ft)
                        <option value="{{ $ft }}" {{ old('fabric_type', $contract->fabric_type ?? '') === $ft ? 'selected' : '' }}>{{ $ft }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Contract Date</label>
                <input type="date" name="contract_date" class="form-control" max="{{ now()->toDateString() }}"
                    value="{{ old('contract_date', isset($contract) && $contract->contract_date ? $contract->contract_date->toDateString() : now()->toDateString()) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Season</label>
                <select name="season_id" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($seasons as $s)
                        <option value="{{ $s->id }}" {{ old('season_id', $contract->season_id ?? '') === $s->id ? 'selected' : '' }}>{{ $s->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Order Type</label>
                <select name="order_type_id" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($orderTypes as $o)
                        <option value="{{ $o->id }}" {{ old('order_type_id', $contract->order_type_id ?? '') === $o->id ? 'selected' : '' }}>{{ $o->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mode of Transport</label>
                <select name="transport_mode_id" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($transportModes as $t)
                        <option value="{{ $t->id }}" {{ old('transport_mode_id', $contract->transport_mode_id ?? '') === $t->id ? 'selected' : '' }}>{{ $t->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Terms of Delivery</label>
                <select name="delivery_term_id" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($deliveryTerms as $d)
                        <option value="{{ $d->id }}" {{ old('delivery_term_id', $contract->delivery_term_id ?? '') === $d->id ? 'selected' : '' }}>{{ $d->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">VAT / PPN</label>
                <select name="vat_treatment_id" id="vat_treatment_id" class="form-select searchable">
                    <option value="">Select…</option>
                    @foreach ($vatTreatments as $v)
                        <option value="{{ $v->id }}" data-code="{{ $v->code }}" {{ old('vat_treatment_id', $contract->vat_treatment_id ?? '') === $v->id ? 'selected' : '' }}>{{ $v->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3" id="vatRateWrapper">
                <label class="form-label">VAT Rate (%)</label>
                <input type="number" step="0.01" name="vat_rate" id="vat_rate" class="form-control"
                    value="{{ old('vat_rate', $contract->vat_rate ?? \App\Models\Setting::getValue('pc_default_vat_rate', '11.00')) }}">
            </div>
            <div class="col-12">
                <label class="form-label">Internal Remarks (not printed)</label>
                <textarea name="internal_remarks" class="form-control" rows="2">{{ old('internal_remarks', $contract->internal_remarks ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="pc-card mb-3">
        <div class="pc-card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Line Items</span>
            <button type="button" class="btn btn-sm pc-btn-primary addLineBtn"><i class="fas fa-plus me-1"></i>Add Line</button>
        </div>
        <div class="pc-card-body">
            <div id="linesContainer">
                @foreach ($lines as $i => $line)
                    @include('admin.pc.contracts.partials.line-row', ['index' => $i, 'line' => $line])
                @endforeach
            </div>
            {{-- Mirrors the header's Add Line button — on a contract with
                 many lines, scrolling all the way back up just to add one
                 more is the kind of friction nobody notices until they're
                 the one doing it a dozen times. --}}
            <button type="button" class="btn btn-sm btn-outline-secondary addLineBtn mt-2"><i class="fas fa-plus me-1"></i>Add Line</button>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ $isEdit ? route('admin.pc.contracts.show', $contract) : route('admin.pc.contracts.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn pc-btn-primary">Save Draft</button>
    </div>
</form>

<template id="lineRowTemplate">
    @include('admin.pc.contracts.partials.line-row', ['index' => '__INDEX__', 'line' => null])
</template>

@push('scripts')
<script>
    (function () {
        let lineCounter = {{ count($lines) }};

        // VAT Rate only means anything when the treatment actually applies
        // VAT inline to the price (INCLUDE) — PcContractFreezeService only
        // ever reads it in that case. Showing an editable rate field
        // regardless of treatment invites "why am I filling this in?"; this
        // disables (not hides, so the layout doesn't jump) it otherwise.
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

        function initPickers(scope) {
            scope.querySelectorAll('select.ts-fabric-item').forEach(function (el) {
                if (el.tomselect) return;
                new TomSelect(el, {
                    valueField: 'id', labelField: 'text', searchField: 'text',
                    load: function (query, callback) {
                        fetch(@json(route('admin.pc.search.fabric-items')) + '?q=' + encodeURIComponent(query))
                            .then(r => r.json()).then(data => callback(data)).catch(() => callback());
                    },
                });
            });
            scope.querySelectorAll('select.ts-plm-ref').forEach(function (el) {
                if (el.tomselect) return;
                new TomSelect(el, {
                    valueField: 'id', labelField: 'text', searchField: 'text',
                    load: function (query, callback) {
                        fetch(@json(route('admin.pc.search.plm-refs')) + '?q=' + encodeURIComponent(query))
                            .then(r => r.json()).then(data => callback(data)).catch(() => callback());
                    },
                });
            });
            // partials/searchable-select.blade.php only scans the page once
            // on DOMContentLoaded, so a line row added later (a fresh
            // template render, not a clone — safe to enhance) needs its own
            // HS Code combobox wired up the same way by hand.
            scope.querySelectorAll('select.searchable').forEach(function (el) {
                if (el.tomselect) return;
                new TomSelect(el, {
                    allowEmptyOption: !el.hasAttribute('data-no-empty-option'),
                    maxOptions: null,
                    placeholder: el.getAttribute('placeholder') || undefined,
                    plugins: el.hasAttribute('data-no-clear') ? [] : ['clear_button'],
                });
            });
        }

        function fetchFdr(row) {
            const itemSel = row.querySelector('select.ts-fabric-item');
            const vendorSel = document.getElementById('vendor_id');
            const panel = row.querySelector('.fdr-panel');
            if (!itemSel || !itemSel.tomselect || !vendorSel || !panel) return;
            const value = itemSel.tomselect.getValue();
            if (!value || !vendorSel.value) { panel.innerHTML = ''; return; }
            // Tom Select caches the full JSON object the `load:` callback
            // returned (including item_number, not just id/text) keyed by
            // value — the underlying native <option> only ever gets
            // id/text synced onto it, so this cache is the only place
            // item_number is still reachable client-side.
            const cached = itemSel.tomselect.options[value];
            const itemNumber = cached && cached.item_number ? cached.item_number : '';
            if (!itemNumber) { panel.innerHTML = ''; return; }
            fetch(@json(route('admin.pc.search.fdr-lookup')) + '?item_number=' + encodeURIComponent(itemNumber) + '&vendor_id=' + encodeURIComponent(vendorSel.value))
                .then(r => r.json())
                .then(function (fdr) {
                    if (!fdr) { panel.innerHTML = ''; return; }
                    panel.innerHTML = '<div class="alert alert-light border small mb-0 mt-2">' +
                        '<strong>FDR reference (' + fdr.fdr_number + ', not binding):</strong> ' +
                        [fdr.composition, fdr.construction, fdr.hs_code ? ('HS ' + fdr.hs_code) : null,
                         (fdr.price ? (fdr.price + ' ' + (fdr.currency || '')) : null)].filter(Boolean).join(' · ') +
                        '</div>';
                })
                .catch(() => {});
        }

        document.getElementById('linesContainer').querySelectorAll('.line-row').forEach(initPickers.bind(null));

        document.querySelectorAll('.addLineBtn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tpl = document.getElementById('lineRowTemplate').innerHTML.replace(/__INDEX__/g, lineCounter++);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = tpl;
                const row = wrapper.firstElementChild;
                document.getElementById('linesContainer').appendChild(row);
                initPickers(row);
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        document.getElementById('linesContainer').addEventListener('click', function (e) {
            if (e.target.closest('.removeLineBtn')) {
                e.target.closest('.line-row').remove();
            }
            if (e.target.closest('.removeDeliveryBtn')) {
                const rows = e.target.closest('.delivery-rows');
                // Always keep at least one delivery row per line (PC-H08).
                if (rows.querySelectorAll('.delivery-row').length > 1) {
                    e.target.closest('.delivery-row').remove();
                }
            }
            if (e.target.closest('.addDeliveryBtn')) {
                const lineRow = e.target.closest('.line-row');
                const lineIndex = lineRow.dataset.index;
                const deliveryRows = lineRow.querySelectorAll('.delivery-row');
                const dIndex = deliveryRows.length;
                const first = deliveryRows[0];
                const clone = first.cloneNode(true);
                clone.querySelectorAll('input, select').forEach(function (field) {
                    field.name = field.name.replace(/\[deliveries\]\[\d+\]/, '[deliveries][' + dIndex + ']');
                    if (field.tagName === 'SELECT') { field.selectedIndex = 0; } else { field.value = ''; }
                });
                lineRow.querySelector('.delivery-rows').appendChild(clone);
            }
        });

        document.getElementById('linesContainer').addEventListener('change', function (e) {
            if (e.target.matches('select.ts-fabric-item') || e.target.tomselect) {
                const row = e.target.closest('.line-row');
                if (row) setTimeout(() => fetchFdr(row), 50);
            }
        });
    })();
</script>
@endpush
