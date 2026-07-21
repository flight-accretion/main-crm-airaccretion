@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-2 col-span-12"></div>
        <div class="xl:col-span-8 col-span-12">
            <div class="flex justify-center mb-6">
                <img src="{{ asset('assets/admin/images/logo.png') }}" alt="Accretion Aviation" style="max-width:180px; height:auto;padding-top: 1rem;" />
            </div>
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
                                @if(isset($voucher))
                                    <h5 class="font-semibold mb-0 leading-none text-[1rem]">Registration for Voucher: <span>{{ $voucher->id }}</span></h5>
                                @else
                                    <h5 class="font-semibold mb-0 leading-none text-[1rem]">Pre-Registration for {{ $lead->client->name ?? 'Client' }}</h5>
                                @endif
                                <div class="text-danger font-semibold">
                                    @if(isset($voucher))
                                        <button type="button"
                                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                            data-hs-overlay="#view-payment-review">
                                            <span class="sr-only">Close modal</span>
                                            <i class="ri-close-circle-line text-3xl text-primary"></i>
                                        </button>
                                    @else
                                        <!-- <span class="text-primary text-sm">Lead ID: {{ $lead->id }}</span> -->
                                    @endif
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
                                        @php
                                            $client = isset($voucher) ? $voucher->lead->client : $lead->client;
                                            $leadData = isset($voucher) ? $voucher->lead : $lead;
                                        @endphp
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->email ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->contact_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->alternate_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->country->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                            <div class="text-gray-800 dark:text-white">{{ $client->city->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                            <div class="text-gray-800 dark:text-white">{{ $leadData->address ?? ($client->address ?? '-') }}</div>
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
                                                    <div class="text-gray-800 dark:text-white mb-1">{{ $s->service_name ?? $s->service }}</div>
                                                @endforeach
                                            @else
                                                <div class="text-gray-800 dark:text-white">No service selected</div>
                                            @endif
                                        </div>
                                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
                                            @if (isset($selectedExtraServices) && $selectedExtraServices->isNotEmpty())
                                                @foreach ($selectedExtraServices as $es)
                                                    <div class="text-gray-800 dark:text-white mb-1">{{ $es->service_name ?? ($es->extra_service ?? 'Extra Service') }}</div>
                                                @endforeach
                                            @else
                                                <div class="text-gray-800 dark:text-white">No extra service selected</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Travel Information (only for voucher registration) --}}
                            @if(isset($voucher))
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
                            @endif

                            {{-- Person Details Form --}}
                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title">Person Details <small id="max-passenger-label" class="text-muted">(Max: {{ $leadData->number_of_passengers ?? 1 }})</small></h5>
                                        <button type="button" class="ti-btn ti-btn-primary" id="add-personal-detail" title="Add Passenger">
                                            <i class="ri-add-line me-1"></i>Add Passenger
                                        </button>
                                </div>
                                <div class="box-body bg-gray-50">
                                    <form method="POST" 
                                        action="{{ isset($voucher) ? route('voucher.register.store', $voucher->id) : route('lead.register.store', ['lead' => $lead->id, 'token' => $token]) }}" 
                                        enctype="multipart/form-data" id="registration-form">
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $token }}">
                                        
                                        @if(isset($voucher))
                                            <input type="hidden" id="travel-data" value="{{ json_encode($voucher->lead->rideSegments ?? []) }}">
                                        @endif

                                        @if(isset($isAirAmbulance) && $isAirAmbulance)
                                            <div class="alert alert-danger">Patient information is mandatory for Air Ambulance services. First passenger cannot be removed.</div>
                                        @endif

                                        <div id="personal-details-container">
                                            @php
                                                $oldPassengers = old('passengers');
                                                $initial = $existingPassengers ?? collect();
                                                // For voucher registration, use voucher->passengers, for lead registration use existingPassengers
                                                if (isset($voucher)) {
                                                    $initial = $voucher->passengers
                                                        ->where('is_handler', false)
                                                        ->where('is_additional_person', false)
                                                        ->values();
                                                }
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
                                                            <a href="{{ Storage::url($p->front_document) }}" target="_blank" class="text-primary text-sm">View Current</a>
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
                                                            <a href="{{ Storage::url($p->back_document) }}" target="_blank" class="text-primary text-sm">View Current</a>
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
                                {{-- Default single passenger form --}}
                                <div class="box personal-detail" data-index="0">
                                    <div class="box-body">
                                        <div class="grid grid-cols-12 gap-6">
                                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                                <div class="flex items-center justify-between">
                                                    <span class="badge !rounded-full bg-primary text-white !px-3">#1</span>
                                                    <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" @if (isset($isAirAmbulance) && $isAirAmbulance) style="display:none" @endif>
                                                        <i class="ri-close-line text-2xl"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label mb-0">Person Name</label>
                                                <input type="text" name="passengers[0][name]" value="{{ old('passengers.0.name') }}" class="ti-form-input rounded-sm form-control-sm" required>
                                                @error('passengers.0.name')
                                                    <small class="text-danger">{{ $message }}</small>
                                                @enderror
                                            </div>

                                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label mb-0">Age</label>
                                                <input type="number" name="passengers[0][age]" value="{{ old('passengers.0.age') }}" class="ti-form-input rounded-sm form-control-sm">
                                                @error('passengers.0.age')
                                                    <small class="text-danger">{{ $message }}</small>
                                                @enderror
                                            </div>

                                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label mb-0">Weight (KG)</label>
                                                <input type="number" name="passengers[0][weight]" value="{{ old('passengers.0.weight') }}" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                                @error('passengers.0.weight')
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
                                                        <input type="file" name="passengers[0][front_document]" class="hidden" />
                                                    </label>
                                                </div>
                                                @error('passengers.0.front_document')
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

                        <div class="text-center mt-4">
                            <button type="submit" class="ti-btn ti-btn-primary ti-btn-lg">
                                <i class="ri-save-line me-1"></i>Submit Registration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
    <div class="xl:col-span-2 col-span-12"></div>
    </div>
