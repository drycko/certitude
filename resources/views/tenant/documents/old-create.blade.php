@extends('layouts.admin')

@section('title', 'Upload File')
@section('page-title', 'Upload File')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
<li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Files</a></li>
<li class="breadcrumb-item active">Upload File</li>
@endsection

@section('content')
@include('partials.choices-cdn')

<div class="row">
  <!-- Document Form -->
  <div class="col-xl-8 col-lg-7">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-dolesa-primary">
          <i class="bi bi-upload me-2"></i>Document Information
        </h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" id="uploadForm">
          @csrf
          @php
            use App\Models\User;
            $user = auth()->user();
          @endphp
          <!-- File Upload (single) -->
          <div class="form-group mb-3">
            <label for="file" class="form-label text-muted">File <span class="text-danger">*</span></label>
            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip" required>
            <small class="form-text text-muted">Accepted formats:  {{ implode(', ', $allowedFileExtensions) }}. Max {{ $maxFileSize / 1024 / 1024 }}MB each.</small>
            @if($errors->any() && !$errors->has('file'))
            <small class="form-text text-warning">
              <i class="bi bi-exclamation-triangle me-1"></i>Please re-select your file to continue.
            </small>
            @endif
            @error('file')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <hr>
          <div class="row">
            <div hidden class="col-md-6">
              <!-- Title (get it from the uploaded file) -->
              <div class="form-group mb-3" style="display:none;">
                <label for="title" class="form-label text-muted">
                  Document Title <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control @error('title') is-invalid @enderror" 
                id="title" name="title" value="{{ old('title') }}" required readonly>
                @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-6">
              <!-- Document Type -->
              <div class="form-group mb-3">
                <label for="document_type_id" class="form-label text-muted">
                  Document Type <span class="text-danger">*</span>
                </label>
                <select class="form-control @error('document_type_id') is-invalid @enderror" 
                  id="document_type_id" name="document_type_id" required>
                  <option value="">Select Document Type</option>
                  @foreach($documentTypes as $type)
                  <option value="{{ $type->id }}" {{ old('document_type_id') == $type->id ? 'selected' : '' }} data-attrib="{{ $type->attribute_type }}">
                    {{ $type->name }}
                  </option>
                  @endforeach
                </select>
                @error('document_type_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-6">  
              <!-- Sub Document Type -->
              <div class="form-group mb-3">
                <label for="sub_document_type_id" class="form-label text-muted">Sub Document Type</label>
                <select class="form-control @error('sub_document_type_id') is-invalid @enderror" 
                  id="sub_document_type_id" name="sub_document_type_id">
                  <option value="">Select Sub Document Type</option>
                  @foreach($subDocumentTypes as $subType)
                  <option value="{{ $subType->id }}" {{ old('sub_document_type_id') == $subType->id ? 'selected' : '' }} data-parent-id="{{ $subType->parent_id }}">
                    {{ $subType->name }}
                  </option>
                  @endforeach
                </select>
                @error('sub_document_type_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div hidden class="col-md-6">
              <!-- Company -->
              <div id="companyFormGroup" class="form-group mb-3" >
                <label for="company_id" class="form-label text-muted">
                  Client <span class="text-muted">(optional)</span>
                </label>
                <select class="form-control @error('company_id') is-invalid @enderror" 
                  id="company_id" name="company_id">
                  <option value="">No Company Selected</option>
                  @foreach($companies as $company)
                  <option value="{{ $company->id }}" {{ old('company_id', $user->company_id) == $company->id ? 'selected' : '' }}>
                    {{ $company->name }}
                  </option>
                  @endforeach
                </select>
                @error('company_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-6">
              <!-- Grower -->
              <div id="growerFormGroup" class="form-group mb-3">
                <label for="grower_id" class="form-label text-muted">Grower</label>
                <select class="form-control @error('grower_id') is-invalid @enderror" 
                  id="grower_id" name="grower_id">
                  <option value="" >Select Grower</option>
                  @foreach($growers as $grower)
                  <option value="{{ $grower->id }}" {{ old('grower_id', $defaultGrowerId ?? null) == $grower->id ? 'selected' : '' }}>
                    {{ $grower->grower_number }} - {{ $grower->name }}
                  </option>
                  @endforeach
                </select>
                @error('grower_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
      
            <div class="col-md-6">
              <!-- FBOs -->
              <div class="form-group mb-3">
                <label for="fbos" class="form-label text-muted">Associated FBOs (PUC&PHC)</label>
                <select class="form-control @error('fbos') is-invalid @enderror" 
                  id="fbos" name="fbos[]" multiple>
                  <option value="" disabled>Select Associated FBOs</option>
                  @foreach($fbos as $fbo)
                  <option value="{{ $fbo->code }}" {{ in_array($fbo->code, old('fbos', [])) ? 'selected' : '' }}>
                    {{ $fbo->code }}
                  </option>
                  @endforeach
                </select>
                @error('fbos')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-3">
              <!-- Expiry Date -->
              <div class="form-group mb-3">
                <label for="expiry_date" class="form-label text-muted">Expiry Date</label>
                <input type="date" class="form-control @error('expiry_date') is-invalid @enderror" 
                id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}" 
                onclick="this.showPicker ? this.showPicker() : this.focus()">
                @error('expiry_date')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="col-md-3">
              <!-- Season Year -->
              <div class="form-group mb-3">
                <label for="season_year" class="form-label text-muted">Season Year</label>
                <input type="number" class="form-control @error('season_year') is-invalid @enderror" 
                id="season_year" name="season_year" min="2018" max="2050" value="{{ old('season_year', date('Y')) }}">
                @error('season_year')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-6">
              {{-- variety --}}
              <div class="form-group mb-3">
                <label for="varieties" class="form-label text-muted">Variety</label>
                <select class="form-control @error('varieties') is-invalid @enderror" 
                  id="varieties" name="varieties[]" multiple>
                  <option value="" disabled>Select Varieties</option>
                  @foreach($varieties as $variety)
                  <option value="{{ $variety->id }}" {{ in_array($variety->id, old('varieties', [])) ? 'selected' : '' }}>
                    {{ $variety->name }}
                  </option>
                  @endforeach
                </select>
                @error('varieties')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

                
            {{-- <div hidden class="col-md-3 justify-content-center d-flex align-items-end">
              <!-- Visibility -->
              <div class="form-group mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_public">
                    Public Document
                  </label>
                </div>
              </div>
            </div> --}}
             
            <div class="col-md-3">
              <!-- Vessel -->
              <div class="form-group mb-3">
                <label for="vessel_name" class="form-label text-muted">Vessel Name</label>
                <input type="text" class="form-control @error('vessel_name') is-invalid @enderror" 
                  id="vessel_name" name="vessel_name" value="{{ old('vessel_name') }}" placeholder="Enter vessel name">
                @error('vessel_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div> 
            <div class="col-md-3">
              {{-- Container No. --}}
              <div class="form-group mb-3">
                <label for="container_number" class="form-label text-muted">Container No.</label>
                <input type="text" class="form-control @error('container_number') is-invalid @enderror" 
                id="container_number" name="container_number" value="{{ old('container_number') }}">
                @error('container_number')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-3">
              {{-- QC ref --}}
              <div class="form-group mb-3">
                <label for="quality_ref_number" class="form-label text-muted">QC Reference</label>
                <input type="text" class="form-control @error('quality_ref_number') is-invalid @enderror" 
                id="quality_ref_number" name="quality_ref_number" value="{{ old('quality_ref_number') }}">
                @error('quality_ref_number')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-md-3">
              {{-- quality rating --}}
              <div class="form-group mb-3">
                <label for="quality_rating" class="form-label text-muted">QC Rating</label>
                <select class="form-control @error('quality_rating') is-invalid @enderror" 
                  id="quality_rating" name="quality_rating">
                  <option value="" >Select QC Rating</option>
                  <option value="Sound" {{ old('quality_rating') == 'Sound' ? 'selected' : '' }}>Sound</option>
                  <option value="Unsound" {{ old('quality_rating') == 'Unsound' ? 'selected' : '' }}>Unsound</option>
                </select>
                @error('quality_rating')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>
        {{-- </div> --}}
        
        <!-- Description -->
        <div hidden class="form-group mb-3">
          <label for="description" class="form-label text-muted">Description</label>
          <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Enter document description (optional)">{{ old('description') }}</textarea>
          @error('description')
          <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
        <hr>
        <!-- Commodities -->
        <div class="form-group mb-3">
          <label class="form-label text-muted">Associated Fruit Types</label>
          <div class="row">
            @foreach($commodities as $commodity)
            <div class="col-md-4 col-sm-6 mb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="commodity_{{ $commodity->id }}" name="commodities[]" value="{{ $commodity->id }}" {{ in_array($commodity->id, old('commodities', [])) ? 'checked' : '' }}>
                <label class="form-check-label" for="commodity_{{ $commodity->id }}">
                  <i class="bi bi-leaf text-dolesa-primary me-1"></i>{{ $commodity->name }}
                </label>
              </div>
            </div>
            @endforeach
          </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-group mb-3">
          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-dolesa-success">
              <i class="bi bi-upload me-2"></i>Upload Document
            </button>
            <a href="{{ route('documents.index') }}" class="btn btn-secondary me-2">
              <i class="bi bi-arrow-left me-2"></i>Cancel
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Guidelines -->
<div class="col-xl-4 col-lg-5">
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-info">
        <i class="bi bi-info-circle me-2"></i>File Guidelines
      </h6>
    </div>
    <div class="card-body">
      <ul class="list-unstyled mb-0">
        <li class="mb-2 border-bottom pb-2">
          <i class="bi bi-check text-dolesa-primary me-2"></i>
          <strong>Max size:</strong> {{ $maxFileSize / 1024 / 1024 }}MB
        </li>
        <li class="mb-2 border-bottom pb-2">
          <i class="bi bi-check text-dolesa-primary me-2"></i>
          <strong>Formats:</strong> {{ implode(', ', $allowedFileExtensions) }}
        </li>
        <li class="mb-2">
          <i class="bi bi-info-circle text-info me-2"></i>
          <strong>Naming:</strong> System automatically generates the title from the uploaded file name.
        </li>
        <li class="mb-2 border-bottom pb-2">
          <i class="bi bi-shield text-dolesa-primary me-2"></i>
          <strong>Security:</strong> Files are virus-scanned
        </li>
        <li>
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>
          <strong>Note:</strong> Replacing file will not be allowed after upload. If document with grower attribute type is selected, if not uploaded by user with grower role, the document will be forced to private.
        </li>
      </ul>
    </div>
  </div>
</div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // document_type_id change event to filter sub-document types
    const documentTypeSelect = document.getElementById('document_type_id');
    const subDocumentTypeSelect = document.getElementById('sub_document_type_id');
    // const isPublicCheckbox = document.getElementById('is_public');
    const growerFormGroup = document.getElementById('growerFormGroup');
    const companyFormGroup = document.getElementById('companyFormGroup');
    let hasGrowerAttributeType = false;

    // only show the company form if user is admin or super-user
    @if(auth()->user()->hasAnyRole(['admin', 'super-user']))
      companyFormGroup.style.display = 'block';
    @endif

    documentTypeSelect.addEventListener('change', function() {
      const selectedTypeId = this.value;

      // Show/Hide sub-document types based on selected document type
      Array.from(subDocumentTypeSelect.options).forEach(option => {
        option.style.display = option.dataset.parentId == selectedTypeId ? 'block' : 'none';
      });

      // If document with grower attribute type is selected, if not uploaded by user with grower role force it to private
      const selectedOption = documentTypeSelect.options[documentTypeSelect.selectedIndex];
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

      // Reset sub-document type selection
      subDocumentTypeSelect.value = '';
    });

    // If document with grower attribute & If public - select atleast 1 commodity types is required
    // isPublicCheckbox.addEventListener('change', function() {
    //   const commodityCheckboxes = document.querySelectorAll('input[name="commodities[]"]');
    //   const commoditiesLabel = document.querySelectorAll('label[for="commodities"]');
    //   if (hasGrowerAttributeType) {
    //     // select atleast 1 commodity type is required not all
    //     if (isPublicCheckbox.checked) {
    //       commodityCheckboxes.forEach(checkbox => {
    //         checkbox.required = true;
    //       });
    //       // add a red asterisk to the main label
    //       commoditiesLabel.insertAdjacentHTML('beforeend', ' <span class="text-danger">*</span>');
    //     } else {
    //       commodityCheckboxes.forEach(checkbox => {
    //         checkbox.required = false;
    //       });
    //       // remove the red asterisk from the label
    //       commoditiesLabel.forEach(label => {
    //         const asterisk = label.querySelector('.text-danger');
    //         if (asterisk) {
    //           asterisk.remove();
    //         }
    //       });
    //     }

    //     const fbosLabel = document.querySelector('label[for="fbos"]');
    //     // If document with grower attribute & If private - select grower number & FBOs is required
    //     if (isPublicCheckbox.checked === false) {
    //       const fbosSelect = document.getElementById('fbos');
    //       fbosSelect.required = true;
    //       // add a red asterisk to the label
    //       fbosLabel.insertAdjacentHTML('beforeend', ' <span class="text-danger">*</span>');
    //     } else {
    //       const fbosSelect = document.getElementById('fbos');
    //       fbosSelect.required = false;
    //       // remove the red asterisk from the label
    //       fbosLabel.querySelector('.text-danger').remove();

    //     }
    //   }
    // });


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