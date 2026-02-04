@extends('layouts.tenant')

@section('page-title', 'Upload File')

@section('breadcrumb')
    {{-- <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li> --}}
    <li class="breadcrumb-item"><a href="{{ route('files.index') }}">Files</a></li>
    <li class="breadcrumb-item">Upload</li>
@endsection

@section('content')
@include('partials.choices-cdn')
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
<div class="row">
    <div class="col-lg-12">
        <form method="POST" action="{{ route('files.store') }}" enctype="multipart/form-data" id="uploadForm">
            @csrf
            <div class="row">
                <!-- Left Column - File Information -->
                <div class="col-xl-8">
                    <div class="card stretch stretch-full">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-upload me-2"></i>File Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- File Upload -->
                            <div class="mb-4">
                                <label class="form-label">File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                    id="file" name="file" 
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip" required>
                                <small class="text-muted">
                                    Accepted formats: {{ implode(', ', $allowedFileExtensions) }}. 
                                    Max {{ $maxFileSize / 1024 / 1024 }}MB
                                </small>
                                @if($errors->any() && !$errors->has('file'))
                                    <small class="text-warning d-block mt-1">
                                        <i class="feather-alert-triangle me-1"></i>Please re-select your file to continue.
                                    </small>
                                @endif
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Hidden Title Field -->
                            <input type="hidden" id="title" name="title" value="{{ old('title') }}">

                            <div class="row">
                                <!-- File Type -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">File Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('file_type_id') is-invalid @enderror" 
                                        id="file_type_id" name="file_type_id" required>
                                        <option value="">Select File Type</option>
                                        @foreach($fileTypes as $type)
                                            <option value="{{ $type->id }}" 
                                                {{ old('file_type_id') == $type->id ? 'selected' : '' }} 
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
                                                {{ old('sub_file_type_id') == $subType->id ? 'selected' : '' }} 
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
                                                {{ old('grower_id', $defaultGrowerId ?? null) == $grower->id ? 'selected' : '' }}>
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
                                        <option value="" disabled>Select FBOs</option>
                                        @foreach($fbos as $fbo)
                                            <option value="{{ $fbo->code }}" 
                                                {{ in_array($fbo->code, old('fbos', [])) ? 'selected' : '' }}>
                                                {{ $fbo->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    {{-- <small class="text-muted">Hold Ctrl/Cmd to select multiple</small> --}}
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
                                                {{ in_array($variety->id, old('varieties', [])) ? 'selected' : '' }}>
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
                                        id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
                                    @error('expiry_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Season Year -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Season Year</label>
                                    <input type="number" class="form-control @error('season_year') is-invalid @enderror" 
                                        id="season_year" name="season_year" 
                                        min="2018" max="2099" value="{{ old('season_year', date('Y')) }}">
                                    @error('season_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Vessel Name -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Vessel Name</label>
                                    <input type="text" class="form-control @error('vessel_name') is-invalid @enderror" 
                                        id="vessel_name" name="vessel_name" 
                                        value="{{ old('vessel_name') }}" placeholder="Vessel name">
                                    @error('vessel_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Container Number -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">Container No.</label>
                                    <input type="text" class="form-control @error('container_number') is-invalid @enderror" 
                                        id="container_number" name="container_number" 
                                        value="{{ old('container_number') }}">
                                    @error('container_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- QC Reference -->
                                <div class="col-md-3 mb-4">
                                    <label class="form-label">QC Reference</label>
                                    <input type="text" class="form-control @error('quality_ref_number') is-invalid @enderror" 
                                        id="quality_ref_number" name="quality_ref_number" 
                                        value="{{ old('quality_ref_number') }}">
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
                                        <option value="Sound" {{ old('quality_rating') == 'Sound' ? 'selected' : '' }}>Sound</option>
                                        <option value="Unsound" {{ old('quality_rating') == 'Unsound' ? 'selected' : '' }}>Unsound</option>
                                    </select>
                                    @error('quality_rating')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Description (Hidden) -->
                                <input type="hidden" id="description" name="description" value="{{ old('description') }}">
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
                            <div class="row">
                                @foreach($commodities as $commodity)
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input @error('commodities') is-invalid @enderror" 
                                                type="checkbox" 
                                                id="commodity_{{ $commodity->id }}" 
                                                name="commodities[]" 
                                                value="{{ $commodity->id }}" 
                                                {{ in_array($commodity->id, old('commodities', [])) ? 'checked' : '' }}>
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

                    <!-- Form Actions -->
                    <div class="card ">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-upload me-2"></i>Upload File
                                </button>
                                <a href="{{ route('files.index') }}" class="btn btn-light">
                                    <i class="feather-x me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Guidelines -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="feather-info me-2"></i>Upload Guidelines
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="feather-check-circle text-success me-2"></i>
                                    <small>Maximum file size: {{ $maxFileSize / 1024 / 1024 }}MB</small>
                                </li>
                                <li class="mb-2">
                                    <i class="feather-check-circle text-success me-2"></i>
                                    <small>Supported formats: PDF, JPG, PNG, DOC, XLS, ZIP</small>
                                </li>
                                <li class="mb-2">
                                    <i class="feather-check-circle text-success me-2"></i>
                                    <small>Select at least one fruit type</small>
                                </li>
                                <li class="mb-0">
                                    <i class="feather-check-circle text-success me-2"></i>
                                    <small>Choose appropriate file type</small>
                                </li>
                            </ul>
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
    // file_type_id change event to filter sub-file types
    const fileTypeSelect = document.getElementById('file_type_id');
    const subFileTypeSelect = document.getElementById('sub_file_type_id');
    // const isPublicCheckbox = document.getElementById('is_public');
    const growerFormGroup = document.getElementById('growerFormGroup');
    const companyFormGroup = document.getElementById('companyFormGroup');
    let hasGrowerAttributeType = false;

    // only show the company form if user is admin or super-user
    @if(auth()->user()->hasAnyRole(['admin', 'super-user']))
      if (companyFormGroup) {
        companyFormGroup.style.display = 'block';
      }
    @endif

    console.log('Max file size from server:', {{ $maxFileSize }});
    const uploadForm = document.querySelector('form');
    if (!uploadForm) return;
    const fileInput = uploadForm.querySelector('input[type="file"][name="file"]');
    if (!fileInput) return;
    const maxFileSize = {{ $maxFileSize }}; // Max file size from server in bytes
    uploadForm.addEventListener('submit', function(e) {
      if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (file.size > maxFileSize) {
          e.preventDefault();
          alert('File size exceeds the maximum limit of 15MB. Please select a smaller file.');
        }
      }
    });

    fileTypeSelect.addEventListener('change', function() {
      const selectedTypeId = this.value;

      // Show/Hide sub-file types based on selected file type
      Array.from(subFileTypeSelect.options).forEach(option => {
        option.style.display = option.dataset.parentId == selectedTypeId ? 'block' : 'none';
      });

      // If document with grower attribute type is selected, if not uploaded by user with grower role force it to private
      const selectedOption = fileTypeSelect.options[fileTypeSelect.selectedIndex];
      const attributeType = selectedOption ? selectedOption.dataset.attrib : '';

      if (attributeType === 'grower') {
        hasGrowerAttributeType = true;
        // if the user is not a grower, force it to private
        @if(!auth()->user()->hasRole('grower'))
        // Show grower form group
        // growerFormGroup.style.display = 'block';
        // hide company form group
        // companyFormGroup.style.display = 'none';
        // isPublicCheckbox.checked = false;
        // isPublicCheckbox.disabled = true;
        // @else
        // // companyFormGroup.style.display = 'block';
        // isPublicCheckbox.disabled = false;
        // @endif
      } else {
        // Hide grower form group
        // growerFormGroup.style.display = 'none';
        hasGrowerAttributeType = false;
        // isPublicCheckbox.disabled = false;
      }

      // Reset sub-file type selection
      subFileTypeSelect.value = '';
    });

    // make the year input accept only valid years, with year input format enforcement like the date input
    const seasonYearInput = document.getElementById('season_year');
    seasonYearInput.addEventListener('input', function() {
      let year = this.value;
      if (year.length > 4) {
        year = year.slice(0, 4);
      }
      if (year && (year < 2018 || year > 2050)) {
        year = '';
      }
      this.value = year;
    });

    // choices.js for company_id
    const companySelect = document.getElementById('company_id');
    let companyChoices;
    if (companySelect) {
      companyChoices = new Choices(companySelect, {
        searchEnabled: true,
        placeholder: true,
        placeholderValue: 'Select Company',
        shouldSort: false
      });
    }
    
    // Choices.js for FBOs multi-select
    const fbosSelect = document.getElementById('fbos');
    let fbosChoices;
    if (fbosSelect) {
      fbosChoices = new Choices(fbosSelect, {
        removeItemButton: true,
        searchEnabled: true,
        placeholder: true,
        placeholderValue: 'Select Associated FBOs',
        shouldSort: false
      });
    }

    // Choices.js for varieties multi-select
    const varietiesSelect = document.getElementById('varieties');
    let varietiesChoices;
    if (varietiesSelect) {
      varietiesChoices = new Choices(varietiesSelect, {
        removeItemButton: true,
        searchEnabled: true,
        placeholder: true,
        placeholderValue: 'Select Varieties',
        shouldSort: false
      });
    }

    // Choices.js for vessel multi-select
    const vesselSelect = document.getElementById('vessel');
    let vesselChoices;
    if (vesselSelect) {
      vesselChoices = new Choices(vesselSelect, {
        removeItemButton: true,
        searchEnabled: true,
        placeholder: true,
        placeholderValue: 'Select Vessel',
        shouldSort: false
      });
    }

    // when file is selected, set the title input to the file name without extension
    document.querySelector('#file').addEventListener('change', function(e) {
      const titleInput = document.getElementById('title');
      if (e.target.files.length > 0) {
        // Get the first file's name and remove the extension
        const fileName = e.target.files[0].name;
        const nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
        titleInput.value = nameWithoutExt;

        // get expiry date from filename if present in dd-mm-yyyy format
        const expiryDateMatch = nameWithoutExt.match(/(\d{2}-\d{2}-\d{4})/);
        if (expiryDateMatch) {
          const expiryDate = expiryDateMatch[1];
          const [day, month, year] = expiryDate.split('-');
          const formattedDate = `${year}-${month}-${day}`;
          document.getElementById('expiry_date').value = formattedDate;
        }

        // split nameWithoutExt by underscores
        const splitName = nameWithoutExt.split(/[_\s-]+/);
        // Automatically select only FBOs found in the nameWithoutExt
        if (fbosChoices) {
          // clear previous selections
          fbosChoices.removeActiveItems();
          // foreach looping of splitName
          splitName.forEach(part => {
            // console.log('Part:', part);
            // we can use setChoiceByValue here
            fbosChoices.setChoiceByValue(part);
          });
        }
        // remove special characters from nameWithoutExt
        const nameWithoutSpecial = nameWithoutExt.replace(/[_\s-]+/g, ' ');
        // Automatically select company found in the nameWithoutSpecial
        console.log('Name without special characters:', nameWithoutSpecial);
        if (companyChoices) {
          companyChoices.removeActiveItems();
          // normalize both strings for comparison
          const normalizedFilename = nameWithoutSpecial.toLowerCase().replace(/\s+/g, '');
          Array.from(companySelect.options).forEach(option => {
            const normalizedOption = option.text.toLowerCase().replace(/\s+/g, '');
            if (normalizedFilename.includes(normalizedOption)) {
              companyChoices.setChoiceByValue(option.value);
            }
            // if not direct match, check for partial matches by splitting the company name into words
            else {
              const companyWords = normalizedOption.split(/[\s-]+/);
              for (const word of companyWords) {
                console.log('Checking word:', word);
                if (word.length >= 4 && normalizedFilename.includes(word)) {
                  companyChoices.setChoiceByValue(option.value);
                  break;
                }
              }
            }
          });
          // Array.from(companySelect.options).forEach(option => {
          //   console.log('Option:', option.text);
          //   // not case sensitive comparison, and allow partial matches
          //   if (nameWithoutSpecial.toLowerCase().includes(option.text.toLowerCase())) {
          //     companyChoices.setChoiceByValue(option.value);
          //   }
          // });
        }
      } else {
        titleInput.value = '';
      }

    });

  });
</script>
@endpush

@endsection
