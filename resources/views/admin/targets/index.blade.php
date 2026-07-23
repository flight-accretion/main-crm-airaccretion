@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
    </div>
    <!-- Page Header -->
    
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
    
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="add-target-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                            viewBox="0 0 24 24" width="24px" fill="#000000">
                                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"></path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Assign New Target</h5>
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="add-target-form">
                                                <svg class="hs-accordion-active:hidden block size-4 ml-2"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M5 12h14" />
                                                    <path d="M12 5v14" />
                                                </svg>
                                                <svg class="hs-accordion-active:block hidden size-4 ml-2"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M5 12h14" />
                                                </svg>
                                                Assign Target
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-target-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300 @if ($errors->add->any()) !block @endif"
                            aria-labelledby="add-target-accordion">
                            <div class="box-body">
                                <form class="ti-custom-validation" id="add-target-form-element" novalidate method="POST" action="{{ route('admin.targets.store') }}">
                                    @csrf
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="sales_executive_id" class="ti-form-label mb-0">Sales Executive<span class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm select2" name="sales_executive_id" id="sales_executive_id" required>
                                                <option value="">Select Sales Executive / Manager</option>
                                                @foreach($assignableStaff as $staff)
                                                    <option value="{{ $staff->id }}" {{ old('sales_executive_id') == $staff->id ? 'selected' : '' }}>
                                                        {{ $staff->name }} ({{ $staff->userType->user_type ?? 'N/A' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('sales_executive_id')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-2 col-span-12">
                                            <label for="add_year" class="ti-form-label mb-0">Year<span
                                                    class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm" name="year"
                                                id="add_year" required>
                                                @for ($i = date('Y') - 2; $i <= date('Y') + 2; $i++)
                                                    <option value="{{ $i }}" {{ $i == date('Y') ? 'selected' : '' }}>
                                                        {{ $i }}
                                                    </option>
                                                @endfor
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="xl:col-span-2 col-span-12">
                                            <label for="add_month" class="ti-form-label mb-0">Month<span
                                                    class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm" name="month"
                                                id="add_month" required>
                                                <option value="1" {{ date('n') == 1 ? 'selected' : '' }}>January</option>
                                                <option value="2" {{ date('n') == 2 ? 'selected' : '' }}>February</option>
                                                <option value="3" {{ date('n') == 3 ? 'selected' : '' }}>March</option>
                                                <option value="4" {{ date('n') == 4 ? 'selected' : '' }}>April</option>
                                                <option value="5" {{ date('n') == 5 ? 'selected' : '' }}>May</option>
                                                <option value="6" {{ date('n') == 6 ? 'selected' : '' }}>June</option>
                                                <option value="7" {{ date('n') == 7 ? 'selected' : '' }}>July</option>
                                                <option value="8" {{ date('n') == 8 ? 'selected' : '' }}>August</option>
                                                <option value="9" {{ date('n') == 9 ? 'selected' : '' }}>September</option>
                                                <option value="10" {{ date('n') == 10 ? 'selected' : '' }}>October</option>
                                                <option value="11" {{ date('n') == 11 ? 'selected' : '' }}>November</option>
                                                <option value="12" {{ date('n') == 12 ? 'selected' : '' }}>December</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="add_target_amount" class="ti-form-label mb-0">Target Amount<span
                                                    class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="ti-form-input" 
                                                name="target_amount" id="add_target_amount" placeholder="Enter target amount" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="xl:col-span-6 col-span-12">
                                            <label for="add_description" class="ti-form-label mb-0">Description</label>
                                            <textarea class="ti-form-input" name="description" id="add_description"
                                                placeholder="Enter description" rows="3"></textarea>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="xl:col-span-3 col-span-12">
                                            <label for="add_status" class="ti-form-label mb-0">Status<span
                                                    class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm" name="status"
                                                id="add_status" required>
                                                <option value="active" selected>Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="xl:col-span-3 col-span-12 pt-4">
                                            <button type="submit" class="ti-btn ti-btn-primary ti-custom-validate-btn">
                                                <i class="ti ti-device-floppy"></i> Assign Target
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Targets DataTable -->
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header">
                    <div class="box-title">Search Filters</div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>

                <!-- Filter Section -->
                <div class="box-body" id="filter-section">
                    <form class="ti-custom-validation" method="GET" action="{{ route('admin.targets.index') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-3 col-span-12">
                                <label for="filter_year" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Year</label>
                                <select class="ti-form-select rounded-sm form-control-sm" name="year" id="filter_year">
                                    <option value="">All Years</option>
                                    @for ($i = date('Y') - 2; $i <= date('Y') + 2; $i++)
                                        <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>
                                            {{ $i }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <div class="xl:col-span-3 col-span-12">
                                <label for="filter_month" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Month</label>
                                <select class="ti-form-select rounded-sm form-control-sm" name="month" id="filter_month">
                                    <option value="">All Months</option>
                                    <option value="1" {{ $month == 1 ? 'selected' : '' }}>January</option>
                                    <option value="2" {{ $month == 2 ? 'selected' : '' }}>February</option>
                                    <option value="3" {{ $month == 3 ? 'selected' : '' }}>March</option>
                                    <option value="4" {{ $month == 4 ? 'selected' : '' }}>April</option>
                                    <option value="5" {{ $month == 5 ? 'selected' : '' }}>May</option>
                                    <option value="6" {{ $month == 6 ? 'selected' : '' }}>June</option>
                                    <option value="7" {{ $month == 7 ? 'selected' : '' }}>July</option>
                                    <option value="8" {{ $month == 8 ? 'selected' : '' }}>August</option>
                                    <option value="9" {{ $month == 9 ? 'selected' : '' }}>September</option>
                                    <option value="10" {{ $month == 10 ? 'selected' : '' }}>October</option>
                                    <option value="11" {{ $month == 11 ? 'selected' : '' }}>November</option>
                                    <option value="12" {{ $month == 12 ? 'selected' : '' }}>December</option>
                                </select>
                            </div>

                            <div class="xl:col-span-4 col-span-12">
                                <label for="filter_sales_executive" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales Executive / Manager</label>
                                <select class="js-example-basic-single w-full form-control-sm" name="sales_executive_id" id="filter_sales_executive">
                                    <option value="">All Sales Executives</option>
                                    @foreach($assignableStaff as $staff)
                                        <option value="{{ $staff->id }}" {{ $salesExecutiveId == $staff->id ? 'selected' : '' }}>
                                            {{ $staff->name }} ({{ $staff->userType->user_type ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 sm:gap-6 mt-4">
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12 text-end">
                                <div class="flex gap-2 justify-end">
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                        <i class="ti ti-filter me-2"></i>Apply Filters
                                    </button>
                                    <button type="button" onclick="clearFilters()" class="ti-btn ti-btn-outline-secondary !py-1 !px-2" title="Reset Filters">
                                        <i class="ri-refresh-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if($year || $month || $salesExecutiveId)
                            <div class="mt-3">
                                <span class="badge bg-info">
                                    <i class="ti ti-info-circle"></i> 
                                    Filtered Results
                                    @if($year) - Year: {{ $year }} @endif
                                    @if($month) - Month: {{ DateTime::createFromFormat('!m', $month)->format('F') }} @endif
                                    @if($salesExecutiveId) - Executive: {{ $assignableStaff->firstWhere('id', $salesExecutiveId)->name ?? 'N/A' }} @endif
                                </span>
                            </div>
                        @else
                            <div class="mt-3">
                                <span class="badge bg-success">
                                    <i class="ti ti-calendar"></i> Showing Latest 3 Months Records
                                </span>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Targets Table -->
    <div class="grid grid-cols-12 gap-6 mt-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">Targets Management</div>
                </div>

                <div class="box-body">
                    <div class="table-responsive">
                        <table id="targets-table" class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">Sr.No</th>
                                    <th data-priority="2">Sales Executive</th>
                                    <th data-priority="3">Year</th>
                                    <th data-priority="4">Month</th>
                                    <th data-priority="5">Target Amount</th>
                                    <th data-priority="6">Achieved Amount</th>
                                    <th data-priority="7">Achievement %</th>
                                    <th data-priority="8">Status</th>
                                    <th data-priority="9">Assigned By</th>
                                    <th data-priority="10">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($targets as $target)
                                    <tr class="border-b border-defaultborder">
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $target->salesExecutive->name ?? 'N/A' }}</td>
                                        <td>{{ $target->year }}</td>
                                        <td>{{ $target->month_name }}</td>
                                        <td class="text-end">₹{{ number_format($target->target_amount, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($target->achieved_amount, 2) }}</td>
                                        <td class="text-center">{{ $target->target_amount > 0 ? round(($target->achieved_amount / $target->target_amount) * 100, 2) . '%' : '0%' }}</td>
                                        <td class="text-center"><span class="badge {{ $target->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($target->status) }}</span></td>
                                        <td>{{ $target->assignedBy->name ?? 'N/A' }}</td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <!-- <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-target view-target-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View"
                                                    data-target-id="{{ $target->id }}" data-id="{{ $target->id }}">
                                                    <i class="ri-eye-line"></i>
                                                </button> -->

                                                <a aria-label="anchor"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-target edit-target-btn"
                                                    data-id="{{ $target->id }}" data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>

                                                @if(optional(Auth::user())->isSuperAdmin())
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-target delete-target-btn"
                                                    data-id="{{ $target->id }}" data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
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

    <!-- Edit Target Off-canvas -->
    <div id="edit-target"
        class="edit-target hs-overlay ti-offcanvas ti-offcanvas-right @if ($errors->edit->any()) open @else hidden @endif"
        tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-target-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Target</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                                data-hs-overlay="#edit-target">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.26 1.01C0.35 0.91 0.48 0.86 0.61 0.86C0.74 0.86 0.87 0.91 0.97 1.01L3.61 3.65L6.26 1.01C6.36 0.91 6.55 0.85 6.71 0.89C6.87 0.92 7.02 1.05 7.08 1.16C7.11 1.23 7.12 1.29 7.12 1.36C7.12 1.42 7.1 1.49 7.08 1.55C7.05 1.61 7.01 1.67 6.96 1.71L4.32 4.36L6.96 7.01C7.06 7.1 7.11 7.23 7.11 7.36C7.1 7.49 7.05 7.61 6.96 7.71C6.87 7.8 6.74 7.85 6.61 7.85C6.48 7.85 6.35 7.8 6.26 7.71L3.61 5.07L0.97 7.71C0.87 7.8 0.74 7.85 0.61 7.85C0.48 7.85 0.36 7.8 0.26 7.71C0.17 7.61 0.12 7.49 0.12 7.36C0.12 7.23 0.17 7.1 0.26 7.01L2.9 4.36L0.26 1.71C0.17 1.61 0.12 1.49 0.12 1.36C0.12 1.23 0.17 1.1 0.26 1.01Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ti-offcanvas-body edit-target-body">
            <form class="ti-custom-validation" action=""
                id="edit-target-form" method="POST" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_target_id" name="target_id">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <!-- Sales Executive -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales Executive / Manager<span class="text-danger">*</span></label>
                                        <select name="sales_executive_id" id="edit_sales_executive_id" class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select Sales Executive / Manager</option>
                                            @foreach($assignableStaff as $staff)
                                                <option value="{{ $staff->id }}">{{ $staff->name }} ({{ $staff->userType->user_type ?? 'N/A' }})</option>
                                            @endforeach
                                        </select>
                                        @error('sales_executive_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Year -->
                                    <div class="xl:col-span-3 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Year<span class="text-danger">*</span></label>
                                        <select name="year" id="edit_year"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            @for ($i = date('Y') - 2; $i <= date('Y') + 2; $i++)
                                                <option value="{{ $i }}">{{ $i }}</option>
                                            @endfor
                                        </select>
                                        @error('year', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Month -->
                                    <div class="xl:col-span-3 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Month<span class="text-danger">*</span></label>
                                        <select name="month" id="edit_month"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="1">January</option>
                                            <option value="2">February</option>
                                            <option value="3">March</option>
                                            <option value="4">April</option>
                                            <option value="5">May</option>
                                            <option value="6">June</option>
                                            <option value="7">July</option>
                                            <option value="8">August</option>
                                            <option value="9">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                        @error('month', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Target Amount -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Target Amount<span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" name="target_amount" id="edit_target_amount"
                                            class="ti-form-input w-full rounded-sm form-control-sm"
                                            placeholder="Enter target amount" required>
                                        @error('target_amount', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Status -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status<span class="text-danger">*</span></label>
                                        <select name="status" id="edit_status"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        @error('status', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Description -->
                                    <div class="xl:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                        <textarea name="description" id="edit_description" rows="3" 
                                            class="ti-form-input w-full rounded-sm form-control-sm"
                                            placeholder="Enter description"></textarea>
                                        @error('description', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="xl:col-span-12 col-span-12 mt-4">
                                        <button type="submit"
                                            class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Update
                                            Target</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View Target Modal -->
    <div id="viewTargetModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700 dark:shadow-slate-700/[.7]">
                <div class="flex justify-between items-center py-3 px-4 border-b dark:border-gray-700">
                    <h3 class="font-bold text-gray-800 dark:text-white">Target Details</h3>
                    <button type="button" class="hs-overlay-close flex justify-center items-center size-7 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-white dark:hover:bg-gray-700 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-gray-600">
                        <span class="sr-only">Close</span>
                        <svg class="flex-shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m18 6-12 12"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto" id="target-details-content">
                    <!-- Target details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    @endsection
    @include('admin.partials.modals.success-error-modals')
    @push('scripts')
    <script>
        // Clear filters function
        function clearFilters() {
            window.location.href = "{{ route('admin.targets.index') }}";
        }

        // Toggle filter section
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggle-filters');
            const filterSection = document.getElementById('filter-section');
            const filterIcon = document.getElementById('filter-icon');

            if (toggleButton && filterSection && filterIcon) {
                toggleButton.addEventListener('click', function() {
                    if (filterSection.style.display === 'none') {
                        filterSection.style.display = 'block';
                        filterIcon.classList.remove('ti-chevron-down');
                        filterIcon.classList.add('ti-chevron-up');
                    } else {
                        filterSection.style.display = 'none';
                        filterIcon.classList.remove('ti-chevron-up');
                        filterIcon.classList.add('ti-chevron-down');
                    }
                });
            }
        });

        $(document).ready(function() {
            // Use the global DataTable initialization from header.blade.php
            // No need to initialize manually as it's already handled by $('.table-datatable').DataTable()

            function removeOverlayBackdrop(overlaySelector) {
                const overlayId = overlaySelector.replace(/^#/, '');
                $(`#${overlayId}-backdrop`).remove();
            }

            function restorePageScroll() {
                $('html, body').removeClass('overflow-hidden');
                $('body').removeClass('ti-offcanvas-open');

                document.documentElement.style.overflow = '';
                document.documentElement.style.paddingRight = '';
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }

            function cleanupClosedOverlay(overlaySelector) {
                const cleanup = function() {
                    removeOverlayBackdrop(overlaySelector);

                    const hasOpenOverlay = $('.hs-overlay').filter(function() {
                        return !this.classList.contains('hidden') || this.classList.contains('open');
                    }).length > 0;

                    if (!hasOpenOverlay) {
                        restorePageScroll();
                    }
                };

                window.setTimeout(cleanup, 100);
                window.setTimeout(cleanup, 350);
            }

            function openOverlay(overlaySelector) {
                removeOverlayBackdrop(overlaySelector);

                if (window.HSOverlay) {
                    try {
                        window.HSOverlay.open(overlaySelector);
                    } catch (error) {
                        window.HSOverlay.open(document.querySelector(overlaySelector));
                    }
                } else {
                    $(overlaySelector).removeClass('hidden').addClass('open');
                    $('body').addClass('ti-offcanvas-open');
                }
            }

            function closeOverlay(overlaySelector) {
                if (window.HSOverlay) {
                    try {
                        window.HSOverlay.close(overlaySelector);
                    } catch (error) {
                        window.HSOverlay.close(document.querySelector(overlaySelector));
                    }
                }

                $(overlaySelector).addClass('hidden').removeClass('open');
                cleanupClosedOverlay(overlaySelector);
            }
            
            // Helper to populate a select element with assignable staff list
            function populateAssignableSelect(selectEl, list, selectedId) {
                selectEl.empty().append('<option value="">Select Sales Executive / Manager</option>');
                if (list && list.length > 0) {
                    list.forEach(function(item) {
                        selectEl.append(`<option value="${item.id}">${item.name} (${item.user_type || ''})</option>`);
                    });
                }
                if (selectedId) {
                    selectEl.val(selectedId);
                }
            }

            // Edit Target
            $(document).on('click', '.edit-target-btn', function(e) {
                e.preventDefault();
                const targetId = $(this).data('id');
                
                console.log('Edit button clicked, targetId:', targetId);
                
                // Set form action
                $('#edit-target-form').attr('action', `{{ url('/admin/targets') }}/${targetId}`);
                
                // Clear form first
                resetEditForm();
                
                // Fetch and populate data
                fetchTargetDetails(targetId);
            });

            // Function to reset edit form
            function resetEditForm() {
                $('.edit-target-body form')[0].reset();
                $('.text-red-500').remove();
            }

            // Function to fetch target details
            function fetchTargetDetails(targetId) {
                $.ajax({
                    url: `{{ url('/admin/targets') }}/${targetId}/edit`,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Edit response received:', response);
                        if (response.target) {
                                    // Populate edit staff select using assignableStaff returned from server
                                    const editSelect = $('#edit_sales_executive_id');
                                    if (response.assignableStaff) {
                                        populateAssignableSelect(editSelect, response.assignableStaff.map(function(s) {
                                            return { id: s.id, name: s.name, user_type: s.user_type };
                                        }), response.target.sales_executive_id);
                                    } else {
                                        // Fallback: just set the selected executive if present
                                        editSelect.val(response.target.sales_executive_id);
                                    }

                                    // Populate other fields
                                    populateEditForm(response);

                                    // Open off-canvas after population
                                    openOverlay('#edit-target');
                                } else {
                                    console.error('Target data not found in response');
                                }
                    },
                    error: function(xhr) {
                        console.error('Error fetching target details:', xhr);
                        showAlert('error', 'Error loading target details.');
                    }
                });
            }

            // Function to populate edit form
            function populateEditForm(data) {
                console.log('Populating form with data:', data);
                const target = data.target;

                // Populate form fields
                $('#edit_target_id').val(target.id);
                $('#edit_sales_executive_id').val(target.sales_executive_id);
                $('#edit_year').val(target.year);
                $('#edit_month').val(target.month);
                $('#edit_target_amount').val(target.target_amount);
                $('#edit_status').val(target.status);
                $('#edit_description').val(target.description || '');
            }

            // Handle form submission
            $('.edit-target-body form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const actionUrl = $(this).attr('action');
                
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            // Close off-canvas
                            closeOverlay('#edit-target');
                            
                            // Show success message and reload page
                            showAlert('success', 'Target updated successfully!');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error updating target:', xhr);
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            let errorMessage = 'Validation errors:\n';
                            for (let field in xhr.responseJSON.errors) {
                                errorMessage += `${field}: ${xhr.responseJSON.errors[field].join(', ')}\n`;
                            }
                            showAlert('error', errorMessage);
                        } else {
                            showAlert('error', 'Error updating target.');
                        }
                    }
                });
            });

            // Delete Target using app confirmation modal
            $(document).on('click', '.delete-target-btn', function(e) {
                e.preventDefault();
                const targetId = $(this).data('id');

                showConfirmationModal('Delete Target', 'Are you sure you want to delete this target?', function() {
                    $.ajax({
                        url: `{{ url('/admin/targets') }}/${targetId}`,
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                showSuccessModal('Deleted', 'Target deleted successfully!', function() {
                                    location.reload();
                                });
                            } else {
                                showErrorModal('Delete Failed', response.message || 'Failed to delete target.');
                            }
                        },
                        error: function(xhr) {
                            console.error('Error deleting target:', xhr);
                            let errMsg = 'Error deleting target.';
                            if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
                            showErrorModal('Delete Failed', errMsg);
                        }
                    });
                });
            });

            // Show alert function
            function showAlert(type, message) {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                const alertHtml = `<div class="alert ${alertClass} mb-4">${message}</div>`;
                
                $('.page-header').after(alertHtml);
                
                setTimeout(function() {
                    $('.alert').fadeOut();
                }, 5000);
            }

            // Close off-canvas handler
            $(document).on('click', '[data-hs-overlay="#edit-target"], #edit-target-backdrop', function(e) {
                e.preventDefault();
                closeOverlay('#edit-target');
            });
        });
    </script>
    @endpush
