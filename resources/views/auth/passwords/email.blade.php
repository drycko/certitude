<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Certitude') }} - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <!-- Logo & Title -->
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold text-primary mb-2">{{ config('app.name', 'Certitude') }}</h1>
                        <p class="text-muted">Reset your password</p>
                    </div>

                    <!-- Reset Password Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('password.email') }}">
                                @csrf

                                @if (session('status'))
                                    <div class="alert alert-success" role="alert">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                <!-- Email -->
                                <div class="mb-4">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input id="email" type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           name="email" 
                                           value="{{ old('email') }}" 
                                           required 
                                           autocomplete="email" 
                                           autofocus
                                           placeholder="Enter your email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">We'll send you a password reset link</small>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                    <i class="bi bi-envelope me-2"></i>Send Reset Link
                                </button>

                                <!-- Back to Login -->
                                <div class="text-center">
                                    <a href="{{ route('login') }}" class="text-decoration-none small">
                                        <i class="bi bi-arrow-left me-1"></i>Back to login
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Footer Text -->
                    <div class="text-center mt-4">
                        <p class="text-muted small mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
