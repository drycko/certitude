@extends('layouts.central')

@section('title', 'Tenants')
@section('page-id', 'tenants')
@section('page-title', 'Tenant Management')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Tenants</h4>
                <p class="text-muted mb-0">Manage your tenants and their subscriptions</p>
            </div>
            <a href="{{ route('tenants.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Create Tenant
            </a>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tenants.index') }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search tenants..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="plan" class="form-select">
                        <option value="">All Plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan }}" {{ request('plan') === $plan ? 'selected' : '' }}>{{ ucfirst($plan) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('tenants.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tenants Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Domain</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td>
                                <div>
                                    <strong>{{ $tenant->name }}</strong>
                                    <div class="text-muted small">{{ $tenant->email }}</div>
                                </div>
                            </td>
                            <td>
                                <a href="http://{{ $tenant->primary_domain }}" target="_blank" class="text-decoration-none">
                                    {{ $tenant->primary_domain }}
                                    <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ ucfirst($tenant->plan) }}</span>
                            </td>
                            <td>
                                @if($tenant->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $tenant->created_at->format('M d, Y') }}</small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('{{ $tenant->id }}', '{{ $tenant->name }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                No tenants found. <a href="{{ route('tenants.create') }}">Create your first tenant</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tenants->hasPages())
        <div class="card-footer">
            {{ $tenants->links() }}
        </div>
    @endif
</div>

<!-- Delete Confirmation Form -->
<form id="delete-form" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@endsection

@push('scripts')
<script>
function confirmDelete(tenantId, tenantName) {
    if (confirm(`Are you sure you want to delete tenant "${tenantName}"? This action cannot be undone.`)) {
        const form = document.getElementById('delete-form');
        form.action = `/tenants/${tenantId}`;
        form.submit();
    }
}
</script>
@endpush
