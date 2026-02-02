@extends('layouts.central')

@section('title', 'Dashboard')
@section('page-id', 'dashboard')
@section('page-title', 'Dashboard')

@section('content')
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-muted mb-0">Welcome back! Here's your platform overview.</p>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
        <button type="button" class="btn btn-primary" onclick="window.location='{{ route('tenants.create') }}'">
            <i class="bi bi-plus-lg me-2"></i>
            <span class="d-none d-sm-inline">New Tenant</span>
        </button>
        <button type="button" class="btn btn-outline-secondary" 
                data-bs-toggle="tooltip"
                title="Refresh data"
                onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise icon-hover"></i>
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <!-- Total Tenants -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-2 text-uppercase fw-normal" style="font-size: 0.75rem;">Total Tenants</h6>
                        <h2 class="mb-0">{{ $stats['total_tenants'] }}</h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-building text-primary fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success bg-opacity-10 text-success">
                        <i class="bi bi-arrow-up"></i> Active
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Tenants -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-2 text-uppercase fw-normal" style="font-size: 0.75rem;">Active Tenants</h6>
                        <h2 class="mb-0">{{ $stats['active_tenants'] }}</h2>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-check-circle text-success fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted small">
                        {{ $stats['total_tenants'] > 0 ? number_format(($stats['active_tenants'] / $stats['total_tenants']) * 100, 1) : 0 }}% of total
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Users -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-2 text-uppercase fw-normal" style="font-size: 0.75rem;">Total Users</h6>
                        <h2 class="mb-0">{{ $stats['total_users'] }}</h2>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-people text-info fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted small">Central admin users</span>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-2 text-uppercase fw-normal" style="font-size: 0.75rem;">System Status</h6>
                        <h2 class="mb-0">Healthy</h2>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-activity text-success fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success bg-opacity-10 text-success">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> All systems operational
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Tenants -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Tenants</h5>
                    <a href="{{ route('tenants.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            </div>
            <div class="card-body">
                @if($stats['recent_tenants']->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Domain</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['recent_tenants'] as $tenant)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-2">
                                            <i class="bi bi-building text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium">{{ $tenant->name }}</div>
                                            <small class="text-muted">{{ $tenant->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($tenant->domains->first())
                                        <span class="badge bg-light text-dark">{{ $tenant->domains->first()->domain }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $tenant->plan === 'enterprise' ? 'primary' : ($tenant->plan === 'growth' ? 'success' : 'secondary') }}">
                                        {{ ucfirst($tenant->plan ?? 'starter') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $tenant->is_active ? 'success' : 'warning' }} bg-opacity-10 text-{{ $tenant->is_active ? 'success' : 'warning' }}">
                                        {{ $tenant->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $tenant->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No tenants yet</h5>
                    <p class="text-muted">Create your first tenant to get started.</p>
                    <a href="{{ route('tenants.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Create Tenant
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize Bootstrap tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
