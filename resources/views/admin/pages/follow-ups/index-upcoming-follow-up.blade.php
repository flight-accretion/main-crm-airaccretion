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
                    Today's Follow-up
                </div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>

            <div class="box-body" id="filter-section">
                <form class="ti-custom-validation" id="filter-form" method="GET"
                    action="{{ route('admin.upcoming-follow-up.index') }}" novalidate>
                    @csrf
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

                    @if (isset($objFollowUp))
                    @method('POST')
                    @endif
                    <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Next
                                Follow-up
                                Date</label>
                            <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm"
                                id="from-date" value="{{ $fromDate->format('Y-m-d') ?? request('from_date') }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="product_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product
                                *</label>
                            <select class="js-example-basic-single w-full form-control-sm" name="product_id"
                                id="product_id" required>
                                <option value="">Select Product</option>
                                @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id')==$product->id ||
                                    request('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->product }}
                                </option>
                                @endforeach
                            </select>
                            @error('product_id')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        @if(!in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_EXECUTIVE]))
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="representative_user_id"
                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Staff</label>
                            <select name="representative_user_id" id="representative_user_id"
                                class="js-example-basic-single w-full form-control-sm">
                                <option value="">Team (All)</option>
                                @if(in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_MANAGER,
                                \App\Models\UserType::SENIOR_SALES_MANAGER]))
                                <option value="{{ Auth::id() }}" {{ request('representative_user_id')==Auth::id()
                                    ? 'selected' : '' }}>{{ Auth::user()->name }} (You)</option>
                                @endif
                                @foreach(($assignedExecutives ?? collect()) as $exec)
                                @php $typeLabel = $exec->userType->user_type ?? ''; @endphp
                                <option value="{{ $exec->id }}" {{ request('representative_user_id')==$exec->id ?
                                    'selected' : '' }}>{{ $exec->name }} @if($typeLabel) ({{ $typeLabel }}) @endif
                                </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
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
                <div class="table-responsive">
                    <table id="followUpTable" class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">

                                <th>Sr.No</th>
                                <th>Name</th>
                                <!-- <th>Email</th> -->
                                <th>Phone</th>
                                <th>Representative</th>
                                <th>Next Follow-up</th>
                                <th>Service</th>
                                <th>Created Date</th>
                                <th>Service Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($arrFollowUps as $intKey => $obj)
                            {{-- If follow-up is missed (scheduled earlier but not done), highlight in yellow --}}
                            <tr class="border-b border-defaultborder" @if(!empty($obj->is_missed) && $obj->is_missed)
                                style="background-color: #fded57ff;" @endif>
                                <td class="text-center">{{ $intKey + 1 }}</td>
                                <td>{{ $obj->enquiry->client->name }}</td>
                                <!-- <td>{{ $obj->enquiry->client->email }}</td> -->
                                <td>{{ $obj->enquiry->client->contact_number }}</td>
                                <td>{{ $obj->enquiry->representative->name ?? '--' }}</td>
                                <td class="text-center"
                                    data-order="{{ $obj->next_followup_date ? $obj->next_followup_date->format('Y-m-d H:i:s') : '' }}">
                                    {{ $obj->next_followup_date ? $obj->next_followup_date->format('d-M, Y H:i') : '--'
                                    }}
                                </td>
                                <td>
                                    @php
                                    $serviceNames = $obj->enquiry->service_names ?? [];
                                    @endphp

                                    @if (!empty($serviceNames) && is_array($serviceNames))
                                    {{ Str::limit(implode(', ', $serviceNames), 50) }}
                                    @else
                                    N/A
                                    @endif
                                </td>

                                <td class="text-center"
                                    data-order="{{ $obj->enquiry->created_at ? $obj->enquiry->created_at->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                                    {{ $obj->enquiry->created_at ? $obj->enquiry->created_at->format('d-M, Y') : '--' }}
                                </td>
                                <td class="text-center"
                                    data-order="{{ $obj->enquiry->rideSegments->first()?->from_date ? $obj->enquiry->rideSegments->first()->from_date->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                                    {{ $obj->enquiry->rideSegments->first()?->from_date ?
                                    $obj->enquiry->rideSegments->first()->from_date->format('d-M, Y ') : '--' }}
                                </td>
                                <td>
                                    <div class="hstack flex gap-3 text-[.9375rem]">
                                        <a aria-label="anchor"
                                            href="{{ route('admin.leads.follow-up.create', $obj->enquiry->id) }}"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                            title="View Lead"><i class="ri-eye-line"></i></a>

                                        {{-- <a aria-label="anchor"
                                            href="{{ route('admin.leads.edit', $obj->enquiry->id) }}"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" title="Edit Lead"><i
                                                class="ri-edit-line"></i></a>
                                        </a> --}}
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

<!-- Custom Modal -->
<div id="custom-delete-alert" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
        <button type="button" class="btn-close ms-auto" id="close-alert">
            <i class="bi bi-x"></i>
        </button>
        <div class="text-center px-[3rem] pb-0">
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
            <p class="mb-4 text-gray-600" id="modal-message">Confirm action</p>
            <form method="POST" id="confirm-delete-form" action="{{ route('admin.upcoming-follow-up.toggle') }}">
                @csrf
                <input type="hidden" name="id" id="id-to-toggle">
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                        id="decline-delete">Decline</button>
                    <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Yes, Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop
@push('scripts')
<!-- Script -->
<script>
    document.querySelectorAll('.open-delete-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;
                const status = this.dataset.status;

                document.getElementById('id-to-toggle').value = id;
                document.getElementById('modal-message').textContent =
                    status == 1 ? `You want to deactivate "${title}"?` : `You want to activate "${title}"?`;

                document.getElementById('custom-delete-alert').classList.remove('hidden');
            });
        });

        document.getElementById('close-alert').addEventListener('click', () => {
            document.getElementById('custom-delete-alert').classList.add('hidden');
        });

        document.getElementById('decline-delete').addEventListener('click', () => {
            document.getElementById('custom-delete-alert').classList.add('hidden');
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.upcoming-follow-up.index') }}";
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