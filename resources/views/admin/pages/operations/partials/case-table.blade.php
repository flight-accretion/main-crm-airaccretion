{{--
    Leads-style table for Operations cases.
    Expects: $cases (paginator), $latestFollowups, $tableId, $mode ("queue" | "history"), $filterAction.
--}}
@php
    $isQueue = ($mode ?? 'queue') === 'queue';
    $filters = $filters ?? [];
@endphp

<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header flex justify-between items-center">
                <div class="box-title">{{ $listTitle }}</div>

                <div class="flex gap-3 items-center">
                    <div class="flex items-center gap-2">
                        <label for="per-page-select" class="text-sm whitespace-nowrap">Show</label>
                        <select id="per-page-select" name="per_page" form="filter-form"
                            class="ti-form-select rounded-sm form-control-sm" style="width: 80px;">
                            @foreach ([10, 25, 50, 100] as $size)
                                <option value="{{ $size }}"
                                    {{ (int) ($filters['per_page'] ?? 25) === $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                        <label class="text-sm whitespace-nowrap">entries</label>
                    </div>

                    <div class="search-container">
                        <input type="text" id="global-search" name="search" form="filter-form"
                            class="ti-form-input rounded-sm form-control-sm" placeholder="Search"
                            value="{{ $filters['search'] ?? '' }}" style="min-width: 250px;">
                    </div>
                </div>
            </div>

            <div class="box-body">
                <div class="table-responsive">
                    <table id="{{ $tableId }}" class="table display responsive nowrap lead-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">
                                <th data-priority="1">S.No</th>
                                <th data-priority="2">Client Name</th>
                                <th data-priority="6">Phone</th>
                                @unless ($isQueue)
                                    <th data-priority="6">Type</th>
                                    <th data-priority="6">Completed</th>
                                @endunless
                                <th data-priority="6">Next Follow Up</th>
                                <th data-priority="8">Assigned:</th>
                                <th data-priority="9">Service Date:</th>
                                <th data-priority="10" class="lead-service-column">Service:</th>
                                <th data-priority="1">Status</th>
                                <th data-priority="1">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cases as $key => $case)
                                @php
                                    $lead = $case->lead;
                                    $client = optional($lead)->client;
                                    $segments = optional($lead)->rideSegments ?? collect();
                                    $leadStatus = optional($latestFollowups->get($case->lead_id))->status;
                                    $leadStatus = $leadStatus === null ? null : (int) $leadStatus;
                                @endphp

                                <tr class="border-b border-defaultborder">
                                    <td class="text-center">
                                        {{ $cases->firstItem() ? $cases->firstItem() + $key : $key + 1 }}
                                    </td>
                                    <td>{{ optional($client)->name ?? 'N/A' }}</td>
                                    <td class="text-center">{{ optional($client)->contact_number ?? 'N/A' }}</td>

                                    @unless ($isQueue)
                                        <td class="text-center">
                                            {{ ucfirst(str_replace('_', ' ', $case->type)) }}
                                        </td>
                                        <td class="text-center">
                                            {{ optional($case->completed_at)->format('d-m-Y H:i') ?? 'N/A' }}
                                        </td>
                                    @endunless

                                    <td class="text-center">
                                        {{ optional($case->next_followup_at)->format('d-m-Y H:i') ?? 'N/A' }}
                                    </td>
                                    <td>{{ optional(optional($lead)->representative)->name ?? 'N/A' }}</td>
                                    <td>
                                        @if ($segments->count() > 0)
                                            From: {{ date('d-m-Y', strtotime($segments->first()->from_date)) }}
                                            To: {{ date('d-m-Y', strtotime($segments->last()->to_date)) }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="lead-service-column">
                                        @include('admin.pages.leads.partials.service-preview', [
                                            'serviceNames' => $lead ? ($lead->service_names ?? []) : [],
                                        ])
                                    </td>
                                    <td class="text-center">
                                        @include('admin.pages.operations.partials.lead-status-badge', [
                                            'status' => $leadStatus,
                                        ])
                                    </td>
                                    <td>
                                        <div class="hstack flex gap-3 text-[.9375rem]" style="align-items:flex-start;">
                                            @if ($isQueue)
                                                <a aria-label="Add Follow-up"
                                                    href="{{ route('admin.operations.followups.create', $case) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                    title="Add Follow-up">
                                                    <i class="ri-add-line"></i>
                                                </a>
                                            @endif

                                            @if ($case->lead_id)
                                                <a aria-label="View Lead"
                                                    href="{{ route('admin.leads.view', $case->lead_id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                                    target="_blank" title="View Lead">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            @endif

                                            @if ($isQueue)
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full complete-case-btn"
                                                    data-complete-url="{{ route('admin.operations.case.complete', $case) }}"
                                                    data-client-name="{{ optional($client)->name ?? 'this lead' }}"
                                                    title="Complete">
                                                    <i class="ri-check-line"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isQueue ? 9 : 11 }}" class="text-center">
                                        {{ $emptyMessage }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $cases->links() }}
            </div>
        </div>
    </div>
</div>
