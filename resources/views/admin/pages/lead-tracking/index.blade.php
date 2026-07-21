@extends('admin.layouts.header')
@section('content')
<style>
    /* Fix phone input padding for intl-tel-input flag */
    #phone {
        padding-left: 52px !important;
    }
    .iti--allow-dropdown input#phone {
        padding-left: 52px !important;
    }
</style>
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
    <div>
        <h3
            class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
            Lead Tracking
        </h3>
    </div>
    <ol class="flex items-center whitespace-nowrap min-w-0">
        <li class="text-[0.813rem] ps-[0.5rem]">
            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                href="javascript:void(0);">
                Lead Tracking
                <i
                    class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
            </a>
        </li>
        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50"
            aria-current="page">
            All Leads
        </li>
    </ol>
</div>
<!-- Page Header Close -->

@if (session('success'))
<div class="alert alert-success mb-4">
    {{ session('success') }}
</div>
@endif
@if (session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif

<!-- Search Filters -->
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
                <form method="GET" action="{{ route('admin.lead-tracking.index') }}" id="filter-form">
                    <div class="grid grid-cols-12 gap-6 flex items-center">

                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="staff-select"
                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Representative</label>
                            <select name="representative_user_id" class="js-example-basic-single w-full form-control-sm"
                                id="staff-select">
                                <option value="">Select Staff</option>
                                @foreach ($staff as $user)
                                <option value="{{ $user->id }}" {{ request('representative_user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="name" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Client Name</label>
                            <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm"
                                id="name" value="{{ request('name') }}" placeholder="Search by Name">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="email" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                            <input type="text" name="email" class="ti-form-input rounded-sm form-control-sm"
                                id="email" value="{{ request('email') }}" placeholder="Search by Email">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="phone" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone</label>
                            <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm !ps-14"
                                id="phone" value="{{ request('phone') }}" placeholder="Search by Phone">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="invoice-number" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Booking Slip Number</label>
                            <input type="text" name="invoice_number" class="ti-form-input rounded-sm form-control-sm"
                                id="invoice-number" value="{{ request('invoice_number') }}" placeholder="Booking Slip Number">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From
                                Date</label>
                            <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm"
                                id="from-date" value="{{ request('from_date') }}">
                        </div>

                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="to-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To
                                Date</label>
                            <input type="date" name="to_date" class="ti-form-input rounded-sm form-control-sm"
                                id="to-date" value="{{ request('to_date') }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                Apply Filters
                            </button>
                            <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                onclick="clearFilters()">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Leads Table -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header flex justify-between items-center">
                <div class="box-title">Lead Tracking - All Leads</div>
                <!-- <div class="export-buttons flex gap-2 mb-3">
                    <button type="button" class="ti-btn ti-btn-success-full ti-btn-sm export-excel-btn"
                        title="Export to Excel">
                        <i class="ri-file-excel-line"></i>
                    </button>
                    <button type="button" class="ti-btn ti-btn-info-full ti-btn-sm export-csv-btn"
                        title="Export to CSV">
                        <i class="ri-file-text-line"></i>
                    </button>
                </div> -->
            </div>
            <div class="box-body">
                @if (!$hasFilters)
                <div class="alert alert-info text-center">
                    <i class="ri-information-line me-2"></i>
                    Please apply at least one filter to view leads data.
                </div>
                @else
                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable server-paginated" width="100%">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">
                                <th data-priority="1">S.No</th>
                                <th data-priority="2">Client Name</th>
                                <th data-priority="3">Email</th>
                                <th data-priority="4">Phone</th>
                                <th data-priority="5">Representative</th>
                                <th data-priority="6">Passengers</th>
                                <th data-priority="8">Latest Status</th>
                                <th data-priority="7">Booking Slip Number</th>
                                <th data-priority="9">Created Date</th>
                                <th data-priority="1">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leads as $key => $lead)
                            <tr class="border-b border-defaultborder">
                                <td class="text-center">{{ (isset($leadsPaginator) && $leadsPaginator->firstItem() ? $leadsPaginator->firstItem() : 1) + $key }}</td>
                                <td>{{ $lead->client->name ?? 'N/A' }}</td>
                                <td>{{ $lead->client->email ?? 'N/A' }}</td>
                                <td>{{ $lead->client->contact_number ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-primary/10 text-primary">
                                        {{ $lead->representative->name ?? 'Unassigned' }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $lead->number_of_passengers ?? 0 }}</td>
                                <td class="text-center">
                                    @php
                                    // Use the 'latestFollowup' hasOne relationship which returns the most recent followup
                                    $latestFollowup = $lead->latestFollowup ?? ($lead->leadFollowups->sortByDesc('created_at')->first() ?? null);
                                    $status = $latestFollowup->status ?? null;
                                    @endphp

                                        @if ($status === 0)
                                    <span class="badge bg-secondary/10 text-secondary">Initiated</span>
                                    @elseif($status === 1)
                                    <span class="badge bg-success/10 text-success">Active</span>
                                    @elseif($status === 2)
                                    <span class="badge bg-danger/10 text-danger">Canceled</span>
                                    @elseif($status === 3)
                                    <span class="badge bg-primary/10 text-primary">Full Payment</span>
                                    @elseif($status === 4)
                                    <span class="badge bg-warning/10 text-warning">Partial Payment</span>
                                    @elseif($status === 5)
                                    <span class="badge bg-info/10 text-info">Complete</span>
                                    @elseif($status === 6)
                                    <span class="badge bg-default/10 text-default">Pending</span>
                                    @elseif($status === 7)
                                    <span class="badge bg-light/10 text-light">Reschedule</span>
                                    @elseif($status === 8)
                                    <span class="badge bg-success/10 text-success">Approved</span>
                                    @elseif($status === 9)
                                    <span class="badge bg-danger/10 text-danger">Rejected</span>
                                    @else
                                    <span class="badge bg-default/10 text-default">No Followup</span>
                                    @endif
                                        </td>
                                        @php
                                        $latestVoucher = $lead->vouchers->sortByDesc('created_at')->first();
                                        $invoiceNo = $latestVoucher && $latestVoucher->invoice ? $latestVoucher->invoice->invoice_id : 'N/A';
                                        @endphp
                                        <td class="text-center">{{ $invoiceNo }}</td>
                                <td class="text-center">{{ $lead->created_at->format('d-m-Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.lead-tracking.show', $lead->id) }}"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                        title="View Lead Details" target="_blank">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(isset($leadsPaginator) && $leadsPaginator->hasPages())
                <div class="mt-4">
                    {{ $leadsPaginator->appends(request()->except('page'))->links() }}
                </div>
                @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {

        // Custom export button handlers
        $('.export-excel-btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('.table-datatable')) {
                $('.table-datatable').DataTable().button('.buttons-excel').trigger();
            }
        });

        $('.export-csv-btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('.table-datatable')) {
                $('.table-datatable').DataTable().button('.buttons-csv').trigger();
            }
        });

        // Toggle filter section
        $('#toggle-filters').on('click', function() {
            var filterSection = $('#filter-section');
            var filterIcon = $('#filter-icon');

            if (filterSection.is(':visible')) {
                filterSection.slideUp();
                filterIcon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
            } else {
                filterSection.slideDown();
                filterIcon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
            }
        });

        // Initialize Select2
        $('.js-example-basic-single').select2({
            placeholder: "Select an option",
            allowClear: true
        });
    });

    // Clear filters function
    function clearFilters() {
        window.location.href = "{{ route('admin.lead-tracking.index') }}";
    }
</script>
@endpush