@endsection

@push('scripts')
    <script>
        function initRegistrationForm() {
            // Max passengers allowed for this lead (from server)
            const MAX_PASSENGERS = @json(isset($leadData) ? ($leadData->number_of_passengers ?? 1) : 1);

            // Initialize passenger index and current count based on existing passengers
            let currentCount = document.querySelectorAll('.personal-detail').length || 0;
            let personalDetailIndex = currentCount;
            console.log('Personal detail index initialized:', personalDetailIndex, 'currentCount:', currentCount, 'MAX_PASSENGERS:', MAX_PASSENGERS);
            
            // Add Personal Detail
            const addButton = document.getElementById('add-personal-detail');
            const container = document.getElementById('personal-details-container');
            
            if (!addButton) {
                console.error('Add passenger button not found!');
                return;
            }
            
            if (!container) {
                console.error('Personal details container not found!');
                return;
            }
            
            addButton.addEventListener('click', function() {
                console.log('Add passenger button clicked, current index:', personalDetailIndex, 'currentCount:', currentCount);

                if (currentCount >= MAX_PASSENGERS) {
                    // Prefer showing site modal if available, otherwise fallback to inline modal or alert
                    showFriendlyError('Maximum passengers reached', 'Maximum number of passengers reached: ' + MAX_PASSENGERS);
                    disableAddButton();
                    return;
                }
                const personalDetailHtml = `
                    <div class="box personal-detail" data-index="${personalDetailIndex}">
                        <div class="box-body">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                    <div class="flex items-center justify-between">
                                        <span class="badge !rounded-full bg-primary text-white !px-3">#${personalDetailIndex + 1}</span>
                                        <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail">
                                            <i class="ri-close-line text-2xl"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label mb-0">Person Name</label>
                                    <input type="text" name="passengers[${personalDetailIndex}][name]" class="ti-form-input rounded-sm form-control-sm" required>
                                </div>

                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label mb-0">Age</label>
                                    <input type="number" name="passengers[${personalDetailIndex}][age]" class="ti-form-input rounded-sm form-control-sm">
                                </div>

                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label mb-0">Weight (KG)</label>
                                    <input type="number" name="passengers[${personalDetailIndex}][weight]" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label mb-0">Front Document</label>
                                    <div class="relative">
                                        <label class="flex items-center bg-white cursor-pointer border rounded-sm overflow-hidden">
                                            <span class="bg-primary !py-1 !px-2 flex items-center justify-center">
                                                <i class="ri-image-line text-white text-lg"></i>
                                            </span>
                                            <span class="flex-1 px-3 text-sm">Choose File</span>
                                            <input type="file" name="passengers[${personalDetailIndex}][front_document]" class="hidden" />
                                        </label>
                                    </div>
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
                                            <input type="file" name="passengers[${personalDetailIndex}][back_document]" class="hidden" />
                                        </label>
                                    </div>
                                    <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.insertAdjacentHTML('beforeend', personalDetailHtml);
                personalDetailIndex++;
                currentCount++;
                updateRemoveButtons();

                // Disable add button if reached max
                if (currentCount >= MAX_PASSENGERS) {
                    disableAddButton();
                }
            });

            // Remove Personal Detail
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-personal-detail')) {
                    const personalDetail = e.target.closest('.personal-detail');
                    personalDetail.remove();
                    currentCount = Math.max(0, currentCount - 1);
                    updateRemoveButtons();
                    reIndexPersonalDetails();

                    // Re-enable add button if we are below the limit
                    if (currentCount < MAX_PASSENGERS) {
                        enableAddButton();
                    }
                }
            });

            function updateRemoveButtons() {
                const personalDetails = document.querySelectorAll('.personal-detail');
                const removeButtons = document.querySelectorAll('.remove-personal-detail');
                
                removeButtons.forEach((btn, index) => {
                    // Check if this is air ambulance and first passenger
                    const isAirAmbulance = false; // Will be set dynamically if needed
                    if (isAirAmbulance && index === 0) {
                        btn.style.display = 'none';
                    } else {
                        btn.style.display = personalDetails.length > 1 ? 'block' : 'none';
                    }
                });
            }

            function reIndexPersonalDetails() {
                const personalDetails = document.querySelectorAll('.personal-detail');
                personalDetails.forEach((detail, index) => {
                    detail.setAttribute('data-index', index);
                    const badge = detail.querySelector('.badge');
                    if (badge) {
                        badge.textContent = `#${index + 1}`;
                    }
                    
                    const inputs = detail.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        const name = input.name;
                        if (name && name.includes('passengers[')) {
                            input.name = name.replace(/passengers\[\d+\]/, `passengers[${index}]`);
                        }
                    });
                });
                personalDetailIndex = personalDetails.length;
                currentCount = personalDetails.length;
            }

            // Initial setup
            updateRemoveButtons();
            setupFileInputHandlers();

            // Disable add button initially if already at or above max
            if (currentCount >= MAX_PASSENGERS) {
                disableAddButton();
            }

            // Update max label (in case server-side value changed)
            const maxLabel = document.getElementById('max-passenger-label');
            if (maxLabel) {
                maxLabel.textContent = '(Max: ' + MAX_PASSENGERS + ')';
            }

            // File input handlers for showing selected file names
            function setupFileInputHandlers() {
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.type === 'file') {
                        const fileInput = e.target;
                        const label = fileInput.closest('label');
                        // label contains two spans then the input; pick the descriptive span
                        let span = null;
                        if (label) {
                            span = label.querySelector('span.flex-1') || label.querySelectorAll('span')[1] || null;
                        }

                        if (span) {
                            if (fileInput.files && fileInput.files[0]) {
                                const fileName = fileInput.files[0].name;
                                span.textContent = fileName;
                                span.style.color = '#10b981'; // green color to show file selected
                            } else {
                                span.textContent = 'Choose File';
                                span.style.color = '';
                            }
                        }
                    }
                });
            }

            // Enable/disable helpers for Add button (kept in outer scope so other functions can call)
            function disableAddButton() {
                if (!addButton) return;
                addButton.disabled = true;
                addButton.style.pointerEvents = 'none';
                addButton.style.opacity = '0.6';
                addButton.style.cursor = 'not-allowed';
                addButton.title = 'Maximum passengers reached';
            }

            function enableAddButton() {
                if (!addButton) return;
                addButton.disabled = false;
                addButton.style.pointerEvents = '';
                addButton.style.opacity = '';
                addButton.style.cursor = '';
                addButton.title = 'Add Passenger';
            }

            // Travel information loading for voucher registration
            const isVoucherRegistration = document.getElementById('travel-information-container') !== null;
            if (isVoucherRegistration) {
                loadTravelInformation();
            }
            
            function loadTravelInformation() {
                const travelContainer = document.getElementById('travel-information-container');
                if (!travelContainer) return;
                
                // Check if travel data is available in a hidden input
                const travelDataElement = document.getElementById('travel-data');
                let travelData = [];
                
                if (travelDataElement) {
                    try {
                        travelData = JSON.parse(travelDataElement.value || '[]');
                    } catch (e) {
                        console.error('Error parsing travel data:', e);
                    }
                }
                
                console.log('Travel data loaded:', travelData);
                    
                    if (travelData && travelData.length > 0) {
                        let html = '';
                        travelData.forEach((segment, index) => {
                            const service = segment.service_address?.service || {};
                            const city = segment.service_address?.city || {};
                            
                            html += `
                                <div class="grid grid-cols-12 gap-6 mb-4 ${index > 0 ? 'border-t pt-4' : ''}">
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                                        <div class="text-gray-800 dark:text-white">${service.service_name || service.name || 'Service'}</div>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Location</label>
                                        <div class="text-gray-800 dark:text-white">${city.name || 'Location'}</div>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date</label>
                                        <div class="text-gray-800 dark:text-white">${segment.date || 'TBD'}</div>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Time</label>
                                        <div class="text-gray-800 dark:text-white">${segment.time || 'TBD'}</div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        travelContainer.innerHTML = html;
                    } else {
                        travelContainer.innerHTML = `
                            <div class="text-center py-4">
                                <p class="text-gray-500">Travel details will be provided by our team</p>
                            </div>
                        `;
                    }
                }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRegistrationForm);
        } else {
            initRegistrationForm();
        }
    </script>
    @endpush

