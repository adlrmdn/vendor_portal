<div class="container-fluid">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div class="sidebar p-0 bg-dark" style="min-height: calc(100vh - 56px);">
            <div class="d-flex flex-column flex-shrink-0 p-3 h-100">
                <div id="sidebarToggle" class="align-self-center" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}"
                            class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.purchase-orders') }}"
                            class="nav-link text-nowrap {{ request()->routeIs('admin.purchase-orders') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Purchase Orders">
                            <i class="fas fa-file-invoice me-2"></i> <span>Purchase Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.vendors') }}"
                            class="nav-link {{ request()->routeIs('admin.vendors') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Vendors">
                            <i class="fas fa-users me-2"></i> <span>Vendors</span>
                        </a>
                    </li>
                    <li>
                        @php($pendingFabricApprovals = \App\Models\ToleranceAmendmentRequest::where('status', 'pending')->count())
                        <a href="{{ route('admin.approvals') }}"
                            class="nav-link d-flex align-items-center {{ request()->routeIs('admin.approvals') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Approvals">
                            <i class="fas fa-gavel me-2"></i> <span>Approvals</span>
                            @if($pendingFabricApprovals > 0)
                                <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingFabricApprovals }}</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.workflow') }}"
                            class="nav-link {{ request()->routeIs('admin.workflow*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Workflow Settings">
                            <i class="fas fa-sitemap me-2"></i> <span>Workflow</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.settings') }}"
                            class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Settings">
                            <i class="fas fa-cogs me-2"></i> <span>Settings</span>
                        </a>
                    </li>
                    @if(auth()->user()->isAdmin())
                    <li><hr class="border-secondary my-1"></li>
                    <li>
                        <a href="{{ route('subcon.admin.dashboard') }}" class="nav-link text-secondary"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Subcon Admin">
                            <i class="fas fa-exchange-alt me-2"></i> <span>Subcon Panel</span>
                        </a>
                    </li>
                    @endif
                </ul>
                <hr>
                <div class="text-white small text-center text-md-start">
                    <i class="fas fa-user-shield me-1"></i>
                    <span>{{ auth()->user()->isAdmin() ? 'Root Admin' : 'Fabric Admin' }}</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-area">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>