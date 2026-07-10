<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
    <title>@yield('title', 'Vendor Portal')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .navbar-brand {
            font-weight: bold;
            color: #0d6efd !important;
        }

        .sidebar {
            background-color: #343a40;
            min-height: calc(100vh - 56px);
            color: white;
        }

        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 20px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: #495057;
        }

        .content-area {
            padding: 20px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
            margin-bottom: 20px;
        }

        .stat-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-processing {
            background-color: #0dcaf0;
            color: #000;
        }

        .badge-completed {
            background-color: #198754;
        }

        .badge-cancelled {
            background-color: #dc3545;
        }

        .table-responsive {
            max-height: 500px;
        }

        /* Sidebar Collapse Styles */
        .sidebar {
            transition: all 0.3s ease;
            z-index: 100;
            width: 250px; /* Use fixed width for more control */
            min-width: 250px;
            flex: 0 0 250px;
        }

        .content-area {
            transition: all 0.3s ease;
            flex: 1;
            min-width: 0; /* CRITICAL: Allows content to shrink and prevents horizontal scroll */
            overflow-x: auto; /* Allow horizontal scroll ONLY inside the content area if table is truly huge */
        }

        body[data-sidebar-collapsed="true"] .sidebar {
            width: 70px !important;
            min-width: 70px !important;
            flex: 0 0 70px !important;
        }

        body[data-sidebar-collapsed="true"] .sidebar span:not(.badge),
        body[data-sidebar-collapsed="true"] .sidebar hr,
        body[data-sidebar-collapsed="true"] .sidebar .small,
        body[data-sidebar-collapsed="true"] .sidebar .badge span,
        body[data-sidebar-collapsed="true"] .sidebar .nav-link span {
            display: none !important;
        }

        body[data-sidebar-collapsed="true"] .sidebar .nav-link {
            padding: 12px 0;
            border-radius: 8px;
            margin: 4px 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        body[data-sidebar-collapsed="true"] .sidebar .nav-link i {
            margin-right: 0 !important;
            font-size: 1.3rem;
            display: block;
        }

        body[data-sidebar-collapsed="true"] .sidebar .badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        body[data-sidebar-collapsed="true"] .content-area {
            width: calc(100% - 70px) !important;
            flex: 1 !important;
            min-width: 0 !important;
        }

        #sidebarToggle {
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            color: #adb5bd;
            transition: all 0.2s;
            margin-bottom: 20px;
            display: inline-block;
        }

        #sidebarToggle:hover {
            background-color: #495057;
            color: white;
        }

        body[data-sidebar-collapsed="true"] #sidebarToggle {
            margin-left: auto;
            margin-right: auto;
            display: block;
            width: fit-content;
        }
    </style>
    @stack('styles')
</head>

