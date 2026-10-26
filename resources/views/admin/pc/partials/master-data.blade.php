{{-- PC Master Data tab-pane content. --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-database text-primary me-2"></i>PC Master Data</h5>
</div>
<div class="row g-3">
    {{-- HS Codes --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-barcode text-primary me-2"></i>HS Codes</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $hsCodes->count() }} codes</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Description</th>
                                <th class="pe-3 text-end">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hsCodes as $row)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->description ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-2">
                <form action="{{ route('admin.pc.master-data.hs-codes.store') }}" method="POST">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="code" class="form-control" placeholder="10-digit code" maxlength="10" required>
                        <input type="text" name="description" class="form-control" placeholder="Description (optional)">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Lots --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-layer-group text-success me-2"></i>Lots</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $lots->count() }} lots</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $row)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-2">
                <form action="{{ route('admin.pc.master-data.lots.store') }}" method="POST">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="code" class="form-control" placeholder="Code (e.g. 1-2)" maxlength="20" required>
                        <input type="text" name="label" class="form-control" placeholder="Label (optional)">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Mode of Transport --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-ship text-info me-2"></i>Mode of Transport</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $transportModes->count() }} modes</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transportModes as $row)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-2">
                <form action="{{ route('admin.pc.master-data.transport-modes.store') }}" method="POST">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="code" class="form-control" placeholder="Code (e.g. SEA)" maxlength="20" required>
                        <input type="text" name="label" class="form-control" placeholder="Label (optional)">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Terms of Delivery --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-truck text-warning me-2"></i>Terms of Delivery</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $deliveryTerms->count() }} terms</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryTerms as $row)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label ?: '—' }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge {{ $row->source === 'seed' ? 'bg-light text-dark border' : 'bg-primary-subtle text-primary' }}">
                                            {{ $row->source }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-2">
                <form action="{{ route('admin.pc.master-data.delivery-terms.store') }}" method="POST">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="code" class="form-control" placeholder="Code (e.g. FOB)" maxlength="50" required>
                        <input type="text" name="label" class="form-control" placeholder="Label (optional)">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Seasons --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-calendar-alt text-purple me-2"></i>Seasons</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $seasons->count() }} seasons</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Code</th>
                                <th>Label</th>
                                <th class="pe-3 text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seasons as $row)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $row->code }}</td>
                                    <td class="text-muted">{{ $row->label }}</td>
                                    <td class="pe-3 text-end">
                                        <span class="badge {{ $row->is_open ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $row->is_open ? 'Active' : 'Closed' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-2">
                <form action="{{ route('admin.pc.master-data.seasons.store') }}" method="POST">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="code" class="form-control" placeholder="Code (e.g. FALL'26)" required>
                        <input type="text" name="label" class="form-control" placeholder="Label" required>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Fixed Master Lists (seed/admin-managed only) --}}
    <div class="col-lg-6">
        <div class="card h-100 border">
            <div class="card-header bg-white py-2">
                <span class="fw-bold"><i class="fas fa-lock text-secondary me-2"></i>Fixed System Catalogs (Seed Managed)</span>
            </div>
            <div class="card-body p-3">
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
