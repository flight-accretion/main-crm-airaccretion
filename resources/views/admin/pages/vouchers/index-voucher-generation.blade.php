{{--
    Voucher Generation Dashboard
    This page displays leads that have at least one approved payment.
    It allows Operations team to generate vouchers for these leads.
    Only accessible by Admin and Operations roles.
--}}
@extends('admin.layouts.header')
@section('content')
@php
    $tableProductNames = app(\App\Services\LeadTableProducts::class)->forLeads($leads);
    $voucherState = $voucherState ?? 'pending';
    $voucherSummary = $voucherSummary ?? ['pending' => 0, 'within_48h' => 0, 'generated' => 0, 'sent' => 0, 'sent_this_month' => 0];
    $serviceNames = $serviceNames ?? collect();

    // Determine current user's user_type (string) - same rule as the old action column.
    $currentUserType = Auth::user() && Auth::user()->userType ? Auth::user()->userType->user_type : null;
    $canManageVouchers = $currentUserType
        && (in_array($currentUserType, \App\Models\UserType::ADMIN_ROLES) || in_array($currentUserType, \App\Models\UserType::OPERATIONS_ROLES));
    $isSuperAdmin = in_array(optional(auth()->user()->userType)->user_type, [\App\Models\UserType::SUPER_ADMIN]);

    $crmStatusLabels = [0 => 'Initiated', 1 => 'Active', 2 => 'Cancelled', 3 => 'Full Payment Received', 4 => 'Partial Payment Received', 5 => 'Ride Completed', 6 => 'Pending', 7 => 'Rescheduled', 8 => 'Approved', 9 => 'Rejected', 10 => 'Sales Follow-up', 11 => 'Close Follow-up'];
    $within48hLimit = now()->addHours(48);

    // "in 1 day 4 hrs" / "2 days ago" - time left to the service date.
    $countdown = function ($date) {
        $seconds = $date->getTimestamp() - now()->getTimestamp();
        $abs = abs($seconds);
        $days = intdiv($abs, 86400);
        $hours = intdiv($abs % 86400, 3600);
        if ($days === 0 && $hours === 0) {
            return $seconds >= 0 ? 'within the hour' : 'started';
        }
        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }
        if ($hours > 0 && $days < 7) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hr' : 'hrs');
        }
        return $seconds >= 0 ? 'in ' . implode(' ', $parts) : implode(' ', $parts) . ' ago';
    };

    // Rides within 48h first (amber rows); otherwise the existing newest-first order is kept.
    $isUrgentLead = fn ($lead) => $lead->vouchers->isEmpty()
        && $lead->rideSegments->contains(fn ($ride) => !$ride->is_tba && $ride->from_date && $ride->from_date->between(now(), $within48hLimit));
    $queueLeads = $leads->sortBy(fn ($lead) => $isUrgentLead($lead) ? 0 : 1)->values();

    $chipQuery = request()->except(['voucher_state', 'page']);
    $chips = [
        'pending' => 'Pending · ' . $voucherSummary['pending'],
        'generated' => 'Generated · ' . $voucherSummary['generated'],
        'sent' => 'Sent · ' . $voucherSummary['sent'],
        'all' => 'All',
    ];
    $hasActiveFilters = collect(request()->only(['from_date', 'to_date', 'name', 'email', 'phone', 'representative_user_id', 'status', 'service_ids', 'product_ids']))
        ->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vouchers/tokens.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vouchers/voucher-generation.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vouchers/voucher-generation-mobile.css') }}?v=1">

