                                    <div class="hstack flex gap-3 text-[.9375rem]" style="align-items:flex-start;">
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
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-warning-full" target="_blank"
                                            title="Generate Voucher"><i class="ri-file-text-line"></i>
                                        </a>
                                        
                                        @endif
                                        @if($canWriteLeads)
                                        <div>
                                            <a aria-label="anchor"
                                                href="{{ route('admin.leads.follow-up.create', $enquiry->id) }}"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full" target="_blank"
                                                title="Add Lead Followup"><i class="ri-add-line"></i></a>

                                        </div>
                                        @endif
                                        <a aria-label="anchor" href="{{ route('admin.leads.view', $enquiry->id) }}"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full" target="_blank"
                                            title="View Lead"><i class="ri-eye-line"></i></a>
                                        @php
                                            $vendorFollowupRole = optional(auth()->user()->userType)->user_type;
                                            $canCreateVendorFollowup = ($vendorFollowupRole === \App\Models\UserType::SUPER_ADMIN)
                                                || in_array($vendorFollowupRole, \App\Models\UserType::OPERATIONS_ROLES, true);
                                        @endphp
                                        @if($canCreateVendorFollowup)
                                            <a aria-label="Create Vendor Follow-up"
                                                href="{{ route('admin.operations.vendor-followups.create', $enquiry->id) }}"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-warning-full" target="_blank"
                                                title="Create Vendor Follow-up"><i class="ri-store-2-line"></i></a>
                                        @endif
                                        @if ($canWriteLeads && $canCreateManualRepeatLead)
                                        <a aria-label="Create New Lead"
                                            href="{{ route('admin.clients.create', ['repeat_from_lead' => $enquiry->id]) }}"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-secondary-full" target="_blank"
                                            title="Create New Lead for Same Customer"><i class="ri-add-circle-line"></i></a>
                                        @endif
                                        @if($canWriteLeads)
                                        <a aria-label="anchor" href="{{ route('admin.leads.edit', $enquiry->id) }}"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" title="Edit Lead"><i
                                                class="ri-edit-line"></i></a>
                                        @endif
                                                @if ($isSuperAdmin ?? false)

<a
    aria-label="Transfer Lead"
    href="javascript:void(0);"
    class="
        ti-btn
        ti-btn-icon
        ti-btn-sm
        ti-btn-warning-full
        lead-direct-transfer-btn
    "
    data-lead-id="{{ $enquiry->id }}"
    data-client-name="{{
        optional(
            $enquiry->client
        )->name ?? 'N/A'
    }}"
    data-current-representative="{{
        optional(
            $enquiry->representative
        )->name ?? 'N/A'
    }}"
    title="Transfer Lead"
>
    <i class="ri-arrow-left-right-line"></i>
</a>

@endif
                                        @if($canWriteLeads)
                                        <a aria-label="Cancel Lead" href="javascript:void(0);"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full cancel-lead"
                                            data-lead-id="{{ $enquiry->id }}" title="Cancel Lead"><i
                                                class="ri-close-line"></i></a>
                                        @endif
                                        @if (in_array(optional(auth()->user()->userType)->user_type,
                                        [\App\Models\UserType::SUPER_ADMIN]))
                                        <a aria-label="Delete Lead" href="javascript:void(0);"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-lead"
                                            data-lead-id="{{ $enquiry->id }}" title="Delete Lead"><i
                                                class="ri-delete-bin-line"></i></a>
                                        @endif
                                    </div>
