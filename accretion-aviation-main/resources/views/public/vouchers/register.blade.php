@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-2 col-span-12"></div>
        <div class="xl:col-span-8 col-span-12">
            <div class="box">
                <div class="box-header border-dashed" style="display: block;">
                    <div class="flex items-center">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-md p-2 !rounded-full bg-theme m-0">
                                <i class="ri-article-line text-2xl text-white"></i>
                            </span>
                        </div>
                        <div class="flex-grow">
                            <div class="flex items-center justify-between">
                                <h5 class="font-semibold mb-0 leading-none text-[1rem]">Registration for Voucher:
                                    <span>{{ $voucher->id }}</span>
                                </h5>
                                <div class="text-danger font-semibold">
                                    <button type="button"
                                        class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                        data-hs-overlay="#view-payment-review">
                                        <span class="sr-only">Close modal</span>
                                        <i class="ri-close-circle-line text-3xl text-primary"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="col-span-12">
                            {{-- Client Information --}}
                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title text-primary">Client Information</h5>
                                </div>
                                <div class="box-body bg-gray-50">
                                    <div class="grid grid-cols-12 gap-6">
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email
                                                Address</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->email ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone
                                                Number</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->contact_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp
                                                Number</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->alternate_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->country->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->client->city->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                            <div class="text-gray-800 dark:text-white">
                                                {{ $voucher->lead->address ?? ($voucher->lead->client->address ?? '-') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Service Information --}}
                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title text-primary">Service Information</h5>
                                </div>
                                <div class="box-body bg-gray-50">
                                    <div class="grid grid-cols-12 gap-6">
                                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                                            @if (isset($selectedServices) && $selectedServices->isNotEmpty())
                                                @foreach ($selectedServices as $s)
                                                    <div class="text-gray-800 dark:text-white mb-1">
                                                        {{ $s->service_name ?? $s->service }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="text-gray-800 dark:text-white">No service selected</div>
                                            @endif
                                        </div>

                                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra
                                                Services</label>
                                            @if (isset($selectedExtraServices) && $selectedExtraServices->isNotEmpty())
                                                @foreach ($selectedExtraServices as $es)
                                                    <div class="text-gray-800 dark:text-white mb-1">
                                                        {{ $es->service_name ?? ($es->extra_service ?? 'Extra Service') }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="text-gray-800 dark:text-white">No extra service selected</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Travel Information --}}
                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title text-primary">Travel Information</h5>
                                </div>
                                <div class="box-body bg-gray-50" id="travel-information-container">
                                    <!-- Travel information will be rendered here (single or multiple trip segments) -->
                                    <div class="text-center py-4">
                                        <p class="text-gray-500">Loading travel information...</p>
                                    </div>
                                </div>
                            </div>

                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title">Person Details</h5>
                                </div>
                                <div class="box-body bg-gray-50">
                                    <form method="POST" action="{{ route('voucher.register.store', $voucher->id) }}"
                                        enctype="multipart/form-data" id="registration-form">
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $voucher->registration_token }}">

                                        @if (isset($isAirAmbulance) && $isAirAmbulance)
                                            <div class="alert alert-danger">Patient information is mandatory for Air
                                                Ambulance services. First passenger cannot be removed.</div>
                                        @endif

                                        <div id="personal-details-container">
                                            @php
                                                $oldPassengers = old('passengers');
                                                $initial = $voucher->passengers
                                                    ->where('is_handler', false)
                                                    ->where('is_additional_person', false)
                                                    ->values();
                                            @endphp

                                            {{-- If old input exists (validation failed), prefer that so newly added rows persist --}}
                                            @if (!empty($oldPassengers) && is_array($oldPassengers))
                                                @foreach ($oldPassengers as $idx => $p)
                                                    <div class="box personal-detail" data-index="{{ $idx }}">
                                                        <div class="box-body">
                                                            <div class="grid grid-cols-12 gap-6">
                                                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                                                    <div class="flex items-center justify-between">
                                                                        <span class="badge !rounded-full bg-primary text-white !px-3">#{{ $idx + 1 }}</span>
                                                                        <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" @if ($idx == 0 && isset($isAirAmbulance) && $isAirAmbulance) style="display:none" @endif>
                                                                            <i class="ri-close-line text-2xl"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Person Name</label>
                                                                    <input type="text" name="passengers[{{ $idx }}][name]" value="{{ old('passengers.' . $idx . '.name', $p['name'] ?? '') }}" class="ti-form-input rounded-sm form-control-sm" required>
                                                                    @error('passengers.' . $idx . '.name')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Age</label>
                                                                    <input type="number" name="passengers[{{ $idx }}][age]" value="{{ old('passengers.' . $idx . '.age', $p['age'] ?? '') }}" class="ti-form-input rounded-sm form-control-sm">
                                                                    @error('passengers.' . $idx . '.age')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Weight (KG)</label>
                                                                    <input type="number" name="passengers[{{ $idx }}][weight]" value="{{ old('passengers.' . $idx . '.weight', $p['weight'] ?? '') }}" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                                                    @error('passengers.' . $idx . '.weight')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Front Document</label>
                                                                    <div class="relative">
                                                                        <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                            <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                                <i class="ri-image-line text-white text-lg"></i>
                                                                            </span>
                                                                            <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                            <input type="file" name="passengers[{{ $idx }}][front_document]" class="hidden" />
                                                                        </label>
                                                                    </div>
                                                                    @error('passengers.' . $idx . '.front_document')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                    <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                                </div>

                                                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Back Document</label>
                                                                    <div class="relative">
                                                                        <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                            <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                                <i class="ri-image-line text-white text-lg"></i>
                                                                            </span>
                                                                            <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                            <input type="file" name="passengers[{{ $idx }}][back_document]" class="hidden" />
                                                                        </label>
                                                                    </div>
                                                                    @error('passengers.' . $idx . '.back_document')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                    <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @elseif ($initial->isNotEmpty())
                                                @foreach ($initial as $idx => $p)
                                                    <div class="box personal-detail" data-index="{{ $idx }}">
                                                        <div class="box-body">
                                                            <div class="grid grid-cols-12 gap-6">
                                                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                                                    <div class="flex items-center justify-between">
                                                                        <span class="badge !rounded-full bg-primary text-white !px-3">#{{ $idx + 1 }}</span>
                                                                        <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" @if ($idx == 0 && isset($isAirAmbulance) && $isAirAmbulance) style="display:none" @endif>
                                                                            <i class="ri-close-line text-2xl"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Person Name</label>
                                                                    <input type="text" name="passengers[{{ $idx }}][name]" value="{{ old('passengers.' . $idx . '.name', $p->name) }}" class="ti-form-input rounded-sm form-control-sm" required>
                                                                    @error('passengers.' . $idx . '.name')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Age</label>
                                                                    <input type="number" name="passengers[{{ $idx }}][age]" value="{{ old('passengers.' . $idx . '.age', $p->age) }}" class="ti-form-input rounded-sm form-control-sm">
                                                                    @error('passengers.' . $idx . '.age')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Weight (KG)</label>
                                                                    <input type="number" name="passengers[{{ $idx }}][weight]" value="{{ old('passengers.' . $idx . '.weight', $p->weight) }}" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                                                    @error('passengers.' . $idx . '.weight')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                </div>

                                                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Front Document</label>
                                                                    <div class="relative">
                                                                        <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                            <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                                <i class="ri-image-line text-white text-lg"></i>
                                                                            </span>
                                                                            <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                            <input type="file" name="passengers[{{ $idx }}][front_document]" class="hidden" />
                                                                        </label>
                                                                        @if ($p->front_document)
                                                                            <a href="{{ Storage::url($p->front_document) }}" target="_blank">View</a>
                                                                        @endif
                                                                    </div>
                                                                    @error('passengers.' . $idx . '.front_document')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                    <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                                </div>

                                                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                    <label class="ti-form-label mb-0">Back Document</label>
                                                                    <div class="relative">
                                                                        <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                            <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                                <i class="ri-image-line text-white text-lg"></i>
                                                                            </span>
                                                                            <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                            <input type="file" name="passengers[{{ $idx }}][back_document]" class="hidden" />
                                                                        </label>
                                                                        @if ($p->back_document)
                                                                            <a href="{{ Storage::url($p->back_document) }}" target="_blank">View</a>
                                                                        @endif
                                                                    </div>
                                                                    @error('passengers.' . $idx . '.back_document')
                                                                        <small class="text-danger">{{ $message }}</small>
                                                                    @enderror
                                                                    <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="box personal-detail" data-index="0">
                                                    <div class="box-body">
                                                        <div class="grid grid-cols-12 gap-6">
                                                            <div class="xl:col-span-12 lg:col-span-6 col-span-12">
                                                                <div class="flex items-center justify-between">
                                                                    <span class="badge !rounded-full bg-primary text-white !px-3">#1</span>
                                                                    <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" style="display:none">
                                                                        <i class="ri-close-line text-2xl"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                                <label class="ti-form-label mb-0">Person Name</label>
                                                                <input type="text" name="passengers[0][name]" value="{{ old('passengers.0.name') }}" class="ti-form-input rounded-sm form-control-sm" required>
                                                                @error('passengers.0.name')
                                                                    <small class="text-danger">{{ $message }}</small>
                                                                @enderror
                                                            </div>
                                                            <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                                <label class="ti-form-label mb-0">Age</label>
                                                                <input type="number" name="passengers[0][age]" value="{{ old('passengers.0.age') }}" class="ti-form-input rounded-sm form-control-sm">
                                                                @error('passengers.0.age')
                                                                    <small class="text-danger">{{ $message }}</small>
                                                                @enderror
                                                            </div>
                                                            <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                                <label class="ti-form-label mb-0">Weight (KG)</label>
                                                                <input type="number" name="passengers[0][weight]" value="{{ old('passengers.0.weight') }}" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                                                @error('passengers.0.weight')
                                                                    <small class="text-danger">{{ $message }}</small>
                                                                @enderror
                                                            </div>
                                                            <div class="xl:col-span-6 col-span-12">
                                                                <label class="ti-form-label mb-0">Front Document</label>
                                                                <div class="relative">
                                                                    <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                        <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                            <i class="ri-image-line text-white text-lg"></i>
                                                                        </span>
                                                                        <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                        <input type="file" name="passengers[0][front_document]" class="hidden" />
                                                                    </label>
                                                                </div>
                                                                @error('passengers.0.front_document')
                                                                    <small class="text-danger">{{ $message }}</small>
                                                                @enderror
                                                                <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                            </div>
                                                            <div class="xl:col-span-6 col-span-12">
                                                                <label class="ti-form-label mb-0">Back Document</label>
                                                                <div class="relative">
                                                                    <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                        <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                            <i class="ri-image-line text-white text-lg"></i>
                                                                        </span>
                                                                        <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                        <input type="file" name="passengers[0][back_document]" class="hidden" />
                                                                    </label>
                                                                </div>
                                                                @error('passengers.0.back_document')
                                                                    <small class="text-danger">{{ $message }}</small>
                                                                @enderror
                                                                <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-12 gap-6">
                                            <div class="xl:col-span-12 col-span-12">
                                                <button id="add-personal-detail" type="button"
                                                    class="add-personal-detail text-primary flex items-center gap-2">
                                                    <i class="ri-add-circle-line text-[1.5rem]"></i> Add Personal Detail
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-5">
                                            <button type="submit"
                                                class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Submit
                                                Registration</button>
                                        </div>

                                    </form>
                                </div>
                            </div>

                            {{-- Personal Details --}}
                            <!-- <div class="box">
                                        <div class="box-header flex justify-between items-center">
                                            <h5 class="box-title">Personal Details</h5>
                                        </div>
                                        <div class="box-body bg-gray-50">
                                            <div id="personal-details-container">
                                                <div class="box personal-detail bg-white p-4 rounded mb-4 relative">
                                                    <div class="grid grid-cols-12 gap-6">
                                                        <div class="xl:col-span-12 lg:col-span-6 col-span-12">
                                                            <div class="flex items-center justify-between">
                                                                <span class="badge !rounded-full bg-primary text-white !px-3">#1</span>
                                                                
                                                                <button
                                                                    class="text-red-500 hover:text-red-700 hidden remove-personal-detail">
                                                                    <i class="ri-close-line text-2xl"></i>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        
                                                        <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Person
                                                                Name</label>
                                                            <input type="text" name="person_name[]"
                                                                class="ti-form-input rounded-sm form-control-sm">
                                                        </div>

                                                        
                                                        <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Age</label>
                                                            <input type="number" name="age[]"
                                                                class="ti-form-input rounded-sm form-control-sm">
                                                        </div>

                                                        
                                                        <div class="xl:col-span-4 lg:col-span-6 col-span-12">
                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Weight</label>
                                                            <input type="number" name="weight[]"
                                                                class="ti-form-input rounded-sm form-control-sm">
                                                        </div>

                                                        
                                                        <div class="xl:col-span-6 col-span-12">
                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Front
                                                                Document</label>
                                                            <div class="relative">
                                                                <label
                                                                    class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                    <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                        <i class="ri-image-line text-white text-lg"></i>
                                                                    </span>
                                                                    <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                    <input type="file" name="front_doc[]" class="hidden" />
                                                                </label>
                                                            </div>
                                                        </div>

                                                        
                                                        <div class="xl:col-span-6 col-span-12">
                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Back
                                                                Document</label>
                                                            <div class="relative">
                                                                <label
                                                                    class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                                                    <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                                        <i class="ri-image-line text-white text-lg"></i>
                                                                    </span>
                                                                    <span class="flex-1 px-3 text-sm">Choose File</span>
                                                                    <input type="file" name="back_doc[]" class="hidden" />
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                         
                                            <div class="grid grid-cols-12 gap-6">
                                                <div class="xl:col-span-12 col-span-12">
                                                    <button id="add-personal-detail" type="button"
                                                        class="add-personal-detail text-primary flex items-center gap-2">
                                                        <i class="ri-add-circle-line text-[1.5rem]"></i> Add Personal Detail
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div> -->


                        </div>
                    </div>
                    <!-- <div class="mt-5">
                                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Submit
                                    Registration</button>
                            </div>          -->
                </div>
            </div>
        </div>
        <div class="xl:col-span-2 col-span-12"></div>
    </div>

    <script>
        (function() {
            const container = document.getElementById('personal-details-container');
            const addBtn = document.getElementById('add-personal-detail');
            const isAir = @json(isset($isAirAmbulance) && $isAirAmbulance);

            function reindexDetails() {
                const boxes = container.querySelectorAll('.personal-detail');
                boxes.forEach((box, idx) => {
                    box.dataset.index = idx;
                    const badge = box.querySelector('.badge');
                    if (badge) badge.innerText = `#${idx + 1}`;
                    // rename inputs
                    box.querySelectorAll('input, select, textarea').forEach(input => {
                        const name = input.getAttribute('name') || '';
                        if (!name) return;
                        const newName = name.replace(/passengers\[[0-9]+\]/, 'passengers[' + idx + ']');
                        input.setAttribute('name', newName);
                    });
                    // toggle remove button visibility for first when air ambulance
                    const removeBtn = box.querySelector('.remove-personal-detail');
                    if (idx === 0 && isAir) {
                        if (removeBtn) removeBtn.style.display = 'none';
                    } else {
                        if (removeBtn) removeBtn.style.display = '';
                    }
                });
            }

            addBtn.addEventListener('click', function() {
                const original = container.querySelector('.personal-detail');
                if (!original) return;
                const clone = original.cloneNode(true);
                // clear values and wire file input handlers
                clone.querySelectorAll('input').forEach(i => {
                    if (i.type === 'file') {
                        const newInput = i.cloneNode();
                        newInput.value = '';
                        // ensure change handler is attached later via attachFileListeners
                        i.parentNode.replaceChild(newInput, i);
                    } else {
                        i.value = '';
                    }
                });
                // append and reindex
                container.appendChild(clone);
                reindexDetails();
                // attach file listeners to inputs inside the newly appended clone
                attachFileListeners(clone);
            });

            container.addEventListener('click', function(e) {
                const btn = e.target.closest('.remove-personal-detail');
                if (!btn) return;
                const boxes = container.querySelectorAll('.personal-detail');
                if (boxes.length === 1) {
                    alert('At least one passenger is required');
                    return;
                }
                const box = btn.closest('.personal-detail');
                const idx = Array.from(container.querySelectorAll('.personal-detail')).indexOf(box);
                if (idx === 0 && isAir) {
                    alert('Patient cannot be removed for Air Ambulance');
                    return;
                }
                box.parentNode.removeChild(box);
                reindexDetails();
            });

            // helper to attach change listeners for file inputs to show file name
            function attachFileListeners(scope) {
                scope = scope || document;
                scope.querySelectorAll('input[type="file"]').forEach(input => {
                    // avoid double-binding
                    if (input.__file_listener_attached) return;
                    input.addEventListener('change', function() {
                        const fileNameSpan = input.closest('.relative')?.querySelector(
                            '.selected-file-name');
                        const label = input.closest('label');
                        if (input.files && input.files.length > 0) {
                            const name = input.files[0].name;
                            if (fileNameSpan) fileNameSpan.textContent = name;
                            else if (label) {
                                // append a small span to show the filename
                                const span = document.createElement('span');
                                span.className = 'selected-file-name ml-2 text-sm';
                                span.textContent = name;
                                label.parentNode.appendChild(span);
                            }
                        } else {
                            if (fileNameSpan) fileNameSpan.textContent = '';
                        }
                    });
                    input.__file_listener_attached = true;
                });
            }

            // initial reindex in case server rendered indexes are non-sequential
            reindexDetails();
            attachFileListeners(container);

            // Render travel information for this voucher's rides (single or multiple segments)
            const rides = @json($voucher->lead->rideSegments ?? []);
            // reuse helper functions similar to payment-review
            function calculateDuration(fromDate, toDate) {
                if (!fromDate || !toDate) return 'Not specified';
                const from = new Date(fromDate);
                const to = new Date(toDate);
                const diffTime = Math.abs(to - from);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays === 1) {
                    return '1 day';
                } else if (diffDays < 1) {
                    const diffHours = Math.round(diffTime / (1000 * 60 * 60));
                    return diffHours === 1 ? '1 hour' : `${diffHours} hours`;
                } else {
                    return `${diffDays} days`;
                }
            }

            function calculateTotalDuration(firstFrom, lastTo) {
                if (!firstFrom || !lastTo) return 'Not specified';
                const from = new Date(firstFrom);
                const to = new Date(lastTo);
                const diffTime = Math.abs(to - from);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays === 1) return '1 day';
                if (diffDays < 1) {
                    const diffHours = Math.round(diffTime / (1000 * 60 * 60));
                    return diffHours === 1 ? '1 hour' : `${diffHours} hours`;
                }
                return `${diffDays} days`;
            }

            function updateTravelInformation(ridesData) {
                const container = document.getElementById('travel-information-container');
                if (!ridesData || ridesData.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-4">
                            <p class="text-gray-500">No travel information found</p>
                        </div>
                    `;
                    return;
                }

                let travelHtml = '';
                if (ridesData.length === 1) {
                    const ride = ridesData[0];
                    travelHtml = `
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                                <p class="text-gray-800 dark:text-white">${ride.from_date ? new Date(ride.from_date).toLocaleString('en-GB') : '-'}</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place</label>
                                <p class="text-gray-800 dark:text-white">${ride.from_place || '-'}</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                                <p class="text-gray-800 dark:text-white">${ride.to_date ? new Date(ride.to_date).toLocaleString('en-GB') : '-'}</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place</label>
                                <p class="text-gray-800 dark:text-white">${ride.to_place || '-'}</p>
                            </div>
                        </div>
                    `;
                } else {
                    travelHtml = `
                        
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-semibold text-primary">Multiple Trip Segments (${ridesData.length} trips)</span>
                                <span class="badge bg-primary/10 text-primary rounded-full px-3 py-1 text-xs">Multi-Trip Journey</span>
                            </div>
                        
                    `;

                    ridesData.forEach((ride, index) => {
                        const fromDate = ride.from_date ? new Date(ride.from_date).toLocaleDateString('en-GB') :
                            '-';
                        const toDate = ride.to_date ? new Date(ride.to_date).toLocaleDateString('en-GB') : '-';
                        const fromTime = ride.from_date ? new Date(ride.from_date).toLocaleTimeString('en-GB', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : '';
                        const toTime = ride.to_date ? new Date(ride.to_date).toLocaleTimeString('en-GB', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : '';

                        travelHtml += `
                            <div class="box ${index > 0 ? '' : ''}">
                                <div class="box-header">
                                    <div class="w-8 h-8 bg-theme text-white rounded-full flex items-center justify-center text-sm font-semibold mr-3">${index + 1}</div>
                                    <h6 class="text-lg font-semibold text-gray-800">Trip Segment ${index + 1}</h6>
                                </div>
                                <div class="box-body">
                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Departure</label>
                                            <div class="bg-white p-2 rounded border">
                                                <p class="text-gray-800 dark:text-white font-medium text-sm">${ride.from_place || '-'}</p>
                                                <p class="text-gray-600 text-xs">${fromDate}${fromTime ? ' • ' + fromTime : ''}</p>
                                            </div>
                                        </div>
                                        <div class="xl:col-span-2 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12 flex items-center justify-center">
                                            <div class="flex flex-col items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                </svg>
                                                <span class="text-xs text-gray-500">Journey</span>
                                            </div>
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Arrival</label>
                                            <div class="bg-white p-2 rounded border">
                                                <p class="text-gray-800 dark:text-white font-medium text-sm">${ride.to_place || '-'}</p>
                                                <p class="text-gray-600 text-xs">${toDate}${toTime ? ' • ' + toTime : ''}</p>
                                            </div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Duration</label>
                                            <div class="bg-white p-2 rounded border">
                                                <p class="text-gray-700 text-sm">${calculateDuration(ride.from_date, ride.to_date)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    const firstRide = ridesData[0];
                    const lastRide = ridesData[ridesData.length - 1];
                    const totalDuration = calculateTotalDuration(firstRide.from_date, lastRide.to_date);

                    travelHtml += `
                        <div class="box">
                            <div class="box-header">
                                <h6 class="text-sm font-semibold text-blue-800 mb-2">Journey Summary</h6>
                            </div>
                            <div class="box-body">
                                <div class="grid grid-cols-12 gap-4 text-sm">
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Start:</label>
                                        <p class="text-gray-800 dark:text-white">${firstRide.from_place || '-'}</p>
                                        <p class="text-gray-800 dark:text-white">${firstRide.from_date ? new Date(firstRide.from_date).toLocaleDateString('en-GB') : '-'}</p>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">End:</label>
                                        <p class="text-gray-800 dark:text-white">${lastRide.to_place || '-'}</p>
                                        <p class="text-gray-800 dark:text-white">${lastRide.to_date ? new Date(lastRide.to_date).toLocaleDateString('en-GB') : '-'}</p>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Duration:</label>
                                        <p class="text-gray-800 dark:text-white">${totalDuration}</p>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Segments:</label>
                                        <p class="text-gray-800 dark:text-white">${ridesData.length} trip(s)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }

                container.innerHTML = travelHtml;
            }

            // call to render travel info immediately
            updateTravelInformation(rides);
        })();
    </script>

@endsection
