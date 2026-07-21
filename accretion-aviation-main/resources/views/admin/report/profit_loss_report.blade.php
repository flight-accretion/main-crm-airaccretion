@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
  <div>
    <h3
      class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
      Profit/Loss Report</h3>
  </div>
  <ol class="flex items-center whitespace-nowrap min-w-0">
    <li class="text-[0.813rem] ps-[0.5rem]">
      <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
        href="{{ route('admin.report.profit-loss') }}">
        Dashboard
        <i
          class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
      </a>
    </li>
    <li
      class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
      aria-current="page">Profit/Loss Report
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
        <form class="ti-custom-validation view-client-filters" method="GET"
          action="{{ route('admin.report.profit-loss') }}" id="filter-form" novalidate>
          <div class="grid grid-cols-12 sm:gap-6 flex items-center">
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="representative" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales Person</label>
              <select name="representative_user_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Sales Person</option>
                @if(isset($staff))
                @foreach ($staff as $user)
                <option value="{{ $user->id }}" {{ request('representative_user_id')==$user->id ? 'selected' : '' }}>
                  {{ $user->name }}</option>
                @endforeach
                @endif
              </select>
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
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
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
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
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="manager_user_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Manager</label>
              <select name="manager_user_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Manager</option>
                @if(isset($managers))
                @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" {{ request('manager_user_id')==$manager->id ? 'selected' : '' }}>{{
                  $manager->name }}</option>
                @endforeach
                @endif
              </select>
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="from-payment-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Payment
                Date</label>
              <input type="date" name="from_payment_date" class="ti-form-input rounded-sm form-control-sm"
                id="from-payment-date" value="{{ request('from_payment_date') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="to-payment-date" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">To Payment
                Date</label>
              <input type="date" name="to_payment_date" class="ti-form-input rounded-sm form-control-sm"
                id="to-payment-date" value="{{ request('to_payment_date') }}">
            </div>
          </div>

          <div class="grid grid-cols-12 sm:gap-6 mt-4">
            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12 text-end">
              <div class="flex gap-2 justify-end">
                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                  <i class="ti ti-filter me-2"></i>Apply Filters
                </button>
                <button type="button" onclick="clearFilters()" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                  title="Reset Filters">
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
        <div class="box-title">
          Profit/Loss Report
        </div>
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
          <table class="table display responsive nowrap table-datatable" width="100%"
            data-empty-msg="No profit/loss data found">
            <thead class="bg-primary text-white">
              <tr class="border-b border-defaultborder">
                <th>S.No</th>
                <th scope="col" class="text-start">Customer Name</th>
                <th scope="col" class="text-start">Client Received Amount</th>
                <th scope="col" class="text-start">Paid Date</th>
                <th scope="col" class="text-start">Vendor Name</th>
                <th scope="col" class="text-start">Vendor Amount</th>
                <th scope="col" class="text-start">Profit/Loss</th>
                <th scope="col" class="text-start">Profit/Loss %</th>
                <th scope="col" class="text-start">Sales Person</th>
                <th scope="col" class="text-start">Manager</th>
              </tr>
            </thead>
            <tbody>
              @if(isset($profitLossData) && $profitLossData->count() > 0)
              @foreach($profitLossData as $index => $row)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row->customer_name }}</td>
                <td>₹{{ number_format($row->client_received_amount, 2) }}</td>
                <td>{{ $row->paid_date }}</td>
                <td>{{ $row->vendor_name }}</td>
                <td>₹{{ number_format($row->vendor_amount, 2) }}</td>
                <td class="{{ $row->profit_loss >= 0 ? 'text-success' : 'text-danger' }}">
                  ₹{{ number_format($row->profit_loss, 2) }}
                </td>
                <td class="{{ $row->profit_loss_percent >= 0 ? 'text-success' : 'text-danger' }}">
                  {{ number_format($row->profit_loss_percent, 2) }}%
                </td>
                <td>{{ $row->sales_person_name }}</td>
                <td>{{ $row->manager_name }}</td>
              </tr>
              @endforeach
              @else
              <tr>
                <td colspan="10" class="text-center">No data available</td>
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

    // Export handlers - build full export URL and copy current filters
    $('.export-excel-btn').on('click', function() {
      try {
        const exportUrl = new URL('{{ route("admin.report.profit-loss.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'xlsx');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.profit-loss.export") }}?format=xlsx';
      }
    });

    $('.export-csv-btn').on('click', function() {
      try {
        const exportUrl = new URL('{{ route("admin.report.profit-loss.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'csv');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.profit-loss.export") }}?format=csv';
      }
    });

    function clearFilters() {
      $('#filter-form')[0].reset();
      window.location.href = '{{ route("admin.report.profit-loss") }}';
    }

    // Attach clearFilters to window so inline onclick still works if needed
    window.clearFilters = clearFilters;

    // Toggle filters
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