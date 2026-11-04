{{--
    Module navigation for Preliminary Contract.
    $pcSection: 'dashboard' | 'contracts' | 'data' | 'create' | 'show' | 'edit'
--}}
<div class="apple-module-nav">
    <div class="apple-module-tabs">
        <a href="{{ route('admin.pc.index') }}" 
           class="apple-module-tab {{ ($pcSection ?? '') === 'dashboard' ? 'active' : '' }}">
            <i class="fas fa-chart-pie"></i>
            <span>Overview &amp; Pipeline</span>
        </a>
        <a href="{{ route('admin.pc.contracts.index') }}" 
           class="apple-module-tab {{ in_array(($pcSection ?? ''), ['contracts', 'show', 'edit', 'create']) ? 'active' : '' }}">
            <i class="fas fa-file-contract"></i>
            <span>Contracts</span>
        </a>
        <a href="{{ route('admin.pc.data.index') }}" 
           class="apple-module-tab {{ ($pcSection ?? '') === 'data' ? 'active' : '' }}">
            <i class="fas fa-sliders-h"></i>
            <span>Master Data &amp; Settings</span>
        </a>
    </div>
    
    {{-- Actions & Context --}}
    @if(in_array(($pcSection ?? ''), ['edit', 'create']))
        <div>
            <a href="{{ route('admin.pc.contracts.index') }}" class="apple-back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Contracts</span>
            </a>
        </div>
    @else
        <div class="d-none d-md-block">
            <a href="{{ route('admin.pc.guidebook.pdf') }}" class="apple-back-btn" target="_blank" title="Download Admin Guidebook PDF">
                <i class="fas fa-file-pdf text-danger"></i>
                <span>Admin Guidebook</span>
            </a>
        </div>
    @endif
</div>
