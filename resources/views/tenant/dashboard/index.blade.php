@extends('layouts.tenant')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
<li class="breadcrumb-item">Dashboard</li>
@endsection

@section('content')
{{-- <main class="nxl-container">
    <div class="nxl-content"> --}}
        <!-- [ page-header ] start -->
        {{-- <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Dashboard</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item">Dashboard</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <div class="page-header-right-items">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                            <i class="feather-bar-chart"></i>
                        </button>
                        <a href="{{ route('documents.index') }}" class="btn btn-primary">
                            <i class="feather-folder me-2"></i>
                            <span>View Files</span>
                        </a>
                    </div>
                </div>
            </div>
        </div> --}}
        <!-- [ page-header ] end -->

        <!-- [ Main Content ] start -->
        {{-- <div class="main-content"> --}}
            <div class="row">
                <!-- Statistics Cards -->
                @if(isset($stats) && count($stats) > 0)
                    @foreach($stats as $key => $value)
                        @if(!is_array($value))
                            <div class="col-xxl-3 col-md-6">
                                <div class="card stretch stretch-full">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-text avatar-lg rounded {{ $loop->iteration % 4 == 1 ? 'bg-primary' : ($loop->iteration % 4 == 2 ? 'bg-success' : ($loop->iteration % 4 == 3 ? 'bg-warning' : 'bg-danger')) }} text-white">
                                                    <i class="feather-{{ $loop->iteration % 4 == 1 ? 'file' : ($loop->iteration % 4 == 2 ? 'users' : ($loop->iteration % 4 == 3 ? 'briefcase' : 'alert-circle')) }}"></i>
                                                </div>
                                                <div>
                                                    <p class="fs-12 fw-medium text-muted mb-1 text-truncate-1-line">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                                                    <h4 class="fw-bold mb-0">{{ number_format($value) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="row">
                <!-- Recent Documents -->
                <div class="col-xxl-8 col-xl-7">
                    <div class="card stretch stretch-full">
                        <div class="card-header">
                            <h5 class="card-title">Recent Files</h5>
                            <div class="card-header-action">
                                <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light-brand">View All</a>
                            </div>
                        </div>
                        <div class="card-body custom-card-action">
                            @forelse($recentDocuments as $document)
                                <div class="d-flex align-items-center justify-content-between mb-4 {{ !$loop->last ? 'border-bottom pb-3' : '' }}">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text avatar-md">
                                            <i class="feather-file-text"></i>
                                        </div>
                                        <div>
                                            <a href="{{ route('documents.show', $document) }}" class="fw-bold d-block mb-1">{{ $document->title }}</a>
                                            <div class="fs-11 text-muted">
                                                <span>{{ $document->documentType->name ?? 'N/A' }}</span>
                                                <span class="mx-2">•</span>
                                                <span>{{ $document->created_at->format('M d, Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="{{ route('documents.show', $document) }}" class="avatar-text avatar-md">
                                            <i class="feather-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5">
                                    <i class="feather-folder fs-1 text-muted mb-3"></i>
                                    <p class="text-muted">No recent files found</p>
                                    <a href="{{ route('documents.create') }}" class="btn btn-sm btn-primary mt-2">Upload File</a>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- PowerBI Links & Quick Actions -->
                <div class="col-xxl-4 col-xl-5">
                    <!-- PowerBI Dashboards -->
                    <div class="card stretch stretch-full mb-4">
                        <div class="card-header">
                            <h5 class="card-title">PowerBI Dashboards</h5>
                            <div class="card-header-action">
                                <a href="{{ route('powerbi-links.index') }}" class="btn btn-sm btn-light-brand">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            @forelse($powerbiLinks->take(5) as $link)
                                <div class="d-flex align-items-center justify-content-between mb-3 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary">
                                            <i class="feather-bar-chart-2"></i>
                                        </div>
                                        <div>
                                            <a href="{{ route('powerbi-links.show', $link) }}" class="fw-semibold d-block text-truncate-1-line" style="max-width: 200px;">{{ $link->title }}</a>
                                            <span class="fs-11 text-muted">{{ $link->powerbiLinkType->name ?? 'Report' }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('powerbi-links.show', $link) }}" class="avatar-text avatar-sm">
                                        <i class="feather-external-link"></i>
                                    </a>
                                </div>
                            @empty
                                <div class="text-center py-4">
                                    <i class="feather-bar-chart-2 fs-1 text-muted mb-2"></i>
                                    <p class="text-muted fs-12">No PowerBI dashboards available</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Commodities -->
                    @if($commodities && $commodities->count() > 0)
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">My Commodities</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($commodities->take(8) as $commodity)
                                        <span class="badge bg-soft-primary text-primary">{{ $commodity->name }}</span>
                                    @endforeach
                                    @if($commodities->count() > 8)
                                        <span class="badge bg-soft-secondary text-secondary">+{{ $commodities->count() - 8 }} more</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Expiring Documents (Admin Only) -->
            @if(isset($expiringDocuments) && $expiringDocuments->count() > 0)
                <div class="row">
                    <div class="col-12">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Expiring Files (Next 30 Days)</h5>
                                <div class="card-header-action">
                                    <a href="{{ route('documents.index') }}?filter=expiring" class="btn btn-sm btn-light-brand">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>File Name</th>
                                                <th>Type</th>
                                                <th>Expiry Date</th>
                                                <th>Days Left</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($expiringDocuments as $document)
                                                <tr>
                                                    <td>{{ $document->title }}</td>
                                                    <td>{{ $document->documentType->name ?? 'N/A' }}</td>
                                                    <td>{{ $document->expiry_date->format('M d, Y') }}</td>
                                                    <td>
                                                        @php
                                                            $daysLeft = now()->diffInDays($document->expiry_date, false);
                                                        @endphp
                                                        <span class="badge {{ $daysLeft <= 7 ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">
                                                            {{ $daysLeft }} days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('documents.show', $document) }}" class="btn btn-sm btn-light-brand">View</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        {{-- </div> --}}
        <!-- [ Main Content ] end -->
    {{-- </div>
</main> --}}
@endsection

