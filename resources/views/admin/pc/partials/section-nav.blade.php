{{--
    Module navigation for Preliminary Contract.
    $pcSection: 'dashboard' | 'contracts' | 'data' | 'create' | 'show' | 'edit'
--}}
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 border-bottom pb-3">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item">
            <a href="{{ route('admin.pc.index') }}" 
               class="nav-link px-3 py-2 {{ ($pcSection ?? '') === 'dashboard' ? 'active bg-primary' : 'text-secondary' }}">
                <i class="fas fa-chart-pie me-1"></i> Overview & Pipeline
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.pc.contracts.index') }}" 
               class="nav-link px-3 py-2 {{ in_array(($pcSection ?? ''), ['contracts', 'show', 'edit']) ? 'active bg-primary' : 'text-secondary' }}">
                <i class="fas fa-file-contract me-1"></i> Contracts
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.pc.data.index') }}" 
               class="nav-link px-3 py-2 {{ ($pcSection ?? '') === 'data' ? 'active bg-primary' : 'text-secondary' }}">
                <i class="fas fa-sliders-h me-1"></i> Master Data & Settings
            </a>
        </li>
    </ul>
    
    {{-- Breadcrumb or back context when inside a contract view/edit --}}
    @if(in_array(($pcSection ?? ''), ['show', 'edit', 'create']))
        <div>
            <a href="{{ route('admin.pc.contracts.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Contracts
            </a>
        </div>
    @endif
</div>
