@extends('layouts.tenant')

@section('page-title', 'Files')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tenant.files.index') }}">Files</a></li>
    @if(!empty($filters['file_type_id']))
        @php
            $currentFileType = $fileTypes->firstWhere('id', $filters['file_type_id']);
        @endphp
        @if($currentFileType)
            <li class="breadcrumb-item">{{ $currentFileType->name }}</li>
        @endif
    @endif
    @if(isset($filters['commodity_name']))
        <li class="breadcrumb-item">{{ $filters['commodity_name'] }}</li>
    @endif
@endsection

@section('content')
<!-- [ Content Sidebar ] start -->
    <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
        <div class="content-sidebar-header bg-white sticky-top hstack justify-content-between">
            <h4 class="fw-bolder mb-0">Files</h4>
            <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                <i class="feather-x"></i>
            </a>
        </div>
        <div class="content-sidebar-header">
            <a href="{{ route('tenant.files.create') }}" class="btn btn-primary w-100">
                <i class="feather-upload me-2"></i>
                <span>Upload Files</span>
            </a>
        </div>
        <div class="content-sidebar-body">
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="nav-item">
                    <a class="nav-link {{ empty($filters['file_type_id']) ? 'active' : '' }}" href="{{ route('tenant.files.index') }}">
                        <i class="feather-home"></i>
                        <span>All Files</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between" 
                       data-bs-toggle="collapse" 
                       href="#foldersCollapse" 
                       role="button" 
                       aria-expanded="true">
                        <span class="d-flex align-items-center">
                            <i class="feather-folder me-3"></i>
                            <span>File Type Folders</span>
                        </span>
                        <span class="badge bg-soft-primary text-primary">{{ $fileTypes->count() }}</span>
                    </a>
                    <div class="collapse show" id="foldersCollapse">
                        <ul class="nav flex-column ms-4">
                            @foreach($fileTypes as $fileType)
                            <li class="nav-item">
                                <a class="nav-link {{ !empty($filters['file_type_id']) && $filters['file_type_id'] == $fileType->id ? 'active' : '' }}" 
                                   href="{{ route('tenant.files.index', array_merge(request()->except(['file_type_id', 'page']), ['file_type_id' => $fileType->id])) }}">
                                    <i class="feather-folder"></i>
                                    <span>{{ $fileType->name }}</span>
                                    <span class="badge bg-soft-secondary text-secondary ms-auto">{{ $fileType->files()->count() }}</span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-clock"></i>
                        <span>History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-settings"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="px-4 my-2 fs-10 fw-bold text-uppercase text-muted text-spacing-1 d-flex align-items-center justify-content-between">
                    <span>Roles</span>
                    <a href="javascript:void(0);">
                        <span class="avatar-text avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Add New"> <i class="feather-plus"></i> </span>
                    </a>
                </li>
                <li class="nav-item">
                    <div class="nav-link">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="checkboxAlls">
                            <label class="custom-control-label c-pointer" for="checkboxAlls">All</label>
                        </div>
                    </div>
                </li>
                {{-- roles --}}
                @foreach($roles as $role)
                <li class="nav-item">
                    <div class="nav-link">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="checkbox{{ $role->name }}" {{ $role->users_count > 0 ? 'checked' : '' }}>
                            <label class="custom-control-label c-pointer" for="checkbox{{ $role->name }}">{{ ucwords(str_replace('-', ' ', $role->name)) }}</label>
                        </div>
                    </div>
                </li>
                @endforeach
                
            </ul>
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="px-4 mx-2 my-2 fs-10 fw-bold text-uppercase text-muted text-spacing-1 d-flex align-items-center justify-content-between">
                    <span>Filter</span>
                    <a href="javascript:void(0);">
                        <span class="avatar-text avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Add New"> <i class="feather-plus"></i> </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-clock"></i>
                        <span>Recent</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-star"></i>
                        <span>Expiring</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-bell"></i>
                        <span>Expired</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between" href="javascript:void(0);">
                        <span class="d-flex align-items-center">
                            <i class="feather-info me-3"></i>
                            <span>Important</span>
                        </span>
                        <span class="badge bg-soft-success text-success">3</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="feather-share-2"></i>
                        <span>Shared Files</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <!-- [ Content Sidebar  ] end -->

    <!-- [ Main Area  ] start -->
    <div class="content-area" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2">
                <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                    <i class="feather-align-left fs-20"></i>
                </a>
                <select class="form-control" data-select2-selector="storage">
                    <option value="box" data-storage="box">Box</option>
                    <option value="icloud" data-storage="icloud">iCloud</option>
                    <option value="dropbox" data-storage="dropbox" selected>Dropbox</option>
                    <option value="onedrive" data-storage="onedrive">Onedrive</option>
                    <option value="google-drive" data-storage="google-drive">G-Drive</option>
                    <option value="local-storage" data-storage="local-storage">Local</option>
                </select>
                <div class="dropdown d-none d-sm-block">
                    <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,22">
                        <i class="feather-eye"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-eye me-3"></i>
                                <span>Read</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-eye-off me-3"></i>
                                <span>Unread</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-star me-3"></i>
                                <span>Starred</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-shield-off me-3"></i>
                                <span>Unstarred</span>
                            </a>
                        </li>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-clock me-3"></i>
                                <span>Snooze</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-check-circle me-3"></i>
                                <span>Add Tasks</span>
                            </a>
                        </li>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-archive me-3"></i>
                                <span>Archive</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-alert-octagon me-3"></i>
                                <span>Report Spam</span>
                            </a>
                        </li>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)">
                                <i class="feather-trash-2 me-3"></i>
                                <span>Delete</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0)" class="d-flex" data-bs-toggle="dropdown" data-bs-offset="0,22" data-bs-auto-close="outside" aria-expanded="false">
                        <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Tags">
                            <i class="feather-tag"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu">
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Office" checked="checked">
                                <label class="custom-control-label c-pointer" for="Office">Office</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Family">
                                <label class="custom-control-label c-pointer" for="Family">Family</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Friend" checked="checked">
                                <label class="custom-control-label c-pointer" for="Friend">Friend</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Marketplace">
                                <label class="custom-control-label c-pointer" for="Marketplace"> Marketplace </label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Development">
                                <label class="custom-control-label c-pointer" for="Development"> Development </label>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-plus me-3"></i>
                            <span>Create Tag</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-tag me-3"></i>
                            <span>Manages Tag</span>
                        </a>
                    </div>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0)" class="d-flex" data-bs-toggle="dropdown" data-bs-offset="0,22" data-bs-auto-close="outside" aria-expanded="false">
                        <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Labels">
                            <i class="feather-folder-plus"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu">
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Updates">
                                <label class="custom-control-label c-pointer" for="Updates">Updates</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Socials">
                                <label class="custom-control-label c-pointer" for="Socials">Socials</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Primary" checked="checked">
                                <label class="custom-control-label c-pointer" for="Primary">Primary</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Forums">
                                <label class="custom-control-label c-pointer" for="Forums">Forums</label>
                            </div>
                        </div>
                        <div class="dropdown-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="Promotions" checked="checked">
                                <label class="custom-control-label c-pointer" for="Promotions"> Promotions </label>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-plus me-3"></i>
                            <span>Create Label</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-folder-plus me-3"></i>
                            <span>Manages Label</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="page-header-right ms-auto">
                <div class="hstack gap-2">
                    <div class="hstack">
                        <a href="javascript:void(0)" class="search-form-open-toggle">
                            <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Search">
                                <i class="feather-search"></i>
                            </div>
                        </a>
                        <form class="search-form" style="display: none">
                            <div class="search-form-inner">
                                <a href="javascript:void(0)" class="search-form-close-toggle">
                                    <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Back">
                                        <i class="feather-arrow-left"></i>
                                    </div>
                                </a>
                                <input type="search" class="py-3 px-0 border-0 w-100" id="storageSearch" placeholder="Search..." autocomplete="off">
                            </div>
                        </form>
                    </div>
                    <a href="javascript:void(0)" class="d-flex d-none d-sm-block">
                        <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Grid View">
                            <i class="feather-grid"></i>
                        </div>
                    </a>
                    <a href="javascript:void(0)" class="d-flex d-none d-sm-block">
                        <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="List View">
                            <i class="feather-list"></i>
                        </div>
                    </a>
                    <div class="dropdown d-none d-sm-block">
                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-offset="0, 22">
                            <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Add New">
                                <i class="feather-plus"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-file-plus me-3"></i>
                                    <span>New File</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-file-text me-3"></i>
                                    <span>New Note</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-folder-plus me-3"></i>
                                    <span>New Folder</span>
                                </a>
                            </li>
                            <li class="dropdown-divider"></li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-image me-3"></i>
                                    <span>New Image</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-music me-3"></i>
                                    <span>New Audio</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-video me-3"></i>
                                    <span>New Video</span>
                                </a>
                            </li>
                            <li class="dropdown-divider"></li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-hard-drive me-3"></i>
                                    <span>Add New Drive</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-database me-3"></i>
                                    <span>Add New Storage</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="dropdown d-none d-sm-block">
                        <a href="javascript:void(0)" class="d-flex" data-bs-toggle="dropdown" data-bs-offset="0, 22" data-bs-auto-close="outside">
                            <div class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="More Options">
                                <i class="feather-more-vertical"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-sliders me-3"></i>
                                    <span>Filter</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-eye-off me-3"></i>
                                    <span>Unread</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-archive me-3"></i>
                                    <span>Archive</span>
                                </a>
                            </li>
                            <li class="dropdown-divider"></li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-alert-triangle me-3"></i>
                                    <span>Spam</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-alert-octagon me-3"></i>
                                    <span>phishing</span>
                                </a>
                            </li>
                            <li class="dropdown-divider"></li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-bell-off me-3"></i>
                                    <span>Mute</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-slash me-3"></i>
                                    <span>Block</span>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-trash-2 me-3"></i>
                                    <span>Delete</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-area-body">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>{{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!--! BEGIN: [recent-section] !-->
            <div hidden class="recent-section mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="me-4">
                        <h2 class="fs-16 fw-bold mb-1">Recent Files</h2>
                        <div class="fs-12 text-muted text-truncate-1-line">
                            Recently uploaded files 
                            @if($recentFiles->isNotEmpty())
                                <span class="fs-11 fw-normal text-muted">({{ $recentFiles->count() }} files)</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('tenant.files.index') }}" class="btn btn-sm btn-light-brand">View All</a>
                </div>
                <div class="row">
                    @if($recentFiles->isEmpty())
                        <div class="col-12">
                            <div class="alert alert-info mb-0" role="alert">
                                <i class="feather-info me-2"></i>No recent files found. <a href="{{ route('tenant.files.create') }}" class="alert-link">Upload your first file</a>.
                            </div>
                        </div>
                    @else
                        @foreach($recentFiles as $recentFile)
                        <div class="col-xxl-3 col-sm-6">
                            <div class="card mb-4 stretch stretch-full">
                                <div class="card-body p-0 ht-250">
                                    <a href="{{ route('tenant.files.preview', $recentFile->id) }}" 
                                       class="w-100 h-100 d-flex align-items-center justify-content-center">
                                        @php
                                            $extension = strtolower(pathinfo($recentFile->file_name, PATHINFO_EXTENSION));
                                            $iconMap = [
                                                'pdf' => 'pdf.png',
                                                'doc' => 'doc.png',
                                                'docx' => 'doc.png',
                                                'xls' => 'xls.png',
                                                'xlsx' => 'xls.png',
                                                'zip' => 'zip.png',
                                                'rar' => 'zip.png',
                                                'jpg' => 'png.png',
                                                'jpeg' => 'png.png',
                                                'png' => 'png.png',
                                                'gif' => 'png.png',
                                                'csv' => 'csv.png',
                                                'txt' => 'txt.png',
                                            ];
                                            $icon = $iconMap[$extension] ?? 'undefined.png';
                                        @endphp
                                        <img src="{{ asset('vendor/duralux-admin/assets/images/file-icons/' . $icon) }}" 
                                             class="img-fluid wd-100 ht-100" alt="{{ $recentFile->file_name }}">
                                    </a>
                                </div>
                                <div class="card-footer p-4">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div class="flex-grow-1 me-2">
                                            <h2 class="fs-13 mb-1 text-truncate-1-line">
                                                <a href="{{ route('tenant.files.show', $recentFile->id) }}" class="text-dark">
                                                    {{ $recentFile->title ?? $recentFile->file_name }}
                                                </a>
                                            </h2>
                                            <small class="fs-10 text-muted d-block mb-2">
                                                {{ $recentFile->fileType->name ?? 'Unknown' }}
                                                @if($recentFile->commodities->isNotEmpty())
                                                    · {{ $recentFile->commodities->first()->name }}
                                                @endif
                                            </small>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-soft-secondary text-secondary fs-10">
                                                    {{ number_format($recentFile->file_size / 1024, 0) }} KB
                                                </span>
                                                <span class="badge bg-soft-info text-info fs-10">
                                                    {{ strtoupper($extension) }}
                                                </span>
                                                <span class="fs-10 text-muted">
                                                    {{ $recentFile->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                                <i class="feather-more-vertical"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a href="{{ route('tenant.files.preview', $recentFile->id) }}" class="dropdown-item">
                                                        <i class="feather-eye me-3"></i>
                                                        <span>Preview</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('tenant.files.show', $recentFile->id) }}" class="dropdown-item">
                                                        <i class="feather-info me-3"></i>
                                                        <span>Details</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('tenant.files.download', $recentFile->id) }}" class="dropdown-item">
                                                        <i class="feather-download me-3"></i>
                                                        <span>Download</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <a href="{{ route('tenant.files.edit', $recentFile->id) }}" class="dropdown-item">
                                                        <i class="feather-edit-2 me-3"></i>
                                                        <span>Edit</span>
                                                    </a>
                                                </li>
                                                @can('delete', $recentFile)
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('tenant.files.destroy', $recentFile->id) }}" 
                                                          method="POST" 
                                                          onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="feather-trash-2 me-3"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </form>
                                                </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <!--! END: [recent-section] !-->
            <!--! BEGIN: [folder-section] !-->
            <div class="folder-section mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="me-4">
                        <h2 class="fs-16 fw-bold mb-1">File Type Folders</h2>
                        <div class="text-muted text-truncate-1-line">Browse files by document type</div>
                    </div>
                    @if(!empty($filters['file_type_id']))
                        <a href="{{ route('tenant.files.index') }}" class="btn btn-sm btn-light-brand">
                            <i class="feather-x me-1"></i>Clear Filter
                        </a>
                    @endif
                </div>
                <div class="row">
                    @if ($fileTypes->isEmpty())
                        <div class="col-12">
                            <div class="alert alert-info mb-0" role="alert">
                                <i class="feather-info me-2"></i>No file type folders available.
                            </div>
                        </div>
                    @else
                    @foreach ($fileTypes as $fileType)
                    <div class="col-xxl-3 col-xl-4 col-sm-6">
                        <div class="card mb-4 stretch stretch-full {{ !empty($filters['file_type_id']) && $filters['file_type_id'] == $fileType->id ? 'border-primary' : '' }}">
                            <div class="card-body p-0">
                                <a href="{{ route('tenant.files.index', array_merge(request()->except(['file_type_id', 'page']), ['file_type_id' => $fileType->id])) }}" 
                                   class="d-flex align-items-center border-bottom p-3">
                                    <div class="wd-50 ht-50 bg-gray-200 rounded d-flex align-items-center justify-content-center">
                                        <i class="{{ $fileType->metadata['icon'] ?? 'feather-folder' }} fs-4 text-primary"></i>
                                    </div>
                                    <div class="ms-3">
                                        <span class="d-block fw-semibold text-dark">{{ $fileType->name }}</span>
                                        <span class="fs-11 text-muted d-block">{{ $fileType->files()->count() }} Files</span>
                                    </div>
                                </a>
                                <div class="p-3">
                                    @if($fileType->description)
                                        <p class="fs-12 text-muted mb-2 text-truncate-2-line">{{ $fileType->description }}</p>
                                    @endif
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="badge bg-soft-{{ $fileType->attribute_type === 'grower' ? 'success' : ($fileType->attribute_type === 'customer' ? 'info' : 'secondary') }} text-{{ $fileType->attribute_type === 'grower' ? 'success' : ($fileType->attribute_type === 'customer' ? 'info' : 'secondary') }} fs-10">
                                            {{ ucfirst($fileType->attribute_type) }}
                                        </span>
                                        <a href="{{ route('tenant.files.index', array_merge(request()->except(['file_type_id', 'page']), ['file_type_id' => $fileType->id])) }}" 
                                           class="fs-12 text-primary">
                                            View Files <i class="feather-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
            <!--! END: [folder-section] !-->
            <!--! BEGIN: [project-section] !-->
            <div class="project-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="me-4">
                        <h2 class="fs-16 fw-bold mb-1">
                            @if(!empty($filters['file_type_id']))
                                @php
                                    $currentFileType = $fileTypes->firstWhere('id', $filters['file_type_id']);
                                @endphp
                                @if($currentFileType)
                                    <i class="feather-folder me-2 text-primary"></i>{{ $currentFileType->name }} Files
                                @else
                                    Files
                                @endif
                            @else
                                All Files
                            @endif
                        </h2>
                        <div class="fs-12 text-muted text-truncate-1-line">
                            @if(!empty($filters['file_type_id']) && isset($currentFileType))
                                {{ $files->total() }} files in {{ $currentFileType->name }}
                            @else
                                {{ $files->total() }} total files
                            @endif
                        </div>
                    </div>
                    @if(!empty($filters['file_type_id']))
                        <a href="{{ route('tenant.files.index') }}" class="btn btn-sm btn-light-brand">
                            <i class="feather-x me-1"></i>Clear Filter
                        </a>
                    @endif
                </div>
                <div class="card mb-0">
                    @if($files->count() > 0)
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Folder</th>
                                        <th scope="col">Size</th>
                                        <th scope="col">Upload</th>
                                        <th scope="col">Views</th>
                                        <th scope="col" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($files as $file)
                                    @php
                                        $extension = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
                                        $iconMap = [
                                            'pdf' => 'pdf.png',
                                            'doc' => 'doc.png',
                                            'docx' => 'doc.png',
                                            'xls' => 'xls.png',
                                            'xlsx' => 'xls.png',
                                            'zip' => 'zip.png',
                                            'rar' => 'zip.png',
                                            'jpg' => 'png.png',
                                            'jpeg' => 'png.png',
                                            'png' => 'png.png',
                                            'gif' => 'png.png',
                                            'csv' => 'csv.png',
                                            'txt' => 'txt.png',
                                        ];
                                        $icon = $iconMap[$extension] ?? 'undefined.png';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="{{ asset('vendor/duralux-admin/assets/images/file-icons/' . $icon) }}" class="img-fluid rounded wd-30" alt="">
                                                <a href="{{ route('tenant.files.show', $file) }}">{{ truncate_filename($file->original_filename, 30) }}</a>
                                            </div>
                                        </td>
                                        <td>{{ $file->fileType->name }}</td>
                                        <td>{{ number_format($file->file_size / 1048576, 2) }} MB</td>
                                        <td>{{ $file->created_at->format('d F, Y') }}</td>
                                        <td>
                                            @if($file->viewers->isNotEmpty())
                                            <div class="img-group lh-lg">
                                                @foreach($file->viewers as $viewer)
                                                <a href="{{ route('tenant.users.show', $viewer) }}" class="avatar-image avatar-sm" data-bs-toggle="tooltip" data-bs-trigger="hover" title="{{ $viewer->name }}">
                                                    <img src="{{ asset($viewer->profile_photo_url) }}" class="img-fluid" alt="image">
                                                </a>
                                                @endforeach
                                            </div>
                                            @else
                                            <span class="text-muted">No views yet</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dropdown hstack text-end justify-content-end">
                                                <a href="javascript:void(0)" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                                    <i class="feather-more-vertical"></i>
                                                </a>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a href="{{ route('tenant.files.show', $file) }}" class="dropdown-item">
                                                            <i class="feather-eye me-3"></i>
                                                            <span>Open</span>
                                                        </a>
                                                    </li>
                                                    @can('download files')
                                                    <li>
                                                        <a href="{{ route('tenant.files.download', $file) }}" class="dropdown-item">
                                                            <i class="feather-share-2 me-3"></i>
                                                            <span>Download</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    @endcan
                                                    @can('edit files')
                                                    <li>
                                                        <a href="{{ route('tenant.files.edit', $file) }}" class="dropdown-item">
                                                            <i class="feather-edit-3 me-3"></i>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    @endcan
                                                    @can('delete files')
                                                    <li>
                                                        <a href="{{ route('tenant.files.destroy', $file) }}" class="dropdown-item text-danger delete-document-btn">
                                                            <i class="feather-x me-3"></i>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                    @endcan
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        {{-- Pagination links --}}
                        {{-- Beautiful pagination --}}
                        @if($files->hasPages())
                        <div class="container-fluid py-3">
                            <div class="row align-items-center">
                            <div class="col-md-12 float-end">
                                {{-- Persist all filters in pagination links --}}
                                {{ $files->appends(request()->except('page'))->links('vendor.pagination.bootstrap-5') }}
                            </div>
                            </div>
                        </div>
                        @endif
                        
                    </div>
                    @else
                    <div class="card-body text-center py-5">
                        <i class="bi bi-folder fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No files found</h5>
                        <p class="text-muted">Try adjusting your search criteria or upload some files.</p>
                        @can('upload files')
                        
                        <a href="{{ route('tenant.files.create') }}" class="btn btn-primary d-inline-block">
                            <i class="feather-upload me-2"></i>
                            <span>Upload Files</span>
                        </a>
                        @endcan
                    </div>
                    @endif
                </div>
            </div>
            <!--! END: [project-section] !-->
        </div>
    </div>
@endsection

@section('page-script')
    <script src="{{ asset('vendor/duralux-admin/assets/js/apps-storage-init.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle delete confirmation
            document.querySelectorAll('.delete-document-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const documentId = btn.getAttribute('data-document-id');
                const documentName = btn.getAttribute('data-document-name');
                if(confirm(`Are you sure you want to permanently delete "${documentName}"?`)) {
                fetch(`/t/files/${documentId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if(response.ok) {
                    // Reload the page with current page parameter to preserve pagination
                    const urlParams = new URLSearchParams(window.location.search);
                    const page = urlParams.get('page') || 1;
                    window.location.href = `{{ route('tenant.files.index') }}?page=${page}`;
                    } else {
                    alert('Failed to delete document.');
                    }
                })
                .catch(() => alert('Failed to delete document.'));
                }
            });
            });
        });
    </script>

@endsection
