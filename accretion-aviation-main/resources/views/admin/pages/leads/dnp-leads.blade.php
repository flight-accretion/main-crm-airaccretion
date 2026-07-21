@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">

    </div>

    <!-- Form -->
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="box-title">
                        DNP Leads
                    </div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>

                <div class="box-body" id="filter-section">
                    <form class="ti-custom-validation view-client-filters" method="GET"
                        action="{{ route('admin.leads.dnp') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Service
                                    Date</label>
                                <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm"
                                    id="from-date" value="{{ request('from_date') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="to-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To
                                    Service Date</label>
                                <input type="date" name="to_date" class="ti-form-input rounded-sm form-control-sm"
                                    id="to-date" value="{{ request('to_date') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="input-label"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                                <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm"
                                    id="input" value="{{ request('name') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="input-label"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                <input type="text" name="email" class="ti-form-input rounded-sm form-control-sm"
                                    id="input-label" value="{{ request('email') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="input-placeholder"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone</label>
                                <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm"
                                    id="input-placeholder" value="{{ request('phone') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                @if(!in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_EXECUTIVE]))
                                    <label for="input-placeholder"
                                        class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Staff</label>
                                    <select name="representative_user_id" class="ti-form-select rounded-sm form-control-sm">
                                        <option value="">Select Staff</option>
                                        @foreach ($staff as $user)
                                            <option value="{{ $user->id }}" {{ request('representative_user_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }} @if($user->id == Auth::id()) (You) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <div class="flex gap-2">
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                        Apply Filters
                                    </button>
                                    <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                        onclick="clearFilters()">
                                        <i class="ri-refresh-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-body">
                    <div class="flex justify-end mb-3">
                        <button id="export-dnp" type="button" class="ti-btn ti-btn-success-full ti-btn-sm" title="Export to Excel">
                            <i class="ri-file-excel-line"></i>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="" class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th>S.No</th>
                                    <th>Client Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Next Follow Up</th>
                                    <th>Created Date</th>
                                    <th>Assigned</th>
                                    <th>Services</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dnpLeads as $key => $enquiry)
                                    <tr data-id="{{ $enquiry->id }}" class="border-b border-defaultborder">
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ $enquiry->client->name }}</td>
                                        <td>{{ $enquiry->client->email }}</td>
                                        <td class="text-center">{{ $enquiry->client->contact_number }}</td>
                                        <td class="text-center">
                                            @if ($enquiry->latest_followup && $enquiry->latest_followup->next_followup_date)
                                                {{ $enquiry->latest_followup->next_followup_date->format('d-m-Y H:i') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="text-center">{{ date('d-m-Y', strtotime($enquiry->created_at)) }}</td>
                                        <td>
                                            @if ($enquiry->representative)
                                                {{ $enquiry->representative->name }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $serviceNames = $enquiry->service_names ?? [];
                                            @endphp
                                            @if (!empty($serviceNames) && is_array($serviceNames))
                                                {{ implode(', ', $serviceNames) }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.leads.view', $enquiry->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                                    title="View Lead"><i class="ri-eye-line"></i></a>
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.leads.edit', $enquiry->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop
@push('scripts')
    <!-- Script -->
    <script>
        // Export DNP leads (apply filters and datatable visible rows when available)
        function getFilterParams() {
            const params = {};
            $('#filter-form').serializeArray().forEach(function (f) {
                if (f.value !== '') params[f.name] = f.value;
            });
            return params;
        }

        async function exportDnpLeads() {
            const params = getFilterParams();

            // If a DataTable is present, attempt to get currently visible row ids
            try {
                const table = $('.table-datatable').DataTable();
                // get currently displayed rows (after search/filter applied)
                const nodes = table.rows({ search: 'applied' }).nodes().toArray();
                const ids = [];
                nodes.forEach(function (rowNode) {
                    const id = $(rowNode).data('id');
                    if (id) ids.push(id);
                });
                if (ids.length > 0) {
                    params.ids = ids.join(',');
                }
            } catch (e) {
                // DataTable not initialised - ignore
            }

            // Build query string and navigate to export URL to trigger download
            const query = Object.keys(params).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(params[k])).join('&');
            const url = "{{ route('admin.leads.dnp.export') }}" + (query ? ('?' + query) : '');
            window.location = url;
        }

        $(document).on('click', '#export-dnp', function () {
            exportDnpLeads();
        });

    // Initialize DataTable with export functionality
    // var leadsTable = $('.table-datatable').DataTable();

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.leads.dnp') }}";
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Initially hide filter section and set icon to down
            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function() {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });
        });
    </script>
@endpush
