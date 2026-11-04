{{-- PC Master Data tab-pane content --}}
<div class="row g-3">
    {{-- HS Codes --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-barcode" style="color: #1d1d1f;"></i>
                    <span>HS Codes</span>
                </div>
                <span class="apple-count-pill">{{ $hsCodes->count() }} codes</span>
            </div>
            <div class="p-0 flex-grow-1">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table apple-table mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3" style="width: 155px;">Code</th>
                                <th>Description</th>
                                <th class="pe-3 text-end" style="width: 90px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hsCodes as $row)
                                <tr class="apple-table-row">
                                    <td class="ps-3 fw-bold">{{ $row->formatted_code }}</td>
                                    <td class="text-muted">{{ $row->description ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge rounded-pill {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 500;">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="apple-card-action-shelf">
                <form action="{{ route('admin.pc.master-data.hs-codes.store') }}" method="POST">
                    @csrf
                    <div class="apple-shelf-eyebrow">
                        <i class="fas fa-plus-circle"></i> Add HS Code
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" name="code" class="form-control apple-shelf-input" placeholder="e.g. 5407.69.90" maxlength="15" required style="width: 150px;">
                        <input type="text" name="description" class="form-control apple-shelf-input" placeholder="Description (optional)" style="flex: 1;">
                        <button type="submit" class="apple-btn-primary apple-shelf-btn flex-shrink-0">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Lots --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-layer-group" style="color: #1d1d1f;"></i>
                    <span>Lots</span>
                </div>
                <span class="apple-count-pill">{{ $lots->count() }} lots</span>
            </div>
            <div class="p-0 flex-grow-1">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table apple-table mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end" style="width: 90px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $row)
                                <tr class="apple-table-row">
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge rounded-pill {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 500;">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="apple-card-action-shelf">
                <form action="{{ route('admin.pc.master-data.lots.store') }}" method="POST">
                    @csrf
                    <div class="apple-shelf-eyebrow">
                        <i class="fas fa-plus-circle"></i> Add Lot
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" name="code" class="form-control apple-shelf-input" placeholder="e.g. 1-2" maxlength="20" required style="width: 140px;">
                        <input type="text" name="label" class="form-control apple-shelf-input" placeholder="Label (optional)" style="flex: 1;">
                        <button type="submit" class="apple-btn-primary apple-shelf-btn flex-shrink-0">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Mode of Transport --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-ship" style="color: #1d1d1f;"></i>
                    <span>Mode of Transport</span>
                </div>
                <span class="apple-count-pill">{{ $transportModes->count() }} modes</span>
            </div>
            <div class="p-0 flex-grow-1">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table apple-table mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end" style="width: 90px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transportModes as $row)
                                <tr class="apple-table-row">
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge rounded-pill {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 500;">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="apple-card-action-shelf">
                <form action="{{ route('admin.pc.master-data.transport-modes.store') }}" method="POST">
                    @csrf
                    <div class="apple-shelf-eyebrow">
                        <i class="fas fa-plus-circle"></i> Add Transport Mode
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" name="code" class="form-control apple-shelf-input" placeholder="e.g. SEA, AIR" maxlength="20" required style="width: 140px;">
                        <input type="text" name="label" class="form-control apple-shelf-input" placeholder="Description (e.g. Ocean Freight)" style="flex: 1;">
                        <button type="submit" class="apple-btn-primary apple-shelf-btn flex-shrink-0">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Terms of Delivery --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-truck" style="color: #1d1d1f;"></i>
                    <span>Terms of Delivery</span>
                </div>
                <span class="apple-count-pill">{{ $deliveryTerms->count() }} terms</span>
            </div>
            <div class="p-0 flex-grow-1">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table apple-table mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end" style="width: 90px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryTerms as $row)
                                <tr class="apple-table-row">
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge rounded-pill {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 500;">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="apple-card-action-shelf">
                <form action="{{ route('admin.pc.master-data.delivery-terms.store') }}" method="POST">
                    @csrf
                    <div class="apple-shelf-eyebrow">
                        <i class="fas fa-plus-circle"></i> Add Delivery Term
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" name="code" class="form-control apple-shelf-input" placeholder="e.g. FOB, CIF" maxlength="50" required style="width: 140px;">
                        <input type="text" name="label" class="form-control apple-shelf-input" placeholder="Description (e.g. Free On Board)" style="flex: 1;">
                        <button type="submit" class="apple-btn-primary apple-shelf-btn flex-shrink-0">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Seasons --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-calendar-alt" style="color: #1d1d1f;"></i>
                    <span>Seasons</span>
                </div>
                <span class="apple-count-pill">{{ $seasons->count() }} seasons</span>
            </div>
            <div class="p-0 flex-grow-1">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table apple-table mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end" style="width: 90px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seasons as $row)
                                <tr class="apple-table-row">
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge rounded-pill {{ $row->is_open ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}" style="font-size: 0.72rem; padding: 3px 8px; font-weight: 500;">
                                            {{ $row->is_open ? 'Active' : 'Closed' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="apple-card-action-shelf">
                <form action="{{ route('admin.pc.master-data.seasons.store') }}" method="POST">
                    @csrf
                    <div class="apple-shelf-eyebrow">
                        <i class="fas fa-plus-circle"></i> Add Season
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" name="code" class="form-control apple-shelf-input" placeholder="e.g. FALL'26" required style="width: 140px;">
                        <input type="text" name="label" class="form-control apple-shelf-input" placeholder="Season Label (e.g. Fall 2026)" required style="flex: 1;">
                        <button type="submit" class="apple-btn-primary apple-shelf-btn flex-shrink-0">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Fixed Master Lists (seed/admin-managed only) --}}
    <div class="col-lg-6">
        <div class="apple-card h-100 d-flex flex-column">
            <div class="apple-card-header">
                <div class="apple-card-title">
                    <i class="fas fa-lock text-secondary"></i>
                    <span>Fixed System Catalogs (Seed Managed)</span>
                </div>
            </div>
            <div class="p-3 flex-grow-1">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">VAT / PPN Treatments</span>
                        <span class="fw-semibold">{{ $vatTreatments->pluck('code')->implode(', ') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Order Types</span>
                        <span class="fw-semibold">{{ $orderTypes->pluck('code')->implode(', ') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Currencies</span>
                        <span class="fw-semibold">{{ $currencies->pluck('code')->implode(', ') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Payment Terms</span>
                        <span class="badge bg-light text-dark border">{{ $paymentTerms->count() }} configured</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Standard Note Templates</span>
                        <span class="badge bg-light text-dark border">{{ $noteTemplates->count() }} clauses</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                        <span class="text-muted">Shipment Tolerance Templates</span>
                        <span class="badge bg-light text-dark border">{{ $toleranceTemplates->count() }} rows</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
