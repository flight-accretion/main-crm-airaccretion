@extends('admin.layouts.header')
@section('content')
            <!-- Page Header -->
                <div class="block justify-between page-header md:flex">
                    <div>
                        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Edit Service</h3>
                    </div>
                    <ol class="flex items-center whitespace-nowrap min-w-0">
                        <li class="text-[0.813rem] ps-[0.5rem]">
                            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.services.index') }}">
                                Services
                                <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                            </a>
                        </li>
                        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                            Edit Service
                        </li>
                    </ol>
                </div>
                 <!-- Page Header Close -->
                    @if(session('success'))
                    <div class="alert alert-success mb-4">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger mb-4">
                        {{ session('error') }}
                    </div>
                @endif
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <form class="ti-custom-validation" action="{{ route('admin.services.update', $service->id) }}" method="POST" id="serviceForm" novalidate>
                            @csrf
                            @method('PUT')
                            <div class="box">
                                <div class="box-body">
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Name <span class="text-danger">*</span></label>
                                            <input type="text" name="service" class="my-auto ti-form-input rounded-sm form-control-sm" placeholder="Service Name" value="{{ old('service', $service->service) }}" required>
                                            @error('service')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Amount<span class="text-danger">*</span></label>
                                            <input type="number" name="service_amount" class="my-auto ti-form-input rounded-sm form-control-sm" placeholder="Service Amount" value="{{ old('service_amount', $service->service_amount) }}" required>
                                            @error('service_amount')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label for="product_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product <span class="text-danger">*</span></label>
                                            <select class="js-example-basic-single w-full form-control-sm" name="product_id" id="product_id" required>
                                                <option value="">Select Product</option>
                                                @php
                                                    $selectedProduct = old('product_id', isset($service->product_ids[0]) ? $service->product_ids[0] : '');
                                                @endphp
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}" {{ $selectedProduct == $product->id ? 'selected' : '' }}>
                                                        {{ $product->product }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('product_id')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                            <textarea class="form-control form-control-sm" name="description" rows="1">{{ old('description', $service->description) }}</textarea>
                                            @error('description')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                    </div>
                                    <div class="my-5">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Term & Condition's</label>
                                        <textarea id="termsEditor" name="terms_and_conditions" class="hidden">{{ old('terms_and_conditions', $service->terms_and_conditions) }}</textarea>
                                        <div id="editor">{!! old('terms_and_conditions', $service->terms_and_conditions) !!}</div>
                                        @error('terms_and_conditions')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Update
                                        Service</button>
                                </div>
                            </div>
                {{-- <div class="box">
                                <div class="box-header">
                                    <h5 class="box-title">Extra Services</h5>
                                </div>
                                <div class="box-body">
                                    <div id="repeatableFieldsContainer">
                                        @php
                                            // Handle old input or existing service data
                                            if (old('extra_services')) {
                                                $extraServices = old('extra_services');
                                            } else {
                                                // Convert service's extra services to proper array format
                                                $extraServices = [];
                                                if ($service->extraServices && count($service->extraServices) > 0) {
                                                    foreach ($service->extraServices as $extraService) {
                                                        $extraServices[] = [
                                                            'id' => $extraService->id, // Use the service_extra_service ID for tracking
                                                            'extra_service_id' => $extraService->extra_service_id, // The ID from extra_services table
                                                            'name' => $extraService->extra_service,
                                                            'extra_service' => $extraService->extra_service,
                                                            'description' => $extraService->description,
                                                            'amount' => $extraService->extra_service_amount,
                                                            'extra_service_amount' => $extraService->extra_service_amount,
                                                        ];
                                                    }
                                                } else {
                                                    // Default empty row
                                                    $extraServices = [['id' => '', 'extra_service_id' => '', 'name' => '', 'extra_service' => '', 'amount' => '', 'description' => '']];
                                                }
                                            }
                                        @endphp
                                        @foreach($extraServices as $index => $extra)
                            <div class="repeatable-row grid grid-cols-12 gap-x-4 gap-y-2 items-center mt-5">
                                <!-- Hidden field to track existing extra service ID for updates -->
                                @if(isset($extra['id']) && !empty($extra['id']))
                                    <input type="hidden" name="extra_services[{{ $index }}][existing_id]" value="{{ old("extra_services.$index.existing_id", $extra['id']) }}">
                                @endif

                                <div class="sm:col-span-3 col-span-12">
                                    <label class="ti-form-label mb-0" for="add_extra_service_ids">Extra Service Name *</label>
                                    <select class="js-example-basic-single w-full form-control-sm extra-service-select" 
                                            name="extra_services[{{ $index }}][id]"
                                            data-index="{{ $index }}">
                                        @php
                                            $currentExtraServiceId = old("extra_services.$index.id", $extra['extra_service_id'] ?? '');
                                            $isNewService = empty($extra['extra_service_id']) && !empty($extra['extra_service']);
                                        @endphp
                                        <!-- Debug Row {{ $index }}: extra_service_id={{ $extra['extra_service_id'] ?? 'null' }}, currentExtraServiceId={{ $currentExtraServiceId }}, isNewService={{ $isNewService ? 'true' : 'false' }} -->
                                        <option value="">Select Extra Service</option>
                                        <option value="new" {{ old("extra_services.$index.id") == 'new' || $isNewService ? 'selected' : '' }}>-- Add New Extra Service --</option>
                                        @foreach($existingExtraServices as $existing)
                                           <option value="{{ $existing->id }}"
                                            data-amount="{{ $existing->extra_service_amount }}"
                                            data-description="{{ $existing->description }}"
                                            {{ !empty($extra['extra_service_id']) && $extra['extra_service_id'] == $existing->id ? 'selected' : '' }}>
                                            {{ $existing->extra_service }} - (₹{{ number_format($existing->extra_service_amount, 2) }})
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-3 col-span-12 extra-service-name-container {{ !empty($extra['extra_service_id']) ? 'hidden' : '' }}">
                                    <label class="ti-form-label mb-0">Extra Service Name (New)</label>
                                    <input type="text"
                                        name="extra_services[{{ $index }}][name]"
                                        class="ti-form-input extra-service-name rounded-sm form-control-sm {{ !empty($extra['extra_service_id']) ? 'hidden' : '' }}"
                                        placeholder="Service Name"
                                        value="{{ old("extra_services.$index.name", $extra['extra_service'] ?? '') }}"
                                        {{ !empty($extra['extra_service_id']) ? 'readonly' : '' }}>
                                    @error("extra_services.$index.name")
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="sm:col-span-2 col-span-12 extra-service-amount-container hidden">
                                    <label class="ti-form-label mb-0">Service Amount *</label>
                                    <input type="number"
                                        name="extra_services[{{ $index }}][amount]"
                                        class="ti-form-input extra-service-amount rounded-sm form-control-sm"
                                        placeholder="Service Amount"
                                        value="{{ old("extra_services.$index.amount", $extra['amount'] ?? $extra['extra_service_amount'] ?? '') }}"
                                        min="0" required>
                                    @error("extra_services.$index.amount")
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2 col-span-12 hidden extra-service-description-container">
                                    <label class="ti-form-label mb-0">Description</label>
                                    <textarea class="ti-form-input extra-service-description w-full rounded-sm form-control-sm"
                                            name="extra_services[{{ $index }}][description]"
                                            rows="1">{{ old("extra_services.$index.description", $extra['description'] ?? '') }}</textarea>
                                    @error("extra_services.$index.description")
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                                                <div class="sm:col-span-1 col-span-12" style="align-self: end;">
                                                    @if($index === 0)
                                                        <button type="button" class="addBtn bg-green-500 text-white px-4 py-2 rounded">+</button>
                                                    @else
                                                        <button type="button" class="removeBtn bg-red-500 text-white px-4 py-2 rounded">-</button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div> --}}
                        </form>
                    </div>
                </div>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle editor content syncing for the existing Quill editor
            const termsEditor = document.getElementById('termsEditor');

            // Wait for the global Quill editor to be initialized
            setTimeout(function() {
                if (window.quill && window.quill.root) {
                    // Set initial content if exists
                    if (termsEditor.value) {
                        window.quill.root.innerHTML = termsEditor.value;
                    }

                    // Sync changes back to hidden textarea
                    window.quill.on('text-change', function() {
                        termsEditor.value = window.quill.root.innerHTML;
                    });
                }
            }, 1000);

            // Dynamic fields handling
            const container = document.getElementById('repeatableFieldsContainer');
            let isProcessing = false;

            // Single event listener for the container
            container.addEventListener('click', function(e) {
                if (isProcessing) return;
                isProcessing = true;

                try {
                    const target = e.target;

                    // Handle Add Button
                    if (target.classList.contains('addBtn')) {
                        e.preventDefault();
                        addServiceRow();
                    }

                    // Handle Remove Button
                    if (target.classList.contains('removeBtn')) {
                        e.preventDefault();
                        removeServiceRow(target);
                    }
                } finally {
                    isProcessing = false;
                }
            });

            // Handle dropdown changes for extra services
            $(document).on('change', '.extra-service-select', function() {
                const row = $(this).closest('.repeatable-row');
                const selectedOption = $(this).find('option:selected');
                const nameInput = row.find('.extra-service-name');
                const nameInputContainer = row.find('.extra-service-name-container');
                const amountInput = row.find('.extra-service-amount');
                const amountInputContainer = row.find('.extra-service-amount-container');
                const descriptionInput = row.find('.extra-service-description');
                const descriptionInputContainer = row.find('.extra-service-description-container');

                if (selectedOption.val() === 'new') {
                    // Show name input for new service, show amount and description fields
                    nameInputContainer.removeClass('hidden');
                    nameInput.removeClass('hidden').val('').prop('readonly', false).prop('required', true);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.val('').prop('readonly', false).prop('required', true);
                    descriptionInput.val('').prop('readonly', false);
                } else if (selectedOption.val()) {
                    // Hide name input and show/populate fields with existing service data
                    nameInputContainer.addClass('hidden');
                    nameInput.addClass('hidden').val('').prop('required', false);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.val(selectedOption.data('amount')).prop('readonly', true).prop('required', true);
                    descriptionInput.val(selectedOption.data('description')).prop('readonly', true);
                } else {
                    // Empty selection - hide name input, show amount and description fields but empty
                    nameInputContainer.addClass('hidden');
                    nameInput.addClass('hidden').val('').prop('required', false);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.val('').prop('readonly', false).prop('required', true);
                    descriptionInput.val('').prop('readonly', false);
                }
            });

            // Function to add a new service row
           function addServiceRow() {
                const rows = container.querySelectorAll('.repeatable-row');
                const newIndex = rows.length;
                const templateRow = rows[0];

                // Create new row
                const newRow = templateRow.cloneNode(true);

                // Reset values and update indices
                $(newRow).find('.extra-service-select')
                    .val('')
                    .attr('name', `extra_services[${newIndex}][id]`)
                    .data('index', newIndex);

                $(newRow).find('.extra-service-name')
                    .val('')
                    .attr('name', `extra_services[${newIndex}][name]`)
                    .removeClass('hidden')
                    .prop('readonly', false)
                    .prop('disabled', false);
                $(newRow).find('.extra-service-name-container').addClass('hidden');
                $(newRow).find('.extra-service-amount-container').removeClass('hidden');
                $(newRow).find('.extra-service-description-container').removeClass('hidden');

                // Remove any existing_id hidden field from cloned row
                $(newRow).find('input[name*="[existing_id]"]').remove();

                $(newRow).find('.extra-service-amount')
                    .val('')
                    .attr('name', `extra_services[${newIndex}][amount]`);

                $(newRow).find('.extra-service-description')
                    .val('')
                    .attr('name', `extra_services[${newIndex}][description]`);

                // Clear any existing error messages
                $(newRow).find('.text-red-500').remove();

                // Convert add button to remove button
                const button = newRow.querySelector('button');
                button.textContent = '-';
                button.classList.remove('addBtn', 'bg-green-500');
                button.classList.add('removeBtn', 'bg-red-500');

                // Add the new row
                container.appendChild(newRow);

                // Initialize Select2 for the new dropdown
                $(newRow).find('.extra-service-select').select2({
                    width: '100%'
                });
            }

            // Function to remove a service row
            function removeServiceRow(button) {
                const rows = container.querySelectorAll('.repeatable-row');
                if (rows.length > 1) {
                    const rowToRemove = button.closest('.repeatable-row');
                    rowToRemove.remove();

                    // Re-index remaining rows
                    container.querySelectorAll('.repeatable-row').forEach((row, index) => {
                        row.querySelectorAll('[name]').forEach(input => {
                            const oldName = input.name;
                            input.name = input.name.replace(/extra_services\[(\d+)\]/, `extra_services[${index}]`);
                        });
                        // Update data-index for select elements
                        $(row).find('.extra-service-select').attr('data-index', index);
                    });
                }
            }

            // Form submission handler
            const form = document.getElementById('serviceForm');
            form.addEventListener('submit', function(e) {
                // Sync editor content if Quill is available
                if (window.quill && window.quill.root) {
                    termsEditor.value = window.quill.root.innerHTML;
                }
            });

            // Initialize the dropdown states on page load
            // Initialize the dropdown states on page load
            $('.extra-service-select').each(function() {
                const row = $(this).closest('.repeatable-row');
                const selectedOption = $(this).find('option:selected');
                const nameInput = row.find('.extra-service-name');
                const nameInputContainer = row.find('.extra-service-name-container');
                const amountInput = row.find('.extra-service-amount');
                const amountInputContainer = row.find('.extra-service-amount-container');
                const descriptionInput = row.find('.extra-service-description');
                const descriptionInputContainer = row.find('.extra-service-description-container');
                const selectedValue = $(this).val();

                // Check if this is an existing extra service (has extra_service_id)
                const hasExtraServiceId = $(this).find('option:selected').val() !== 'new' && $(this).find('option:selected').val() !== '';

                if (selectedValue === 'new') {
                    // Show name input for new service
                    nameInputContainer.removeClass('hidden');
                    nameInput.removeClass('hidden').prop('readonly', false).prop('required', true);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.prop('readonly', false).prop('required', true);
                    descriptionInput.prop('readonly', false);
                } else if (hasExtraServiceId) {
                    // Existing service - hide name input and show populated fields
                    nameInputContainer.addClass('hidden');
                    nameInput.addClass('hidden').prop('required', false);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.prop('readonly', true).prop('required', true);
                    descriptionInput.prop('readonly', true);
                } else {
                    // Empty selection
                    nameInputContainer.addClass('hidden');
                    nameInput.addClass('hidden').prop('required', false);
                    amountInputContainer.removeClass('hidden');
                    descriptionInputContainer.removeClass('hidden');
                    amountInput.prop('readonly', false).prop('required', true);
                    descriptionInput.prop('readonly', false);
                }
            });
        });

        // Initialize Select2 for products and extra services
        $(document).ready(function() {
            $('#product_id').select2({
                placeholder: "Select product",
                allowClear: true,
                width: '100%'
            });

            // Initialize Select2 for existing extra service dropdowns
            $('.extra-service-select').select2({
                width: '100%'
            });
        });
</script>
@endsection
