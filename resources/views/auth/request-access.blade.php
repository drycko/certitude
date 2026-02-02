<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Certitude') }} - Request Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <!-- Logo & Title -->
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold text-primary mb-2">{{ config('app.name', 'Certitude') }}</h1>
                        <p class="text-muted">Request platform access</p>
                    </div>

                    <!-- Request Access Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            @if (session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('access.request.submit') }}">
                                @csrf

                                <!-- Organization Name -->
                                <div class="mb-3">
                                    <label for="organization" class="form-label">Organization Name</label>
                                    <input id="organization" type="text" 
                                           class="form-control @error('organization') is-invalid @enderror" 
                                           name="organization" 
                                           value="{{ old('organization') }}" 
                                           required 
                                           autofocus
                                           placeholder="Your company or organization name">
                                    @error('organization')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Full Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Your Full Name</label>
                                    <input id="name" type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           name="name" 
                                           value="{{ old('name') }}" 
                                           required
                                           placeholder="Your full name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input id="email" type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           name="email" 
                                           value="{{ old('email') }}" 
                                           required
                                           placeholder="Your work email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Phone -->
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input id="phone" type="tel" 
                                           class="form-control @error('phone') is-invalid @enderror" 
                                           name="phone" 
                                           value="{{ old('phone') }}" 
                                           required
                                           placeholder="Your contact number">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Message/Reason -->
                                <div class="mb-4">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea id="message" 
                                              class="form-control @error('message') is-invalid @enderror" 
                                              name="message" 
                                              rows="3"
                                              placeholder="Tell us why you'd like access to {{ config('app.name') }}">{{ old('message') }}</textarea>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                    <i class="bi bi-send me-2"></i>Submit Request
                                </button>

                                <!-- Login Link -->
                                <div class="text-center">
                                    <span class="text-muted small">Already have an account?</span>
                                    <a href="{{ route('login') }}" class="text-decoration-none small fw-semibold">Sign in</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Footer Text -->
                    <div class="text-center mt-4">
                        <p class="text-muted small mb-2">Your request will be reviewed by our team.</p>
                        <p class="text-muted small mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