<body>
    {{-- Standalone/token pages (e.g. the approval forms reached from an email or
         from the admin Approvals tab) opt into "bare" chrome: the top navbar is
         kept (so a logged-in admin can still navigate), but the role sidebar is
         dropped so the page reads as a focused, standalone approval. --}}
    @php($__bare = trim($__env->yieldContent('bare')) !== '')
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                <img src="{{ asset('images/mpg_header.png') }}" alt="Logo" height="24" class="me-2">
                Vendor Portal
            </a>



            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Dashboard link removed (duplicates sidebar) -->
                </ul>

                <ul class="navbar-nav ms-auto">
                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">
                                    <i class="fas fa-user-plus me-1"></i> Register
                                </a>
                            </li>
                        @endif
                    @else
                        <!-- Notifications Bell -->
                        <li class="nav-item dropdown px-2 position-relative d-flex align-items-center">
                            <a class="nav-link p-0 position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell fs-5"></i>
                                @if(auth()->user()->unreadNotifications->count() > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.35em 0.5em; transform: translate(0%, -50%) !important;">
                                        {{ auth()->user()->unreadNotifications->count() }}
                                    </span>
                                @endif
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 p-0" aria-labelledby="notificationDropdown" style="width: 320px; border-radius: 12px; overflow: hidden;">
                                <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small">Notifications</span>
                                    @if(auth()->user()->unreadNotifications->count() > 0)
                                        <a href="#" class="text-primary small text-decoration-none" onclick="markAllAsRead(event)">Mark all as read</a>
                                    @endif
                                </div>
                                <div class="notification-list" style="max-height: 380px; overflow-y: auto;">
                                    @forelse(auth()->user()->unreadNotifications->take(8) as $notification)
                                        <a class="dropdown-item d-flex align-items-start py-3 border-bottom whitespace-normal" href="{{ $notification->data['url'] ?? '#' }}" style="white-space: normal;">
                                            <div class="me-3 mt-1">
                                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                    <i class="{{ $notification->data['icon'] ?? 'fas fa-info-circle' }}"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="small fw-bold text-dark lh-sm">{{ $notification->data['title'] }}</div>
                                                <div class="text-muted small mt-1 lh-sm" style="font-size: 0.8rem;">{{ $notification->data['message'] }}</div>
                                                <div class="text-muted mt-2" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="text-center py-5">
                                            <i class="fas fa-bell-slash text-muted fs-1 mb-3"></i>
                                            <p class="text-muted mb-0">No new notifications</p>
                                        </div>
                                    @endforelse
                                </div>
                                @if(auth()->user()->notifications->count() > 0)
                                    <div class="bg-light p-2 text-center border-top">
                                        <a href="#" class="small text-primary fw-bold text-decoration-none">View All Notifications</a>
                                    </div>
                                @endif
                            </div>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="{{ Auth::user()->role === 'subcon_vendor' ? route('subcon.vendor.profile') : '#' }}">
                                        <i class="fas fa-user me-2"></i> Profile
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    @if(auth()->check() && ! $__bare)
        @if(auth()->user()->isAdmin() || auth()->user()->isFabricAdmin())
            @include('layouts.admin')
        @elseif(auth()->user()->isFabricVendor())
            @include('layouts.vendor')
        @elseif(auth()->user()->isSubconAdmin())
            @include('layouts.subcon-admin')
        @elseif(auth()->user()->isSubconVendor())
            @include('layouts.subcon-vendor')
        @endif
    @else
        <!-- Guest / standalone content -->
        <main>
            @yield('content')
        </main>
    @endif

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Sidebar Toggle Logic
        function toggleSidebar() {
            const body = document.body;
            const isCollapsed = body.getAttribute('data-sidebar-collapsed') === 'true';
            const newState = !isCollapsed;
            
            body.setAttribute('data-sidebar-collapsed', newState);
            localStorage.setItem('sidebar-collapsed', newState);
            
            // Toggle icon
            const icon = document.querySelector('#sidebarToggle i');
            if (newState) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-indent');
            } else {
                icon.classList.remove('fa-indent');
                icon.classList.add('fa-bars');
            }
        }

        // Initialize sidebar state on load
        (function() {
            const savedState = localStorage.getItem('sidebar-collapsed');
            if (savedState === 'true') {
                document.body.setAttribute('data-sidebar-collapsed', 'true');
                const icon = document.querySelector('#sidebarToggle i');
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-indent');
                }
            }
        })();

        // Confirm before delete
        function confirmDelete(event, message = 'Are you sure you want to delete this?') {
            if (!confirm(message)) {
                event.preventDefault();
                return false;
            }
            return true;
        }

        // Mark all notifications as read
        function markAllAsRead(e) {
            e.preventDefault();
            e.stopPropagation();
            
            fetch("{{ route('notifications.mark-as-read') }}", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    "Accept": "application/json"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide badges
                    document.querySelectorAll('.nav-link .badge').forEach(el => el.remove());
                    // Update list text
                    const list = document.querySelector('.notification-list');
                    if (list) {
                        list.innerHTML = '<div class="text-center py-5"><i class="fas fa-bell-slash text-muted fs-1 mb-3"></i><p class="text-muted mb-0">No new notifications</p></div>';
                    }
                    // Hide Mark all link
                    const markLink = document.querySelector('a[onclick*="markAllAsRead"]');
                    if (markLink) markLink.remove();
                }
            })
            .catch(error => console.error('Error marking notifications as read:', error));
        }

        // Numerical Formatting Helper
        function formatNumber(value) {
            if (value === null || value === undefined || isNaN(value)) return '0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        }
    </script>

    @stack('scripts')
</body>

</html>