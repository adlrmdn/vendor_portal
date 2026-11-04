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
    @elseif (isset($errors) && $errors->any())
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
    {{-- Header Section --}}
    <div class="apple-card mb-4">
        <div class="apple-card-header d-flex justify-content-between align-items-center">
            <span class="apple-card-title"><i class="fas fa-file-contract text-secondary me-2"></i>Contract Terms &amp; Header</span>
            <span class="text-muted small">* Required for submission</span>
        </div>
        <div class="apple-card-body p-3 p-md-4 row g-3">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Supplier / Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vendor_id" class="form-select searchable" required data-no-empty-option {{ $isEdit ? 'data-no-clear' : '' }}>
                    <option value="">Search supplier by name or V-code…</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}"
                                data-currency="{{ $v->currency_code }}"
                                data-payment-term="{{ $v->paymentTerm?->label ?? '' }}"
                                data-payment-term-id="{{ $v->payment_term_id }}"
                                {{ (old('vendor_id', $contract->vendor_id ?? '')) === $v->id ? 'selected' : '' }}>
                            {{ $v->name }} ({{ $v->vendor_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Currency <span class="text-danger">*</span></label>
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
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Terms of Payment <span class="text-danger">*</span></label>
                <select name="payment_term_id" id="payment_term_id" class="form-select" required>
                    <option value="">Select payment terms…</option>
                    @foreach ($paymentTerms as $pt)
                        <option value="{{ $pt->id }}"
                            {{ (old('payment_term_id', $contract->payment_term_id ?? '')) === $pt->id ? 'selected' : '' }}>
                            {{ $pt->code }} - {{ $pt->label ?? $pt->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Contract Date <span class="text-danger">*</span></label>
                <input type="date" name="contract_date" class="form-control" max="{{ now()->toDateString() }}"
                    value="{{ old('contract_date', isset($contract) && $contract->contract_date ? $contract->contract_date->toDateString() : now()->toDateString()) }}" required>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Season <span class="text-danger">*</span></label>
                <select name="season_id" class="form-select" required>
                    <option value="">Select season…</option>
                    @foreach ($seasons as $s)
                        <option value="{{ $s->id }}" {{ old('season_id', $contract->season_id ?? '') === $s->id ? 'selected' : '' }}>{{ $s->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Order Type <span class="text-danger">*</span></label>
                <select name="order_type_id" class="form-select" required>
                    <option value="">Select order type…</option>
                    @foreach ($orderTypes as $o)
                        <option value="{{ $o->id }}" {{ old('order_type_id', $contract->order_type_id ?? '') === $o->id ? 'selected' : '' }}>{{ $o->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Mode of Transport <span class="text-danger">*</span></label>
                <select name="transport_mode_id" class="form-select" required>
                    <option value="">Select transport…</option>
                    @foreach ($transportModes as $t)
                        <option value="{{ $t->id }}" {{ old('transport_mode_id', $contract->transport_mode_id ?? '') === $t->id ? 'selected' : '' }}>{{ $t->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Terms of Delivery <span class="text-danger">*</span></label>
                <select name="delivery_term_id" class="form-select" required>
                    <option value="">Select delivery term…</option>
                    @foreach ($deliveryTerms as $d)
                        <option value="{{ $d->id }}" {{ old('delivery_term_id', $contract->delivery_term_id ?? '') === $d->id ? 'selected' : '' }}>{{ $d->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">VAT / PPN <span class="text-danger">*</span></label>
                <select name="vat_treatment_id" id="vat_treatment_id" class="form-select" required>
                    <option value="">Select treatment…</option>
                    @foreach ($vatTreatments as $v)
                        <option value="{{ $v->id }}" data-code="{{ $v->code }}" {{ old('vat_treatment_id', $contract->vat_treatment_id ?? '') === $v->id ? 'selected' : '' }}>{{ $v->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3" id="vatRateWrapper">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">VAT Rate (%)</label>
                <input type="number" step="0.01" name="vat_rate" id="vat_rate" class="form-control"
                    value="{{ old('vat_rate', $contract->vat_rate ?? \App\Models\Setting::getValue('pc_default_vat_rate', '11.00')) }}">
            </div>

            <div class="col-md-12">
                <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.03em;">Internal Remarks <span class="text-muted fw-normal text-lowercase">(internal only, not printed in contract)</span></label>
                <textarea name="internal_remarks" class="form-control" rows="2" placeholder="Internal negotiation, background notes, or audit trail…">{{ old('internal_remarks', $contract->internal_remarks ?? '') }}</textarea>
                <input type="hidden" name="additional_notes" value="{{ old('additional_notes', $contract->additional_notes ?? '') }}">
            </div>

            {{-- Fabric Delivery Schedule (Spec S4 Header) --}}
            <div class="col-12 border-top pt-3" style="border-color: #e5e5ea !important;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-0" style="font-size: 0.74rem; letter-spacing: 0.03em;"><i class="fas fa-truck text-secondary me-1"></i>Fabric Delivery Schedule <span class="text-danger">*</span></label>
                    <button type="button" class="btn btn-sm apple-btn-secondary addDeliveryBtn">+ Add delivery date</button>
                </div>
                <div id="deliveriesContainer" class="row g-2">
                    @forelse($deliveries as $di => $del)
                        <div class="col-md-4 delivery-item">
                            <div class="d-flex align-items-center gap-2">
                                <select name="deliveries[{{ $di }}][lot_id]" class="form-select form-select-sm" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 100px;">
                                    <option value="">Lot…</option>
                                    @foreach($lots as $lot)
                                        <option value="{{ $lot->id }}" {{ $del->lot_id === $lot->id ? 'selected' : '' }}>Lot {{ $lot->label ?? $lot->code }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="deliveries[{{ $di }}][eta_date]" class="form-control form-control-sm" value="{{ $del->eta_date?->toDateString() }}" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 130px;">
                                <button type="button" class="btn btn-sm btn-outline-danger removeDeliveryBtn flex-shrink-0" style="border: 1px solid #ff3b30; color: #ff3b30; border-radius: 8px; width: 34px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="Remove delivery date"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    @empty
                        <div class="col-md-4 delivery-item">
                            <div class="d-flex align-items-center gap-2">
                                <select name="deliveries[0][lot_id]" class="form-select form-select-sm" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 100px;">
                                    <option value="">Lot…</option>
                                    @foreach($lots as $lot)
                                        <option value="{{ $lot->id }}">Lot {{ $lot->label ?? $lot->code }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="deliveries[0][eta_date]" class="form-control form-control-sm" value="{{ now()->addMonth()->toDateString() }}" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 130px;">
                                <button type="button" class="btn btn-sm btn-outline-danger removeDeliveryBtn flex-shrink-0" style="border: 1px solid #ff3b30; color: #ff3b30; border-radius: 8px; width: 34px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="Remove delivery date"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Section --}}
    <div class="apple-card mb-4">
        <div class="apple-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="apple-card-title"><i class="fas fa-layer-group text-secondary me-2"></i>Ordered Lines</span>
                <span class="badge bg-light text-secondary border ms-2" id="linesCountTally">({{ count($lines) }} line{{ count($lines) === 1 ? '' : 's' }})</span>
            </div>
            <button type="button" class="btn btn-sm apple-btn-primary addLineBtn"><i class="fas fa-plus me-1"></i>Add Line</button>
        </div>
        <div class="apple-card-body p-3 p-md-4">
            <div id="linesContainer">
                @forelse ($lines as $i => $line)
                    @include('admin.pc.contracts.partials.line-row', ['index' => $i, 'line' => $line])
                @empty
                    @include('admin.pc.contracts.partials.line-row', ['index' => 0, 'line' => null])
                @endforelse
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top flex-wrap gap-2" style="border-color: #e5e5ea !important;">
                <button type="button" class="btn btn-sm apple-btn-secondary addLineBtn"><i class="fas fa-plus me-1"></i>Add Another Line</button>
                <div class="pc-mono fw-bold fs-6" id="runningTotalDisplay" style="color: #1d1d1f;">
                    Total: <span id="summaryTotalQty" class="text-primary">0.00</span> | <span id="summaryTotalValue" class="text-success">0.00</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Notes & Standard Clauses Section --}}
    @php
        $clausesList = collect();
        if ($isEdit && $contract->notes->isNotEmpty()) {
            $clausesList = $contract->notes->sortBy('clause_number')->values();
        } elseif (isset($noteTemplates) && $noteTemplates->isNotEmpty()) {
            $clausesList = $noteTemplates->sortBy('clause_number')->values();
        } else {
            $standardDefaults = [
                ['body' => 'THIS CONTRACT IS FOR BOOKING YARN ONLY. DO NOT DYE YARN UNLESS YOU HAVE THE PO IN HAND FROM PURCHASING WITH COLOR BREAKDOWN', 'emphasis' => false],
                ['body' => 'PRODUCTION OF FINISH FABRIC SHOULD PROCEED BASED ON QUANTITY ,COLOR BREAKDOWN AND APPROVAL IN PURCHASE ORDER.', 'emphasis' => false],
                ['body' => "MILL HAS TO PROVIDE FOLLOWING DOCS PRIOR TO SHIPMENT :\n*INTERNAL FABRIC TEST REPORT\n*SHADEBAND/SHADELOT\n*INTERNAL 100% FABRIC INSPECTION REPORT", 'emphasis' => false],
                ['body' => 'For Import & overseas shipment it is mandatory for all goods to go through a SGS Inspection at factory  before shipment cost of which is borne by the Buyer.', 'emphasis' => false],
                ['body' => 'The Specification and HS code of the fabric in this contract are stated as declared to us by the supplier.', 'emphasis' => false],
                ['body' => 'If SGS inspection finds non-conformity with the quantity , specification and HS of the goods the non-conformity will result in failure of shipment and extra cost will be incurred for shipment for which these costs are to be borne by the Supplier.', 'emphasis' => false],
                ['body' => 'ANY LATE TIMELINE/DELIVERY FROM COMMITMENT - AIR SHIPMENT MAY APPLY', 'emphasis' => true],
                ['body' => 'SHIPMENT TOLERANCE TO BE FOLLOWED. SHORTAGE MORE THAN DECLARED % - ACCESSORIES AND OR OTHER RELATED TRIMMING CLAIM WILL APPLY', 'emphasis' => true],
                ['body' => 'THIS CONTRACT IS ONLY VALID AFTER QUALITY IS APPROVED ONLY', 'emphasis' => true],
            ];
            $clausesList = collect($standardDefaults)->map(function ($item, $idx) {
                return (object)[
                    'clause_number' => $idx + 1,
                    'body' => $item['body'],
                    'emphasis' => $item['emphasis'],
                    'source_note_template_id' => null,
                ];
            });
        }
    @endphp

    <div class="apple-card mb-4">
        <div class="apple-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="apple-card-title"><i class="fas fa-file-signature text-secondary me-2"></i>Additional Notes &amp; Standard Clauses</span>
                <span class="badge bg-light text-secondary border ms-2" style="font-size: 0.72rem;">frozen at approval</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm apple-btn-secondary" id="resetClausesBtn" title="Reset back to standard 9 clauses">
                    <i class="fas fa-undo me-1"></i>Reset Standard
                </button>
                <button type="button" class="btn btn-sm apple-btn-primary" id="addClauseBtn">
                    <i class="fas fa-plus me-1"></i>Add Clause
                </button>
            </div>
        </div>
        <div class="apple-card-body p-3 p-md-4">
            <p class="text-muted small mb-3">
                All clauses below can be edited, deleted, re-ordered, or expanded with custom requirements (e.g. <em>10. Must be X specification</em>). When the contract is approved, these exact clauses become permanently frozen.
            </p>

            <div id="clausesContainer">
                @foreach ($clausesList as $cIdx => $clause)
                    @php
                        $cNo = $clause->clause_number ?? ($cIdx + 1);
                        $isEmp = (bool)($clause->emphasis ?? in_array($cNo, [7, 8, 9]));
                    @endphp
                    <div class="apple-clause-card {{ $isEmp ? 'clause-emphasis' : '' }}" data-clause-idx="{{ $cIdx }}">
                        <input type="hidden" name="clauses[{{ $cIdx }}][clause_number]" class="clause-number-input" value="{{ $cNo }}">
                        <input type="hidden" name="clauses[{{ $cIdx }}][source_note_template_id]" value="{{ $clause->source_note_template_id ?? ($clause->id ?? '') }}">
                        <input type="hidden" name="clauses[{{ $cIdx }}][emphasis]" class="clause-emphasis-input" value="{{ $isEmp ? '1' : '0' }}">

                        <div class="d-flex align-items-start gap-2">
                            <span class="clause-num-badge">{{ $cNo }}</span>
                            <div class="flex-grow-1">
                                <textarea name="clauses[{{ $cIdx }}][body]" class="form-control form-control-sm clause-body-textarea" rows="2" placeholder="Enter clause text..." required style="resize: vertical; font-size: 0.85rem; line-height: 1.4; border-radius: 8px;">{{ $clause->body }}</textarea>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <button type="button" class="btn btn-sm toggle-clause-emphasis-btn {{ $isEmp ? 'btn-danger' : 'btn-outline-secondary' }}" style="font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;" title="Toggle Critical/Warning Priority">
                                    <i class="fas fa-exclamation-triangle me-1"></i>{{ $isEmp ? 'Critical' : 'Standard' }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-clause-btn" style="border-radius: 6px; padding: 3px 7px;" title="Remove this clause">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" style="border-color: #e5e5ea !important;">
                <button type="button" class="btn btn-sm apple-btn-secondary" id="addClauseBottomBtn">
                    <i class="fas fa-plus me-1"></i>Add Another Clause
                </button>
                <span class="text-muted small" id="clausesCountTally">{{ count($clausesList) }} clauses configured</span>
            </div>
        </div>
    </div>

    {{-- Form Submit Bar --}}
    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top" style="border-color: #e5e5ea !important;">
        <a href="{{ $isEdit ? route('admin.pc.contracts.show', $contract) : route('admin.pc.contracts.index') }}" class="btn apple-btn-secondary px-4 py-2 fw-medium">
            <i class="fas fa-arrow-left me-1"></i>Cancel
        </a>
        <div class="d-flex gap-2">
            <button type="submit" class="btn apple-btn-primary px-4 py-2 fw-semibold">
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
        <div class="d-flex align-items-center gap-2">
            <select name="deliveries[__DINDEX__][lot_id]" class="form-select form-select-sm" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 100px;">
                <option value="">Lot…</option>
                @foreach($lots as $lot)
                    <option value="{{ $lot->id }}">Lot {{ $lot->label ?? $lot->code }}</option>
                @endforeach
            </select>
            <input type="date" name="deliveries[__DINDEX__][eta_date]" class="form-control form-control-sm" value="{{ now()->addMonth()->toDateString() }}" required style="border: 1px solid #d2d2d7; border-radius: 8px; font-size: 0.84rem; flex: 1 1 45%; min-width: 130px;">
            <button type="button" class="btn btn-sm btn-outline-danger removeDeliveryBtn flex-shrink-0" style="border: 1px solid #ff3b30; color: #ff3b30; border-radius: 8px; width: 34px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" title="Remove delivery date"><i class="fas fa-times"></i></button>
        </div>
    </div>
</template>

@push('scripts')
<script>
    (function () {
        let lineCounter = {{ count($lines) }};
        let deliveryCounter = {{ max(count($deliveries), 1) }};

        const vendorDataMap = {
            @foreach ($vendors as $v)
                "{{ $v->id }}": {
                    currency: "{{ $v->currency_code }}",
                    paymentTermId: "{{ $v->payment_term_id }}",
                    isLocal: {{ $v->isLocal() ? 'true' : 'false' }}
                },
            @endforeach
        };

        // Vendor Currency, Payment Term & Local/Import HS Code Requirement auto-reflection
        function syncVendorDetails() {
            const vendorSel = document.getElementById('vendor_id');
            const currencySel = document.getElementById('currencySelect');
            const paymentSel = document.getElementById('payment_term_id');
            if (!vendorSel) return;

            const selectedVendorId = vendorSel.value;
            const vData = vendorDataMap[selectedVendorId];
            if (vData) {
                let cur = (vData.currency || '').toUpperCase();
                if (cur === 'CNY') cur = 'RMB';
                if (currencySel && cur) {
                    for (let i = 0; i < currencySel.options.length; i++) {
                        if (currencySel.options[i].dataset.code === cur || currencySel.options[i].text.startsWith(cur)) {
                            currencySel.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (paymentSel && vData.paymentTermId) {
                    paymentSel.value = vData.paymentTermId;
                }
            }

            const isLocal = vData ? !!vData.isLocal : false;
            document.querySelectorAll('.hs-code-select').forEach(sel => {
                if (isLocal) {
                    sel.removeAttribute('required');
                } else {
                    sel.setAttribute('required', 'required');
                }
            });
            document.querySelectorAll('.hs-code-asterisk').forEach(ast => {
                ast.style.display = isLocal ? 'none' : '';
            });
        }
        document.getElementById('vendor_id')?.addEventListener('change', function () {
            syncVendorDetails();
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

        // Live calculation of line totals, line numbers, and running totals
        function renumberLines() {
            const rows = document.querySelectorAll('.line-row');
            rows.forEach((row, idx) => {
                row.dataset.index = idx;
                const label = row.querySelector('.line-number-label');
                if (label) label.textContent = 'Line ' + (idx + 1);

                row.querySelectorAll('select, input').forEach(input => {
                    if (input.name && input.name.startsWith('lines[')) {
                        input.name = input.name.replace(/^lines\[[^\]]+\]/, 'lines[' + idx + ']');
                    }
                });

                const removeBtn = row.querySelector('.removeLineBtn');
                if (removeBtn) {
                    removeBtn.disabled = (rows.length === 1);
                    removeBtn.classList.toggle('opacity-50', rows.length === 1);
                }
            });

            const tally = document.getElementById('linesCountTally');
            if (tally) tally.textContent = '(' + rows.length + ' line' + (rows.length === 1 ? '' : 's') + ')';
        }

        function recalculateTotals() {
            let totalQty = 0;
            let totalVal = 0;
            const rows = document.querySelectorAll('.line-row');
            const currencySel = document.getElementById('currencySelect');
            const currencyCode = currencySel?.options[currencySel.selectedIndex]?.dataset?.code || currencySel?.value || '';

            rows.forEach(function (row, idx) {
                const qtyInput = row.querySelector('.line-qty-input');
                const priceInput = row.querySelector('.line-price-input');
                const badge = row.querySelector('.line-total-badge');
                const lineTotalInput = row.querySelector('.line-subtotal-display');

                const q = parseFloat(qtyInput?.value || 0);
                const p = parseFloat(priceInput?.value || 0);
                const lineTotal = (q > 0 && p > 0) ? (q * p) : 0;

                const formattedTotal = lineTotal > 0 
                    ? lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '0.00';

                if (badge) {
                    badge.textContent = lineTotal > 0 ? ('= ' + (currencyCode ? currencyCode + ' ' : '') + formattedTotal) : '';
                }
                if (lineTotalInput) {
                    lineTotalInput.value = (currencyCode ? currencyCode + ' ' : '') + formattedTotal;
                }

                totalQty += q;
                totalVal += lineTotal;
            });

            const dispQty = document.getElementById('summaryTotalQty');
            const dispVal = document.getElementById('summaryTotalValue');
            if (dispQty) dispQty.textContent = totalQty.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (dispVal) dispVal.textContent = (currencyCode ? currencyCode + ' ' : '') + totalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.getElementById('linesContainer')?.addEventListener('input', function (e) {
            if (e.target.classList.contains('line-qty-input') || e.target.classList.contains('line-price-input')) {
                recalculateTotals();
            }
        });
        document.getElementById('currencySelect')?.addEventListener('change', function () {
            this.dataset.userModified = 'true';
            recalculateTotals();
        });
        setTimeout(function() {
            renumberLines();
            recalculateTotals();
        }, 150);

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
                ts.on('change', function (val) {
                    const row = el.closest('.line-row');
                    if (row) {
                        if (!val) {
                            delete row.dataset.loadedFabricId;
                            const panel = row.querySelector('.item-details-panel');
                            if (panel) panel.innerHTML = '';
                            const qtyLabel = row.querySelector('.qty-label');
                            if (qtyLabel) qtyLabel.innerHTML = 'Quantity <span class="text-danger">*</span>';
                            return;
                        }
                        fetchItemDetails(row);
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
                delete row.dataset.loadedFabricId;
                panel.innerHTML = '';
                const qtyLabel = row.querySelector('.qty-label');
                if (qtyLabel) qtyLabel.innerHTML = 'Quantity <span class="text-danger">*</span>';
                return;
            }

            // Guard against redundant/looping fetches for the exact same item
            if (row.dataset.loadedFabricId === value && panel.innerHTML.trim() !== '') {
                return;
            }
            row.dataset.loadedFabricId = value;

            const cached = itemSel.tomselect.options[value] || {};
            const itemNumber = cached.item_number || '';
            const vendorId = vendorSel ? vendorSel.value : '';

            if (!panel.innerHTML.trim()) {
                panel.innerHTML = '<div class="text-muted small py-2"><span class="spinner-border spinner-border-sm me-1 text-primary"></span>Loading item specifications & pricing history…</div>';
            }

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

                    // Auto-suggest recommended active PLM if current line has no PLM selected (silent = true to avoid event loop)
                    const plmSel = row.querySelector('select.ts-plm-ref');
                    if (data.recommended_plm && !data.recommended_plm.is_completed && plmSel && plmSel.tomselect && !plmSel.tomselect.getValue()) {
                        const rec = data.recommended_plm;
                        if (!plmSel.tomselect.options[rec.plm_id]) {
                            plmSel.tomselect.addOption({ id: rec.plm_id, text: rec.text });
                        }
                        plmSel.tomselect.setValue(rec.plm_id, true);
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

        // Sync line lot dropdown options to match the lots currently in Fabric Delivery Schedule
        function getAvailableDeliveryLots() {
            const lots = [];
            const seen = new Set();
            document.querySelectorAll('#deliveriesContainer .delivery-item select[name*="lot_id"]').forEach(function (sel) {
                const val = sel.value;
                if (val && !seen.has(val)) {
                    seen.add(val);
                    const text = sel.options[sel.selectedIndex]?.text || ('Lot ' + val);
                    lots.push({ id: val, text: text });
                }
            });
            return lots;
        }

        function syncLineLotOptions(targetRow = null) {
            const lots = getAvailableDeliveryLots();
            const rows = targetRow ? [targetRow] : document.querySelectorAll('#linesContainer .line-row');

            rows.forEach(function (row) {
                const lotSel = row.querySelector('.line-lot-select');
                if (!lotSel) return;

                const currentVal = lotSel.value;
                lotSel.innerHTML = '<option value="">Lot…</option>';

                lots.forEach(function (l) {
                    const opt = document.createElement('option');
                    opt.value = l.id;
                    opt.textContent = l.text;
                    lotSel.appendChild(opt);
                });

                // Preserve selection if still valid; if not valid or empty, auto-select first available lot
                if (currentVal && lots.some(l => l.id === currentVal)) {
                    lotSel.value = currentVal;
                } else if (lots.length > 0) {
                    lotSel.value = lots[0].id;
                } else {
                    lotSel.value = '';
                }
            });
        }

        document.getElementById('linesContainer').querySelectorAll('.line-row').forEach(function (row) {
            initPickers(row);
            const itemSel = row.querySelector('select.ts-fabric-item');
            if (itemSel && itemSel.value) {
                setTimeout(() => fetchItemDetails(row), 200);
            }
        });

        // Initial sync of line lot dropdowns on page load
        syncLineLotOptions();

        document.querySelectorAll('.addLineBtn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const newIdx = document.querySelectorAll('.line-row').length;
                let tpl = document.getElementById('lineRowTemplate').innerHTML;
                tpl = tpl.replace(/__INDEX__/g, lineCounter++);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = tpl;
                const row = wrapper.firstElementChild;
                document.getElementById('linesContainer').appendChild(row);

                // Populate and sync line lots from delivery schedule
                syncLineLotOptions(row);

                initPickers(row);
                syncVendorDetails();
                renumberLines();
                recalculateTotals();
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        document.getElementById('linesContainer').addEventListener('click', function (e) {
            if (e.target.closest('.removeLineBtn')) {
                const rows = document.querySelectorAll('.line-row');
                if (rows.length <= 1) {
                    return; // Retain at least 1 line
                }
                const row = e.target.closest('.line-row');
                row.remove();
                renumberLines();
                recalculateTotals();
            }
        });

        // Deliveries management & auto-sync to line lot selectors
        document.querySelector('.addDeliveryBtn')?.addEventListener('click', function () {
            const tpl = document.getElementById('deliveryTemplate').innerHTML.replace(/__DINDEX__/g, deliveryCounter++);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = tpl;
            const newDel = wrapper.firstElementChild;
            document.getElementById('deliveriesContainer').appendChild(newDel);
            syncLineLotOptions();
        });

        document.getElementById('deliveriesContainer')?.addEventListener('change', function (e) {
            if (e.target.matches('select[name*="lot_id"]')) {
                syncLineLotOptions();
            }
        });

        document.getElementById('deliveriesContainer')?.addEventListener('click', function (e) {
            if (e.target.closest('.removeDeliveryBtn')) {
                const items = document.querySelectorAll('.delivery-item');
                if (items.length > 1) {
                    e.target.closest('.delivery-item').remove();
                    syncLineLotOptions();
                } else {
                    alert('At least one delivery schedule row is required.');
                }
            }
        });

        // ==========================================
        // Clauses Management (Dynamic & Editable)
        // ==========================================
        const standardClausesData = [
            { text: "THIS CONTRACT IS FOR BOOKING YARN ONLY. DO NOT DYE YARN UNLESS YOU HAVE THE PO IN HAND FROM PURCHASING WITH COLOR BREAKDOWN", emphasis: false },
            { text: "PRODUCTION OF FINISH FABRIC SHOULD PROCEED BASED ON QUANTITY ,COLOR BREAKDOWN AND APPROVAL IN PURCHASE ORDER.", emphasis: false },
            { text: "MILL HAS TO PROVIDE FOLLOWING DOCS PRIOR TO SHIPMENT :\n*INTERNAL FABRIC TEST REPORT\n*SHADEBAND/SHADELOT\n*INTERNAL 100% FABRIC INSPECTION REPORT", emphasis: false },
            { text: "For Import & overseas shipment it is mandatory for all goods to go through a SGS Inspection at factory  before shipment cost of which is borne by the Buyer.", emphasis: false },
            { text: "The Specification and HS code of the fabric in this contract are stated as declared to us by the supplier.", emphasis: false },
            { text: "If SGS inspection finds non-conformity with the quantity , specification and HS of the goods the non-conformity will result in failure of shipment and extra cost will be incurred for shipment for which these costs are to be borne by the Supplier.", emphasis: false },
            { text: "ANY LATE TIMELINE/DELIVERY FROM COMMITMENT - AIR SHIPMENT MAY APPLY", emphasis: true },
            { text: "SHIPMENT TOLERANCE TO BE FOLLOWED. SHORTAGE MORE THAN DECLARED % - ACCESSORIES AND OR OTHER RELATED TRIMMING CLAIM WILL APPLY", emphasis: true },
            { text: "THIS CONTRACT IS ONLY VALID AFTER QUALITY IS APPROVED ONLY", emphasis: true }
        ];

        function renumberClauses() {
            const container = document.getElementById('clausesContainer');
            if (!container) return;
            const rows = container.querySelectorAll('.apple-clause-card');
            rows.forEach((row, idx) => {
                const cNo = idx + 1;
                row.setAttribute('data-clause-idx', idx);
                const badge = row.querySelector('.clause-num-badge');
                if (badge) badge.textContent = cNo;

                const numInput = row.querySelector('.clause-number-input');
                if (numInput) {
                    numInput.value = cNo;
                    numInput.name = `clauses[${idx}][clause_number]`;
                }
                const bodyText = row.querySelector('.clause-body-textarea');
                if (bodyText) {
                    bodyText.name = `clauses[${idx}][body]`;
                }
                const empInput = row.querySelector('.clause-emphasis-input');
                if (empInput) {
                    empInput.name = `clauses[${idx}][emphasis]`;
                }
                const srcInput = row.querySelector('input[name*="[source_note_template_id]"]');
                if (srcInput) {
                    srcInput.name = `clauses[${idx}][source_note_template_id]`;
                }
            });

            const tally = document.getElementById('clausesCountTally');
            if (tally) {
                tally.textContent = `${rows.length} clause${rows.length === 1 ? '' : 's'} configured`;
            }
        }

        function createClauseRow(text = '', isEmphasis = false, sourceTemplateId = '') {
            const container = document.getElementById('clausesContainer');
            if (!container) return null;
            const idx = container.querySelectorAll('.apple-clause-card').length;
            const cNo = idx + 1;

            const card = document.createElement('div');
            card.className = `apple-clause-card ${isEmphasis ? 'clause-emphasis' : ''}`;
            card.setAttribute('data-clause-idx', idx);

            const safeText = text.replace(/"/g, '&quot;');
            card.innerHTML = `
                <input type="hidden" name="clauses[${idx}][clause_number]" class="clause-number-input" value="${cNo}">
                <input type="hidden" name="clauses[${idx}][source_note_template_id]" value="${sourceTemplateId}">
                <input type="hidden" name="clauses[${idx}][emphasis]" class="clause-emphasis-input" value="${isEmphasis ? '1' : '0'}">
                <div class="d-flex align-items-start gap-2">
                    <span class="clause-num-badge">${cNo}</span>
                    <div class="flex-grow-1">
                        <textarea name="clauses[${idx}][body]" class="form-control form-control-sm clause-body-textarea" rows="2" placeholder="Enter clause text..." required style="resize: vertical; font-size: 0.85rem; line-height: 1.4; border-radius: 8px;">${text}</textarea>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <button type="button" class="btn btn-sm toggle-clause-emphasis-btn ${isEmphasis ? 'btn-danger' : 'btn-outline-secondary'}" style="font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;" title="Toggle Critical/Warning Priority">
                            <i class="fas fa-exclamation-triangle me-1"></i>${isEmphasis ? 'Critical' : 'Standard'}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-clause-btn" style="border-radius: 6px; padding: 3px 7px;" title="Remove this clause">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(card);
            renumberClauses();
            return card;
        }

        function setupClausesUI() {
            const addClause = function () {
                const card = createClauseRow('', false, '');
                if (card) {
                    const textarea = card.querySelector('.clause-body-textarea');
                    if (textarea) {
                        textarea.placeholder = "e.g. Must be X specification";
                        textarea.focus();
                    }
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            };

            document.getElementById('addClauseBtn')?.addEventListener('click', addClause);
            document.getElementById('addClauseBottomBtn')?.addEventListener('click', addClause);

            document.getElementById('resetClausesBtn')?.addEventListener('click', function () {
                if (confirm('Reset clauses to the standard 9 clauses? Customized clauses will be replaced.')) {
                    const container = document.getElementById('clausesContainer');
                    if (!container) return;
                    container.innerHTML = '';
                    standardClausesData.forEach(item => {
                        createClauseRow(item.text, item.emphasis, '');
                    });
                }
            });

            document.getElementById('clausesContainer')?.addEventListener('click', function (e) {
                // Remove clause
                const removeBtn = e.target.closest('.remove-clause-btn');
                if (removeBtn) {
                    const card = removeBtn.closest('.apple-clause-card');
                    if (card) {
                        card.remove();
                        renumberClauses();
                    }
                    return;
                }

                // Toggle emphasis
                const toggleBtn = e.target.closest('.toggle-clause-emphasis-btn');
                if (toggleBtn) {
                    const card = toggleBtn.closest('.apple-clause-card');
                    const empInput = card.querySelector('.clause-emphasis-input');
                    const isNowEmp = empInput.value !== '1';
                    empInput.value = isNowEmp ? '1' : '0';
                    if (isNowEmp) {
                        card.classList.add('clause-emphasis');
                        toggleBtn.className = 'btn btn-sm toggle-clause-emphasis-btn btn-danger';
                        toggleBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Critical';
                    } else {
                        card.classList.remove('clause-emphasis');
                        toggleBtn.className = 'btn btn-sm toggle-clause-emphasis-btn btn-outline-secondary';
                        toggleBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Standard';
                    }
                }
            });
        }

        setupClausesUI();
    })();
</script>
@endpush
