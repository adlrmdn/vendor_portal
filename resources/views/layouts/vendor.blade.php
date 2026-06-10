<div class="container-fluid">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div class="sidebar p-0 bg-dark" style="min-height: calc(100vh - 56px);">
            <div class="d-flex flex-column flex-shrink-0 p-3 h-100">
                <div id="sidebarToggle" class="align-self-center" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="mb-3 text-center">
                    <span class="badge bg-info">
                        <i class="fas fa-user-tie me-1"></i> <span>Fabric Vendor</span>
                    </span>
                </div>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <a href="{{ route('vendor.dashboard') }}" class="nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('vendor.purchase-orders') }}" class="nav-link {{ request()->routeIs('vendor.purchase-orders') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="My Orders">
                            <i class="fas fa-shopping-cart me-2"></i> <span>My Orders</span>
                        </a>
                    </li>
                </ul>
                <hr>
                <div class="text-white small text-center text-md-start">
                    <i class="fas fa-building me-1"></i> 
                    <span>
                    @if(auth()->user()->vendor)
                        {{ auth()->user()->vendor->name }}
                    @else
                        Vendor Account
                    @endif
                    </span>
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