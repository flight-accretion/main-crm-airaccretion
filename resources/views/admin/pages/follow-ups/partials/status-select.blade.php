@php
    $currentLeadFollowup =
        $followups->first();

    $currentLeadStatusLabel =
        $currentLeadFollowup
            ? $currentLeadFollowup->statusLabel()
            : 'Initiated';

    /*
     * "keep" remains the internal default.
     * It is no longer exposed as "Keep current status".
     */
    $selectedLeadStatus =
        (string) old(
            'status',
            'keep'
        );

    $statusLabels =
        \App\Models\LeadFollowup::statusLabels();

    $allowedStatusOptions =
        $allowedStatusOptions
        ?? [1, 2, 3, 4, 10, 11];

    /*
     * Defense-in-depth for Sales UI.
     *
    * allowedSalesStatuses() should already enforce this,
    * but never expose Active/Cancelled to Sales after an
    * approved payment even if upstream data changes.
     */
    if (
        ($isSalesUser ?? false)
        && ($hasApprovedPayment ?? false)
    ) {
        $allowedStatusOptions =
            array_values(
                array_diff(
                    $allowedStatusOptions,
                    [
                        \App\Models\LeadFollowup::STATUS_ACTIVE,
                        \App\Models\LeadFollowup::STATUS_CANCELLED,
                    ]
                )
            );
    }
@endphp


{{-- Current commercial/business status --}}
<div class="mb-2">
    <span class="text-xs text-gray-500">
        Current Status
    </span>

    <div class="mt-1">
        <span class="badge bg-primary/10 text-primary px-3 py-2">
            {{ $currentLeadStatusLabel }}
        </span>
    </div>
</div>


<select
    class="ti-form-select rounded-sm form-control-sm"
    name="status"
    id="status"
    required
>
    {{--
        IMPORTANT:
        value="keep" is intentionally retained internally.

        To the user this simply means:
        no status-changing action selected.
    --}}
    <option
        value="keep"
        {{ $selectedLeadStatus === 'keep' ? 'selected' : '' }}
    >
        Select action
    </option>

    @foreach($allowedStatusOptions as $statusOption)

        <option
            value="{{ $statusOption }}"
            {{
                $selectedLeadStatus === (string) $statusOption
                    ? 'selected'
                    : ''
            }}
        >
            {{
                $statusLabels[$statusOption]
                ?? 'Unknown status'
            }}
        </option>

    @endforeach
</select>


@if(
    ($isSalesUser ?? false)
    && ($hasApprovedPayment ?? false)
)
    <p class="text-xs text-gray-500 mt-2">
        Active and Cancelled are unavailable because a payment has already been approved.
    </p>
@endif