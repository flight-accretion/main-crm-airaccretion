@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
  <div>
    <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white text-[1.125rem] font-semibold">
      Vendor Report</h3>
  </div>
  <ol class="flex items-center whitespace-nowrap min-w-0">
    <li class="text-[0.813rem] ps-[0.5rem]"><a class="flex items-center text-primary hover:text-primary truncate"
        href="{{ route('admin.report.vendor') }}">Dashboard
        <i
          class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
      </a>
    </li>
    <li
      class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50"
      aria-current="page">Vendor Report</li>
  </ol>
</div>
<!-- Page Header Close -->

<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12">
    <div class="box">
      <div class="box-header">
        <div class="box-title">Search Filters</div>
        <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
          <i class="ti ti-chevron-up" id="filter-icon"></i>
        </button>
      </div>
      <div class="box-body" id="filter-section">
        <form class="ti-custom-validation view-client-filters" method="GET" action="{{ route('admin.report.vendor') }}"
          id="filter-form" novalidate>
          <div class="grid grid-cols-12 sm:gap-6 flex items-center">
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label class="ti-form-label">From Payment Date</label>
              <input type="date" name="from_payment_date" class="ti-form-input rounded-sm form-control-sm"
                value="{{ request('from_payment_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label class="ti-form-label">To Payment Date</label>
              <input type="date" name="to_payment_date" class="ti-form-input rounded-sm form-control-sm"
                value="{{ request('to_payment_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label class="ti-form-label">Vendor</label>
              <select name="vendor_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">All Vendors</option>
                @if(isset($vendors))
                @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id ? 'selected' : '' }}>{{
                  $vendor->name }}</option>
                @endforeach
                @endif
              </select>
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label class="ti-form-label">Service</label>
              <select name="service_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">All Services</option>
                @if(isset($services))
                @foreach ($services as $service)
                <option value="{{ $service->id }}" {{ request('service_id')==$service->id ? 'selected' : '' }}>{{
                  $service->service }}</option>
                @endforeach
                @endif
              </select>
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label class="ti-form-label">Payment Status</label>
              <select name="status" class="ti-form-select rounded-sm form-control-sm">
                <option value="">All</option>
                <option value="paid" {{ request('status')=='paid' ? 'selected' : '' }}>Full Paid</option>
                <option value="partial" {{ request('status')=='partial' ? 'selected' : '' }}>Partial Paid</option>
                <option value="unpaid" {{ request('status')=='unpaid' ? 'selected' : '' }}>Unpaid</option>
              </select>
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <div class="flex gap-2">
                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">Apply Filters</button>
                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2" onclick="clearFilters()"><i
                    class="ri-refresh-line"></i></button>
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
        <div class="box-title">Vendor Report</div>
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
        <div class="table-responsive">
          <table class="table display responsive nowrap table-datatable" width="100%">
            <thead class="bg-primary text-white">
              <tr class="border-b border-defaultborder">
                <th>S.No</th>
                <th>Vendor Name</th>
                <th>Booking Slip Number</th>
                <th>Product</th>
                <th>Customer Name</th>
                <th>Number</th>
                <th>Vendor Service Cost</th>
                <th>Balance Amount</th>
                <th>Paid Amount</th>
                <th>Paid Date</th>
                <th>Ride Status</th>
                <th>Service Date</th>
                <th>Booking Date</th>
                <th>Manager Name</th>
              </tr>
            </thead>
            <tbody>
              @if(isset($rows) && $rows->count() > 0)
              @foreach($rows as $index => $row)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row->vendor_name }}</td>
                <td>{{ $row->booking_slip_number }}</td>
                <td>{{ $row->product }}</td>
                <td>{{ $row->client_name }}</td>
                <td>{{ $row->client_contact }}</td>
                <td>{{ number_format($row->vendor_service_cost, 2) }}</td>
                <td>{{ number_format($row->balance_amount, 2) }}</td>
                <td>{{ number_format($row->paid_amount, 2) }}</td>
                <td>{{ $row->paid_date }}</td>
                <td>{{ $row->ride_status }}</td>
                <td>{{ $row->service_date }}</td>
                <td>{{ $row->booking_date }}</td>
                <td>{{ $row->manager_name }}</td>
              </tr>
              @endforeach
              @else
              <tr>
                <td colspan="14" class="text-center">No vendor payments found</td>
              </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  $(document).ready(function() {
    $('.export-excel-btn').on('click', function() {
      try {
        const exportUrl = new URL('{{ route("admin.report.vendor.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'xlsx');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.vendor.export") }}?format=xlsx';
      }
    });

    $('.export-csv-btn').on('click', function() {
      try {
        const exportUrl = new URL('{{ route("admin.report.vendor.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'csv');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.vendor.export") }}?format=csv';
      }
    });
  });

  function clearFilters() {
    $('#filter-form')[0].reset();
    window.location.href = '{{ route("admin.report.vendor") }}';
  }
  // Toggle filters
  $(document).ready(function() {
    const filterSection = $('#filter-section');
    const icon = $('#filter-icon');

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