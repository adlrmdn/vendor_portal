<div class="container-fluid">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div class="sidebar p-0 bg-dark" style="min-height: calc(100vh - 56px);">
            <div class="d-flex flex-column flex-shrink-0 p-3 h-100">
                <div id="sidebarToggle" class="align-self-center" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="mb-3 text-center">
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-hard-hat me-1"></i> <span>Subcon Admin</span>
                    </span>
                </div>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <a href="{{ route('subcon.admin.dashboard') }}"
                            class="nav-link {{ request()->routeIs('subcon.admin.dashboard') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('subcon.admin.orders') }}"
                            class="nav-link {{ request()->routeIs('subcon.admin.orders*') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Work Orders">
                            <i class="fas fa-clipboard-list me-2"></i> <span>Work Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('subcon.admin.vendors') }}"
                            class="nav-link {{ request()->routeIs('subcon.admin.vendors*') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Subcon Vendors">
                            <i class="fas fa-users me-2"></i> <span>Vendors</span>
                        </a>
                    </li>
                    @if(auth()->user()->isAdmin())
                    <li><hr class="border-secondary my-1"></li>
                    <li>
                        <a href="{{ route('admin.dashboard') }}" class="nav-link text-secondary"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Fabric Admin">
                            <i class="fas fa-exchange-alt me-2"></i> <span>Fabric Panel</span>
                        </a>
                    </li>
                    @endif
                </ul>
                <hr>
                <div class="text-white small text-center text-md-start">
                    <i class="fas fa-hard-hat me-1"></i> <span>Subcon Panel</span>
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