<div class="crm-voucher vg-page">
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

    <header class="vg-heading">
        <div>
            <h1>Voucher Generation</h1>
            <p>Travel vouchers for confirmed bookings — generated after payment approval, sent to the client before the ride</p>
        </div>
    </header>

    <section class="vg-stats" aria-label="Voucher summary">
        <div class="vg-stat acc-brand">
            <strong>{{ $voucherSummary['pending'] }}</strong>
            <div class="vg-label">Pending generation</div>
            <div class="vg-sub2">payment approved, no voucher yet</div>
        </div>
        <div class="vg-stat acc-red">
            <strong>{{ $voucherSummary['within_48h'] }}</strong>
            <div class="vg-label">Ride within 48h</div>
            <div class="vg-sub2">voucher needed before client travels</div>
        </div>
        <div class="vg-stat acc-amber">
            <strong>{{ $voucherSummary['generated'] }}</strong>
            <div class="vg-label">Generated, not sent</div>
            <div class="vg-sub2">ride upcoming — send to client</div>
        </div>
        <div class="vg-stat acc-green">
            <strong>{{ $voucherSummary['sent_this_month'] }} <small>· {{ now()->format('M') }}</small></strong>
            <div class="vg-label">Vouchers sent</div>
            <div class="vg-sub2">{{ $voucherSummary['sent'] }} sent to clients in total</div>
        </div>
    </section>

    <nav class="vg-chips" aria-label="Voucher queue">
        @foreach ($chips as $chipState => $chipLabel)
            <a class="vg-chip {{ $voucherState === $chipState ? 'on' : '' }}"
                href="{{ route('admin.vouchers.index', array_merge($chipQuery, $chipState === 'pending' ? [] : ['voucher_state' => $chipState])) }}"
                @if ($voucherState === $chipState) aria-current="page" @endif>{{ $chipLabel }}</a>
        @endforeach
    </nav>

    <section class="vg-section" aria-labelledby="vg-queue-title">
        <div class="vg-section-head">
            <div class="vg-section-title">
                <h2 id="vg-queue-title">Voucher queue</h2>
                @if (in_array($voucherState, ['pending', 'all'], true))
                    <span class="vg-tag amber">urgent first</span>
                @endif
            </div>
            <div class="vg-tools">
                <label class="vg-search">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <span class="sr-only">Search vouchers</span>
                    <input type="search" id="vg-search" placeholder="Search client, phone, service…" autocomplete="off">
                </label>
                <button type="button" class="vg-ghost {{ $hasActiveFilters ? 'is-active' : '' }}" id="toggle-filters" aria-controls="filter-section">
                    <i class="ri-filter-3-line" aria-hidden="true"></i> Filters
                    <i class="ti ti-chevron-down" id="filter-icon" aria-hidden="true"></i>
                </button>
                <div class="export-buttons vg-export">
                    <button type="button" class="vg-iconbtn export-excel-btn" title="Export to Excel" aria-label="Export to Excel">
                        <i class="ri-file-excel-line"></i>
                    </button>
                    <button type="button" class="vg-iconbtn export-csv-btn" title="Export to CSV" aria-label="Export to CSV">
                        <i class="ri-file-text-line"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="vg-section-meta">Rides within 48h are highlighted and listed first. Countdown shows time left to the service date. Click a row to open the lead.</div>

        {{-- Same GET filters as before; voucher_state keeps the selected chip. --}}
        <div id="filter-section" class="vg-filter-panel">
            <form class="ti-custom-validation view-client-filters vg-filt" method="GET"
                action="{{ route('admin.vouchers.index') }}" id="filter-form" novalidate>
                @if ($voucherState !== 'pending')
                    <input type="hidden" name="voucher_state" value="{{ $voucherState }}">
                @endif
                <div class="vg-fg">
                    <label for="from-date">From service date</label>
                    <input type="date" name="from_date" id="from-date" value="{{ request('from_date') }}">
                </div>
                <div class="vg-fg">
                    <label for="to-date">To service date</label>
                    <input type="date" name="to_date" id="to-date" value="{{ request('to_date') }}">
                </div>
                <div class="vg-fg">
                    <label for="vg-filter-name">Name</label>
                    <input type="text" name="name" id="vg-filter-name" value="{{ request('name') }}">
                </div>
                <div class="vg-fg">
                    <label for="vg-filter-email">Email</label>
                    <input type="text" name="email" id="vg-filter-email" value="{{ request('email') }}">
                </div>
                <div class="vg-fg">
                    <label for="vg-filter-phone">Phone</label>
                    <input type="text" name="phone" id="vg-filter-phone" value="{{ request('phone') }}">
                </div>
                <div class="vg-fg">
                    <label for="vg-filter-staff">Staff</label>
                    <select name="representative_user_id" id="vg-filter-staff" class="js-example-basic-single w-full">
                        <option value="">Select Staff</option>
                        @foreach ($staff as $user)
                            <option value="{{ $user->id }}"
                                {{ request('representative_user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="vg-fg">
                    <label for="status-filter">Status</label>
                    <select name="status" class="js-example-basic-single w-full" id="status-filter">
                        <option value="" {{ (string) request('status') === '' ? 'selected' : '' }}>Select Status</option>
                        @foreach ($statusOptions as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}"
                                {{ (string) request('status') === (string) $statusValue ? 'selected' : '' }}>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="vg-fg">
                    <label for="service-filter">Service</label>
                    <select name="service_ids" class="js-example-basic-single w-full" id="service-filter">
                        <option value="">Select Service</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}"
                                {{ request('service_ids') == $service->id ? 'selected' : '' }}>
                                {{ $service->service }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="vg-fg">
                    <label for="product-filter">Product</label>
                    <select name="product_ids" class="js-example-basic-single w-full" id="product-filter">
                        <option value="">Select Product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                {{ request('product_ids') == $product->id ? 'selected' : '' }}>
                                {{ $product->product }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="vg-filter-actions">
                    <button type="button" class="vg-ghost" onclick="clearFilters()" title="Reset filters">
                        <i class="ri-refresh-line" aria-hidden="true"></i> Reset
                    </button>
                    <button type="submit" class="vg-primary">Apply filters</button>
                </div>
            </form>
        </div>

        <div class="vg-tblwrap">
            <table class="lead-datatable no-horizontal-scroll vg-table" width="100%"
                data-empty-msg="No leads in this voucher queue">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Service date</th>
                        <th>Assigned</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($queueLeads as $enquiry)
                        @php
                            $viewUrl = route('admin.leads.view', $enquiry->id);
                            $firstSegment = $enquiry->rideSegments->first();
                            $lastSegment = $enquiry->rideSegments->last();
                            $voucher = $enquiry->vouchers->sortByDesc('created_at')->first();
                            $isUrgent = $isUrgentLead($enquiry);

                            $productText = $tableProductNames[$enquiry->id] ?? '';
                            $serviceText = collect($enquiry->service_ids_array)
                                ->map(fn ($serviceId) => $serviceNames[$serviceId] ?? null)
                                ->filter()
                                ->unique()
                                ->implode(', ');
                            $routeText = $firstSegment && ($firstSegment->from_place || $firstSegment->to_place)
                                ? ($firstSegment->from_place ?: 'Not set') . ' → ' . ($firstSegment->to_place ?: 'Not set')
                                : '';

                            $status = $enquiry->leadFollowups()->orderBy('created_at', 'desc')->first()->status ?? null;
                            $hasApprovedPayment = in_array($enquiry->id, $leadsWithApprovedPayments ?? []);
                        @endphp
                        <tr class="{{ $isUrgent ? 'blocked' : '' }}" data-view-url="{{ $viewUrl }}">
                            <td data-label="Client">
                                <div class="vg-cell">
                                    <a class="vg-main vg-client" href="{{ $viewUrl }}" target="_blank" rel="noopener"
                                        title="View lead">{{ $enquiry->client->name }}</a>
                                    <div class="vg-cellsub">{{ $enquiry->client->email ?: 'No email' }} · {{ $enquiry->client->contact_number }}</div>
                                </div>
                            </td>
                            <td data-label="Service">
                                <div class="vg-cell">
                                    <div class="vg-wrap">{{ $serviceText ?: ($productText ?: 'N/A') }}</div>
                                    @if (($serviceText && $productText) || $routeText)
                                        <div class="vg-cellsub">{{ collect([$serviceText ? $productText : '', $routeText])->filter()->implode(' · ') }}</div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Service date"
                                data-order="{{ $firstSegment && $firstSegment->from_date ? $firstSegment->from_date->getTimestamp() : 0 }}">
                                <div class="vg-cell">
                                    @if ($firstSegment)
                                        @if ($firstSegment->is_tba)
                                            <div class="vg-main">TBA</div>
                                        @else
                                            <div class="vg-main">
                                                {{ $firstSegment->from_date ? $firstSegment->from_date->format('d M Y') : 'N/A' }}@if ($lastSegment && $lastSegment->to_date && $firstSegment->from_date && !$lastSegment->to_date->isSameDay($firstSegment->from_date)) – {{ $lastSegment->to_date->format('d M Y') }}@endif
                                            </div>
                                            @if ($firstSegment->from_date)
                                                <div class="vg-cellsub">{{ $countdown($firstSegment->from_date) }}</div>
                                            @endif
                                        @endif
                                    @else
                                        <div class="vg-main">N/A</div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Assigned">
                                <div class="vg-cell">
                                    <div class="vg-main">{{ $enquiry->representative ? $enquiry->representative->name : 'N/A' }}</div>
                                    @if ($enquiry->next_followup && $enquiry->next_followup->next_followup_date)
                                        <div class="vg-cellsub">Next follow-up {{ $enquiry->next_followup->next_followup_date->format('d M, H:i') }}</div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Payment">
                                @if ($status === 4)
                                    <span class="vg-tag amber">Part-paid</span>
                                @else
                                    <span class="vg-tag green">Approved</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <div class="vg-status">
                                    @if (!$voucher)
                                        <span class="vg-tag red">Pending</span>
                                    @elseif ($voucher->customer_sent_at)
                                        <span class="vg-tag green" title="Sent {{ $voucher->customer_sent_at->format('d M Y, H:i') }}{{ $voucher->customer_sent_via ? ' via ' . $voucher->customer_sent_via : '' }}">Sent · {{ $voucher->customer_sent_at->format('d M') }}</span>
                                    @else
                                        <span class="vg-tag amber">Generated · not sent</span>
                                    @endif
                                    <small class="vg-cellsub">{{ $crmStatusLabels[$status] ?? 'N/A' }}</small>
                                </div>
                            </td>
                            <td data-label="Action">
                                <div class="vg-actions">
                                    @if ($canManageVouchers)
                                        @if (!$voucher)
                                            @if ($hasApprovedPayment)
                                                <a href="{{ route('admin.vouchers.generate', $enquiry->id) }}"
                                                    class="vg-rowbtn solid" target="_blank" title="Generate Voucher">Generate voucher</a>
                                            @endif
                                        @else
                                            <a href="{{ route('admin.vouchers.form', $enquiry->id) }}" class="vg-rowbtn {{ $voucher->customer_sent_at ? '' : 'solid' }}"
                                                target="_blank" title="Open the voucher page to send it by email / WhatsApp">{{ $voucher->customer_sent_at ? 'Open voucher' : 'Send to client' }}</a>
                                            <a href="{{ route('admin.vouchers.pdf', $voucher->id) }}" class="vg-rowbtn" target="_blank" title="Voucher PDF">PDF</a>
                                        @endif
                                    @endif
                                    <span class="vg-more" aria-label="More lead actions">
                                        <a aria-label="Add Lead Followup" href="{{ route('admin.leads.follow-up.create', $enquiry->id) }}"
                                            class="vg-iconbtn" target="_blank" title="Add Lead Followup"><i class="ri-add-line"></i></a>
                                        <a aria-label="Manage lead" href="{{ route('admin.leads.follow-up.create', $enquiry->id) }}#lead-details"
                                            class="vg-iconbtn" title="Edit Lead"><i class="ri-edit-line"></i></a>
                                        <a aria-label="Cancel Lead" href="javascript:void(0);"
                                            class="vg-iconbtn is-danger cancel-lead" data-lead-id="{{ $enquiry->id }}"
                                            title="Cancel Lead"><i class="ri-close-line"></i></a>
                                        @if ($isSuperAdmin)
                                            <a aria-label="Delete Lead" href="javascript:void(0);"
                                                class="vg-iconbtn is-danger delete-lead" data-lead-id="{{ $enquiry->id }}"
                                                title="Delete Lead"><i class="ri-delete-bin-line"></i></a>
                                        @endif
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
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
            // Initialize DataTable (sorting + paging). Search uses the box in the "Voucher queue" header.
            var leadsTable = $('.lead-datatable').DataTable({
                responsive: false,
                scrollX: false,
                autoWidth: false,
                dom: 'rt<"vg-tfoot"lip>',
                // Keep the server order: rides within 48h first, then newest leads.
                order: [],
                columnDefs: [{
                    orderable: false,
                    targets: [3, 4, 5, 6]
                }],
                language: {
                    emptyTable: $('.lead-datatable').data('empty-msg') || 'No records available',
                    zeroRecords: 'No matching leads found'
                }
            });

            $('#vg-search').on('input search', function() {
                leadsTable.search(this.value).draw();
            });

            // Row click = old "View Lead" button (opens the lead in a new tab).
            // Links, buttons and other controls inside the row keep their own action.
            $('.lead-datatable tbody').on('click', 'tr[data-view-url]', function(e) {
                if ($(e.target).closest('a, button, input, select, textarea, label').length) return;
                if (window.getSelection && String(window.getSelection()).trim() !== '') return;
                window.open($(this).data('view-url'), '_blank', 'noopener');
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
        function clearFilters() {
            $('#filter-form')[0].reset();
            // Back to this page (same queue chip) without filters.
            window.location.href = @json(route('admin.vouchers.index', $voucherState === 'pending' ? [] : ['voucher_state' => $voucherState]));
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Hidden by default; stays open while filters are applied so they are easy to change.
            if (@json($hasActiveFilters)) {
                icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
            } else {
                filterSection.hide();
                icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
            }

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
