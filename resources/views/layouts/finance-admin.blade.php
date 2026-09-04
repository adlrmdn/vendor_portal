<div class="container-fluid">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div class="sidebar p-0 bg-dark" style="min-height: calc(100vh - 56px);">
            <div class="d-flex flex-column flex-shrink-0 p-3 h-100">
                <div id="sidebarToggle" class="align-self-center" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="mb-3 text-center">
                    <span class="badge bg-success">
                        <i class="fas fa-file-invoice-dollar me-1"></i> <span>Finance Admin</span>
                    </span>
                </div>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <a href="{{ route('finance.admin.dashboard') }}"
                            class="nav-link {{ request()->routeIs('finance.admin.dashboard') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('finance.admin.pending-payment') }}"
                            class="nav-link d-flex align-items-center {{ request()->routeIs('finance.admin.pending-payment') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Pending Payment">
                            <i class="fas fa-hourglass-half me-2"></i> <span>Pending Payment</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('finance.admin.fabric-delivery') }}"
                            class="nav-link d-flex align-items-center {{ request()->routeIs('finance.admin.fabric-delivery') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Fabric Delivery">
                            <i class="fas fa-truck-ramp-box me-2"></i> <span>Fabric Delivery</span>
                        </a>
                    </li>
                    <li>
                        @php($uncheckedInvoices = \App\Http\Controllers\FinanceAdminController::uncheckedCount('invoice'))
                        <a href="{{ route('finance.admin.invoices') }}"
                            class="nav-link d-flex align-items-center {{ request()->routeIs('finance.admin.invoices') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Invoices">
                            <i class="fas fa-file-invoice me-2"></i> <span>Invoices</span>
                            @if($uncheckedInvoices > 0)
                                <span class="badge bg-danger rounded-pill ms-auto">{{ $uncheckedInvoices }}</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        @php($uncheckedDebitNotes = \App\Http\Controllers\FinanceAdminController::uncheckedCount('deduction'))
                        <a href="{{ route('finance.admin.debit-notes') }}"
                            class="nav-link d-flex align-items-center {{ request()->routeIs('finance.admin.debit-notes') ? 'active' : '' }}"
                            data-bs-toggle="tooltip" data-bs-placement="right" title="Debit Notes">
                            <i class="fas fa-file-circle-minus me-2"></i> <span>Debit Notes</span>
                            @if($uncheckedDebitNotes > 0)
                                <span class="badge bg-danger rounded-pill ms-auto">{{ $uncheckedDebitNotes }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
                <hr>
                <div class="text-white small text-center text-md-start">
                    <i class="fas fa-file-invoice-dollar me-1"></i> <span>Finance Panel</span>
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
