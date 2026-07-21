@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
  <div>
    <h3
      class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
      KPI Report</h3>
  </div>
  <ol class="flex items-center whitespace-nowrap min-w-0">
    <li class="text-[0.813rem] ps-[0.5rem]">
      <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
        href="{{ route('admin.report.kpi') }}">
        Dashboard
        <i
          class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
      </a>
    </li>
    <li
      class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
      aria-current="page">KPI Report</li>
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
        <form class="ti-custom-validation view-client-filters" method="GET" action="{{ route('admin.report.kpi') }}"
          id="filter-form" novalidate>
          <div class="grid grid-cols-12 sm:gap-6 flex items-center">
            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="representative" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales Person</label>
              <select name="representative_user_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Sales Person</option>
                @if(isset($staff))
                @foreach ($staff as $user)
                <option value="{{ $user->id }}" {{ request('representative_user_id')==$user->id ? 'selected' : '' }}>{{
                  $user->name }}</option>
                @endforeach
                @endif
              </select>
            </div>

            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
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
              <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Created Date</label>
              <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm" id="from-date"
                value="{{ request('from_date') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="to-date" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">To Created Date</label>
              <input type="date" name="to_date" class="ti-form-input rounded-sm form-control-sm" id="to-date"
                value="{{ request('to_date') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="month" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">Month</label>
              <input type="month" name="month" class="ti-form-input rounded-sm form-control-sm" id="month"
                value="{{ request('month') }}">
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
        <div class="box-title">KPI Report</div>
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
            data-empty-msg="No KPI data found">
            <thead class="bg-primary text-white">
              <tr class="border-b border-defaultborder">
                <th>S.No</th>
                <th scope="col" class="text-start">Employee</th>
                <th scope="col" class="text-start">Total Leads</th>
                <th scope="col" class="text-start">Active</th>
                <th scope="col" class="text-start">Cancelled</th>
                <th scope="col" class="text-start">Completed</th>
                <th scope="col" class="text-start">Conversion Rate</th>
                <th scope="col" class="text-start">Target Amount</th>
                <th scope="col" class="text-start">Achieved Amount</th>
                <th scope="col" class="text-start">Remaining Amount</th>
                <th scope="col" class="text-start">Action</th>
              </tr>
            </thead>
            <tbody>
              @if(isset($kpiData) && $kpiData->count() > 0)
              @foreach($kpiData as $index => $row)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row->employee }}</td>
                <td>{{ $row->total_leads }}</td>
                <td>{{ $row->active }}</td>
                <td>{{ $row->cancelled }}</td>
                <td>{{ $row->completed }}</td>
                <td>{{ number_format($row->conversion_rate, 2) }}%</td>
                <td>{{ number_format($row->target_amount, 2) }}</td>
                <td>{{ number_format($row->achieved_amount, 2) }}</td>
                <td>{{ number_format($row->remaining_amount, 2) }}</td>
                <td>
                  @if($row->representative_id)
                  <button type="button" class="ti-btn ti-btn-success-full ti-btn-sm individual-export-btn"
                    data-representative-id="{{ $row->representative_id }}" data-employee-name="{{ $row->employee }}"
                    title="Export {{ $row->employee }} KPI Report">
                    <i class="ri-file-excel-line"></i>
                  </button>
                  @else
                  <span class="text-gray-400">-</span>
                  @endif
                </td>
              </tr>
              @endforeach
              @else
              <tr>
                <td colspan="11" class="text-center">No data available</td>
              </tr>
              @endif
            </tbody>
            @if(isset($kpiTotals))
            <tfoot>
              <tr class="bg-primary/5">
                <th colspan="7" class="text-end">Totals</th>
                <th>{{ number_format($kpiTotals['target_amount'], 2) }}</th>
                <th>{{ number_format($kpiTotals['achieved_amount'], 2) }}</th>
                <th>{{ number_format($kpiTotals['remaining_amount'], 2) }}</th>
                <th></th>
              </tr>
            </tfoot>
            @endif
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
        const exportUrl = new URL('{{ route("admin.report.kpi.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'xlsx');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.kpi.export") }}?format=xlsx';
      }
    });

    $('.export-csv-btn').on('click', function() {
      try {
        const exportUrl = new URL('{{ route("admin.report.kpi.export") }}');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'csv');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ route("admin.report.kpi.export") }}?format=csv';
      }
    });

    // Individual export button click handler
    $(document).on('click', '.individual-export-btn', function() {
      const representativeId = $(this).data('representative-id');
      const employeeName = $(this).data('employee-name');
      try {
        const baseUrl = '{{ url("admin/report/kpi/export-individual") }}';
        const exportUrl = new URL(baseUrl + '/' + representativeId);
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('format', 'xlsx');
        exportUrl.search = currentParams.toString();
        window.location.href = exportUrl.toString();
      } catch (e) {
        window.location.href = '{{ url("admin/report/kpi/export-individual") }}/' + representativeId + '?format=xlsx';
      }
    });

    function clearFilters() {
      $('#filter-form')[0].reset();
      window.location.href = '{{ route("admin.report.kpi") }}';
    }
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
   $('#month').on('change', function () {
      const hasMonth = $(this).val() !== '';

      if (hasMonth) {
        $('#from-date').val('');
        $('#to-date').val('');
      }

      $('#from-date, #to-date').prop('disabled', hasMonth);
    });
  });

</script>

@endpush