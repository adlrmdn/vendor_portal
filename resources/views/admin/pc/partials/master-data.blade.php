{{-- PC Master Data tab-pane content. Data from PcController::index(). Any
     admin/fabric_admin can add to the 4 user-extensible tables and manage
     seasons — same blanket access as the rest of /admin/*. --}}
<h1 class="pc-h1 mb-4"><i class="fas fa-list-ul me-2"></i>PC Master Data</h1>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">HS Codes</div>
            <div class="pc-card-body">
                <div class="pc-table-scroll">
                <table class="pc-table">
                    <thead><tr><th>Code</th><th>Description</th><th>Source</th></tr></thead>
                    <tbody>
                        @foreach ($hsCodes as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->description }}</td><td><span class="pc-pill {{ $row->source === 'seed' ? 'pc-pill-draft' : 'pc-pill-neutral' }}">{{ $row->source }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <form action="{{ route('admin.pc.master-data.hs-codes.store') }}" method="POST" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="10-digit code" maxlength="10" required>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Description (optional)">
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">Lots</div>
            <div class="pc-card-body">
                <div class="pc-table-scroll">
                <table class="pc-table">
                    <thead><tr><th>Code</th><th>Label</th><th>Source</th></tr></thead>
                    <tbody>
                        @foreach ($lots as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->label }}</td><td><span class="pc-pill {{ $row->source === 'seed' ? 'pc-pill-draft' : 'pc-pill-neutral' }}">{{ $row->source }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <form action="{{ route('admin.pc.master-data.lots.store') }}" method="POST" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="Code, e.g. 1-2" maxlength="20" required>
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Label (optional)">
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">Mode of Transport</div>
            <div class="pc-card-body">
                <div class="pc-table-scroll">
                <table class="pc-table">
                    <thead><tr><th>Code</th><th>Label</th><th>Source</th></tr></thead>
                    <tbody>
                        @foreach ($transportModes as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->label }}</td><td><span class="pc-pill {{ $row->source === 'seed' ? 'pc-pill-draft' : 'pc-pill-neutral' }}">{{ $row->source }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <form action="{{ route('admin.pc.master-data.transport-modes.store') }}" method="POST" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="Code, e.g. SEA" maxlength="20" required>
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Label (optional)">
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">Terms of Delivery</div>
            <div class="pc-card-body">
                <div class="pc-table-scroll">
                <table class="pc-table">
                    <thead><tr><th>Code</th><th>Label</th><th>Source</th></tr></thead>
                    <tbody>
                        @foreach ($deliveryTerms as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->label }}</td><td><span class="pc-pill {{ $row->source === 'seed' ? 'pc-pill-draft' : 'pc-pill-neutral' }}">{{ $row->source }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <form action="{{ route('admin.pc.master-data.delivery-terms.store') }}" method="POST" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="Code, e.g. FOB" maxlength="50" required>
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Label (optional)">
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">Seasons</div>
            <div class="pc-card-body">
                <div class="pc-table-scroll">
                <table class="pc-table">
                    <thead><tr><th>Code</th><th>Label</th><th>Open</th></tr></thead>
                    <tbody>
                        @foreach ($seasons as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->label }}</td><td>{{ $row->is_open ? 'Yes' : 'No' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <form action="{{ route('admin.pc.master-data.seasons.store') }}" method="POST" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="Code, e.g. FALL'26" required>
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Label" required>
                    <button type="submit" class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pc-card h-100">
            <div class="pc-card-head">Fixed Master Lists (seed/admin-managed only)</div>
            <div class="pc-card-body pc-meta">
                <p class="mb-1"><strong>VAT/PPN:</strong> {{ $vatTreatments->pluck('code')->implode(', ') }}</p>
                <p class="mb-1"><strong>Order Types:</strong> {{ $orderTypes->pluck('code')->implode(', ') }}</p>
                <p class="mb-1"><strong>Currencies:</strong> {{ $currencies->pluck('code')->implode(', ') }}</p>
                <p class="mb-1"><strong>Payment Terms:</strong> {{ $paymentTerms->count() }} configured</p>
                <p class="mb-1"><strong>Note Templates:</strong> {{ $noteTemplates->count() }} clauses</p>
                <p class="mb-0"><strong>Tolerance Templates:</strong> {{ $toleranceTemplates->count() }} rows</p>
            </div>
        </div>
    </div>
</div>
