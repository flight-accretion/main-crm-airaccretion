@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
  <div>
    <h3
      class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
      Sales Report</h3>
  </div>
  <ol class="flex items-center whitespace-nowrap min-w-0">
    <li class="text-[0.813rem] ps-[0.5rem]">
      <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
        href="{{ route('admin.report.sales') }}">
        Dashboard
        <i
          class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
      </a>
    </li>
    <li
      class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
      aria-current="page">Sales Report
    </li>
  </ol>
</div>
<!-- Page Header Close -->

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
        <form class="ti-custom-validation view-client-filters" method="GET" action="{{ route('admin.report.sales') }}"
          id="filter-form" novalidate>
          <div class="grid grid-cols-12 sm:gap-6 flex items-center">
            {{-- <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Service Date</label>
              <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm" id="from-date"
                value="{{ request('from_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="to-date" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">To Service Date</label>
              <input type="date" name="to_date" class="ti-form-input rounded-sm form-control-sm" id="to-date"
                value="{{ request('to_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="from-create-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Create
                Date</label>
              <input type="date" name="from_create_date" class="ti-form-input rounded-sm form-control-sm"
                id="from-create-date" value="{{ request('from_create_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="to-create-date" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">To Create
                Date</label>
              <input type="date" name="to_create_date" class="ti-form-input rounded-sm form-control-sm"
                id="to-create-date" value="{{ request('to_create_date') }}">
            </div> --}}

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="month" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Month</label>
              <select name="month" class="ti-form-select rounded-sm form-control-sm">
                <option value="">Select Month</option>
                @foreach(range(1, 12) as $m)
                <option value="{{ $m }}" {{ request('month')==$m ? 'selected' : '' }}>
                  {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                </option>
                @endforeach
              </select>
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="year" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Year</label>
              <select name="year" class="ti-form-select rounded-sm form-control-sm">
                <option value="">Select Year</option>
                @php
                $currentYear = date('Y');
                $startYear = $currentYear - 5; // Adjust range as needed
                $endYear = $currentYear + 1;
                @endphp
                @for($y = $endYear; $y >= $startYear; $y--)
                <option value="{{ $y }}" {{ request('year')==$y ? 'selected' : '' }}>
                  {{ $y }}
                </option>
                @endfor
              </select>
            </div>
            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="manager_user_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales
                Manager</label>
              <select name="manager_user_id" id="manager_user_id"
                class="js-example-basic-single w-full form-control-sm">
                <option value="">All Managers</option>
                @if(isset($managers))
                @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" {{ request('manager_user_id')==$manager->id ? 'selected' : '' }}>
                  {{ $manager->name }} ({{ $manager->userType->user_type ?? 'N/A' }})
                </option>
                @endforeach
                @endif
              </select>
            </div>

            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="representative_user_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales
                Person</label>
              <select name="representative_user_id" id="representative_user_id"
                class="js-example-basic-single w-full form-control-sm">
                <option value="">All Sales Persons</option>
                @if(isset($staff))
                @foreach ($staff as $user)
                <option value="{{ $user->id }}" {{ request('representative_user_id')==$user->id ? 'selected' : '' }}>
                  {{ $user->name }} ({{ $user->userType->user_type ?? 'N/A' }})
                </option>
                @endforeach
                @endif
              </select>
            </div>



            {{-- <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="product" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
              <select name="product_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Product</option>
                @if(isset($products))
                @foreach ($products as $product)
                <option value="{{ $product->id }}" {{ request('product_id')==$product->id ? 'selected' : '' }}>
                  {{ $product->product }}</option>
                @endforeach
                @endif
              </select>
            </div> --}}

            {{-- <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="service" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
              <select name="service_name" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Service</option>
                @if(isset($services))
                @foreach ($services as $service)
                <option value="{{ $service->id }}" {{ request('service_name')==$service->id ? 'selected' : '' }}>
                  {{ $service->service }}</option>
                @endforeach
                @endif
              </select>
            </div> --}}

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="status" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride Status</label>
              <select name="status" class="ti-form-select rounded-sm form-control-sm">
                <option value="">Select Status</option>
                @foreach ($statusArray as $key=>$statusOption)
                <option value="{{ $key }}" {{ request('status')==$key && request('status') !='' ? 'selected' : '' }}>
                  {{ $statusOption }}
                </option>
                @endforeach
              </select>
            </div>

            <!-- <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="name" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Client Name</label>
              <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm" id="name"
                value="{{ request('name') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="email" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
              <input type="text" name="email" class="ti-form-input rounded-sm form-control-sm" id="email"
                value="{{ request('email') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="phone" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone</label>
              <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm" id="phone"
                value="{{ request('phone') }}">
            </div> -->

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <div class="flex gap-2">
                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">Apply Filters
                </button>
                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2" onclick="clearFilters()">
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
        <div class="box-title">Sales Report</div>
        <div class="export-buttons flex gap-2 mb-3">
          <button type="button" class="ti-btn ti-btn-success-full ti-btn-sm export-excel-btn" title="Export to Excel">
            <i class="ri-file-excel-line"></i>
          </button>
          <button type="button" class="ti-btn ti-btn-info-full ti-btn-sm export-csv-btn" title="Export to CSV">
            <i class="ri-file-text-line"></i>
          </button>
        </div>
      </div>
      <div class="box-body">
        @if (!$hasFilters)
        {{-- CASE 1: No filters applied, show info message --}}
        <div class="alert alert-info text-center">
          <i class="ri-information-line me-2"></i>
          Please apply at least one filter to view sales data.
        </div>
        @elseif(isset($salesData) && count($salesData) > 0)
        {{-- CASE 2: Filters applied and records found, show the table --}}
        <div class="table-responsive">
          <table class="table display responsive nowrap table-datatable" width="100%">
            <thead class="bg-primary text-white">
              <tr class="border-b border-defaultborder">
                <th data-priority="1">S.No</th>
                <th data-priority="2">Client Name</th>
                <th data-priority="3">Email</th>
                <th data-priority="4">Contact</th>
                <th data-priority="5">Received Amount</th>
                <th data-priority="6">Pending Amount</th>
                <th data-priority="7">Total Amount</th>
                <th data-priority="8">Refund Amount</th>
                <th data-priority="9">Sales Amount</th>
                <th data-priority="10">Sales Person</th>
                <th data-priority="11">Product Name</th>
                <th data-priority="12">Service</th>
                <th data-priority="13">Extra Service</th>
                <th data-priority="14">Paid Date</th>
                <th data-priority="15">Ride Status</th>
                <th data-priority="16">Booking Date</th>
                <th data-priority="17">Service Date</th>
                <th data-priority="18">Manager Name</th>
              </tr>
            </thead>
            <tbody>
              @foreach($salesData as $index => $sale)
              <tr class="border-b border-defaultborder">
                <td>{{ $index + 1 }}</td>
                <td>{{ $sale->client_name }}</td>
                <td>{{ $sale->email }}</td>
                <td>{{ $sale->contact }}</td>
                <td>{{ number_format($sale->received_amount, 2) }}</td>
                <td>{{ number_format($sale->pending_amount, 2) }}</td>
                <td>{{ number_format($sale->total_amount, 2) }}</td>
                <td>{{ number_format($sale->refund_amount, 2) }}</td>
                <td>{{ number_format($sale->sales_amount, 2) }}</td>
                <td>{{ $sale->sales_person_name }}</td>
                <td>{{ $sale->product_name }}</td>
                <td>{{ $sale->service }}</td>
                <td>{{ $sale->extra_service }}</td>
                <td>{{ $sale->paid_date }}</td>
                <td>
                  @php
                  $status = $sale->status;
                  @endphp
                  @if($status === 0)
                  <span class="badge bg-secondary/10 text-secondary">Initiated</span>
                  @elseif($status === 1)
                  <span class="badge bg-success/10 text-success">Active</span>
                  @elseif($status === 2)
                  <span class="badge bg-danger/10 text-danger">Cancelled</span>
                  @elseif($status === 3)
                  <span class="badge bg-primary/10 text-primary">Full Payment Received</span>
                  @elseif($status === 4)
                  <span class="badge bg-warning/10 text-warning">Partial Payment Received</span>
                  @elseif($status === 5)
                  <span class="badge bg-info/10 text-info">completed</span>
                  @elseif($status === 6)
                  <span class="badge bg-default/10 text-default">Pending</span>
                  @elseif($status === 7)
                  <span class="badge bg-secondary/10 text-pri">Rescheduled</span>
                  @elseif($status === 8)
                  <span class="badge bg-warning/10 text-warning">Approved</span>
                  @elseif($status === 9)
                  <span class="badge bg-danger/10 text-danger">Rejected</span>
                  @else
                  <span class="badge bg-default/10 text-default">N/A</span>
                  @endif
                </td>
                <td>{{ $sale->booking_date }}</td>
                <td>{{ $sale->service_date }}</td>
                <td>{{ $sale->manager_name }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        {{-- CASE 3: Filters applied but no records found --}}
        <div class="alert alert-warning text-center">
          <i class="ri-error-warning-line me-2"></i>
          No records found matching your criteria.
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  // Store all original sales person options for fallback
  var allSalesPersonOptions = $('#representative_user_id').html();

  $(document).ready(function() {

    // Cascade: when Sales Manager changes, filter Sales Person dropdown
    $('#manager_user_id').on('change', function() {
      var managerId = $(this).val();
      var $salesPersonSelect = $('#representative_user_id');
      var currentSalesPerson = '{{ request("representative_user_id") }}';

      if (!managerId) {
        // No manager selected - restore all sales persons
        $salesPersonSelect.html(allSalesPersonOptions);
        // Re-initialize select2 if used
        if ($.fn.select2 && $salesPersonSelect.hasClass('js-example-basic-single')) {
          $salesPersonSelect.trigger('change.select2');
        }
        return;
      }

      // Fetch sales persons for the selected manager via AJAX
      $.ajax({
        url: '{{ route("admin.report.sales.persons-by-manager") }}',
        type: 'GET',
        data: { manager_id: managerId },
        success: function(response) {
          var options = '<option value="">All Sales Persons</option>';
          if (response.data && response.data.length > 0) {
            response.data.forEach(function(person) {
              var selected = (person.id == currentSalesPerson) ? ' selected' : '';
              options += '<option value="' + person.id + '"' + selected + '>' + person.name + ' (' + person.user_type + ')</option>';
            });
          }
          $salesPersonSelect.html(options);
          // Re-initialize select2 if used
          if ($.fn.select2 && $salesPersonSelect.hasClass('js-example-basic-single')) {
            $salesPersonSelect.trigger('change.select2');
          }
        },
        error: function() {
          // On error, restore all options
          $salesPersonSelect.html(allSalesPersonOptions);
          if ($.fn.select2 && $salesPersonSelect.hasClass('js-example-basic-single')) {
            $salesPersonSelect.trigger('change.select2');
          }
        }
      });
    });

    // Trigger on page load if a manager is already selected (e.g. after filter apply)
    if ($('#manager_user_id').val()) {
      $('#manager_user_id').trigger('change');
    }

    // Helper: collect current filter values from the form inputs
    function getFilterParams() {
      var params = new URLSearchParams();
      // Gather all form inputs that have a value
      $('#filter-form').find('input, select').each(function() {
        var name = $(this).attr('name');
        var val = $(this).val();
        if (name && val && val !== '') {
          params.set(name, val);
        }
      });
      return params;
    }

    // Export handlers - export using current filter form values
    $('.export-excel-btn').on('click', function() {
      var params = getFilterParams();
      params.set('format', 'xlsx');
      window.location.href = '{{ route("admin.report.sales.export") }}?' + params.toString();
    });

    $('.export-csv-btn').on('click', function() {
      var params = getFilterParams();
      params.set('format', 'csv');
      window.location.href = '{{ route("admin.report.sales.export") }}?' + params.toString();
    });
  });

  function clearFilters() {
    $('#filter-form')[0].reset();
    window.location.href = '{{ route("admin.report.sales") }}';
  }

  // Toggle filters
  $(document).ready(function() {
    const filterSection = $('#filter-section');
    const icon = $('#filter-icon');

    // filterSection.hide();
    // icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

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