{{--
    Leads-style Search Filters for the Operations Review queue and Work Done pages.
    Expects: $filterAction, $filters, $dateLabel ("Opened" | "Completed"), $staff, $services,
    $products, $leadStatusOptions, $operationUsers, $caseStatuses, optional $caseTypes.
--}}
@php
    $filters = $filters ?? [];
    $col = 'xl:col-span-2 lg:col-span-6 md:col-span-6 col-span-12';
@endphp

<div class="grid grid-cols-12 gap-6">
    <div class="col-span-12">
        <div class="box">
            <div class="box-header">
                <div class="box-title">Search Filters</div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>

            <div class="box-body" id="filter-section">
                <form
                    class="ti-custom-validation view-client-filters"
                    method="GET"
                    action="{{ $filterAction }}"
                    id="filter-form"
                    novalidate
                >
                    <div class="grid grid-cols-12 gap-x-4 gap-y-3 items-end">
                        <div class="{{ $col }}">
                            <label for="from-service-date" class="ti-form-label mb-1">From Service Date</label>
                            <input type="date" name="from_service_date" id="from-service-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['from_service_date'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="to-service-date" class="ti-form-label mb-1">To Service Date</label>
                            <input type="date" name="to_service_date" id="to-service-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['to_service_date'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="from-created-date" class="ti-form-label mb-1">From Created Date</label>
                            <input type="date" name="from_created_date" id="from-created-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['from_created_date'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="to-created-date" class="ti-form-label mb-1">To Created Date</label>
                            <input type="date" name="to_created_date" id="to-created-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['to_created_date'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="from-date" class="ti-form-label mb-1">From {{ $dateLabel }} Date</label>
                            <input type="date" name="from_date" id="from-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['from_date'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="to-date" class="ti-form-label mb-1">To {{ $dateLabel }} Date</label>
                            <input type="date" name="to_date" id="to-date"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['to_date'] ?? '' }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-x-4 gap-y-3 items-end mt-4">
                        <div class="{{ $col }}">
                            <label for="name" class="ti-form-label mb-1">Name</label>
                            <input type="text" name="name" id="name"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['name'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="email" class="ti-form-label mb-1">Email</label>
                            <input type="text" name="email" id="email"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['email'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="phone" class="ti-form-label mb-1">Phone</label>
                            <input type="text" name="phone" id="phone"
                                class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $filters['phone'] ?? '' }}">
                        </div>
                        <div class="{{ $col }}">
                            <label for="staff" class="ti-form-label mb-1">Staff</label>
                            <select name="representative_user_id" id="staff"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">Select Staff</option>
                                @foreach ($staff as $user)
                                    <option value="{{ $user->id }}"
                                        {{ ($filters['representative_user_id'] ?? '') === $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $col }}">
                            <label for="lead-status-filter" class="ti-form-label mb-1">Status</label>
                            <select name="lead_status" id="lead-status-filter"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">Select Status</option>
                                <option value="na"
                                    {{ in_array(strtolower((string) ($filters['lead_status'] ?? '')), ['na', 'n/a'], true) ? 'selected' : '' }}>
                                    N/A
                                </option>
                                @foreach ($leadStatusOptions as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}"
                                        {{ (string) ($filters['lead_status'] ?? '') === (string) $statusValue ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $col }}">
                            <label for="operations-status-filter" class="ti-form-label mb-1">Operations Status</label>
                            <select name="status" id="operations-status-filter"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">All Status</option>
                                @foreach ($caseStatuses as $caseStatus)
                                    <option value="{{ $caseStatus }}"
                                        {{ ($filters['status'] ?? '') === $caseStatus ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $caseStatus)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-x-4 gap-y-3 items-end mt-4">
                        <div class="{{ $col }}">
                            <label for="service-filter" class="ti-form-label mb-1">Service</label>
                            <select name="service_ids" id="service-filter"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">Select Service</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}"
                                        {{ ($filters['service_ids'] ?? '') === (string) $service->id ? 'selected' : '' }}>
                                        {{ $service->service }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $col }}">
                            <label for="product-filter" class="ti-form-label mb-1">Product</label>
                            <select name="product_ids" id="product-filter"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">Select Product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}"
                                        {{ ($filters['product_ids'] ?? '') === (string) $product->id ? 'selected' : '' }}>
                                        {{ $product->product }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $col }}">
                            <label for="operations-user-filter" class="ti-form-label mb-1">Operations User</label>
                            <select name="operations_user_id" id="operations-user-filter"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">All Users</option>
                                @foreach ($operationUsers as $user)
                                    <option value="{{ $user->id }}"
                                        {{ ($filters['operations_user_id'] ?? '') === $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @isset($caseTypes)
                            <div class="{{ $col }}">
                                <label for="type-filter" class="ti-form-label mb-1">Type</label>
                                <select name="type" id="type-filter"
                                    class="js-example-basic-single w-full form-control-sm">
                                    <option value="">All Types</option>
                                    @foreach ($caseTypes as $caseType)
                                        <option value="{{ $caseType }}"
                                            {{ ($filters['type'] ?? '') === $caseType ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $caseType)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endisset

                        <div class="{{ $col }}">
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="ti-btn bg-primary text-white !py-1 !px-4 hover:bg-primary-hover">
                                    Apply
                                </button>
                                <a href="{{ $filterAction }}"
                                    class="ti-btn ti-btn-outline-secondary !py-1 !px-2" title="Reset">
                                    <i class="ri-refresh-line"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
