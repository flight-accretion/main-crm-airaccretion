{{-- 
    Voucher Generation Dashboard
    This page displays leads that have at least one approved payment.
    It allows Operations team to generate vouchers for these leads.
    Only accessible by Admin and Operations roles.
--}}
@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Voucher Generation</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="javascript:void(0);">
                    Voucher
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Voucher Generation
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
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="box-title">
                        Search Filters
                    </div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>
                <div class="box-body" id="filter-section">
                    <form class="ti-custom-validation view-client-filters" method="GET"
                        action="{{ route('admin.vouchers.index') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 gap-6 flex items-center">
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Service
                                    Date</label>
                                <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm"
                                    id="from-date" value="{{ request('from_date') }}">
                            </div>
                            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="to-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Service
                                    Date</label>
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
                                <label for="input-placeholder"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Staff</label>
                                <select name="representative_user_id"
                                    class="js-example-basic-single w-full form-control-sm">
                                    <option value="">Select Staff</option>
                                    @foreach ($staff as $user)
                                        <option value="{{ $user->id }}"
                                            {{ request('representative_user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Second row of filters -->
                        <div class="grid grid-cols-12 gap-6 flex items-center mt-4">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="status-filter"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <select name="status" class="js-example-basic-single w-full form-control-sm"
                                    id="status-filter">
                                    <option value="" {{ (string) request('status') === '' ? 'selected' : '' }}>Select
                                        Status</option>
                                    @foreach ($statusOptions as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}"
                                            {{ (string) request('status') === (string) $statusValue ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="service-filter"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                                <select name="service_ids" class="js-example-basic-single w-full form-control-sm"
                                    id="service-filter">
                                    <option value="">Select Service</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}"
                                            {{ request('service_ids') == $service->id ? 'selected' : '' }}>
                                            {{ $service->service }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="product-filter"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                                <select name="product_ids" class="js-example-basic-single w-full form-control-sm"
                                    id="product-filter">
                                    <option value="">Select Product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            {{ request('product_ids') == $product->id ? 'selected' : '' }}>
                                            {{ $product->product }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <div class="flex gap-2 mt-6">
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

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header flex justify-between items-center">
                    <div class="box-title">Voucher Generation</div>
                    <div class="export-buttons flex gap-2 mb-3">
                        <button type="button" class="ti-btn ti-btn-success-full ti-btn-sm export-excel-btn"
                            title="Export to Excel">
                            <i class="ri-file-excel-line"></i>
                        </button>
                        <button type="button" class="ti-btn ti-btn-info-full ti-btn-sm export-csv-btn"
                            title="Export to CSV">
                            <i class="ri-file-text-line"></i>
                        </button>
                        <!-- <button type="button" class="ti-btn ti-btn-danger-full ti-btn-sm export-pdf-btn" title="Export to PDF"><i class="ri-file-pdf-line"></i>
                                            </button>
                                            <button type="button" class="ti-btn ti-btn-secondary-full ti-btn-sm export-print-btn" title="Print"><i class="ri-printer-line"></i>
                                            </button> -->
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table id="" class="table display responsive nowrap lead-datatable" width="100%"
                            data-empty-msg="No vendor payments found">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th></th>
                                    <th data-priority="1">S.No</th>
                                    <th data-priority="2">Client Name</th>
                                    <th data-priority="3">Email</th>
                                    <th data-priority="5">Phone</th>
                                    <th data-priority="6">Next Follow Up</th>
                                    <th data-priority="7">Created Date</th>
                                    <th data-priority="8">Assigned:</th>
                                    <th data-priority="9">Service Date:</th>
                                    <th data-priority="10">Service:</th>
                                    <th data-priority="11">Last Update:</th>
                                    <th data-priority="12">Follow-up History:</th>
                                    <th data-priority="1">Status</th>
                                    <th data-priority="1">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($leads as $key => $enquiry)
                                    <tr class="border-b border-defaultborder">
                                        <td></td>
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ $enquiry->client->name }}</td>
                                        <td>{{ $enquiry->client->email }}</td>
                                        <td class="text-center">{{ $enquiry->client->contact_number }}</td>
                                        <td class="text-center">
                                            @if ($enquiry->next_followup && $enquiry->next_followup->next_followup_date)
                                                {{ $enquiry->next_followup->next_followup_date->format('d-m-Y H:i') }}
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
                                            @if ($enquiry->rideSegments->count() > 0)
                                                @php
                                                    $firstSegment = $enquiry->rideSegments->first();
                                                    $lastSegment = $enquiry->rideSegments->last();
                                                @endphp
                                                From: {{ date('d-m-Y', strtotime($firstSegment->from_date)) }} To:
                                                {{ date('d-m-Y', strtotime($lastSegment->to_date)) }}
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
                                        <td>{{ $enquiry->updated_at->format('d-m-Y H:i:s') }}</td>
                                        <td>
                                            {{--  <div class="followup-history grid gap-2">
                                                    @forelse($enquiry->leadFollowups->sortByDesc('next_followup_date') as $followup)
                                                        <div class="grid gap-2">
                                                            <p class="text-sm text-gray-700 "><strong>Note:</strong> {{ $followup->followup_note }}</p>
                                                            <p class="text-sm text-gray-700">
                                                                <strong>Status:</strong>
                                                                <span class="badge bg-primary/10 text-primary">
                                                                    {{ $followup->status == 1 ? 'Pending' : ($followup->status == 2 ? 'Completed' : 'Skipped') }}
                                                                </span>
                                                            </p>
                                                            <p class="text-sm text-gray-700">
                                                                <strong>By:</strong> {{ $followup->followedBy->name ?? 'System' }}
                                                                @if ($followup->file)
                                                                    <a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}" target="_blank" class="text-blue-500 ml-2">
                                                                        <i class="ri-image-line"></i> View
                                                                    </a>
                                                                @endif
                                                            </p>
                                                            <p class="text-sm text-gray-700">
                                                                <strong>Next Follow-up:</strong> {{ $followup->next_followup_date?->format('Y-m-d H:i T') }}
                                                            </p>
                                                        </div>
                                                    @empty
                                                        <div class="text-sm text-gray-700 text-center">No follow-up history</div>
                                                    @endforelse
                                                </div>  --}}
                                            <div class="followup-history table-responsive">
                                                <table class="table display responsive nowrap" width="100%">
                                                    <thead class="bg-primary text-white">
                                                        <tr class="border-b border-defaultborder">
                                                            <th scope="col" class="text-start">Note</th>
                                                            <th scope="col" class="text-start">By</th>
                                                            <th scope="col" class="text-start">Next Follow-up</th>
                                                            <th scope="col" class="text-start">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="table-group-divider dark:border-defaultborder/10">
                                                        @forelse($enquiry->leadFollowups->sortByDesc('next_followup_date') as $followup)
                                                            <tr class="border-b border-defaultborder">
                                                                <td
                                                                    style="white-space: normal; word-break: break-word; max-width: 300px;">
                                                                    {{ $followup->followup_note }}
                                                                </td>
                                                                <td>
                                                                    {{ $followup->followedBy->name ?? 'System' }}
                                                                    @if ($followup->file)
                                                                        <a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}"
                                                                            target="_blank" class="text-blue-500 ml-2">
                                                                            <i class="ri-image-line"></i> View
                                                                        </a>
                                                                    @endif
                                                                </td>
                                                                <td
                                                                    style="white-space: normal; word-break: break-word; max-width: 100px;">
                                                                    {{ $followup->next_followup_date?->format('Y-m-d H:i T') }}
                                                                </td>
                                                                <td>
                                                                    <span
                                                                        class="badge 
                                                                            {{ $followup->status === 0
                                                                                ? 'bg-secondary/10 text-secondary'
                                                                                : ($followup->status === 1
                                                                                    ? 'bg-success/10 text-success'
                                                                                    : ($followup->status === 2
                                                                                        ? 'bg-danger/10 text-danger'
                                                                                        : ($followup->status === 3
                                                                                            ? 'bg-primary/10 text-primary'
                                                                                            : ($followup->status === 4
                                                                                                ? 'bg-warning/10 text-warning'
                                                                                                : ($followup->status === 5
                                                                                                    ? 'bg-info/10 text-info'
                                                                                                    : 'bg-default/10 text-default'))))) }}">
                                                                        {{ $followup->status === 0
                                                                            ? 'Initiated'
                                                                            : ($followup->status === 1
                                                                                ? 'Active'
                                                                                : ($followup->status === 2
                                                                                    ? 'Cancelled'
                                                                                    : ($followup->status === 3
                                                                                        ? 'Full Payment Received'
                                                                                        : ($followup->status === 4
                                                                                            ? 'Partial Payment Received'
                                                                                            : ($followup->status === 5
                                                                                                ? 'Confirmed'
                                                                                                : ($followup->status === 6
                                                                                                    ? 'Pending'
                                                                                                    : ($followup->status === 7
                                                                                                        ? 'Rescheduled'
                                                                                                        : ($followup->status === 8
                                                                                                            ? 'Approved'
                                                                                                            : ($followup->status === 9
                                                                                                                ? 'Rejected'
                                                                                                                : 'N/A'))))))))) }}

                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4"
                                                                    class="text-sm text-gray-700 text-center">No follow-up
                                                                    history</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $status =
                                                    $enquiry->leadFollowups()->orderBy('created_at', 'desc')->first()
                                                        ->status ?? null;
                                            @endphp

                                            @if ($status === 0)
                                                <span class="badge bg-secondary/10 text-secondary">Initiated</span>
                                            @elseif($status === 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @elseif($status === 2)
                                                <span class="badge bg-danger/10 text-danger">Cancelled</span>
                                            @elseif($status === 3)
                                                <span class="badge bg-primary/10 text-primary">Full Payment Received</span>
                                            @elseif($status === 4)
                                                <span class="badge bg-warning/10 text-warning">Partial Payment
                                                    Received</span>
                                            @elseif($status === 5)
                                                <span class="badge bg-info/10 text-info">Confirmed</span>
                                            @elseif($status === 6)
                                                <span class="badge bg-default/10 text-default">Pending</span>
                                            @elseif($status === 7)
                                                <span class="badge bg-light/10 text-light">Rescheduled</span>
                                            @elseif($status === 8)
                                                <span class="badge bg-warning/10 text-warning">Approved</span>
                                            @elseif($status === 9)
                                                <span class="badge bg-danger/10 text-danger">Rejected</span>
                                            @else
                                                <span class="badge bg-default/10 text-default">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                @php
                                                    $hasApprovedPayment = in_array(
                                                        $enquiry->id,
                                                        $leadsWithApprovedPayments ?? [],
                                                    );
                                                    // Determine current user's user_type (string)
                                                    $currentUserType =
                                                        Auth::user() && Auth::user()->userType
                                                            ? Auth::user()->userType->user_type
                                                            : null;
                                                @endphp

                                                @if (
                                                    $hasApprovedPayment &&
                                                        $currentUserType &&
                                                        (in_array($currentUserType, \App\Models\UserType::ADMIN_ROLES) ||
                                                            in_array($currentUserType, \App\Models\UserType::OPERATIONS_ROLES)))
                                                    <a aria-label="anchor"
                                                        href="{{ route('admin.vouchers.generate', $enquiry->id) }}"
                                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-warning-full"
                                                        target="_blank" title="Generate Voucher"><i
                                                            class="ri-file-text-line"></i>
                                                    </a>
                                                @endif
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.leads.follow-up.create', $enquiry->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full"
                                                    target="_blank" data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Add Lead Followup"><i class="ri-add-line"></i></a>
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.leads.view', $enquiry->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                                    target="_blank" title="View Lead"><i class="ri-eye-line"></i></a>
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.leads.edit', $enquiry->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                    title="Edit Lead"><i class="ri-edit-line"></i></a>
                                                <!-- <a aria-label="anchor" href="javascript:void(0);" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full toggle-client-status" data-id="{{ $enquiry->client->id }}" data-status="{{ $enquiry->client->status }}" data-name="{{ $enquiry->client->name }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $enquiry->client->status ? 'Deactivate' : 'Activate' }}"><i class="{{ $enquiry->client->status ? 'ri-lock-line' : 'ri-check-line' }}"></i></a> -->
                                                <!-- <a aria-label="Confirm Lead" href="javascript:void(0);"
                                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full confirm-lead"
                                                                    data-lead-id="{{ $enquiry->id }}"title="Confirm Lead"><i
                                                                        class="ri-check-line"></i></a> -->
                                                <a aria-label="Cancel Lead" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full cancel-lead"
                                                    data-lead-id="{{ $enquiry->id }}" title="Cancel Lead"><i
                                                        class="ri-close-line"></i></a>
                                                @if (in_array(optional(auth()->user()->userType)->user_type, [\App\Models\UserType::SUPER_ADMIN]))
                                                    <a aria-label="Delete Lead" href="javascript:void(0);"
                                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-lead"
                                                        data-lead-id="{{ $enquiry->id }}" title="Delete Lead"><i
                                                            class="ri-delete-bin-line"></i></a>
                                                @endif
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

    <!-- Confirm Followup Modal -->
    <div id="confirm-followup-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-confirm-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-4 text-gray-800">Confirm Lead</h5>
                <div class="grid grid-cols-12 gap-6">
                    <div class="xl:col-span-12 col-span-12">
                        <form id="confirm-followup-form">
                            <input type="hidden" name="lead_id" id="confirm-lead-id">
                            <input type="hidden" name="status" value="5">
                            <!-- Status 5 for confirmed -->
                            <div class="mb-4">
                                <label for="confirm-notes"
                                    class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" id="confirm-notes" rows="3" class="ti-form-input form-control form-control-sm"
                                    required maxlength="1000" pattern="^(?=.*[A-Za-z])[A-Za-z0-9\s]+$"
                                    title="Please enter letters and numbers only; at least one letter is required"></textarea>
                            </div>
                            <div>
                                <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                                    id="cancel-confirm">Cancel</button>
                                <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Cancel Followup Modal -->
    <div id="cancel-followup-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-cancel-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
                <p class="mb-4 text-gray-600">You want to cancel this lead?</p>
                <div class="">
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="decline-cancel">No</button>
                    <button type="button" class="ti-btn bg-primary text-white px-4 py-1"
                        id="proceed-to-cancel">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Notes Modal -->
    <div id="cancel-notes-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-cancel-notes-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-4 text-gray-800">Cancel Lead</h5>
                <form id="cancel-followup-form">
                    <input type="hidden" name="lead_id" id="cancel-lead-id">
                    <input type="hidden" name="status" value="2">
                    <!-- Status 2 for cancelled -->
                    <div class="mb-4">
                        <label for="cancel-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="cancel-notes" rows="3" class="ti-form-textarea" required maxlength="1000"
                            pattern="^(?=.*[A-Za-z])[A-Za-z0-9\s]+$"
                            title="Please enter letters and numbers only; at least one letter is required"></textarea>
                    </div>
                    <div>
                        <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                            id="cancel-cancel-notes">Cancel</button>
                        <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Generate Voucher Modal Removed - Now redirects to separate page -->

    <!-- Delete Confirmation Alert Modal -->
    <div id="custom-delete-alert"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
            <button type="button" class="float-right text-gray-500 hover:text-black" id="close-alert">
                <i class="bi bi-x"></i>
            </button>
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
            <p class="mb-4 text-gray-600">You want to deactivate this client?</p>
            <div class="flex justify-center gap-4">
                <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                    id="decline-delete">Decline</button>
                <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-delete">Yes,
                    Deactivate</button>
            </div>
        </div>
    </div>
    <!-- Lead Delete Confirmation Modal -->
    <div id="custom-delete-lead-alert"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
            <button type="button" class="float-right text-gray-500 hover:text-black" id="close-lead-delete-alert">
                <i class="bi bi-x"></i>
            </button>
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Delete Lead</h5>
            <p class="mb-4 text-gray-600">This will permanently delete the lead and all related data (followups, payments,
                vouchers, passengers, rides, vendor payments, refunds etc.). Are you sure?</p>
            <div class="flex justify-center gap-4">
                <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                    id="decline-lead-delete">Decline</button>
                <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-lead-delete">Yes,
                    Delete</button>
            </div>
        </div>
    </div>
    <div id="toggle-status-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
            <button type="button" class="float-right text-gray-500 hover:text-black" id="close-toggle-modal">
                <i class="bi bi-x"></i>
            </button>
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Status Change</h5>
            <p class="mb-4 text-gray-600" id="status-modal-message"></p>
            <div class="flex justify-center gap-4">
                <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                    id="cancel-toggle">Cancel</button>
                <button type="button" class="ti-btn bg-primary text-white px-4 py-1"
                    id="confirm-status-toggle">Confirm</button>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            let currentLeadId = null;
            // Confirm button click
            $(document).on('click', '.confirm-lead', function() {
                currentLeadId = $(this).data('lead-id');
                $('#confirm-lead-id').val(currentLeadId);
                $('#confirm-followup-modal').removeClass('hidden');
            });

            // Cancel button click - first confirmation
            $(document).on('click', '.cancel-lead', function() {
                currentLeadId = $(this).data('lead-id');
                $('#cancel-followup-modal').removeClass('hidden');
            });

            // Proceed to cancel notes after confirming cancellation
            $('#proceed-to-cancel').click(function() {
                $('#cancel-followup-modal').addClass('hidden');
                $('#cancel-lead-id').val(currentLeadId);
                $('#cancel-notes-modal').removeClass('hidden');
            });

            // Close modals
            $('#close-confirm-modal, #cancel-confirm').click(function() {
                $('#confirm-followup-modal').addClass('hidden');
            });

            $('#close-cancel-modal, #decline-cancel').click(function() {
                $('#cancel-followup-modal').addClass('hidden');
            });

            $('#close-cancel-notes-modal, #cancel-cancel-notes').click(function() {
                $('#cancel-notes-modal').addClass('hidden');
            });

            // Form submissions
            $('#confirm-followup-form').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serializeArray();
                // Remove any existing status if present
                formData = formData.filter(item => item.name !== 'status');
                // Add the correct status
                formData.push({
                    name: 'status',
                    value: '5'
                });
                submitFollowup(formData, 'confirmed');
            });

            $('#cancel-followup-form').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serializeArray();
                // Remove any existing status if present
                formData = formData.filter(item => item.name !== 'status');
                // Add the correct status
                formData.push({
                    name: 'status',
                    value: '2'
                });
                submitFollowup(formData, 'cancelled');
            });

            function submitFollowup(formData, action) {
                // Convert array to object
                const data = {};
                formData.forEach(item => {
                    data[item.name] = item.value;
                });

                // Get the lead ID from the row
                const leadId = currentLeadId;
                $.ajax({
                    url: `/admin/leads/${leadId}/follow-up`,
                    type: 'POST',
                    data: data,
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        console.log('Response:', response); // Debug log
                        if (response.success) {
                            // Close the appropriate modal
                            if (action === 'confirmed') {
                                $('#confirm-followup-modal').addClass('hidden');
                            } else {
                                $('#cancel-notes-modal').addClass('hidden');
                            }

                            showToast('success', response.message || 'Lead ' + action +
                                ' successfully!');

                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('error', response.message || 'Error processing request');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText); // Debug log
                        let errorMessage = 'Something went wrong';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.statusText) {
                            errorMessage = xhr.statusText;
                        }
                        showToast('error', errorMessage);
                    }
                });
            }


            window.showToast = function(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            // Export functionality - attach to header buttons and include all filters
            function buildExportUrl(isCsv = false) {
                const filters = {
                    name: $('input[name="name"]').val(),
                    email: $('input[name="email"]').val(),
                    phone: $('input[name="phone"]').val(),
                    representative_user_id: $('select[name="representative_user_id"]').val(),
                    from_date: $('input[name="from_date"]').val(),
                    to_date: $('input[name="to_date"]').val(),
                    status: $('select[name="status"]').val(),
                    service_ids: $('select[name="service_ids"]').val(),
                    product_ids: $('select[name="product_ids"]').val()
                };

                const exportPath = '{{ route('admin.leads.export') }}';
                const params = new URLSearchParams();

                Object.keys(filters).forEach(key => {
                    const val = filters[key];
                    if (val === undefined || val === null || val === '') return;
                    // If it's an array (multi-select), append each value
                    if (Array.isArray(val)) {
                        val.forEach(v => params.append(key + '[]', v));
                    } else {
                        params.append(key, val);
                    }
                });

                if (isCsv) params.append('format', 'csv');

                return params.toString() ? exportPath + '?' + params.toString() : exportPath;
            }

            $(document).on('click', '.export-excel-btn', function(e) {
                e.preventDefault();
                const url = buildExportUrl(false);
                showToast('success', 'Export started! Download will begin shortly...');
                window.location.href = url;
            });

            $(document).on('click', '.export-csv-btn', function(e) {
                e.preventDefault();
                const url = buildExportUrl(true);
                showToast('success', 'Export started! Download will begin shortly...');
                window.location.href = url;
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            let selectedClientId = null;

            // When delete button clicked
            $(document).on('click', '.delete-client', function(e) {
                e.preventDefault();
                selectedClientId = $(this).data('id');
                $('#custom-delete-alert').removeClass('hidden');
            });

            // When confirm delete
            $('#confirm-delete').click(function() {
                if (!selectedClientId) return;

                $.ajax({
                    url: "{{ url('admin/clients') }}/" + selectedClientId,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        console.log('Client delete response:', response);
                        if (response.success) {
                            $('#custom-delete-alert').addClass('hidden');
                            location.reload();
                        } else {
                            showToast('error', response.message || 'Failed to delete client');
                            $('#custom-delete-alert').addClass('hidden');
                            console.warn('Client delete returned falsy success:', response);
                        }
                    },
                    error: function(xhr) {
                        console.error('Client delete error:', xhr);
                        let msg = 'Something went wrong.';
                        try {
                            const json = JSON.parse(xhr.responseText || '{}');
                            if (json.message) msg = json.message;
                        } catch (e) {}
                        showToast('error', msg);
                        $('#custom-delete-alert').addClass('hidden');
                    }
                });
            });

            // Cancel delete
            $('#decline-delete, #close-alert').click(function() {
                $('#custom-delete-alert').addClass('hidden');
                selectedClientId = null;
            });

            // Lead deletion
            let selectedLeadId = null;
            $(document).on('click', '.delete-lead', function(e) {
                e.preventDefault();
                selectedLeadId = $(this).data('lead-id');
                $('#custom-delete-lead-alert').removeClass('hidden');
            });

            $('#confirm-lead-delete').click(function() {
                if (!selectedLeadId) return;

                $.ajax({
                    url: "{{ url('admin/leads') }}/" + selectedLeadId,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        console.log('Lead delete response:', response);
                        if (response.success) {
                            $('#custom-delete-lead-alert').addClass('hidden');
                            showToast('success', response.message ||
                                'Lead deleted successfully');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast('error', response.message || 'Failed to delete lead');
                        }
                    },
                    error: function(xhr) {
                        console.error('Lead delete error:', xhr);
                        let msg = 'Something went wrong.';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr
                            .responseJSON.message;
                        else if (xhr && xhr.responseText) {
                            try {
                                const parsed = JSON.parse(xhr.responseText);
                                if (parsed && parsed.message) msg = parsed.message;
                            } catch (e) {
                                // fallback to raw text
                                msg = xhr.responseText.substring(0, 200);
                            }
                        }
                        showToast('error', msg);
                        $('#custom-delete-lead-alert').addClass('hidden');
                    }
                });
            });

            $('#decline-lead-delete, #close-lead-delete-alert').click(function() {
                $('#custom-delete-lead-alert').addClass('hidden');
                selectedLeadId = null;
            });
        });
        $(document).ready(function() {
            let clientIdToToggle = null;
            let currentStatus = null;

            // Initialize tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Status toggle functionality
            $(document).on('click', '.toggle-client-status', function(e) {
                e.preventDefault();
                clientIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                const clientName = $(this).data('name');

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(`Are you sure you want to ${action} ${clientName}?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!clientIdToToggle) return;

                $.ajax({
                    url: "{{ route('admin.clients.toggle-status', '') }}/" + clientIdToToggle,
                    type: 'PATCH',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            // Update the UI elements
                            const button = $(
                                `.toggle-client-status[data-id="${clientIdToToggle}"]`);
                            const newStatus = response.new_status;

                            // Update button icon and tooltip
                            button.find('i')
                                .removeClass(newStatus ? 'ri-lock-line' : 'ri-check-line')
                                .addClass(newStatus ? 'ri-check-line' : 'ri-lock-line');

                            button.attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            // Update status badge (10th column)
                            const statusCell = button.closest('tr').find('td:nth-child(10)');
                            if (newStatus) {
                                statusCell.html(
                                    '<span class="badge bg-success/10 text-success">Active</span>'
                                );
                            } else {
                                statusCell.html(
                                    '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                                );
                            }
                        } else {
                            showToast('error', response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        showToast('error', xhr.responseJSON?.message ||
                            "Something went wrong.");
                    }
                });
            });

            // Cancel toggle
            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                clientIdToToggle = null;
                currentStatus = null;
            });

            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
    </script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with export functionality
            var isMobile = window.innerWidth <= 768;
            var leadsTable = $('.lead-datatable').DataTable({

                responsive: isMobile ? false : {
                    details: {
                        type: 'column',
                        target: 0,
                        renderer: function(api, rowIdx, columns) {
                            var rowData = api.row(rowIdx).data();
                            return `
                        <div class="leads-tableshow">
                            <div class="p-3 ml-5 text-sm text-gray-700">
                                <div class="mb-4"><strong>Assigned:</strong> ${columns[6] ? columns[6].data : 'N/A'}</div>
                                <div class="mb-4">
                                    <strong>Service Date:</strong> ${columns[7] ? columns[7].data : 'N/A'}
                                </div>
                                <div class="mb-4"><strong>Service:</strong> ${columns[8] ? columns[8].data : 'N/A'} </div>
                                <div class="mb-4"><strong>Last Update:</strong> ${columns[9] ? columns[9].data : 'N/A'} </div>
                            </div>
                            <div class="follow-history p-3 ml-5 text-sm text-gray-700"><div class="mb-4"><strong>Follow-up History:</strong></div> ${columns[10] ? columns[10].data : 'No history'} </div>
                        </div>
                        `;
                        }
                    }
                },
                scrollX: true, // Horizontal scroll
                columnDefs: [{
                        className: 'control',
                        orderable: false,
                        targets: 0
                    },
                    {
                        orderable: false,
                        targets: 1
                    }
                ],
                order: [
                    [1, 'asc']
                ],
                drawCallback: function(settings) {
                    var api = this.api();
                    api.rows({
                        page: 'current'
                    }).every(function(rowIdx) {
                        var cell = this.cell(rowIdx, 1).node();
                        $(cell).html(rowIdx + 1);
                    });
                },
                buttons: [{
                        extend: 'excel',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                            format: {
                                body: function(data, row, column, node) {
                                    var tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = data;
                                    var plainText = tempDiv.textContent || tempDiv.innerText || '';

                                    if (column === 9) { // Status column
                                        if (plainText.includes('Active')) return 'Active';
                                        if (plainText.includes('Pending')) return 'Pending';
                                        if (plainText.includes('Cancelled')) return 'Cancelled';
                                        if (plainText.includes('Completed')) return 'Completed';
                                        return 'N/A';
                                    }

                                    // Phone column (index 4) - wrap in ="..." so Excel preserves leading + and formatting when opening CSV
                                    if (column === 4) {
                                        var cleaned = plainText.trim();
                                        // escape any double quotes inside the value
                                        cleaned = cleaned.replace(/"/g, '""');
                                        return '="' + cleaned + '"';
                                    }

                                    return plainText.trim();
                                }
                            }
                        },
                        title: 'Leads Export - ' + new Date().toLocaleDateString()
                    },
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                            format: {
                                body: function(data, row, column, node) {
                                    var tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = data;
                                    var plainText = tempDiv.textContent || tempDiv.innerText || '';

                                    if (column === 9) { // Status column
                                        if (plainText.includes('Active')) return 'Active';
                                        if (plainText.includes('Pending')) return 'Pending';
                                        if (plainText.includes('Cancelled')) return 'Cancelled';
                                        if (plainText.includes('Completed')) return 'Completed';
                                        return 'N/A';
                                    }

                                    // Phone column (index 4) - wrap in ="..." so Excel preserves leading + and formatting when opening CSV
                                    if (column === 4) {
                                        var cleaned = plainText.trim();
                                        // escape any double quotes inside the value
                                        cleaned = cleaned.replace(/"/g, '""');
                                        // prefix with single quote so Excel treats it as text when opening CSV
                                        return "'" + cleaned;
                                    }

                                    return plainText.trim();
                                }
                            }
                        },
                        title: 'Leads Export - ' + new Date().toLocaleDateString()
                    },
                    {
                        extend: 'pdf',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                            format: {
                                body: function(data, row, column, node) {
                                    var tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = data;
                                    var plainText = tempDiv.textContent || tempDiv.innerText || '';

                                    if (column === 9) { // Status column
                                        if (plainText.includes('Active')) return 'Active';
                                        if (plainText.includes('Pending')) return 'Pending';
                                        if (plainText.includes('Cancelled')) return 'Cancelled';
                                        if (plainText.includes('Completed')) return 'Completed';
                                        return 'N/A';
                                    }

                                    return plainText.trim();
                                }
                            }
                        },
                        title: 'Leads Export - ' + new Date().toLocaleDateString(),
                        customize: function(doc) {
                            doc.content[1].table.widths = Array(doc.content[1].table.body[0]
                                .length + 1).join('*').split('');
                            doc.styles.tableHeader.fontSize = 8;
                            doc.defaultStyle.fontSize = 7;
                        }
                    },
                    {
                        extend: 'print',
                        exportOptions: {
                            columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                            format: {
                                body: function(data, row, column, node) {
                                    var tempDiv = document.createElement('div');
                                    tempDiv.innerHTML = data;
                                    var plainText = tempDiv.textContent || tempDiv.innerText || '';

                                    if (column === 9) { // Status column
                                        if (plainText.includes('Active')) return 'Active';
                                        if (plainText.includes('Pending')) return 'Pending';
                                        if (plainText.includes('Cancelled')) return 'Cancelled';
                                        if (plainText.includes('Completed')) return 'Completed';
                                        return 'N/A';
                                    }

                                    return plainText.trim();
                                }
                            }
                        },
                        title: 'Leads Export - ' + new Date().toLocaleDateString()
                    }
                ]
            });

            // Custom export button handlers - connect your buttons to DataTable export functions
            $('.export-excel-btn').on('click', function() {
                // Build export URL with filters and request server-side XLSX
                const filters = {
                    name: $('input[name="name"]').val(),
                    email: $('input[name="email"]').val(),
                    phone: $('input[name="phone"]').val(),
                    representative_user_id: $('select[name="representative_user_id"]').val(),
                    from_date: $('input[name="from_date"]').val(),
                    to_date: $('input[name="to_date"]').val()
                };

                const exportPath = '{{ route('admin.leads.export') }}';
                const params = new URLSearchParams();
                Object.keys(filters).forEach(key => {
                    if (filters[key]) params.append(key, filters[key]);
                });
                const finalUrl = params.toString() ? exportPath + '?' + params.toString() : exportPath;
                // Trigger server-side XLSX download
                window.location.href = finalUrl;
            });

            $('.export-csv-btn').on('click', function() {
                // Build export URL with filters and request server-side CSV (format=csv)
                const filters = {
                    name: $('input[name="name"]').val(),
                    email: $('input[name="email"]').val(),
                    phone: $('input[name="phone"]').val(),
                    representative_user_id: $('select[name="representative_user_id"]').val(),
                    from_date: $('input[name="from_date"]').val(),
                    to_date: $('input[name="to_date"]').val()
                };

                const exportPath = '{{ route('admin.leads.export') }}';
                const params = new URLSearchParams();
                Object.keys(filters).forEach(key => {
                    if (filters[key]) params.append(key, filters[key]);
                });
                params.append('format', 'csv');
                const finalCsvUrl = params.toString() ? exportPath + '?' + params.toString() : exportPath +
                    '?format=csv';
                // Trigger server-side CSV download
                window.location.href = finalCsvUrl;
            });

            $('.export-pdf-btn').on('click', function() {
                leadsTable.button('.buttons-pdf').trigger();
            });

            $('.export-print-btn').on('click', function() {
                leadsTable.button('.buttons-print').trigger();
            });
        });
    </script>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const containers = document.querySelectorAll('.followup-history');

            containers.forEach(container => {
                if (container.scrollHeight > 150) {
                    container.style.height = '150px';
                    container.style.overflowY = 'auto';
                } else {
                    container.style.height = 'auto';
                    container.style.overflowY = 'visible';
                }
            });
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = '{{ route('admin.clients.index') }}';
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
    <!-- Add service & vendor information script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("serviceVendor-container");
            const addBtn = document.getElementById("add-serviceVendor-detail");

            if (!container || !addBtn) return; // guard when element not present on page

            // Function to update numbering and toggle remove button visibility
            function updateDetails() {
                const boxes = container.querySelectorAll(".box");
                boxes.forEach((box, index) => {
                    box.querySelector(".badge").textContent = `#${index + 1}`;
                    const removeBtn = box.querySelector(".remove-serviceVendor-detail");
                    if (index === 0) {
                        removeBtn.classList.add("hidden");
                    } else {
                        removeBtn.classList.remove("hidden");
                    }
                });
            }

            // Add new box
            addBtn.addEventListener("click", function() {
                const firstBox = container.querySelector(".box");
                const newBox = firstBox.cloneNode(true);

                // Clear inputs & selects
                newBox.querySelectorAll("input").forEach(input => input.value = "");
                newBox.querySelectorAll("select").forEach(select => select.selectedIndex = 0);

                container.appendChild(newBox);
                updateDetails();
            });

            // Remove box (event delegation)
            container.addEventListener("click", function(e) {
                if (e.target.closest(".remove-serviceVendor-detail")) {
                    const box = e.target.closest(".box");
                    if (box) {
                        box.remove();
                        updateDetails();
                    }
                }
            });

            // Initial setup
            updateDetails();
        });
    </script>

    <!-- Add extra service & vendor information script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("extraServiceVendor-container");
            const addBtn = document.getElementById("add-extraServiceVendor-detail");

            if (!container || !addBtn) return; // guard when elements not present

            // Add new box
            addBtn.addEventListener("click", function() {
                // Clone first box
                const firstBox = container.querySelector(".box");
                const newBox = firstBox.cloneNode(true);

                // Show close button for new boxes
                const closeBtn = newBox.querySelector(".remove-extraServiceVendor-detail");
                closeBtn.classList.remove("hidden");

                // Update badge number
                const totalBoxes = container.querySelectorAll(".box").length + 1;
                newBox.querySelector(".badge").textContent = "#" + totalBoxes;

                // Clear inputs
                newBox.querySelectorAll("input").forEach(input => input.value = "");
                newBox.querySelectorAll("select").forEach(select => select.selectedIndex = 0);

                container.appendChild(newBox);
            });

            // Remove box (event delegation)
            container.addEventListener("click", function(e) {
                if (e.target.closest(".remove-extraServiceVendor-detail")) {
                    const box = e.target.closest(".box");
                    if (box) box.remove();

                    // Re-number badges after removal
                    container.querySelectorAll(".badge").forEach((badge, index) => {
                        badge.textContent = "#" + (index + 1);
                    });
                }
            });
        });
    </script>

    <!-- Add personal detail information script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('personal-details-container');
            const addBtn = document.getElementById('add-personal-detail');

            if (!container || !addBtn) return;

            addBtn.addEventListener('click', () => {
                // Clone the first box
                const original = container.querySelector('.personal-detail');
                if (!original) return;
                const clone = original.cloneNode(true);

                // Reset form values
                clone.querySelectorAll('input').forEach(input => input.value = '');

                // Update badge number
                const count = container.querySelectorAll('.personal-detail').length + 1;
                const badge = clone.querySelector('.badge');
                if (badge) badge.innerText = `#${count}`;

                // Show the close button
                const removeBtn = clone.querySelector('.remove-personal-detail');
                if (removeBtn) removeBtn.classList.remove('hidden');

                // Append clone
                container.appendChild(clone);
            });

            // Delegate removal
            container.addEventListener('click', function(e) {
                if (e.target.closest('.remove-personal-detail')) {
                    const detailBox = e.target.closest('.personal-detail');
                    if (detailBox && container.contains(detailBox)) container.removeChild(detailBox);

                    // Re-number remaining badges
                    container.querySelectorAll('.badge').forEach((badge, index) => {
                        badge.innerText = `#${index + 1}`;
                    });
                }
            });
        });
    </script>
@endpush
