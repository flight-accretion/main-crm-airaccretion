@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Add Follow Up</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="{{ route('admin.clients.index') }}">
                    Follow Up
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Add Follow Up
            </li>
        </ol>
    </div>
    <!-- Registration Link Section -->

    <!-- Page Header Close -->
    @if (session('success'))
        <div class="alert alert-success mb-4">
            @if (session('success') === 'image_updated')
                Image updated successfully!
            @else
                {{ session('success') }}
            @endif
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif
    <div class="grid grid-cols-12 text-defaultsize">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Passenger Registration</h5>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-12 col-span-12">
                            <p class="text-sm text-gray-600 mb-3">Generate a registration link to collect passenger details
                                before
                                creating voucher.</p>

                            <div class="flex gap-2 mb-3">
                                <button type="button" id="generate-registration-link-btn"
                                    class="ti-btn ti-btn-secondary ti-btn" data-client-id="{{ $client->id }}">
                                    Generate Registration Link
                                </button>
                                <button type="button" id="copy-registration-link-btn" class="ti-btn ti-btn-info ti-btn"
                                    style="display: none;">
                                    Copy Link
                                </button>
                            </div>

                            <div id="registration-link-container" style="display: none;">
                                <label class="ti-form-label dark:text-defaulttextcolor/70">Registration Link:</label>
                                <div class="flex rounded-sm">
                                    <input type="text" id="registration-link-input"
                                        class="ti-form-input rounded-none rounded-s-sm focus:z-10" readonly>
                                    <button type="button"
                                        class="py-2 px-3 inline-flex flex-shrink-0 justify-center items-center gap-2 rounded-e-sm border border-transparent font-semibold bg-primary text-white hover:bg-primary focus:z-10 focus:outline-none focus:ring-0 focus:ring-primary transition-all text-sm"
                                        onclick="copyToClipboard()">
                                        <i class="ri-file-copy-line"></i> Copy
                                    </button>
                                </div>
                                <small class="text-muted">Share this link with your client to collect passenger details
                                    before
                                    voucher
                                    creation.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header justify-between">
                    <div class="box-title">
                        Client Detail
                    </div>
                    <div class="ms-2">
                        <a aria-label="anchor" href="{{ route('admin.leads.edit', $lead->id) }}"
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-client-btn" title="Edit Lead"><i
                                class="ri-edit-line"></i></a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['name'] }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['email'] }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['phone'] }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date - Place From</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['trip_from'] }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date - Place To</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['trip_to'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header justify-between">
                    <div class="box-title">
                        Services and Extra Services
                    </div>
                </div>
                @php
                    // Compute approved paid amount across all followups for this lead
                    $followupIdsForPending = $followups->pluck('id')->toArray();
                    $approvedPaidSum = 0;
                    if (!empty($followupIdsForPending)) {
                        try {
                            $approvedPaidSum = \App\Models\PaymentAuditTrail::whereIn(
                                'lead_followup_id',
                                $followupIdsForPending,
                            )
                                ->where('payment_status', 1)
                                ->sum('paid_amount');
                        } catch (\Throwable $e) {
                            $approvedPaidSum = 0;
                        }
                    }
                    $lastTotalAmountForPending = isset($lastFollowupTotalAmount) ? (float) $lastFollowupTotalAmount : 0;
                    $pendingAmount = max(0, $lastTotalAmountForPending - $approvedPaidSum);
                @endphp

                <form class="ti-custom-validation" method="POST"
                    action="{{ isset($lead) ? route('admin.leads.follow-up.store', $lead->id) : route('admin.clients.follow-up.store', $client->id) }}"
                    enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <!-- Services Section -->
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
                                <p class="text-gray-800 dark:text-white">{{ $clientInfo['products'] ?? 'N/A' }}</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="services"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                                <select class="ti-form-select rounded-sm !py-2 !px-3" name="services[]" id="services"
                                    multiple>
                                    @php
                                        $selectedServicesOld = old('services', $selectedServices);
                                    @endphp
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}"
                                            {{ in_array($service->id, $selectedServicesOld) ? 'selected' : '' }}>
                                            {{ $service->service }} (₹{{ number_format($service->service_amount, 2) }})
                                        </option>
                                    @endforeach

                                </select>
                                @error('services')
                                    <p class="text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- In your blade view, update the extra services dropdown section -->
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="extra_services" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra
                                    Services</label>
                                <select class="ti-form-select rounded-sm !py-2 !px-3" name="extra_services[]"
                                    id="extra_services" multiple>
                                    @php
                                        $selectedExtraServicesOld = old('extra_services', $selectedExtraServices);
                                    @endphp
                                    @foreach ($allExtraServices as $extraService)
                                        <option value="{{ $extraService->id }}"
                                            {{ in_array($extraService->id, $selectedExtraServicesOld) ? 'selected' : '' }}>
                                            {{ $extraService->extra_service }}
                                            (₹{{ number_format($extraService->extra_service_amount, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('extra_services')
                                    <p class="text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
            </div>
        </div>
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header justify-between">
                    <div class="box-title">
                        Add Follow Up
                    </div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="notes" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" required maxlength="1000"
                                title="Please enter letters and numbers only; at least one letter is required">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="status" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status<span
                                    class="text-danger">*</span></label>
                            <select class="ti-form-select rounded-sm form-control-sm" name="status" id="status"
                                required>
                                <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="2" {{ old('status') == '2' ? 'selected' : '' }}>Cancelled</option>
                                <option value="3" {{ old('status') == '3' ? 'selected' : '' }}>Full payment received
                                </option>
                                <option value="4" {{ old('status') == '4' ? 'selected' : '' }}>Partial payment
                                    received</option>
                            </select>
                            @error('status')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Service Breakdown Table - Shows when payment status selected -->
                        <div class="xl:col-span-12 col-span-12" id="service_breakdown_container" style="display: none;">
                            <div class="box !bg-gray-50 dark:!bg-gray-800 p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h6 class="text-[.9375rem] font-semibold mb-0">
                                        <i class="ri-list-check-2 text-primary"></i> Service & Discount Breakdown
                                    </h6>
                                    <button type="button" id="edit_total_btn" class="ti-btn ti-btn-warning">
                                        <i class="ri-edit-line"></i> Edit Total Amount
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="service_breakdown_table">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th>Service / Extra Service</th>
                                                <th class="text-end">Original Amount</th>
                                                <th class="text-end" style="width: 180px;">Discount Amount</th>
                                                <th class="text-end">Final Amount</th>
                                                <th class="text-center">Remove</th>
                                            </tr>
                                        </thead>
                                        <tbody id="service_breakdown_body">
                                            <!-- Will be populated by JavaScript -->
                                        </tbody>
                                        <tfoot class="bg-light">
                                            <tr>
                                                <th>Total</th>
                                                <th class="text-end" id="total_original_amount">₹0.00</th>
                                                <th class="text-end" id="total_discount_amount">₹0.00</th>
                                                <th class="text-end">
                                                    <span id="total_final_amount_display">₹0.00</span>
                                                    <input type="number" step="0.01" min="0"
                                                        class="form-control form-control-sm d-none"
                                                        id="total_final_amount_edit" style="display:none"
                                                        placeholder="Enter total">
                                                </th>
                                                <th class="text-center">
                                                    <button type="button" id="save_total_btn"
                                                        class="btn btn-sm btn-success d-none" style="display:none">
                                                        <i class="ri-save-line"></i>
                                                    </button>
                                                    <button type="button" id="cancel_total_btn"
                                                        class="btn btn-sm btn-secondary d-none" style="display:none">
                                                        <i class="ri-close-line"></i>
                                                    </button>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    <i class="ri-information-line"></i>
                                    Enter discount for each service individually. Click "Edit Total Amount" to manually
                                    adjust the final total. Remove services by clicking the remove button.
                                </small>
                            </div>
                        </div>

                        <!-- Hidden fields to store calculated values -->
                        <input type="hidden" id="service_amount" name="service_amount" value="">
                        <input type="hidden" id="discount_amount" name="discount_amount" value="">
                        <input type="hidden" id="total_amount" name="total_amount" value="">
                        <input type="hidden" id="service_details" name="service_details" value="">

                        <!-- Payment Fields - Shown only for payment-related statuses -->
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12"
                            id="received_amount_field" style="display: none;">
                            <label for="received_amount" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Received
                                Amount<span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control rounded-sm form-control-sm"
                                id="received_amount" name="received_amount" value="{{ old('received_amount') }}">
                            @error('received_amount')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12"
                            id="payment_method_field" style="display: none;">
                            <label for="payment_method" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Payment
                                Method<span class="text-danger">*</span></label>
                            <select class="ti-form-select rounded-sm form-control-sm" name="payment_method"
                                id="payment_method">
                                <option value="" {{ old('payment_method') === '' ? 'selected' : '' }}>Select Payment
                                    Method</option>
                                <option value="Cash" {{ old('payment_method') == 'Cash' ? 'selected' : '' }}>Cash
                                </option>
                                <option value="Bank Transfer"
                                    {{ old('payment_method') == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="Online Payment"
                                    {{ old('payment_method') == 'Online Payment' ? 'selected' : '' }}>Online Payment
                                </option>
                                <option value="UPI" {{ old('payment_method') == 'UPI' ? 'selected' : '' }}>UPI</option>
                                <option value="Credit Card"
                                    {{ old('payment_method') == 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                <option value="Debit Card" {{ old('payment_method') == 'Debit Card' ? 'selected' : '' }}>
                                    Debit Card</option>
                                <option value="Cheque" {{ old('payment_method') == 'Cheque' ? 'selected' : '' }}>Cheque
                                </option>
                                <option value="Other" {{ old('payment_method') == 'Other' ? 'selected' : '' }}>Other
                                </option>
                                <option value="Net Banking"
                                    {{ old('payment_method') == 'Net Banking' ? 'selected' : '' }}>Net Banking</option>
                                <option value="Website" {{ old('payment_method') == 'Website' ? 'selected' : '' }}>Website
                                </option>
                                <option value="Acepoint Point Redeem"
                                    {{ old('payment_method') == 'Acepoint Point Redeem' ? 'selected' : '' }}>Acepoint Point
                                    Redeem</option>
                                <option value="Paid Directly to Vendor"
                                    {{ old('payment_method') == 'Paid Directly to Vendor' ? 'selected' : '' }}>Paid
                                    Directly to Vendor</option>
                            </select>
                            @error('payment_method')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Acepoint Points Section - Shown only when 'Acepoint Point Redeem' is selected -->
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12"
                            id="acepoint_points_field" style="display: none;">
                            <label for="redeem_points" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Points to
                                Redeem<span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0"
                                class="form-control rounded-sm form-control-sm" id="redeem_points" name="redeem_points"
                                value="{{ old('redeem_points') }}" placeholder="Enter points to redeem">
                            <small class="text-muted" id="available_points_display" style="display: none;">
                                <i class="ri-information-line"></i> Available Points: <span
                                    id="available_points_value">0</span>
                            </small>
                            <small class="text-muted" id="points_loading" style="display: none;">
                                <i class="ri-loader-4-line"></i> Checking available points...
                            </small>
                            @error('redeem_points')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                            <p id="points_error" class="text-danger mt-1" style="display: none;"></p>
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12"
                            id="paid_date_field" style="display: none;">
                            <label for="paid_date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Paid Date<span
                                    class="text-danger">*</span></label>
                            <input type="date" class="ti-form-input rounded-sm form-control-sm" id="paid_date"
                                name="paid_date" value="{{ old('paid_date', now()->format('Y-m-d')) }}">
                            @error('paid_date')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Pending Amount (non-editable) - shown for payment statuses -->
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12"
                            id="pending_amount_container" style="display: none;">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Pending Amount</label>
                            <input type="text" readonly id="pending_amount_display"
                                class="form-control rounded-sm form-control-sm cursor-not-allowed"
                                value="₹{{ number_format($pendingAmount, 2) }}" style="background-color: lightgray;">
                            <input type="hidden" id="pending_amount_value"
                                value="{{ number_format($pendingAmount, 2, '.', '') }}">
                        </div>

                        <div id="next_followup_field"
                            class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="next_followup_date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Next
                                Follow Up</label>
                            <input type="datetime-local" class="ti-form-input rounded-sm form-control-sm"
                                id="next_followup_date" name="next_followup_date"
                                value="{{ old('next_followup_date', now()->format('Y-m-d H:i')) }}" required>

                            @error('next_followup_date')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="image" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Upload Receipt
                            </label>
                            <div>
                                <input type="file" name="image" id="image" accept=".pdf,.jpg,.jpeg,.png"
                                    capture="environment"
                                    class="block w-full border border-gray-200 focus:shadow-sm dark:focus:shadow-white/10 rounded-sm text-sm focus:z-10 focus:outline-0 focus:border-gray-200 dark:focus:border-white/10 dark:border-white/10
                                                        file:border-0
                                                        file:bg-gray-200 file:me-4
                                                        file:py-2 file:px-4
                                                        dark:file:bg-black/20 dark:file:text-white/50">
                                <small class="form-text text-muted" id="image-help">
                                    Only PDF, JPG, or PNG formats are allowed. File size must not exceed 2MB.
                                </small>
                                <div id="image-preview" class="mt-2"></div>
                                <p id="image-error" class="text-danger mt-1"></p>
                            </div>
                            @error('image')
                                <p class="text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>



                <div class="box-footer">
                    <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                </div>
                </form>
            </div>
        </div>
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between">
                    <div class="box-title">
                        Follow-up History
                    </div>
                </div>
                <div class="box-body">
                    <div class="tab-content">
                        <div id="mon-1" role="tabpanel" aria-labelledby="mon-1">
                            <ul class="list-unstyled mb-0 upcoming-events-list">
                                @forelse($followups as $followup)
                                    <li>
                                        @php
                                            // Determine latest audit entry for this followup (use loaded relation if available)
                                            $latestAudit = null;
                                            if (
                                                isset($followup->paymentAuditTrail) &&
                                                $followup->paymentAuditTrail instanceof \Illuminate\Support\Collection
                                            ) {
                                                $latestAudit = $followup->paymentAuditTrail
                                                    ->sortByDesc('created_at')
                                                    ->first();
                                            } else {
                                                try {
                                                    $latestAudit = $followup->paymentAuditTrail()->latest()->first();
                                                } catch (\Throwable $e) {
                                                    $latestAudit = null;
                                                }
                                            }

                                            // Detect if any rejected audit exists (2 == rejected)
                                            $hasRejectedAudit = false;
                                            if (
                                                isset($followup->paymentAuditTrail) &&
                                                $followup->paymentAuditTrail instanceof \Illuminate\Support\Collection
                                            ) {
                                                $hasRejectedAudit = $followup->paymentAuditTrail->contains(function (
                                                    $a,
                                                ) {
                                                    return isset($a->payment_status) && $a->payment_status == 2;
                                                });
                                            } else {
                                                try {
                                                    $hasRejectedAudit = (bool) $followup
                                                        ->paymentAuditTrail()
                                                        ->where('payment_status', 2)
                                                        ->exists();
                                                } catch (\Throwable $e) {
                                                    $hasRejectedAudit = false;
                                                }
                                            }

                                            // Lock by audit when latest audit is approved(1) or rejected(2), or any rejected audit exists
                                            $isLockedByAudit =
                                                ($latestAudit && in_array($latestAudit->payment_status, [1, 2])) ||
                                                $hasRejectedAudit;
                                        @endphp
                                        <div class="grid grid-cols-12 gap-3">
                                            <div class="xl:col-span-12 col-span-12">
                                                <div class="md:flex block items-start justify-between">
                                                    <p class="mb-0 text-[.875rem]">Note : {{ $followup->followup_note }}
                                                    </p>
                                                    <div>
                                                        <span class="text-[#8c9097] dark:text-white/50">
                                                            <i class="ri-time-line align-middle me-1 inline-block"></i>
                                                            Created At: {{ $followup->created_at->format('Y-m-d H:i:s') }}
                                                            @if ($followup->file && $followup->updated_at->gt($followup->created_at))
                                                                <br>
                                                                <i class="ri-time-line align-middle me-1 inline-block"></i>
                                                                Updated At:
                                                                {{ $followup->updated_at->format('Y-m-d H:i:s') }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="xl:col-span-12 col-span-12">
                                                <p class="mb-0 text-[#8c9097] dark:text-white/50">
                                                    Created By: {{ $followup->followedBy->name ?? 'System' }}</p>
                                            </div>
                                            @if ($followup->file)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <div class="grid grid-cols-12 gap-3">
                                                        <div class="xl:col-span-2 col-span-12">
                                                            <a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}" target="_blank"
                                                                class="me-2 text-primary">
                                                                <i class="ri-image-line"></i> View Image
                                                            </a>
                                                        </div>
                                                        @php
                                                            // Determine latest audit entry for this followup (use loaded relation if available)
                                                            $latestAudit = null;
                                                            if (
                                                                isset($followup->paymentAuditTrail) &&
                                                                $followup->paymentAuditTrail instanceof
                                                                    \Illuminate\Support\Collection
                                                            ) {
                                                                $latestAudit = $followup->paymentAuditTrail
                                                                    ->sortByDesc('created_at')
                                                                    ->first();
                                                            } else {
                                                                $latestAudit = $followup
                                                                    ->paymentAuditTrail()
                                                                    ->latest()
                                                                    ->first();
                                                            }

                                                            // Detect if any rejected audit exists (2 == rejected)
                                                            $hasRejectedAudit = false;
                                                            if (
                                                                isset($followup->paymentAuditTrail) &&
                                                                $followup->paymentAuditTrail instanceof
                                                                    \Illuminate\Support\Collection
                                                            ) {
                                                                $hasRejectedAudit = $followup->paymentAuditTrail->contains(
                                                                    function ($a) {
                                                                        return isset($a->payment_status) &&
                                                                            $a->payment_status == 2;
                                                                    },
                                                                );
                                                            } else {
                                                                try {
                                                                    $hasRejectedAudit = (bool) $followup
                                                                        ->paymentAuditTrail()
                                                                        ->where('payment_status', 2)
                                                                        ->exists();
                                                                } catch (\Throwable $e) {
                                                                    $hasRejectedAudit = false;
                                                                }
                                                            }

                                                            // Lock by audit when latest audit is approved(1) or rejected(2), or any rejected audit exists
                                                            $isLockedByAudit =
                                                                ($latestAudit &&
                                                                    in_array($latestAudit->payment_status, [1, 2])) ||
                                                                $hasRejectedAudit;
                                                        @endphp

                                                        {{-- Show Edit button only when followup is not locked by audit and status is not approved/rejected --}}
                                                        @if (!$isLockedByAudit && !in_array($followup->status, [8, 9]))
                                                            <div class="xl:col-span-2 col-span-12">
                                                                <button type="button"
                                                                    class="text-sm text-warning edit-image-btn"
                                                                    data-followup-id="{{ $followup->id }}"
                                                                    data-current-image="{{ $followup->file }}">
                                                                    <i class="ri-edit-line"></i> Edit Image
                                                                </button>
                                                            </div>
                                                        @else
                                                            <div class="xl:col-span-4 col-span-12">
                                                                <span class="text-sm text-muted"><i
                                                                        class="ri-lock-2-line"></i> Image locked after
                                                                    approval/rejection</span>
                                                            </div>
                                                        @endif
                                                        @if (auth()->user() && auth()->user()->isSuperAdmin() && in_array($followup->status, [3, 4]))
                                                            <div class="xl:col-span-2 col-span-12">
                                                                <button type="button"
                                                                    class="text-sm text-danger delete-followup-btn"
                                                                    data-delete-url="{{ route('admin.followups.destroy', $followup->id) }}">
                                                                    <i class="ri-delete-bin-5-line"></i> Delete Followup
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif


                                            @if ($followup->total_amount || $followup->received_amount || $followup->service_amount || $followup->discount_amount)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <div class="grid grid-cols-12 gap-3">
                                                        @if ($followup->service_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3  col-span-12">
                                                                <span class="text-info">Service Amount:
                                                                    ₹{{ number_format($followup->service_amount, 2) }}</span>
                                                            </div>
                                                        @endif

                                                        @if ($followup->discount_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3  col-span-12">
                                                                <span class="text-success">Discount:
                                                                    ₹{{ number_format($followup->discount_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                        @if ($followup->total_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3  col-span-12">
                                                                <span class="text-primary">Total Amount:
                                                                    ₹{{ number_format($followup->total_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                        @php
                                                            // New status-only followups (e.g. completed/cancelled)
                                                            // may store received_amount as 0, while actual paid amount
                                                            // lives in approved payment audit trail entries.
                                                            $displayReceivedAmount = (float) ($followup->received_amount ?? 0);
                                                            if ($loop->first && $displayReceivedAmount <= 0 && (float) ($approvedPaidSum ?? 0) > 0) {
                                                                $displayReceivedAmount = (float) $approvedPaidSum;
                                                            }
                                                        @endphp
                                                        @if ($displayReceivedAmount > 0)
                                                            <div class="xxl:col-span-3 xl:col-span-3  col-span-12">
                                                                <span class="text-success">Received:
                                                                    ₹{{ number_format($displayReceivedAmount, 2) }}</span>
                                                            </div>
                                                        @endif


                                                        {{-- Show pending amount for the latest followup --}}
                                                        @if ($loop->first)
                                                            <div class="xxl:col-span-3 xl:col-span-3  col-span-12">
                                                                <span class="text-danger">Pending:
                                                                    ₹{{ number_format($pendingAmount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if ($followup->service_details)
                                                    @php
                                                        $serviceDetails = is_string($followup->service_details)
                                                            ? json_decode($followup->service_details, true)
                                                            : $followup->service_details;
                                                    @endphp
                                                    @if (is_array($serviceDetails) && count($serviceDetails) > 0)
                                                        <div class="xl:col-span-12 col-span-12">
                                                            <small class="text-muted d-block mb-1">
                                                                <i class="ri-list-check text-primary"></i> Service
                                                                Breakdown:
                                                            </small>
                                                            <div class="ms-3">
                                                                @foreach ($serviceDetails as $detail)
                                                                    <small class="d-block text-[.8rem]">
                                                                        • {{ $detail['name'] ?? 'N/A' }}:
                                                                        ₹{{ number_format($detail['original_amount'] ?? 0, 2) }}
                                                                        @if (isset($detail['discount_amount']) && $detail['discount_amount'] > 0)
                                                                            <span class="text-success">
                                                                                -
                                                                                ₹{{ number_format($detail['discount_amount'], 2) }}
                                                                            </span>
                                                                        @endif
                                                                        =
                                                                        <strong>₹{{ number_format(($detail['original_amount'] ?? 0) - ($detail['discount_amount'] ?? 0), 2) }}</strong>
                                                                    </small>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif
                                            @endif
                                            @if ($followup->payment_method || $followup->paid_date)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <div class="grid grid-cols-12 gap-3">
                                                        @if ($followup->payment_method)
                                                            <div class="xl:col-span-3 col-span-12">
                                                                <span class="text-info">Payment Method:
                                                                    {{ ucfirst($followup->payment_method) }}</span>
                                                            </div>
                                                        @endif
                                                        @if ($followup->paid_date)
                                                            <div class="xl:col-span-3 col-span-12">
                                                                <span class="text-warning">Paid Date:
                                                                    {{ $followup->paid_date->format('d-m-Y') }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="xl:col-span-12 col-span-12">
                                                <span class="badge bg-primary/10 text-primary">
                                                    Status:
                                                    @if ($followup->status === 0)
                                                        Initiated
                                                    @elseif($followup->status === 1)
                                                        Active
                                                    @elseif($followup->status === 2)
                                                        Cancelled
                                                    @elseif($followup->status === 3)
                                                        Full payment received
                                                    @elseif($followup->status === 4)
                                                        Partial payment received
                                                    @elseif($followup->status === 5)
                                                        Completed
                                                    @elseif($followup->status === 6)
                                                        Pending
                                                    @elseif($followup->status === 7)
                                                        Rescheduled
                                                    @elseif($followup->status === 8)
                                                        Approved
                                                    @elseif($followup->status === 9)
                                                        Rejected
                                                    @else
                                                        N/A
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Edit Image Modal (Hidden by default) -->
                                        @if (!$isLockedByAudit && !in_array($followup->status, [8, 9]))
                                            <div id="edit-image-form-{{ $followup->id }}"
                                                class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
                                                <div
                                                    class="bg-white dark:bg-black/90 rounded-lg shadow-lg p-6 w-full max-w-md relative">
                                                    <button type="button"
                                                        class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:text-white/50 dark:hover:text-white/80 cancel-edit-image"
                                                        data-followup-id="{{ $followup->id }}" title="Close">
                                                        <i class="bi bi-x-lg text-xl"></i>
                                                    </button>
                                                    <h4 class="text-lg font-semibold mb-4 text-center text-theme">Edit
                                                        Payment
                                                        Image</h4>
                                                    @if ($followup->file)
                                                        <div class="mb-4 text-center">
                                                            <img src="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}"
                                                                alt="Current Image"
                                                                class="h-24 w-auto rounded border mx-auto shadow">
                                                            <div class="text-xs text-gray-500 mt-1">Current Image</div>
                                                        </div>
                                                    @endif
                                                    <form class="update-image-form" method="POST"
                                                        action="{{ route('admin.followups.update-image', $followup->id) }}"
                                                        enctype="multipart/form-data">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="mb-4">
                                                            <label
                                                                class="block text-sm font-medium mb-2 text-gray-700 dark:text-white">Choose
                                                                new image</label>
                                                            <input type="file" name="image"
                                                                class="block w-full border border-gray-300 focus:shadow-sm rounded text-sm file:border-0 file:bg-gray-200 file:py-2 file:px-4 dark:file:bg-black/20 dark:file:text-white/50"
                                                                required>
                                                            <small class="form-text text-muted">
                                                                Allowed formats: JPG, PNG, PDF. Max size: 2MB
                                                            </small>

                                                        </div>
                                                        <div class="flex justify-end gap-4 mt-2">
                                                            <button type="submit"
                                                                class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Update</button>
                                                            <button type="button"
                                                                class="ti-btn ti-btn-secondary-full ti-custom-validate-btn cancel-edit-image"
                                                                data-followup-id="{{ $followup->id }}">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @empty
                                    <li>
                                        <div class="text-center text-gray-500 py-4">
                                            No follow-up history found
                                        </div>
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Delete Followup Modal (moved here from shared partial) -->
    <div id="delete-followup-modal" class="hs-overlay hidden ti-modal">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
            <div class="ti-modal-content w-full max-w-md mx-auto">
                <div class="ti-modal-body p-6 text-center">
                    <div class="mb-4">
                        <div class="w-16 h-16 mx-auto mb-4 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M6.938 20h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Delete</h3>
                    <p class="text-gray-600 mb-1">Are you sure you want to delete this followup (payment entry)? This will
                        remove the followup and create an audit record.</p>
                    <div class="text-start mt-3">
                        <label class="form-label">Reason (optional)</label>
                        <textarea id="delete-followup-reason" name="deletion_reason" class="form-control" rows="3"
                            placeholder="Mistaken upload / wrong lead etc (optional)"></textarea>
                    </div>
                </div>
                <div class="ti-modal-footer justify-center space-x-3">
                    <button type="button" class="ti-btn bg-gray-500 text-white"
                        onclick="closeDeleteFollowupModal()">Cancel</button>
                    <button type="button" class="ti-btn bg-danger text-white"
                        id="confirm-delete-followup-btn">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        const lastFollowupTotalAmount = @json($lastFollowupTotalAmount);
        const lastFollowupServiceAmount = @json($lastFollowupServiceAmount);
        const lastFollowupDiscountAmount = @json($lastFollowupDiscountAmount);
        const lastFollowupServiceDetails = @json($lastFollowupServiceDetails ?? []);
        const statusSelect = document.getElementById('status');
        const serviceBreakdownContainer = document.getElementById('service_breakdown_container');
        const serviceBreakdownBody = document.getElementById('service_breakdown_body');
        const serviceAmountInput = document.getElementById('service_amount');
        const discountAmountInput = document.getElementById('discount_amount');
        const totalAmountInput = document.getElementById('total_amount');
        const serviceDetailsInput = document.getElementById('service_details');
        const totalFinalAmountDisplay = document.getElementById('total_final_amount_display');
        const totalFinalAmountEdit = document.getElementById('total_final_amount_edit');
        const editTotalBtn = document.getElementById('edit_total_btn');
        const saveTotalBtn = document.getElementById('save_total_btn');
        const cancelTotalBtn = document.getElementById('cancel_total_btn');
        const receivedAmountField = document.getElementById('received_amount_field');
        const receivedAmountInput = document.getElementById('received_amount');
        const paymentMethodField = document.getElementById('payment_method_field');
        const paymentMethodInput = document.getElementById('payment_method');
        const paidDateField = document.getElementById('paid_date_field');
        const paidDateInput = document.getElementById('paid_date');
        const pendingAmountContainer = document.getElementById('pending_amount_container');
        const pendingAmountDisplay = document.getElementById('pending_amount_display');
        const pendingAmountValueHidden = document.getElementById('pending_amount_value');

        // Service and extra service pricing data
        const servicePrices = @json($servicePrices);
        const extraServicePrices = @json($extraServicePrices);
        const servicesData = @json($services);
        const extraServicesData = @json($allExtraServices);
        // Approved paid sum and pending amount (server-side computed)
        const approvedPaidSum = @json($approvedPaidSum ?? 0);
        const initialPendingAmount = @json($pendingAmount ?? 0);
        // ids recorded on last followup (if any)
        let lastFollowupServiceIds = @json($lastFollowupServiceIds ?? []);
        let lastFollowupExtraServiceIds = @json($lastFollowupExtraServiceIds ?? []);

        // Debug: Log the loaded service details
        console.log('Loaded lastFollowupServiceDetails:', lastFollowupServiceDetails);

        // // Debug log
        // console.log('Follow-up initialization:', {
        //     lastFollowupTotalAmount: lastFollowupTotalAmount,
        //     lastFollowupServiceIds: lastFollowupServiceIds,
        //     lastFollowupExtraServiceIds: lastFollowupExtraServiceIds,
        //     servicePrices: servicePrices,
        //     extraServicePrices: extraServicePrices
        // });

        // Track previous service & extra-service selections for comparison
        let previousServiceSelections = [];
        let previousExtraSelections = [];
        // Flag to indicate first load
        let isInitialLoad = true;
        // Flag to indicate user manually edited the total (prevents auto-overwrite)
        let isManualTotalEdited = false;
        // Store service breakdown data
        let serviceBreakdownData = {};

        // NEW: Build service breakdown table
        function buildServiceBreakdownTable() {
            const servicesSelect = document.getElementById('services');
            const extraServicesSelect = document.getElementById('extra_services');

            const selectedServices = Array.from(servicesSelect.selectedOptions).map(opt => opt.value);
            const selectedExtraServices = Array.from(extraServicesSelect.selectedOptions).map(opt => opt.value);

            // Store current breakdown data before clearing (to preserve discounts during rebuild)
            const previousBreakdownData = {
                ...serviceBreakdownData
            };

            // Clear existing breakdown
            serviceBreakdownBody.innerHTML = '';
            serviceBreakdownData = {};

            // Add regular services
            selectedServices.forEach(serviceId => {
                const service = servicesData.find(s => s.id == serviceId);
                if (service) {
                    const originalAmount = parseFloat(servicePrices[serviceId] || 0);

                    // Check if we have previous values for this service (original amount or discount)
                    let previousDiscount = 0;
                    let previousOriginal = null;

                    const currentKey = `service_${serviceId}`;
                    if (previousBreakdownData[currentKey]) {
                        previousDiscount = parseFloat(previousBreakdownData[currentKey].discount_amount || 0);
                        // allow previousBreakdown to override original amount (for editable zero-amount cases)
                        if (previousBreakdownData[currentKey].original_amount !== undefined &&
                            previousBreakdownData[currentKey].original_amount !== null) {
                            previousOriginal = parseFloat(previousBreakdownData[currentKey].original_amount) || 0;
                        }
                    } else if (lastFollowupServiceDetails && lastFollowupServiceDetails.length > 0) {
                        const prevDetail = lastFollowupServiceDetails.find(d => d.id == serviceId && d.type ===
                            'service');
                        if (prevDetail) {
                            previousDiscount = parseFloat(prevDetail.discount_amount || 0);
                            if (prevDetail.original_amount !== undefined && prevDetail.original_amount !== null) {
                                previousOriginal = parseFloat(prevDetail.original_amount) || 0;
                            }
                        }
                    }

                    serviceBreakdownData[`service_${serviceId}`] = {
                        id: serviceId,
                        type: 'service',
                        name: service.service,
                        original_amount: previousOriginal !== null ? previousOriginal : originalAmount,
                        discount_amount: previousDiscount
                    };
                }
            });

            // Add extra services
            selectedExtraServices.forEach(extraServiceId => {
                const extraService = extraServicesData.find(es => es.id == extraServiceId);
                if (extraService) {
                    const originalAmount = parseFloat(extraServicePrices[extraServiceId] || 0);

                    // Check if we have previous values for this extra service
                    let previousDiscount = 0;
                    let previousOriginal = null;
                    const currentKey = `extra_${extraServiceId}`;
                    if (previousBreakdownData[currentKey]) {
                        previousDiscount = parseFloat(previousBreakdownData[currentKey].discount_amount || 0);
                        if (previousBreakdownData[currentKey].original_amount !== undefined &&
                            previousBreakdownData[currentKey].original_amount !== null) {
                            previousOriginal = parseFloat(previousBreakdownData[currentKey].original_amount) || 0;
                        }
                    } else if (lastFollowupServiceDetails && lastFollowupServiceDetails.length > 0) {
                        const prevDetail = lastFollowupServiceDetails.find(d => d.id == extraServiceId && d.type ===
                            'extra_service');
                        if (prevDetail) {
                            previousDiscount = parseFloat(prevDetail.discount_amount || 0);
                            if (prevDetail.original_amount !== undefined && prevDetail.original_amount !== null) {
                                previousOriginal = parseFloat(prevDetail.original_amount) || 0;
                            }
                        }
                    }

                    serviceBreakdownData[`extra_${extraServiceId}`] = {
                        id: extraServiceId,
                        type: 'extra_service',
                        name: extraService.extra_service,
                        original_amount: previousOriginal !== null ? previousOriginal : originalAmount,
                        discount_amount: previousDiscount
                    };
                }
            });

            // Render table rows
            Object.keys(serviceBreakdownData).forEach(key => {
                const item = serviceBreakdownData[key];
                const finalAmount = item.original_amount - item.discount_amount;

                const row = document.createElement('tr');
                row.setAttribute('data-key', key);

                // If original amount is zero, render an editable original amount input
                const originalCellHtml = (item.original_amount === 0) ?
                    `<input type="number" step="0.01" min="0" class="form-control form-control-sm original-input text-end" data-key="${key}" value="${item.original_amount.toFixed(2)}">` :
                    `₹${item.original_amount.toFixed(2)}`;

                row.innerHTML = `
                    <td>${item.name}</td>
                    <td class="text-end">${originalCellHtml}</td>
                        <td class="text-end discount-amount">₹${item.discount_amount.toFixed(2)}</td>
                        <td class="text-end">
                            <input type="number" step="0.01" min="0"
                                class="form-control form-control-sm final-input text-end"
                                data-key="${key}"
                                value="${finalAmount.toFixed(2)}"
                                placeholder="Final">
                        </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger remove-service-btn" 
                            data-key="${key}" data-type="${item.type}" data-id="${item.id}">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </td>
                `;
                serviceBreakdownBody.appendChild(row);
            });

            // Update totals
            updateBreakdownTotals();

            // Add event listeners for final inputs (user edits final -> discount is auto-calculated)
            document.querySelectorAll('.final-input').forEach(input => {
                input.addEventListener('input', function() {
                    // Any change to final should cancel manual override
                    isManualTotalEdited = false;
                    const key = this.getAttribute('data-key');
                    let finalVal = parseFloat(this.value);
                    if (isNaN(finalVal)) finalVal = 0;
                    const originalAmount = serviceBreakdownData[key].original_amount;

                    // Ensure final amount is not negative
                    if (finalVal < 0) {
                        finalVal = 0;
                        this.value = '0.00';
                    }

                    // Compute discount = original - final
                    const discount = parseFloat((originalAmount - finalVal).toFixed(2));
                    serviceBreakdownData[key].discount_amount = discount;

                    // Update discount cell text
                    const row = this.closest('tr');
                    const discountCell = row.querySelector('.discount-amount');
                    if (discountCell) discountCell.textContent = `₹${discount.toFixed(2)}`;

                    // Update totals
                    updateBreakdownTotals();
                });
            });

            // Add event listeners for original amount inputs (editable when original is 0)
            document.querySelectorAll('.original-input').forEach(input => {
                input.addEventListener('input', function() {
                    // Changing original amount should cancel manual override
                    isManualTotalEdited = false;
                    const key = this.getAttribute('data-key');
                    let newOriginal = parseFloat(this.value) || 0;

                    if (newOriginal < 0) {
                        newOriginal = 0;
                        this.value = '0.00';
                    }

                    // Update the data model
                    if (serviceBreakdownData[key]) {
                        serviceBreakdownData[key].original_amount = newOriginal;

                        // Ensure discount does not exceed new original
                        if (serviceBreakdownData[key].discount_amount > newOriginal) {
                            serviceBreakdownData[key].discount_amount = newOriginal;
                        }

                        // Update final input value = original - discount
                        const row = this.closest('tr');
                        const finalVal = parseFloat((serviceBreakdownData[key].original_amount -
                            serviceBreakdownData[key].discount_amount).toFixed(2));
                        const finalInput = row.querySelector('.final-input');
                        if (finalInput) finalInput.value = finalVal.toFixed(2);

                        // Update discount cell text
                        const discountCell = row.querySelector('.discount-amount');
                        if (discountCell) discountCell.textContent =
                            `₹${serviceBreakdownData[key].discount_amount.toFixed(2)}`;

                        // Update totals
                        updateBreakdownTotals();
                    }
                });
            });

            // Add event listeners for remove buttons
            document.querySelectorAll('.remove-service-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Removing a service should cancel manual override
                    isManualTotalEdited = false;
                    const key = this.getAttribute('data-key');
                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');

                    // Remove from breakdown data
                    delete serviceBreakdownData[key];

                    // Remove from dropdown selection
                    if (type === 'service') {
                        const servicesSelect = document.getElementById('services');
                        const option = servicesSelect.querySelector(`option[value="${id}"]`);
                        if (option) option.selected = false;
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $('#services').trigger('change.select2');
                        }
                    } else if (type === 'extra_service') {
                        const extraServicesSelect = document.getElementById('extra_services');
                        const option = extraServicesSelect.querySelector(`option[value="${id}"]`);
                        if (option) option.selected = false;
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $('#extra_services').trigger('change.select2');
                        }
                    }

                    // Remove row from table
                    this.closest('tr').remove();

                    // Update totals
                    updateBreakdownTotals();
                });
            });
        }

        // Update breakdown totals and hidden fields
        // Update the pending amount display based on current total and approved payments
        function updatePendingDisplay() {
            try {
                if (!pendingAmountDisplay || !pendingAmountValueHidden) return;

                // Use current total if available, otherwise fallback to initial pending calc
                const currentTotal = parseFloat(totalAmountInput.value) || parseFloat(initialPendingAmount) || 0;
                const approved = parseFloat(approvedPaidSum) || 0;
                let pending = currentTotal - approved;
                if (isNaN(pending) || pending < 0) pending = 0;

                // Update DOM
                pendingAmountDisplay.value = `₹${pending.toFixed(2)}`;
                pendingAmountValueHidden.value = pending.toFixed(2);
            } catch (e) {
                console.debug('Error updating pending display:', e);
            }
        }

        function updateBreakdownTotals() {
            let totalOriginal = 0;
            let totalDiscount = 0;
            let totalFinal = 0;

            Object.values(serviceBreakdownData).forEach(item => {
                totalOriginal += item.original_amount;
                totalDiscount += item.discount_amount;
                totalFinal += (item.original_amount - item.discount_amount);
            });

            // Update footer totals
            document.getElementById('total_original_amount').textContent = `₹${totalOriginal.toFixed(2)}`;
            document.getElementById('total_discount_amount').textContent = `₹${totalDiscount.toFixed(2)}`;

            // Respect manual override: if user saved a manual total, don't overwrite it
            if (isManualTotalEdited) {
                // Keep the manual total as-is and show it in the display
                const manual = parseFloat(totalAmountInput.value) || 0;
                totalFinalAmountDisplay.textContent = `₹${manual.toFixed(2)}`;
            } else {
                // Check if manual total input is visible (editing)
                if (totalFinalAmountEdit.classList.contains('d-none')) {
                    // Not in edit mode, show calculated total and update hidden total
                    totalFinalAmountDisplay.textContent = `₹${totalFinal.toFixed(2)}`;
                    totalAmountInput.value = totalFinal.toFixed(2);
                } else {
                    // In edit mode, keep the manual value in the hidden input
                    totalAmountInput.value = parseFloat(totalFinalAmountEdit.value || 0).toFixed(2);
                }
            }

            // Update hidden fields
            serviceAmountInput.value = totalOriginal.toFixed(2);
            discountAmountInput.value = totalDiscount.toFixed(2);

            // Save service details as JSON
            const detailsArray = Object.values(serviceBreakdownData);
            serviceDetailsInput.value = JSON.stringify(detailsArray);

            // Update pending display whenever totals change
            try {
                updatePendingDisplay();
            } catch (e) {
                /* ignore */
            }
        }

        // Function to handle service selection changes
        function handleServiceChange() {
            buildServiceBreakdownTable();
        }

        // Function to handle extra service selection changes
        function handleExtraServiceChange() {
            buildServiceBreakdownTable();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const servicesSelect = document.getElementById('services');
            const extraServicesSelect = document.getElementById('extra_services');

            // Initialize previous service selections
            previousServiceSelections = Array.from(servicesSelect.selectedOptions).map(opt => opt.value);
            previousExtraSelections = Array.from(extraServicesSelect.selectedOptions).map(opt => opt.value);

            // ALWAYS build table on initial load to show last service breakdown
            // Even if status is not payment-related, show the previous breakdown
            const initialStatus = statusSelect.value;

            // If the last follow-up had a manually edited total, initialize the form to show it
            if (lastFollowupTotalAmount !== null && lastFollowupTotalAmount !== undefined) {
                try {
                    totalAmountInput.value = parseFloat(lastFollowupTotalAmount).toFixed(2);
                    totalFinalAmountDisplay.textContent = `₹${parseFloat(lastFollowupTotalAmount).toFixed(2)}`;
                    // Mark manual override so updateBreakdownTotals will not overwrite it
                    isManualTotalEdited = true;
                } catch (e) {
                    console.debug('Error initializing manual total from last followup:', e);
                }
            }
            if (initialStatus === '3' || initialStatus === '4') {
                // Build table for payment status
                buildServiceBreakdownTable();
            } else if (lastFollowupServiceDetails && lastFollowupServiceDetails.length > 0) {
                // Build table from last followup data even for non-payment status
                // This ensures discounts are visible after approval
                buildServiceBreakdownTable();
                // But keep it hidden until payment status is selected
                serviceBreakdownContainer.style.display = 'none';
            }

            // After building the initial table, mark initial load as done
            setTimeout(() => {
                isInitialLoad = false;
            }, 500);

            // Initialize event listeners
            servicesSelect.addEventListener('change', function() {
                isManualTotalEdited = false;
                handleServiceChange();
            });
            extraServicesSelect.addEventListener('change', function() {
                isManualTotalEdited = false;
                handleExtraServiceChange();
            });

            // Edit Total Amount button
            if (editTotalBtn) {
                editTotalBtn.addEventListener('click', function() {
                    // Get current calculated total
                    const currentTotal = totalAmountInput.value || '0.00';

                    // Switch to edit mode (toggle both class and inline style to be robust)
                    try {
                        totalFinalAmountDisplay.classList.add('d-none');
                        totalFinalAmountDisplay.style.display = 'none';

                        totalFinalAmountEdit.classList.remove('d-none');
                        totalFinalAmountEdit.style.display = '';
                        totalFinalAmountEdit.value = currentTotal;
                        totalFinalAmountEdit.focus();

                        // Show save/cancel buttons
                        saveTotalBtn.classList.remove('d-none');
                        saveTotalBtn.style.display = '';
                        cancelTotalBtn.classList.remove('d-none');
                        cancelTotalBtn.style.display = '';

                        editTotalBtn.classList.add('d-none');
                        editTotalBtn.style.display = 'none';
                    } catch (e) {
                        console.debug('Error toggling edit total visibility:', e);
                    }
                });
            }

            // Save Total button
            if (saveTotalBtn) {
                saveTotalBtn.addEventListener('click', function() {
                    const manualTotal = parseFloat(totalFinalAmountEdit.value) || 0;

                    // Update display and mark manual override
                    totalFinalAmountDisplay.textContent = `₹${manualTotal.toFixed(2)}`;
                    totalAmountInput.value = manualTotal.toFixed(2);
                    isManualTotalEdited = true;
                    // Recalculate pending based on manual total
                    try {
                        updatePendingDisplay();
                    } catch (e) {
                        /* ignore */
                    }

                    // Switch back to display mode (toggle class + inline style)
                    try {
                        totalFinalAmountEdit.classList.add('d-none');
                        totalFinalAmountEdit.style.display = 'none';

                        totalFinalAmountDisplay.classList.remove('d-none');
                        totalFinalAmountDisplay.style.display = '';

                        saveTotalBtn.classList.add('d-none');
                        saveTotalBtn.style.display = 'none';

                        cancelTotalBtn.classList.add('d-none');
                        cancelTotalBtn.style.display = 'none';

                        editTotalBtn.classList.remove('d-none');
                        editTotalBtn.style.display = '';
                    } catch (e) {
                        console.debug('Error toggling save total visibility:', e);
                    }

                    console.log('Manual total saved:', manualTotal);
                });
            }

            // Cancel Total button
            if (cancelTotalBtn) {
                cancelTotalBtn.addEventListener('click', function() {
                    // Cancel manual override and switch back to display mode without saving (toggle class + inline style)
                    try {
                        totalFinalAmountEdit.classList.add('d-none');
                        totalFinalAmountEdit.style.display = 'none';

                        totalFinalAmountDisplay.classList.remove('d-none');
                        totalFinalAmountDisplay.style.display = '';

                        saveTotalBtn.classList.add('d-none');
                        saveTotalBtn.style.display = 'none';

                        cancelTotalBtn.classList.add('d-none');
                        cancelTotalBtn.style.display = 'none';

                        editTotalBtn.classList.remove('d-none');
                        editTotalBtn.style.display = '';
                    } catch (e) {
                        console.debug('Error toggling cancel total visibility:', e);
                    }

                    // Clear manual override flag
                    isManualTotalEdited = false;

                    // Recalculate to restore auto-calculated total
                    updateBreakdownTotals();
                });
            }

            // Add validation for received amount
            receivedAmountInput.addEventListener('input', function() {
                // const total = parseFloat(totalAmountInput.value) || 0;
                // const received = parseFloat(this.value) || 0;

                // if (received > total) {
                //     this.setCustomValidity('Received amount cannot be greater than Total amount');
                //     this.reportValidity();
                // } else {
                //     this.setCustomValidity('');
                // }
                this.setCustomValidity('');
            });

            // Initialize select2 if available
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $('#services').select2({
                    placeholder: "Select services",
                    allowClear: true,
                    width: '100%'
                });

                $('#extra_services').select2({
                    placeholder: "Select extra services",
                    allowClear: true,
                    width: '100%'
                });

                // Handle select2 change events
                $('#services').on('change', handleServiceChange);
                $('#extra_services').on('change', handleExtraServiceChange);
            }

            // Handle status change to show/hide payment fields
            function togglePaymentFields() {
                const selectedStatus = statusSelect.value;
                const showPaymentFields = selectedStatus === '3' || selectedStatus === '4';

                // Show/hide payment-related fields
                if (showPaymentFields) {
                    serviceBreakdownContainer.style.display = 'block';
                    receivedAmountField.style.display = 'block';
                    paymentMethodField.style.display = 'block';
                    paidDateField.style.display = 'block';
                    // Show pending amount container and initialize value
                    try {
                        if (pendingAmountContainer) pendingAmountContainer.style.display = 'block';
                        updatePendingDisplay();
                    } catch (e) {
                        console.debug('Error showing pending container:', e);
                    }

                    receivedAmountInput.required = true;
                    paymentMethodInput.required = true;
                    paidDateInput.required = true;

                    isManualTotalEdited = false;

                    // ALWAYS rebuild the service breakdown table when switching to payment status
                    // This ensures discounts from last followup are loaded
                    buildServiceBreakdownTable();
                } else {
                    serviceBreakdownContainer.style.display = 'none';
                    receivedAmountField.style.display = 'none';
                    paymentMethodField.style.display = 'none';
                    paidDateField.style.display = 'none';
                    // Hide pending amount container when not in payment status
                    try {
                        if (pendingAmountContainer) pendingAmountContainer.style.display = 'none';
                    } catch (e) {}

                    receivedAmountInput.required = false;
                    receivedAmountInput.value = '';
                    paymentMethodInput.required = false;
                    paymentMethodInput.value = '';
                    paidDateInput.required = false;
                    paidDateInput.value = '';
                }

                // Hide Next Follow Up when status is Cancelled (2) or Full payment (3)
                const nextFollowupContainer = document.getElementById('next_followup_field');
                const nextFollowupInput = document.getElementById('next_followup_date');
                const hideNextFollowup = selectedStatus === '2' || selectedStatus === '3';
                if (nextFollowupContainer) {
                    nextFollowupContainer.style.display = hideNextFollowup ? 'none' : 'block';
                    if (nextFollowupInput) {
                        nextFollowupInput.required = !hideNextFollowup;
                        if (hideNextFollowup) nextFollowupInput.value = '';
                    }
                }
            }

            // Initialize payment fields visibility
            togglePaymentFields();

            // Listen for status changes
            statusSelect.addEventListener('change', togglePaymentFields);

            // Handle payment method change for Acepoint
            const acepointPointsField = document.getElementById('acepoint_points_field');
            const redeemPointsInput = document.getElementById('redeem_points');
            const availablePointsDisplay = document.getElementById('available_points_display');
            const availablePointsValue = document.getElementById('available_points_value');
            const pointsLoading = document.getElementById('points_loading');
            const pointsError = document.getElementById('points_error');
            const imageInput = document.getElementById('image');

            let userAvailablePoints = 0;

            function toggleAcepointFields() {
                const selectedPaymentMethod = paymentMethodInput.value;
                const isAcepoint = selectedPaymentMethod === 'Acepoint Point Redeem';
                const isVendorPaid = selectedPaymentMethod === 'Paid Directly to Vendor';

                if (isAcepoint) {
                    // Show points field
                    acepointPointsField.style.display = 'block';
                    redeemPointsInput.required = true;

                    // Hide and make optional: receipt upload (for acepoint) and paid date
                    imageInput.required = false;
                    paidDateInput.required = false;

                    // Fetch available points from Airpoints API
                    fetchUserAvailablePoints();

                    // Change received amount label
                    const receivedAmountLabel = document.querySelector('label[for="received_amount"]');
                    if (receivedAmountLabel) {
                        receivedAmountLabel.innerHTML = 'Points Value (₹)<span class="text-danger">*</span>';
                    }

                    // Make received amount read-only and visually disabled (keeps value submitted)
                    try {
                        receivedAmountInput.readOnly = true;
                        receivedAmountInput.classList.add('cursor-not-allowed');
                        // grey background for disabled look
                        receivedAmountInput.style.backgroundColor = '#f3f4f6';
                    } catch (e) {
                        /* ignore if element not present */
                    }
                } else {
                    // Hide points field
                    acepointPointsField.style.display = 'none';
                    redeemPointsInput.required = false;
                    redeemPointsInput.value = '';

                    // Show and make required: receipt upload and paid date (for payment statuses)
                    const s = statusSelect.value;
                    if (s === '3' || s === '4') {
                        // If Paid Directly to Vendor is selected, receipt is NOT mandatory
                        imageInput.required = !isVendorPaid;
                        paidDateInput.required = true;
                    }

                    // Reset available points display
                    availablePointsDisplay.style.display = 'none';
                    pointsLoading.style.display = 'none';
                    pointsError.style.display = 'none';
                    userAvailablePoints = 0;

                    // Reset received amount label
                    const receivedAmountLabel = document.querySelector('label[for="received_amount"]');
                    if (receivedAmountLabel) {
                        receivedAmountLabel.innerHTML = 'Received Amount<span class="text-danger">*</span>';
                    }

                    // Restore received amount interactivity
                    try {
                        receivedAmountInput.readOnly = false;
                        receivedAmountInput.classList.remove('cursor-not-allowed');
                        receivedAmountInput.style.backgroundColor = '';
                    } catch (e) {
                        /* ignore */
                    }
                }
            }

            // Fetch user available points from Airpoints
            function fetchUserAvailablePoints() {
                pointsLoading.style.display = 'block';
                availablePointsDisplay.style.display = 'none';
                pointsError.style.display = 'none';

                fetch('{{ route('admin.airpoints.check-user-points') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            client_id: '{{ $client->id ?? '' }}'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        pointsLoading.style.display = 'none';

                        if (data.success && data.points !== undefined) {
                            userAvailablePoints = parseInt(data.points) || 0;
                            availablePointsValue.textContent = userAvailablePoints;
                            availablePointsDisplay.style.display = 'block';
                        } else {
                            pointsError.textContent = data.message || 'Unable to fetch available points';
                            pointsError.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        pointsLoading.style.display = 'none';
                        pointsError.textContent = 'Error fetching available points';
                        pointsError.style.display = 'block';
                        console.error('Error:', error);
                    });
            }

            // Validate points on input
            if (redeemPointsInput) {
                redeemPointsInput.addEventListener('input', function() {
                    const pointsToRedeem = parseInt(this.value) || 0;

                    if (pointsToRedeem <= 0) {
                        pointsError.textContent = 'Please enter a valid number of points';
                        pointsError.style.display = 'block';
                        this.setCustomValidity('Please enter a valid number of points');
                    } else if (userAvailablePoints > 0 && pointsToRedeem > userAvailablePoints) {
                        pointsError.textContent = `Insufficient points. Available: ${userAvailablePoints}`;
                        pointsError.style.display = 'block';
                        this.setCustomValidity(`Insufficient points. Available: ${userAvailablePoints}`);
                    } else {
                        pointsError.style.display = 'none';
                        this.setCustomValidity('');

                        // Auto-fill received amount based on points (assuming 1 point = 1 rupee)
                        receivedAmountInput.value = pointsToRedeem.toFixed(2);
                    }
                });
            }

            // Listen for payment method changes
            if (paymentMethodInput) {
                paymentMethodInput.addEventListener('change', function() {
                    toggleAcepointFields();
                    toggleImageRequiredOnStatus();
                });
            }

            // Initialize Acepoint fields on page load
            toggleAcepointFields();

            // Also toggle the file input 'required' attribute here so the image field
            // becomes mandatory only when status is 3 or 4.
            function toggleImageRequiredOnStatus() {
                const imageInputLocal = document.getElementById('image');
                if (!imageInputLocal) return;
                const s = statusSelect.value;
                const paymentMethod = paymentMethodInput.value;

                // Skip image requirement if Acepoint or Paid Directly to Vendor is selected
                if (paymentMethod === 'Acepoint Point Redeem' || paymentMethod === 'Paid Directly to Vendor') {
                    imageInputLocal.required = false;
                    return;
                }

                if (s === '3' || s === '4') {
                    imageInputLocal.required = true;
                    // optionally update helper text
                    const help = document.getElementById('image-help');
                    if (help && !help.textContent.includes('Required for payment statuses')) {
                        help.textContent = help.textContent + ' (Required for selected payment status)';
                    }
                } else {
                    imageInputLocal.required = false;
                    const help = document.getElementById('image-help');
                    if (help) {
                        help.textContent =
                            'Only PDF, JPG, or PNG formats are allowed. File size must not exceed 2MB.';
                    }
                }
            }

            // Ensure image required state is correct on init and whenever status changes
            toggleImageRequiredOnStatus();
            statusSelect.addEventListener('change', toggleImageRequiredOnStatus);

            // Image handling functions
            document.querySelectorAll('.edit-image-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const followupId = this.getAttribute('data-followup-id');
                    const form = document.getElementById(`edit-image-form-${followupId}`);
                    if (form) {
                        form.classList.remove('hidden');
                    }
                });
            });
            document.querySelectorAll('.cancel-edit-image').forEach(button => {
                button.addEventListener('click', function() {
                    const followupId = this.getAttribute('data-followup-id');
                    const form = document.getElementById(`edit-image-form-${followupId}`);
                    if (form) {
                        form.classList.add('hidden');
                    }
                });
            });
            document.querySelectorAll('.update-image-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const url = this.action;
                    const followupId = this.closest('[id^="edit-image-form-"]').id.split('-')[3];

                    fetch(url, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Reload the page to show the updated image
                                window.location.reload();
                            } else {
                                alert('Error updating image: ' + (data.message ||
                                    'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating the image');
                        });
                });
            });
        });
    </script>
    <script>
        // Delete followup modal JS (page-specific)
        let _deleteFollowupAction = null;

        function openDeleteFollowupModal(actionUrl) {
            console.log('[page] openDeleteFollowupModal action=', actionUrl);
            _deleteFollowupAction = actionUrl;
            try {
                document.getElementById('delete-followup-reason').value = '';
            } catch (e) {}
            var modalEl = document.getElementById('delete-followup-modal');
            if (!modalEl) {
                console.error('[page] delete modal not found');
                return;
            }

            try {
                console.log('[page] openDeleteFollowupModal start, modal classes:', modalEl.className, 'display:', modalEl
                    .style.display);
                if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                    try {
                        var dfInst = null;
                        if (HSOverlay.getInstance) {
                            try {
                                dfInst = HSOverlay.getInstance('#delete-followup-modal');
                            } catch (e) {
                                dfInst = null;
                            }
                        }
                        // Try autoInit if available to register overlays created without a trigger
                        if (!dfInst && HSOverlay.autoInit) {
                            try {
                                HSOverlay.autoInit();
                                dfInst = HSOverlay.getInstance ? HSOverlay.getInstance('#delete-followup-modal') : null;
                            } catch (e) {
                                dfInst = null;
                            }
                        }
                        if (dfInst) {
                            HSOverlay.open('#delete-followup-modal');
                            return;
                        }
                        console.warn(
                            '[page] HSOverlay instance for #delete-followup-modal not found after autoInit — using DOM fallback'
                        );
                    } catch (e) {
                        console.error('[page] HSOverlay.open threw', e);
                    }
                }

                // DOM fallback (works even when Preline/HSOverlay isn't initialized)
                modalEl.classList.remove('hidden');
                modalEl.classList.add('open');
                try {
                    modalEl.style.display = 'block';
                    modalEl.setAttribute('aria-hidden', 'false');
                } catch (e) {}
                var inner = modalEl.querySelector('.ti-modal-content');
                if (inner) inner.classList.remove('hidden');

                // Create a simple backdrop so clicking outside the modal closes it
                try {
                    var backdropId = modalEl.id + '-backdrop';
                    var backdrop = document.getElementById(backdropId);
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.id = backdropId;
                        // Minimal styling to match overlay behavior
                        backdrop.style.position = 'fixed';
                        backdrop.style.top = '0';
                        backdrop.style.left = '0';
                        backdrop.style.right = '0';
                        backdrop.style.bottom = '0';
                        backdrop.style.backgroundColor = 'rgba(0,0,0,0.5)';
                        backdrop.style.zIndex = '9998';
                        backdrop.style.backdropFilter = 'none';
                        backdrop.className = 'hs-overlay-backdrop';
                        backdrop.addEventListener('click', function(e) {
                            try {
                                closeDeleteFollowupModal();
                            } catch (ex) {
                                console.error('backdrop close error', ex);
                            }
                        });
                        document.body.appendChild(backdrop);
                        // make sure modal is above the backdrop
                        try {
                            modalEl.style.zIndex = '9999';
                        } catch (e) {}
                        console.log('[page] created manual backdrop for delete modal');
                    }
                } catch (e) {
                    console.error('[page] error creating backdrop', e);
                }
                console.log('[page] openDeleteFollowupModal completed, modal classes now:', modalEl.className, 'display:',
                    modalEl.style.display);
            } catch (e) {
                console.error('[page] error opening delete modal', e);
            }
        }

        function closeDeleteFollowupModal() {
            var modalEl = document.getElementById('delete-followup-modal');
            if (!modalEl) return;
            // Prefer using HSOverlay.close only when an HSOverlay instance exists for this modal.
            var usedHsOverlay = false;
            if (window.HSOverlay && typeof window.HSOverlay.close === 'function') {
                try {
                    var inst = null;
                    if (HSOverlay.getInstance) {
                        try {
                            inst = HSOverlay.getInstance('#delete-followup-modal');
                        } catch (e) {
                            inst = null;
                        }
                    }
                    if (inst) {
                        try {
                            HSOverlay.close('#delete-followup-modal');
                            usedHsOverlay = true;
                        } catch (e) {
                            console.error('[page] HSOverlay.close threw', e);
                        }
                    }
                } catch (e) {
                    console.error('[page] HSOverlay readiness check failed', e);
                }
            }

            // If HSOverlay wasn't used (no instance found), fall back to DOM hide so modal is definitely hidden
            if (!usedHsOverlay) {
                modalEl.classList.add('hidden');
                modalEl.classList.remove('open');
                try {
                    modalEl.style.display = 'none';
                    modalEl.setAttribute('aria-hidden', 'true');
                } catch (e) {}
                try {
                    var inner = modalEl.querySelector('.ti-modal-content');
                    if (inner) inner.classList.add('hidden');
                } catch (e) {}
            }
            console.log('[page] closeDeleteFollowupModal completed, usedHsOverlay=', usedHsOverlay, 'modal classes now:',
                modalEl.className, 'display:', modalEl.style.display);

            // Remove manual backdrop if present (created by DOM fallback)
            try {
                var backdrop = document.getElementById(modalEl.id + '-backdrop');
                if (backdrop) backdrop.remove();
                // reset modal z-index
                try {
                    modalEl.style.zIndex = '';
                } catch (e) {}
            } catch (e) {
                console.error('[page] error removing backdrop', e);
            }
        }

        // Bind handlers
        jQuery(function() {
            // Ensure HSOverlay instance exists for this page modal
            try {
                var _dfModal = document.getElementById('delete-followup-modal');
                if (_dfModal && typeof HSOverlay !== 'undefined') {
                    try {
                        // Do NOT call getInstance/new HSOverlay with a raw DOM element (some preline builds throw).
                        // Prefer checking by selector and rely on HSOverlay.open to initialize on demand.
                        var existing = null;
                        if (HSOverlay.getInstance) {
                            try {
                                existing = HSOverlay.getInstance('#delete-followup-modal');
                            } catch (e) {
                                existing = null;
                            }
                        }
                        if (!existing) {
                            console.log(
                                '[page] HSOverlay instance not found for delete-followup-modal (will rely on HSOverlay.open)'
                            );
                        } else {
                            console.log('[page] HSOverlay instance present for delete-followup-modal');
                        }
                    } catch (e) {
                        console.error('[page] error checking HSOverlay instance', e);
                    }
                }
            } catch (e) {
                console.error('[page] HSOverlay readiness check failed', e);
            }

            // Confirm delete button
            var confirmBtn = document.getElementById('confirm-delete-followup-btn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    if (!_deleteFollowupAction) {
                        showErrorModal('Action error', 'No delete action configured');
                        return;
                    }
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = _deleteFollowupAction;
                    var token = document.querySelector('meta[name="csrf-token"]');
                    if (token) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = '_token';
                        inp.value = token.getAttribute('content');
                        form.appendChild(inp);
                    }
                    var m = document.createElement('input');
                    m.type = 'hidden';
                    m.name = '_method';
                    m.value = 'DELETE';
                    form.appendChild(m);
                    var reasonVal = document.getElementById('delete-followup-reason').value || '';
                    var r = document.createElement('input');
                    r.type = 'hidden';
                    r.name = 'deletion_reason';
                    r.value = reasonVal;
                    form.appendChild(r);
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            // Delegated handler for delete buttons within the followups list
            $(document).on('click', '.delete-followup-btn', function(e) {
                try {
                    var action = $(this).data('delete-url') || null;
                    if (!action) {
                        showErrorModal('Configuration error', 'Delete URL not found for this followup.');
                        return;
                    }
                    openDeleteFollowupModal(action);
                } catch (ex) {
                    console.error('Error in delegated delete-followup handler', ex);
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('image');
            const preview = document.getElementById('image-preview');
            const errorEl = document.getElementById('image-error');
            const helpEl = document.getElementById('image-help');
            const form = document.querySelector('form.ti-custom-validation');
            const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

            const MAX_KB = 2048; // 2MB
            const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];

            function clearPreview() {
                preview.innerHTML = '';
                errorEl.textContent = '';
                if (submitBtn) submitBtn.disabled = false;
            }

            function showError(msg) {
                errorEl.textContent = msg;
                if (submitBtn) submitBtn.disabled = true;
            }

            function showPreview(file) {
                preview.innerHTML = '';
                // Create a preview link that opens the file in a new tab instead of embedding inline
                const url = URL.createObjectURL(file);
                // create compact preview button (no filename displayed) and prevent wrapping
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.gap = '8px';
                row.style.flexWrap = 'nowrap';

                const btn = document.createElement('a');
                btn.href = url;
                btn.target = '_blank';
                btn.rel = 'noopener noreferrer';
                btn.className = 'ti-btn ti-btn-outline-primary';
                // Keep text short so it doesn't wrap on small screens
                btn.textContent = 'Preview';

                row.appendChild(btn);
                preview.appendChild(row);

                // Revoke URL when the preview link is clicked (best effort cleanup)
                btn.addEventListener('click', () => setTimeout(() => URL.revokeObjectURL(url), 1000));
            }

            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    clearPreview();
                    const file = this.files && this.files[0];
                    if (!file) return;

                    // Validate size
                    const kb = Math.round(file.size / 1024);
                    if (kb > MAX_KB) {
                        showError('File size must not exceed 2MB');
                        return;
                    }

                    // Validate mime
                    if (!ALLOWED_MIMES.includes(file.type)) {
                        // Some browsers (esp. Safari) may not set file.type for camera photos; also check extension
                        const name = file.name || '';
                        const ext = name.split('.').pop() ? name.split('.').pop().toLowerCase() : '';
                        const allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (!allowedExt.includes(ext)) {
                            showError('Only PDF, JPG, or PNG formats are allowed');
                            return;
                        }
                    }

                    // If passed validations
                    showPreview(file);
                });
            }

            // Prevent form submit when there's an image error
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (errorEl && errorEl.textContent.trim() !== '') {
                        e.preventDefault();
                        alert(errorEl.textContent || 'Please fix the file upload before submitting');
                        return false;
                    }
                });
            }
        });

        // Registration Link Functionality
        document.getElementById('generate-registration-link-btn').addEventListener('click', function() {
            const clientId = this.getAttribute('data-client-id');
            const btn = this;

            btn.disabled = true;
            btn.textContent = 'Generating...';

            fetch(`{{ route('admin.clients.generate-passenger-registration-link', ['client' => ':client']) }}`
                    .replace(':client', clientId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Prefer short link when available
                        const displayLink = data.short_link || data.link || '';
                        document.getElementById('registration-link-input').value = displayLink;
                        document.getElementById('registration-link-container').style.display = 'block';
                        document.getElementById('copy-registration-link-btn').style.display = 'inline-block';

                        // Mark button as generated and disable it
                        btn.disabled = true;
                        btn.textContent = 'Link Generated';
                        btn.setAttribute('aria-disabled', 'true');

                        // Show success message
                        showSuccessMessage('Registration link generated successfully!');
                    } else {
                        // Re-enable on failure so user can retry
                        btn.disabled = false;
                        btn.textContent = 'Generate Registration Link';
                        alert('Error: ' + (data.message || 'Failed to generate registration link'));
                    }
                })
                .catch(error => {
                    // Re-enable on error
                    btn.disabled = false;
                    btn.textContent = 'Generate Registration Link';
                    console.error('Error:', error);
                    alert('Error generating registration link');
                });
        });

        // Copy registration link functionality
        document.getElementById('copy-registration-link-btn').addEventListener('click', function() {
            const link = document.getElementById('registration-link-input').value;
            if (link) {
                copyToClipboard(link);
            }
        });

        // Load existing registration link on page load
        document.addEventListener('DOMContentLoaded', function() {
            const clientId = document.getElementById('generate-registration-link-btn').getAttribute(
                'data-client-id');
            const genBtn = document.getElementById('generate-registration-link-btn');

            fetch(`{{ route('admin.clients.get-passenger-registration-link', ['client' => ':client']) }}`.replace(
                    ':client', clientId))
                .then(response => response.json())
                .then(data => {
                    if (data.success && (data.link || data.short_link)) {
                        const displayLink = data.short_link || data.link;
                        document.getElementById('registration-link-input').value = displayLink;
                        document.getElementById('registration-link-container').style.display = 'block';
                        document.getElementById('copy-registration-link-btn').style.display = 'inline-block';

                        // Disable generate button because a link already exists
                        genBtn.disabled = true;
                        genBtn.textContent = 'Link Generated';
                        genBtn.setAttribute('aria-disabled', 'true');
                    }
                })
                .catch(error => {
                    console.log('No existing registration link found');
                });
        });

        // Copy to clipboard function
        function copyToClipboard(text) {
            if (!text) {
                text = document.getElementById('registration-link-input').value;
            }

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showSuccessMessage('Registration link copied to clipboard!');
                }, function(err) {
                    console.error('Could not copy text: ', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showSuccessMessage('Registration link copied to clipboard!');
                } else {
                    console.error('Fallback: Oops, unable to copy');
                }
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(textArea);
        }

        function showSuccessMessage(message) {
            // Create a temporary success message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 3 seconds
            setTimeout(function() {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 3000);
        }
    </script>
@endpush
