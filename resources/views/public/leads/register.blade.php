<!-- @extends('layouts.app')

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
                                <h5 class="font-semibold mb-0 leading-none text-[1rem]">Pre-Registration for {{ $lead->client->name ?? 'Client' }}</h5>
                                <div class="text-danger font-semibold">
                                    <span class="text-primary text-sm">Lead ID: {{ $lead->id }}</span>
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
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->email ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->contact_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->alternate_number ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->country->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->client->city->name ?? '-' }}</div>
                                        </div>
                                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                            <div class="text-gray-800 dark:text-white">{{ $lead->address ?? ($lead->client->address ?? '-') }}</div>
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

                            {{-- Person Details Form --}}
                            <div class="box">
                                <div class="box-header flex justify-between items-center">
                                    <h5 class="box-title">Person Details</h5>
                                    <button type="button" class="ti-btn ti-btn-primary" id="add-personal-detail">
                                        <i class="ri-add-line me-1"></i>Add Passenger
                                    </button>
                                </div>
                                <div class="box-body bg-gray-50">
                                    <form method="POST" action="{{ route('lead.register.store', ['lead' => $lead->id, 'token' => $token]) }}" enctype="multipart/form-data" id="registration-form">
                                        @csrf
                                        <input type="hidden" name="token" value="{{ $token }}">

                                        <div id="personal-details-container">
                                            @php
                                                $oldPassengers = old('passengers');
                                                $initial = $existingPassengers ?? collect();
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
                                                                        <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" @if ($idx == 0) style="display:none" @endif>
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
                                                        <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" @if ($idx == 0) style="display:none" @endif>
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
                                                    <button type="button" class="text-red-500 hover:text-red-700 remove-personal-detail" style="display:none">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let personalDetailIndex = {{ $existingPassengers ? $existingPassengers->count() : ($initial->count() > 0 ? $initial->count() : 1) }};
            
            // Add Personal Detail
            document.getElementById('add-personal-detail').addEventListener('click', function() {
                const container = document.getElementById('personal-details-container');
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
                updateRemoveButtons();
            });

            // Remove Personal Detail
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-personal-detail')) {
                    const personalDetail = e.target.closest('.personal-detail');
                    personalDetail.remove();
                    updateRemoveButtons();
                    reIndexPersonalDetails();
                }
            });

            function updateRemoveButtons() {
                const personalDetails = document.querySelectorAll('.personal-detail');
                const removeButtons = document.querySelectorAll('.remove-personal-detail');
                
                removeButtons.forEach((btn, index) => {
                    btn.style.display = personalDetails.length > 1 ? 'block' : 'none';
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
            }

            // Initial setup
            updateRemoveButtons();
        });
    </script>
@endsection -->