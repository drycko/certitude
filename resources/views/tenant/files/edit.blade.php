@extends('layouts.tenant')

@section('page-title', 'Edit File')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tenant.files.index') }}">Files</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tenant.files.show', $file) }}">{{ truncate_filename($file->title ?: $file->original_filename, 30) }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
@include('partials.choices-cdn')

<!-- Flash Messages -->
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="feather-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="feather-alert-circle me-2"></i>{{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-lg-12">
        <form method="POST" action="{{ route('tenant.files.update', $file) }}" enctype="multipart/form-data" id="editForm">
            @csrf
            @method('PUT')
            <div class="row">
                <!-- Left Column - File Information -->
                <div class="col-xl-8">
                    <div class="card stretch stretch-full">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-edit me-2"></i>File Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Current File Info -->
                            <div class="mb-4 p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    @php
                                        $extension = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
                                        $iconColorMap = [
                                            'pdf' => 'text-danger',
                                            'doc' => 'text-primary', 'docx' => 'text-primary',
                                            'xls' => 'text-success', 'xlsx' => 'text-success',
                                            'ppt' => 'text-warning', 'pptx' => 'text-warning',
                                            'zip' => 'text-secondary', 'rar' => 'text-secondary',
                                            'jpg' => 'text-info', 'jpeg' => 'text-info', 'png' => 'text-info', 'gif' => 'text-info',
                                        ];
                                        $iconColor = $iconColorMap[$extension] ?? 'text-muted';
                                    @endphp
                                    <div class="avatar-text avatar-lg {{ $iconColor }} me-3">
                                        <i class="feather-file" style="font-size: 24px;"></i>
                                    </div>
                                    <div class="flex-fill">
                                        <h6 class="mb-1">{{ $file->original_filename }}</h6>
                                        <small class="text-muted">
                                            @if($file->file_size)
                                                @if($file->file_size < 1024)
                                                    {{ $file->file_size }} B
                                                @elseif($file->file_size < 1048576)
                                                    {{ number_format($file->file_size / 1024, 2) }} KB
                                                @else
                                                    {{ number_format($file->file_size / 1048576, 2) }} MB
                                                @endif
                                            @endif
                                            • Uploaded {{ $file->created_at->format('M d, Y') }}
                                        </small>
                                    </div>
                                    <a href="{{ route('tenant.files.preview', $file) }}" target="_blank" class="btn btn-sm btn-light">
                                        <i class="feather-eye me-1"></i> Preview
                                    </a>
                                </div>
                            </div>

                            <!-- Replace File (Admin Only) -->
                            @can('upload files')
                                @if(auth()->user()->hasRole(['admin', 'super-user']))
                                    <div class="mb-4">
                                        <label class="form-label">Replace File (Optional)</label>
                                        <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                            id="file" name="file" 
                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                                        <small class="text-muted">
                                            Leave empty to keep current file. Max 50MB
                                        </small>
                                        @error('file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            @endcan

                            <!-- Title -->
                            <div class="mb-4">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                    id="title" name="title" value="{{ old('title', $file->title) }}" required>
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <!-- File Type -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">File Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('file_type_id') is-invalid @enderror" 
                                        id="file_type_id" name="file_type_id" required>
                                        <option value="">Select File Type</option>
                                        @foreach($fileTypes as $type)
                                            <option value="{{ $type->id }}" 
                                                {{ old('file_type_id', $file->file_type_id) == $type->id ? 'selected' : '' }} 
                                                data-attrib="{{ $type->attribute_type }}">
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('file_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Sub File Type -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Sub File Type</label>
                                    <select class="form-select @error('sub_file_type_id') is-invalid @enderror" 
                                        id="sub_file_type_id" name="sub_file_type_id">
                                        <option value="">Select Sub Type</option>
                                        @foreach($subFileTypes as $subType)
                                            <option value="{{ $subType->id }}" 
                                                {{ old('sub_file_type_id', $file->sub_file_type_id) == $subType->id ? 'selected' : '' }} 
                                                data-parent-id="{{ $subType->parent_id }}">
                                                {{ $subType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('sub_file_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Grower -->
                                <div class="col-md-6 mb-4" id="growerFormGroup">
                                    <label class="form-label">Grower</label>
                                    <select class="form-select @error('grower_id') is-invalid @enderror" 
                                        id="grower_id" name="grower_id">
                                        <option value="">Select Grower</option>
                                        @foreach($growers as $grower)
                                            <option value="{{ $grower->id }}" 
                                                {{ old('grower_id', $file->grower_id) == $grower->id ? 'selected' : '' }}>
                                                {{ $grower->grower_number }} - {{ $grower->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('grower_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- FBOs -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">FBO(Food Business Operator)s</label>
                                    <select class="form-select @error('fbos') is-invalid @enderror" 
                                        id="fbos" name="fbos[]" multiple>
                                        @foreach($fbos as $fbo)
                                            <option value="{{ $fbo->code }}" 
                                                {{ in_array($fbo->code, old('fbos', $file->fbos->pluck('code')->toArray())) ? 'selected' : '' }}>
                                                {{ $fbo->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fbos')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Varieties -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Variety</label>
                                    <select class="form-select @error('varieties') is-invalid @enderror" 
                                        id="varieties" name="varieties[]" multiple>
                                        @foreach($varieties as $variety)
                                            <option value="{{ $variety->id }}" 
                                                {{ in_array($variety->id, old('varieties', $file->varieties->pluck('id')->toArray())) ? 'selected' : '' }}>
                                                {{ $variety->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('varieties')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Expiry Date -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control @error('expiry_date') is-invalid @enderror" 
                                        id="expiry_date" name="expiry_date" 
                                        value="{{ old('expiry_date', $fileExpiryDate) }}">
                                    @error('expiry_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Season Year -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Season Year</label>
                                    <input type="number" class="form-control @error('season_year') is-invalid @enderror" 
                                        id="season_year" name="season_year" 
                                        min="2020" max="2099" value="{{ old('season_year', $file->season_year) }}">
                                    @error('season_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Vessel Name -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Vessel Name</label>
                                    <input type="text" class="form-control @error('vessel_name') is-invalid @enderror" 
                                        id="vessel_name" name="vessel_name" 
                                        value="{{ old('vessel_name', $file->metadata['vessel_name'] ?? '') }}" 
                                        placeholder="Vessel name">
                                    @error('vessel_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Container Number -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Container No.</label>
                                    <input type="text" class="form-control @error('container_number') is-invalid @enderror" 
                                        id="container_number" name="container_number" 
                                        value="{{ old('container_number', $file->container_number) }}">
                                    @error('container_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- QC Reference -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">QC Reference</label>
                                    <input type="text" class="form-control @error('quality_ref_number') is-invalid @enderror" 
                                        id="quality_ref_number" name="quality_ref_number" 
                                        value="{{ old('quality_ref_number', $file->quality_ref_number) }}">
                                    @error('quality_ref_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- QC Rating -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">QC Rating</label>
                                    <select class="form-select @error('quality_rating') is-invalid @enderror" 
                                        id="quality_rating" name="quality_rating">
                                        <option value="">Select Rating</option>
                                        <option value="Sound" {{ old('quality_rating', $file->quality_rating) == 'Sound' ? 'selected' : '' }}>Sound</option>
                                        <option value="Unsound" {{ old('quality_rating', $file->quality_rating) == 'Unsound' ? 'selected' : '' }}>Unsound</option>
                                    </select>
                                    @error('quality_rating')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Description -->
                                <div class="col-12 mb-4">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                        id="description" name="description" rows="3">{{ old('description', $file->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Commodities & Actions -->
                <div class="col-xl-4">
                    <!-- Commodities Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-package me-2"></i>Associated Fruit Types
                                <span class="text-danger">*</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            @php
                                $selectedCommodities = old('commodities', $file->commodities->pluck('id')->toArray());
                            @endphp
                            <div class="row">
                                @foreach($commodities as $commodity)
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input @error('commodities') is-invalid @enderror" 
                                                type="checkbox" 
                                                id="commodity_{{ $commodity->id }}" 
                                                name="commodities[]" 
                                                value="{{ $commodity->id }}" 
                                                {{ in_array($commodity->id, $selectedCommodities) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="commodity_{{ $commodity->id }}">
                                                {{ $commodity->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('commodities')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Visibility & Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-settings me-2"></i>Visibility & Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                        value="1" {{ old('is_public', $file->is_public) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_public">
                                        Public File
                                    </label>
                                </div>
                                <small class="text-muted">Public files are visible to all users</small>
                            </div>
                            <div class="mb-0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                        value="1" {{ old('is_active', $file->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active
                                    </label>
                                </div>
                                <small class="text-muted">Inactive files are hidden from lists</small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-save me-2"></i>Update File
                                </button>
                                <a href="{{ route('tenant.files.show', $file) }}" class="btn btn-light">
                                    <i class="feather-x me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- File Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-bar-chart-2 me-2"></i>Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Views</span>
                                <span class="fw-semibold">{{ $file->getViewsCount() }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Downloads</span>
                                <span class="fw-semibold">{{ $file->getDownloadsCount() }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Last Updated</span>
                                <span class="fw-semibold">{{ $file->updated_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@push('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File type change event to filter sub-file types
    const fileTypeSelect = document.getElementById('file_type_id');
    const subFileTypeSelect = document.getElementById('sub_file_type_id');
    const growerFormGroup = document.getElementById('growerFormGroup');

    // Filter sub-file types on page load
    filterSubFileTypes();

    fileTypeSelect.addEventListener('change', function() {
        filterSubFileTypes();
        
        const selectedOption = fileTypeSelect.options[fileTypeSelect.selectedIndex];
        const attributeType = selectedOption ? selectedOption.dataset.attrib : '';

        // Handle grower attribute type logic
        if (attributeType === 'grower') {
            @if(!auth()->user()->hasRole('grower'))
                // Force private for non-grower users
                document.getElementById('is_public').checked = false;
            @endif
        }
    });

    function filterSubFileTypes() {
        const selectedTypeId = fileTypeSelect.value;
        
        // Show/Hide sub-file types based on selected file type
        Array.from(subFileTypeSelect.options).forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                option.style.display = option.dataset.parentId == selectedTypeId ? 'block' : 'none';
            }
        });

        // Reset sub-file type if it doesn't match parent
        const currentSubType = subFileTypeSelect.value;
        if (currentSubType) {
            const currentOption = subFileTypeSelect.querySelector(`option[value="${currentSubType}"]`);
            if (currentOption && currentOption.dataset.parentId != selectedTypeId) {
                subFileTypeSelect.value = '';
            }
        }
    }

    // Choices.js for FBOs multi-select
    const fbosSelect = document.getElementById('fbos');
    if (fbosSelect) {
        new Choices(fbosSelect, {
            removeItemButton: true,
            searchEnabled: true,
            placeholder: true,
            placeholderValue: 'Select Associated FBOs',
            shouldSort: false
        });
    }

    // Choices.js for varieties multi-select
    const varietiesSelect = document.getElementById('varieties');
    if (varietiesSelect) {
        new Choices(varietiesSelect, {
            removeItemButton: true,
            searchEnabled: true,
            placeholder: true,
            placeholderValue: 'Select Varieties',
            shouldSort: false
        });
    }
});
</script>
@endpush

@endsection
