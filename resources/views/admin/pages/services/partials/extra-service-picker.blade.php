@php
    $selectedExtraServiceIds = array_map('strval', $selectedExtraServiceIds ?? []);
@endphp

<div class="mt-6" data-extra-service-picker>
    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-2">Extra Services</label>
    <div class="max-w-xl rounded-sm border border-defaultborder bg-white dark:bg-bodybg dark:border-white/10">
        <div class="p-3 border-b border-defaultborder dark:border-white/10">
            <input type="search"
                class="ti-form-input rounded-sm form-control-sm"
                placeholder="Search extra services"
                data-extra-service-search>
        </div>
        <div class="p-3 space-y-2" style="max-height: 320px; overflow-y: auto;" data-extra-service-list>
            @forelse($extraServices as $extraService)
                @php
                    $searchText = Str::lower(trim($extraService->extra_service . ' ' . ($extraService->description ?? '')));
                @endphp
                <label class="extra-service-option flex items-start gap-3 rounded-sm border border-defaultborder/60 p-2 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                    data-extra-service-option
                    data-search="{{ $searchText }}">
                    <input type="checkbox"
                        name="extra_service_ids[]"
                        value="{{ $extraService->id }}"
                        class="ti-form-checkbox mt-1"
                        {{ in_array((string) $extraService->id, $selectedExtraServiceIds, true) ? 'checked' : '' }}>
                    <span class="min-w-0">
                        <span class="block font-medium text-defaulttextcolor dark:text-defaulttextcolor/70">
                            {{ $extraService->extra_service }}
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-white/50">
                            Price: {{ config('settings.currency_symbol') }}{{ number_format($extraService->extra_service_amount, 2) }}
                        </span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500 dark:text-white/50">No active extra services available.</p>
            @endforelse
            <p class="hidden text-sm text-gray-500 dark:text-white/50" data-extra-service-empty>No matching extra services found.</p>
        </div>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-white/50" data-extra-service-summary>Selected: 0</p>
    @error('extra_service_ids')
        <span class="text-red-500 text-xs">{{ $message }}</span>
    @enderror
    @error('extra_service_ids.*')
        <span class="text-red-500 text-xs">{{ $message }}</span>
    @enderror
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('[data-extra-service-picker]').forEach(function(picker) {
                    const searchInput = picker.querySelector('[data-extra-service-search]');
                    const options = Array.from(picker.querySelectorAll('[data-extra-service-option]'));
                    const emptyMessage = picker.querySelector('[data-extra-service-empty]');
                    const summary = picker.querySelector('[data-extra-service-summary]');

                    function updatePicker() {
                        const query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
                        let visibleCount = 0;
                        let selectedCount = 0;

                        options.forEach(function(option) {
                            const checkbox = option.querySelector('input[type="checkbox"]');
                            const matches = !query || (option.getAttribute('data-search') || '').includes(query);

                            option.classList.toggle('hidden', !matches);
                            if (matches) {
                                visibleCount++;
                            }
                            if (checkbox && checkbox.checked) {
                                selectedCount++;
                            }
                        });

                        if (emptyMessage) {
                            emptyMessage.classList.toggle('hidden', visibleCount > 0 || options.length === 0);
                        }
                        if (summary) {
                            summary.textContent = 'Selected: ' + selectedCount;
                        }
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', updatePicker);
                    }
                    options.forEach(function(option) {
                        const checkbox = option.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.addEventListener('change', updatePicker);
                        }
                    });

                    updatePicker();
                });
            });
        </script>
    @endpush
@endonce
