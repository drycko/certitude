<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Certitude') }} - Multi-Tenant Platform</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .hero-section {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .feature-card {
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="bi bi-building-gear me-2"></i>{{ config('app.name', 'Certitude') }}
            </a>
            <div class="ms-auto">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-outline-light">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-light me-2">Login</a>
                    <a href="{{ route('access.request') }}" class="btn btn-light">Request Access</a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-white d-flex align-items-center">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold mb-4">Multi-Tenant Platform for Modern Businesses</h1>
                    <p class="lead mb-4">Streamline your operations with our powerful multi-tenant platform. Manage multiple clients, subscriptions, and resources from a single dashboard.</p>
                    <div class="d-flex gap-3">
                        @guest
                            <a href="{{ route('access.request') }}" class="btn btn-light btn-lg">
                                <i class="bi bi-rocket-takeoff me-2"></i>Request Access
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </a>
                        @else
                            <a href="{{ url('/dashboard') }}" class="btn btn-light btn-lg">
                                <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                            </a>
                        @endguest
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="text-center">
                        <i class="bi bi-diagram-3 display-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Platform Features</h2>
                <p class="lead text-muted">Everything you need to manage your multi-tenant operations</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                <i class="bi bi-building text-primary fs-1"></i>
                            </div>
                            <h5 class="card-title">Tenant Management</h5>
                            <p class="card-text text-muted">Easily create and manage multiple tenants with isolated data and resources.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                <i class="bi bi-credit-card text-success fs-1"></i>
                            </div>
                            <h5 class="card-title">Subscription Plans</h5>
                            <p class="card-text text-muted">Flexible subscription plans with automated billing and payment processing.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                <i class="bi bi-shield-check text-info fs-1"></i>
                            </div>
                            <h5 class="card-title">Security First</h5>
                            <p class="card-text text-muted">Enterprise-grade security with role-based access control and data encryption.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Multi-Tenant Platform v1.0</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
