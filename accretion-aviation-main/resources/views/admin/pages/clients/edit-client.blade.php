@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold"> Edit Client</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.client.index') }}">
                    Client
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                Edit Client
            </li>
        </ol>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <form class="ti-custom-validation" method="POST" action="{{ route('admin.client.update', $client) }}" novalidate>
                @csrf
                @method('PUT')
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Basic Information</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid lg:grid-cols-2 gap-6">

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                                <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm" value="{{ old('name', $client->name) }}" required>
                                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                <input type="email" name="email" class="ti-form-input rounded-sm form-control-sm" value="{{ old('email', $client->email) }}" required>
                                @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                <input type="tel" id="contact_number" name="contact_number" class="ti-form-input intl-phone-input iti rounded-sm form-control-sm" value="{{ old('contact_number', $client->contact_number) }}" required>
                                <input type="hidden" name="contact_country_code" id="contact_country_code" value="{{ old('contact_country_code', $client->contact_country_code) }}">
                                @error('contact_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp Number</label>
                                <input type="tel" id="alternate_number" name="alternate_number" class="ti-form-input intl-phone-input iti rounded-sm form-control-sm" value="{{ old('alternate_number', $client->alternate_number) }}" required>
                                <input type="hidden" name="whatsapp_country_code" id="whatsapp_country_code" value="{{ old('whatsapp_country_code', $client->whatsapp_country_code) }}">
                                @error('alternate_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="ti-form-input rounded-sm form-control-sm" value="{{ old('date_of_birth', $client->date_of_birth) }}">
                                @error('date_of_birth') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                        <textarea name="address" class="ti-form-input rounded-sm form-control-sm" rows="1" maxlength="500" pattern="[A-Za-z0-9\s]{1,500}" title="Address may contain letters, numbers and spaces only (minimum one letter)">{{ old('address', $client->address) }}</textarea>
                                @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                <select name="country_id" id="countryCodeSelect" class="ti-form-select rounded-sm form-control-sm" required>
                                    <option value="">Select Country</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ (string)old('country_id', $client->country_id) === (string)$country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                <select name="city" id="citySelect" class="ti-form-select rounded-sm form-control-sm" required>
                                    <option value="">Select City</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}" {{ old('city', $client->city) == $city->id ? 'selected' : '' }}>
                                            {{ $city->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('city') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                        </div>

                        <div class="mt-5">
                            <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Select2
    $('#countryCodeSelect, #citySelect').select2({ width: '100%' });

    // City loading function
    function loadCities(countryId) {
        const citySelect = $('#citySelect');
        citySelect.empty().append('<option value="">Select City</option>');

        if (!countryId) return;

        $.ajax({
            url: '/get-cities/' + countryId,
            type: 'GET',
            dataType: 'json',
            beforeSend: () => citySelect.prop('disabled', true),
            success: function (response) {
                response.forEach(city => {
                    const isSelected = city.id == "{{ old('city', $client->city) }}";
                    citySelect.append($('<option></option>')
                        .val(city.id)
                        .text(city.name)
                        .prop('selected', isSelected));
                });
                citySelect.prop('disabled', false);
            },
            error: () => citySelect.html('<option>Error loading cities</option>').prop('disabled', false)
        });
    }

    // Load cities when country changes
    $('#countryCodeSelect').on('change', function () {
        loadCities($(this).val());
    });

    // Load cities immediately if country is preselected
    @if($client->country_id)
        loadCities("{{ $client->country_id }}");
    @endif

    // Rest of your phone input initialization...
    const phoneInput = document.getElementById("contact_number");
    const whatsappInput = document.getElementById("alternate_number");
    const contactCodeInput = document.getElementById("contact_country_code");
    const whatsappCodeInput = document.getElementById("whatsapp_country_code");

    const itiPhone = window.intlTelInput(phoneInput, {
        initialCountry: "in",
        separateDialCode: true,
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
    });

    const itiWhatsapp = window.intlTelInput(whatsappInput, {
        initialCountry: "in",
        separateDialCode: true,
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
    });

    phoneInput.addEventListener('blur', () => {
        if (phoneInput.value && !whatsappInput.value) {
            whatsappInput.value = phoneInput.value;
            itiWhatsapp.setNumber(phoneInput.value);
        }
    });

    const form = document.querySelector('form.ti-custom-validation');
    form.addEventListener('submit', function () {
        contactCodeInput.value = `+${itiPhone.getSelectedCountryData().dialCode}`;
        whatsappCodeInput.value = `+${itiWhatsapp.getSelectedCountryData().dialCode}`;
    });
});
</script>
@endpush
