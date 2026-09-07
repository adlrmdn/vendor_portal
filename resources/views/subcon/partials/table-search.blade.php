{{-- Shared search bar for the array-backed pending/log tables (Director,
     Approvals, Report Validations, Job Logs). $route/$search are required;
     $placeholder and $extra (extra form controls rendered before the button,
     e.g. a status <select>) are optional. --}}
<form method="GET" action="{{ $route }}" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-sm-{{ isset($extra) ? 5 : 8 }}">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm"
                       placeholder="{{ $placeholder ?? 'Search work order, vendor, style…' }}">
            </div>
            @isset($extra)
                {{ $extra }}
            @endisset
            <div class="col-sm-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                @if($search !== '')
                    <a href="{{ $route }}" class="btn btn-sm btn-outline-secondary" title="Clear filter"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </div>
    </div>
</form>
