{{-- Deduction cell content for one Report Validation / Director-list row.
     Shared by the initial server render (when deduction data is already
     known, e.g. the "Deduction only" filter) and the lazy-load endpoint
     (SubconAdminController::reportValidationDeductions) that fills this in
     after the list itself has already rendered — see that controller
     method's docblock for why deduction_total is deferred by default. --}}
@if(($total ?? 0) > 0)
    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25">
        <i class="fas fa-circle-minus me-1"></i> Rp {{ number_format($total, 0, ',', '.') }}
    </span>
    <div class="mt-1 d-flex flex-wrap gap-1">
        @if(($breakdown['fabric'] ?? 0) > 0)<span class="badge bg-light text-dark border">Fabric Cons</span>@endif
        @if(($breakdown['rejectProduksiPenalty'] ?? 0) > 0)<span class="badge bg-light text-dark border">Production</span>@endif
        @if(($breakdown['barangHilangPenalty'] ?? 0) > 0)<span class="badge bg-light text-dark border">Barang Hilang</span>@endif
        @if(($breakdown['accessoryClaimTotal'] ?? 0) > 0)<span class="badge bg-light text-dark border">Retur Material</span>@endif
        @if(($breakdown['manual'] ?? 0) > 0)<span class="badge bg-light text-dark border">Manual</span>@endif
    </div>
@else
    <span class="badge bg-success-subtle text-success border border-success border-opacity-25">
        <i class="fas fa-circle-check me-1"></i> None
    </span>
@endif
