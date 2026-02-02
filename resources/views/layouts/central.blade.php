<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Certitude') }} - @yield('title', 'Central Admin')</title>

    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Admin Theme -->
    <link href="{{ asset('css/admin-theme.css') }}" rel="stylesheet">
    
    @stack('styles')
</head>
<<body data-page="@yield('page-id', 'central')" class="admin-layout">
    <!-- Main Wrapper -->
    <div class="admin-wrapper" id="admin-wrapper">
        
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="admin-sidebar">
            <!-- Sidebar Brand -->
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                    <i class="bi bi-building-gear fs-4 me-2"></i>
                    <span class="fs-5 fw-bold">{{ config('app.name', 'Certitude') }}</span>
                </a>
            </div>
            
            <!-- Sidebar Content -->
            <div class="sidebar-content">
                <nav class="sidebar-nav">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        
                        <li class="nav-item mt-3">
                            <small class="nav-heading">Management</small>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('central.tenants.*') ? 'active' : '' }}" href="{{ route('central.tenants.index') }}">
                                <i class="bi bi-building"></i>
                                <span>Tenants</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i>
                                <span>Users</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-box-seam"></i>
                                <span>Plans</span>
                            </a>
                        </li>
                        
                        <li class="nav-item mt-3">
                            <small class="nav-heading">System</small>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-activity"></i>
                                <span>Activity</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-gear"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>
        
        <!-- Header -->
        <header class="admin-header">
            <div class="d-flex align-items-center justify-content-between h-100 px-4">
                <!-- Left: Sidebar Toggle + Breadcrumb -->
                <div class="d-flex align-items-center">
                    <button class="hamburger-menu me-3" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="d-none d-md-block">
                        <h5 class="mb-0 fw-semibold">@yield('page-title', 'Dashboard')</h5>
                    </div>
                </div>

                <!-- Right: Actions + User -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-icon" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="badge-dot"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><small>New tenant registered</small></a></li>
                            <li><a class="dropdown-item" href="#"><small>System update available</small></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center small" href="#">View all</a></li>
                        </ul>
                    </div>

                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-user d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar me-2">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="d-none d-md-block text-start">
                                <div class="user-name">{{ Auth::user()->name }}</div>
                                <div class="user-role">{{ ucfirst(Auth::user()->role ?? 'Admin') }}</div>
                            </div>
                            <i class="bi bi-chevron-down ms-2 d-none d-md-inline"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar Backdrop (mobile overlay) -->
        <div class="sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="container-fluid p-4">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
            const adminWrapper = document.getElementById('admin-wrapper');
            const adminSidebar = document.getElementById('admin-sidebar');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');
            
            if (sidebarToggle && adminSidebar) {
                sidebarToggle.addEventListener('click', function() {
                    adminWrapper?.classList.toggle('sidebar-open');
                    adminSidebar.classList.toggle('show');
                    backdrop?.classList.toggle('show');
                });
                
                backdrop?.addEventListener('click', function() {
                    adminWrapper?.classList.remove('sidebar-open');
                    adminSidebar.classList.remove('show');
                    backdrop.classList.remove('show');
                });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
