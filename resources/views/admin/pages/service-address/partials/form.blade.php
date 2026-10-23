@php
    $editing = $mode === 'edit';
    $restore = $editing ? $errors->edit->any() : $errors->add->any();
    $value = fn ($key, $default = '') => $restore ? old($key, $default) : $default;
@endphp
<form data-address-form="{{ $mode }}" method="POST" action="{{ $editing && $restore && old('_address_id') ? route('admin.service-addresses.update', old('_address_id')) : route('admin.service-addresses.store') }}">
    @csrf
    @if($editing) @method('PUT') <input type="hidden" name="_address_id" value="{{ $value('_address_id') }}"> @endif
    <p class="sa-error" data-form-error role="alert" hidden></p>
    @if($restore)<div class="sa-error" role="alert">@foreach($errors->getBag($editing ? 'edit' : 'add')->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <fieldset class="sa-grid" data-form-fields {{ $editing && !$restore ? 'disabled' : '' }}>
        <label>Contact person *<input name="contact_person_name" maxlength="100" required value="{{ $value('contact_person_name') }}" autocomplete="name"></label>
        <div><label for="{{ $mode }}-number">Contact number *</label><div class="sa-phone"><label class="sa-sr" for="{{ $mode }}-code">Country calling code</label><input id="{{ $mode }}-code" aria-label="Country calling code" name="{{ $editing ? 'contact_country_code' : 'country_code' }}" required pattern="\+[0-9]{1,4}" maxlength="5" value="{{ $value($editing ? 'contact_country_code' : 'country_code', '+91') }}"><input id="{{ $mode }}-number" name="contact_number" type="tel" inputmode="numeric" required pattern="{{ $editing ? '[0-9]{5,20}' : '[0-9]{6,15}' }}" value="{{ $value('contact_number') }}"></div></div>
        <label class="sa-full">Address *<textarea name="address" rows="3" required>{{ $value('address') }}</textarea></label>
        <label>Product *<select name="product_id" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" {{ $value('product_id') == $product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></label>
        <label>Service *<select name="service_id" required><option value="">Select service</option>@foreach($services as $service)<option value="{{ $service->id }}" {{ $value('service_id') == $service->id ? 'selected' : '' }}>{{ $service->service }}</option>@endforeach</select></label>
        <label>Country *<select name="country_id" required><option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->id }}" {{ $value('country_id') == $country->id ? 'selected' : '' }}>{{ $country->name }}</option>@endforeach</select></label>
        <label>State *<select name="state_id" required><option value="">Select state</option>@if($restore) @foreach($states as $state)<option value="{{ $state->id }}" {{ $value('state_id') == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>@endforeach @endif</select></label>
        <label>City *<select name="city_id" required><option value="">Select city</option>@if($restore) @foreach($cities as $city)<option value="{{ $city->id }}" {{ $value('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>@endforeach @endif</select></label>
        <label>Map link<input name="map_link" type="url" value="{{ $value('map_link') }}"></label>
    </fieldset>
    <footer class="sa-actions"><button type="button" class="k-ghost" data-address-close>Cancel</button><button type="submit" class="k-primary" data-save {{ $editing && !$restore ? 'disabled' : '' }}>{{ $editing ? 'Save changes' : 'Save address' }}</button></footer>
</form>
