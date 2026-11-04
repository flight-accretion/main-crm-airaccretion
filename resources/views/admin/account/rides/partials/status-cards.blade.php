<section class="rs-cases" aria-label="Ride cases">
    @forelse($ridesData as $ride)
        <article class="rs-case" data-ride-card="{{ $ride['id'] }}">
            <div class="rs-case-header">
                <div>
                    <label>Client</label>
                    <a class="rs-client" href="{{ route('admin.leads.view', $ride['lead_id']) }}" target="_blank"
                        rel="noopener">{{ $ride['client_name'] }}</a>
                    <small>{{ $ride['contact_number'] }}@if($ride['whatsapp_number'] && $ride['whatsapp_number'] !== $ride['contact_number'])
                    &middot; WhatsApp {{ $ride['whatsapp_number'] }}@endif</small>
                    <small>{{ $ride['client_email'] ?: 'No email' }}</small>
                    <small>{{ $ride['company_name'] }} &middot; GST {{ $ride['gst_number'] }}</small>
                    <small>Invoice {{ $ride['invoice_id'] }} &middot; Created {{ $ride['created_date'] }}</small>
                </div>
                <div>
                    <label>Product</label>
                    <strong>{{ ($tableProductNames[$ride['lead_id']] ?? '') ?: 'Not specified' }}</strong>
                </div>
                <div>
                    <label>Service date &amp; place</label>
                    @foreach($ride['all_rides'] as $segment)
                        <div class="rs-segment">
                            <strong>{{ $segment->from_place ?: 'Not set' }} &rarr;
                                {{ $segment->to_place ?: 'Not set' }}</strong>
                            <small>From
                                {{ $segment->is_tba ? 'TBA' : (optional($segment->from_date)->format('d M Y, H:i') ?: 'Not set') }}</small>
                            <small>To
                                {{ $segment->is_tba ? 'TBA' : (optional($segment->to_date)->format('d M Y, H:i') ?: 'Not set') }}</small>
                        </div>
                    @endforeach
                </div>
                <div>
                    <label>Vendor / Rep</label>
                    <strong>{{ $ride['vendor_name'] }}</strong>
                    <small>Rep: {{ $ride['assigned_rep'] }}</small>
                </div>
                <div>
                    <label>Payment</label>
                    <strong>&#8377;{{ number_format($ride['received_amount'], 2) }} /
                        &#8377;{{ number_format($ride['total_amount'], 2) }}</strong>
                    <small>Balance &#8377;{{ number_format($ride['balance_amount'], 2) }}</small>
                    <span class="rs-status">{{ $ride['status_label'] ?? $ride['status_text'] }}</span>
                </div>
                <div class="rs-actions">
                    <a class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                        href="{{ route('admin.leads.view', $ride['lead_id']) }}" target="_blank" rel="noopener"
                        title="View lead" aria-label="View lead {{ $ride['client_name'] }}"><i class="ri-eye-line"
                            aria-hidden="true"></i></a>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary rs-status-toggle"
                        data-ride-id="{{ $ride['id'] }}" aria-expanded="false" title="Ride status, refunds and dates"><i
                            class="ri-route-line" aria-hidden="true"></i> Ride Status</button>
                </div>
            </div>
            {{-- The shared Ride Status panel is moved here (directly under the buttons) when this ride's button is clicked.
            --}}
            <div class="rs-panel-slot" data-panel-slot></div>
            <!-- <details class="rs-timeline">
                <summary>Timeline</summary>
                <ol>
                    @foreach($ride['timeline'] ?? [] as $event)
                        <li class="{{ $event['date'] ? 'is-recorded' : '' }}"><span class="rs-step"
                                aria-hidden="true">@if($event['date'])<i
                                class="ri-check-line"></i>@else{{ $loop->iteration }}@endif</span><strong>{{ $event['label'] }}</strong><time>{{ $event['date'] ? \Carbon\Carbon::parse($event['date'])->format('d M Y, H:i') : '--' }}</time><small>{{ $event['note'] }}</small>
                        </li>
                    @endforeach
                </ol>
            </details> -->

            @php
    $isCancelled = (int) ($ride['status'] ?? -1) === 2;

    $timelineEvents = $ride['timeline'] ?? [];

    $refundDisplay = $ride['refund_display'] ?? [];

    $refundSteps = [
        'vendor' => 'Vendor Refund',
        'customer' => 'Customer Refund',
    ];

    $allowedTones = [
        'neutral',
        'warning',
        'info',
        'success',
    ];
@endphp

<details
    class="rs-timeline {{ $isCancelled ? 'rs-timeline--with-refunds' : '' }}"
>
    <summary>
        Timeline
    </summary>

    <ol>
        @foreach($timelineEvents as $event)

            {{-- Original timeline event --}}
            <li class="{{ !empty($event['date']) ? 'is-recorded' : '' }}">

                <span
                    class="rs-step"
                    aria-hidden="true"
                >
                    @if(!empty($event['date']))

                        <i class="ri-check-line"></i>

                    @else

                        {{ $loop->iteration }}

                    @endif
                </span>


                <strong>
                    {{ $event['label'] ?? '' }}
                </strong>


                <time>
                    {{
                        !empty($event['date'])
                            ? \Carbon\Carbon::parse(
                                $event['date']
                            )->format(
                                'd M Y, H:i'
                            )
                            : '--'
                    }}
                </time>


                <small>
                    {{ $event['note'] ?? '' }}
                </small>

            </li>


            {{-- Insert refund statuses immediately after Current status --}}
            @if(
                $isCancelled
                &&
                $loop->index === 3
            )

                @foreach($refundSteps as $type => $heading)

                    @php

                        $refund =
                            $refundDisplay[$type]
                            ??
                            [];


                        $refundLabel =
                            $refund['label']
                            ??
                            'Assessment Pending';


                        $refundTone =
                            $refund['tone']
                            ??
                            'warning';


                        if (
                            !in_array(
                                $refundTone,
                                $allowedTones,
                                true
                            )
                        ) {

                            $refundTone =
                                'warning';

                        }


                        $refundDue =
                            max(
                                0,
                                (float) (
                                    $refund['due']
                                    ??
                                    0
                                )
                            );

                    @endphp


                    <li class="rs-timeline-refund">

                        <span
                            class="
                                rs-step
                                rs-step-refund
                            "
                            aria-hidden="true"
                        >

                            <i class="ri-refund-2-line"></i>

                        </span>


                        <strong>
                            {{ $heading }}
                        </strong>


                        <time>
                            {{ $refund['date'] ?? '--' }}
                        </time>


                        <small class="rs-timeline-refund-details">

                            <span
                                class="
                                    rs-refund-badge
                                    rs-refund-badge--{{ $refundTone }}
                                "
                            >

                                {{ $refundLabel }}

                            </span>


                            @if($refundDue > 0)

                                <span class="rs-refund-timeline-due">

                                    Due ₹{{ number_format($refundDue, 2) }}

                                </span>

                            @endif

                        </small>

                    </li>

                @endforeach

            @endif

        @endforeach
    </ol>

</details>
        </article>
    @empty<div class="rs-empty">No rides match these filters.</div>@endforelse
</section>