@extends('layouts.tenant')

@section('page-title', $file->title ?: $file->original_filename)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tenant.files.index') }}">Files</a></li>
    @if($file->fileType)
        <li class="breadcrumb-item"><a href="{{ route('tenant.files.index', ['file_type_id' => $file->fileType->id]) }}">{{ $file->fileType->name }}</a></li>
    @endif
    <li class="breadcrumb-item active">{{ truncate_filename($file->original_filename ?: $file->filename, 50) }}</li>
@endsection

@section('content')
<div class="row">
    <!-- File Preview Column -->
    <div class="col-xxl-8 col-lg-7">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">File Preview</h5>
                <div class="card-header-action">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                            <i class="feather-more-vertical"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            @can('download files')
                                <a href="{{ route('tenant.files.download', $file) }}" class="dropdown-item">
                                    <i class="feather-download me-2"></i>
                                    <span>Download</span>
                                </a>
                            @endcan
                            @can('edit files')
                                <a href="{{ route('tenant.files.edit', $file) }}" class="dropdown-item">
                                    <i class="feather-edit me-2"></i>
                                    <span>Edit</span>
                                </a>
                            @endcan
                            @can('delete files')
                                <div class="dropdown-divider"></div>
                                <form action="{{ route('tenant.files.destroy', $file) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this file?')">
                                        <i class="feather-trash-2 me-2"></i>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="file-preview-container text-center" style="min-height: 500px;">
                    @php
                        $extension = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
                        $isPdf = $extension === 'pdf';
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp']);
                        $isOffice = in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
                    @endphp

                    @if($isPdf)
                        {{-- PDF Preview --}}
                        <iframe 
                            src="{{ route('tenant.files.preview', $file) }}" 
                            class="w-100 border-0" 
                            style="height: 600px;"
                            title="PDF Preview">
                        </iframe>
                    @elseif($isImage)
                        {{-- Image Preview --}}
                        <img 
                            src="{{ route('tenant.files.preview', $file) }}" 
                            alt="{{ truncate_filename($file->original_filename, 50) }}" 
                            class="img-fluid rounded"
                            style="max-height: 600px; object-fit: contain;">
                    @elseif($isOffice)
                        {{-- Office Documents Preview --}}
                        <div class="d-flex flex-column align-items-center justify-content-center" style="height: 500px;">
                            <div class="mb-4">
                                <i class="feather-file-text" style="font-size: 120px; color: #3454D1;"></i>
                            </div>
                            <h4 class="mb-2">{{ truncate_filename($file->original_filename, 50) }}</h4>
                            <p class="text-muted mb-4">Office document preview not available</p>
                            @can('download files')
                                <a href="{{ route('tenant.files.download', $file) }}" class="btn btn-primary">
                                    <i class="feather-download me-2"></i>
                                    Download to View
                                </a>
                            @endcan
                        </div>
                    @else
                        {{-- Other File Types --}}
                        <div class="d-flex flex-column align-items-center justify-content-center" style="height: 500px;">
                            @php
                                $iconMap = [
                                    'zip' => 'feather-archive',
                                    'rar' => 'feather-archive',
                                    '7z' => 'feather-archive',
                                    'txt' => 'feather-file-text',
                                    'csv' => 'feather-file-text',
                                    'xml' => 'feather-code',
                                    'json' => 'feather-code',
                                ];
                                $iconClass = $iconMap[$extension] ?? 'feather-file';
                            @endphp
                            <div class="mb-4">
                                <i class="{{ $iconClass }}" style="font-size: 120px; color: #64748B;"></i>
                            </div>
                            <h4 class="mb-2">{{ truncate_filename($file->original_filename, 50) }}</h4>
                            <p class="text-muted mb-4">Preview not available for this file type</p>
                            @can('download files')
                                <a href="{{ route('tenant.files.download', $file) }}" class="btn btn-primary">
                                    <i class="feather-download me-2"></i>
                                    Download File
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- File Info Column -->
    <div class="col-xxl-4 col-lg-5">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">File Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="window.location.href='{{ route('tenant.files.index') }}'"></button>
            </div>
            <div class="card-body">
                <!-- File Icon & Name -->
                <div class="text-center mb-4 pb-4 border-bottom">
                    @php
                        $extension = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
                        $iconColorMap = [
                            'pdf' => 'text-danger',
                            'doc' => 'text-primary',
                            'docx' => 'text-primary',
                            'xls' => 'text-success',
                            'xlsx' => 'text-success',
                            'ppt' => 'text-warning',
                            'pptx' => 'text-warning',
                            'zip' => 'text-secondary',
                            'rar' => 'text-secondary',
                            'jpg' => 'text-info',
                            'jpeg' => 'text-info',
                            'png' => 'text-info',
                            'gif' => 'text-info',
                        ];
                        $iconColor = $iconColorMap[$extension] ?? 'text-muted';
                    @endphp
                    <div class="avatar-text avatar-xxl {{ $iconColor }} mb-3">
                        @if(in_array($extension, ['pdf']))
                            <i class="feather-file-text" style="font-size: 48px;"></i>
                        @elseif(in_array($extension, ['doc', 'docx']))
                            <i class="feather-file-text" style="font-size: 48px;"></i>
                        @elseif(in_array($extension, ['xls', 'xlsx']))
                            <i class="feather-file-text" style="font-size: 48px;"></i>
                        @elseif(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp']))
                            <i class="feather-image" style="font-size: 48px;"></i>
                        @elseif(in_array($extension, ['zip', 'rar', '7z']))
                            <i class="feather-archive" style="font-size: 48px;"></i>
                        @else
                            <i class="feather-file" style="font-size: 48px;"></i>
                        @endif
                    </div>
                    <h5 class="mb-1">{{ truncate_filename($file->title ?: $file->original_filename, 50) }}</h5>
                    @if($file->title && $file->original_filename !== $file->title)
                        <small class="text-muted d-block">{{ truncate_filename($file->original_filename, 50) }}</small>
                    @endif
                </div>

                <!-- File Details -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">File Type</span>
                        <span class="fw-semibold">{{ $file->fileType->name ?? 'N/A' }}</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Size</span>
                        <span class="fw-semibold">
                            @if($file->file_size)
                                @if($file->file_size < 1024)
                                    {{ $file->file_size }} B
                                @elseif($file->file_size < 1048576)
                                    {{ number_format($file->file_size / 1024, 2) }} KB
                                @else
                                    {{ number_format($file->file_size / 1048576, 2) }} MB
                                @endif
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Extension</span>
                        <span class="fw-semibold text-uppercase">{{ $extension }}</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Uploaded</span>
                        <span class="fw-semibold">{{ $file->created_at->format('M d, Y') }}</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Last Modified</span>
                        <span class="fw-semibold">{{ $file->updated_at->diffForHumans() }}</span>
                    </div>

                    @if($file->description)
                        <div class="mb-3">
                            <span class="text-muted d-block mb-2">Description</span>
                            <p class="mb-0">{{ $file->description }}</p>
                        </div>
                    @endif
                </div>

                <!-- Commodities -->
                @if($file->commodities && $file->commodities->count() > 0)
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="mb-3">Commodities</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($file->commodities as $commodity)
                                <span class="badge bg-soft-primary text-primary">{{ $commodity->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Views (limit 5) -->
                @if($file->viewers->isNotEmpty())
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="mb-3">Viewed By</h6>
                        <div class="img-group">
                            @foreach($file->viewers->take(5) as $viewer)
                                <a href="{{ route('tenant.users.show', $viewer) }}" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="{{ $viewer->name }}">
                                    <img src="{{ asset($viewer->profile_photo_url) }}" class="img-fluid" alt="image">
                                </a>
                            @endforeach
                            @if($file->viewers->count() > 5)
                             <!-- this will show a modal with full list of viewers in future -->
                                <a href="javascript:void(0)" class="avatar-text avatar-sm bg-soft-secondary" data-bs-toggle="tooltip" data-bs-trigger="hover" title="{{ $file->viewers->count() - 5 }} more">
                                    +{{ $file->viewers->count() - 5 }}
                                </a>
                            @endif
                            <div class="text-truncate-1-line">
                                <span class="text-muted fs-12 ms-2">{{ $file->viewers->count() }} member(s) recently accessed</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Related Entities -->
                <div class="mb-4">
                    <!-- uploaded by -->
                    @if($file->uploadedBy)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Uploaded By</span>
                            <span class="fw-semibold">{{ $file->uploadedBy->name }}</span>
                        </div>
                    @endif
                    <hr>
                    <h6 class="mb-3">Related Entities</h6>
                    @if($file->grower())
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Grower</span>
                            <span class="fw-semibold">{{ $file->grower()->name }}</span>
                        </div>
                    @endif

                    @if($file->fbo)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">FBO</span>
                            <span class="fw-semibold">{{ $file->fbo->name }}</span>
                        </div>
                    @endif

                    @if($file->variety)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Variety</span>
                            <span class="fw-semibold">{{ $file->variety->name }}</span>
                        </div>
                    @endif

                    @if($file->vessel)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Vessel</span>
                            <span class="fw-semibold">{{ $file->vessel->name }}</span>
                        </div>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    @can('download files')
                        <a href="{{ route('tenant.files.download', $file) }}" class="btn btn-primary">
                            <i class="feather-download me-2"></i>
                            Download File
                        </a>
                    @endcan
                    
                    @can('edit files')
                        <a href="{{ route('tenant.files.edit', $file) }}" class="btn btn-light-brand">
                            <i class="feather-edit me-2"></i>
                            Edit Details
                        </a>
                    @endcan
                    
                    <a href="{{ route('tenant.files.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-2"></i>
                        Back to Files
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Handle card dismissal - redirect to files list
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.querySelector('.card .btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                window.location.href = '{{ route("tenant.files.index") }}';
            });
        }
    });
</script>
@endsection
