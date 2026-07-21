@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
  <div>
    <h3
      class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
      Report</h3>
  </div>
  <ol class="flex items-center whitespace-nowrap min-w-0">
    <li
      class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
      aria-current="page">Report
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
        <form class="ti-custom-validation view-client-filters" method="GET" action="{{ route('admin.report') }}"
          id="filter-form" novalidate>
          <div class="grid grid-cols-12 sm:gap-6 flex items-center">
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
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
              <label for="from-date" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Created Date</label>
              <input type="date" name="from_create_date" class="ti-form-input rounded-sm form-control-sm"
                id="from-create-date" value="{{ request('from_create_date') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="to-date" class="ti-form-label  dark:text-defaulttextcolor/70 mb-0">To Created Date</label>
              <input type="date" name="to_create_date" class="ti-form-input rounded-sm form-control-sm"
                id="to-create-date" value="{{ request('to_create_date') }}">
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="input-placeholder"
                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Representative</label>
              <select name="representative_user_id" class="js-example-basic-single w-full form-control-sm">
                <option value="">Select Representative</option>
                @if(isset($staff))
                @foreach ($staff as $user)
                <option value="{{ $user->id }}" {{ request('representative_user_id')==$user->id ? 'selected' : '' }}>
                  {{ $user->name }}</option>
                @endforeach
                @endif
              </select>
            </div>

            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="input-placeholder" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
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
              <label for="input-placeholder" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
              <select name="status" class="ti-form-select rounded-sm form-control-sm">
                <option value="">Select Status</option>
                @foreach ($statusArray as $key=>$statusOption)
                <option value="{{ $key }}" {{ request('status')==$key && request('status') !='' ? 'selected' : '' }}>
                  {{ $statusOption }}
                  @endforeach
              </select>
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
              <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm" id="input"
                value="{{ request('name') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="input-label" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
              <input type="text" name="email" class="ti-form-input rounded-sm form-control-sm" id="input-label"
                value="{{ request('email') }}">
            </div>
            <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
              <label for="input-placeholder" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone</label>
              <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm" id="input-placeholder"
                value="{{ request('phone') }}">
            </div>
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
        <div class="box-title">Report</div>
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
        @php
        $hasFilters = (
        request()->filled('from_date') || request()->filled('to_date') ||
        request()->filled('from_create_date') || request()->filled('to_create_date') ||
        request()->filled('representative_user_id') || request()->filled('product_id') ||
        (request()->has('status') && request('status') !== '') ||
        request()->filled('name') || request()->filled('email') || request()->filled('phone') ||
        request()->filled('service_name')
        );
        @endphp

        @if (!$hasFilters)
        <div class="alert alert-info text-center">
          <i class="ri-information-line me-2"></i>
          Please apply at least one filter to view the report.
        </div>
        @elseif(isset($payments) && count($payments) > 0)
        <div class="table-responsive">
          <table class="table display responsive nowrap table-datatable" width="100%">
            <thead class="bg-primary text-white">
              <tr class="border-b border-defaultborder">
                <th data-priority="1">S.No</th>
                <th data-priority="2">Name</th>
                <th data-priority="3">Email</th>
                <th data-priority="5">Phone</th>
                <th data-priority="6">Assigned</th>
                <th data-priority="7">Next Follow Up</th>
                <th data-priority="8">Created Date</th>
                <th data-priority="9">Service Date</th>
                <th data-priority="10">Status</th>
                <th data-priority="11">Products</th>
                <th data-priority="12">Last Update</th>
                <th data-priority="13">Pending Amount</th>
                <th data-priority="14">Amount</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($payments as $key => $payment)
              @php
              $enquiry = App\Models\Lead::with(['client', 'representative', 'rideSegments'])->find($payment->lead_id);
              @endphp
              <tr class="border-b border-defaultborder">
                <td class="text-center">{{ $key + 1 }}</td>
                <td>{{ $payment->first_name }}</td>
                <td>{{ $payment->email }}</td>
                <td class="text-center">{{ $payment->phone_number }}</td>
                <td>
                  @if ($enquiry->representative)
                  {{ $enquiry->representative->name }}
                  @else
                  N/A
                  @endif
                </td>
                <td class="text-center"
                  data-order="{{ $payment->next_followup_date ? $payment->next_followup_date->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                  @if ($payment->next_followup_date)
                  {{ $payment->next_followup_date->format('d-m-Y H:i') }}
                  @else
                  N/A
                  @endif
                </td>
                <td class="text-center"
                  data-order="{{ $enquiry->created_at ? $enquiry->created_at->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                  {{ date('d-m-Y', strtotime($enquiry->created_at)) }}</td>
                <td
                  data-order="{{ $enquiry->rideSegments->count() > 0 ? $enquiry->rideSegments->first()->from_date->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                  @if ($enquiry->rideSegments->count() > 0)
                  @php
                  $firstSegment = $enquiry->rideSegments->first();
                  $lastSegment = $enquiry->rideSegments->last();
                  @endphp
                  From: {{ date('d-m-Y', strtotime($firstSegment->from_date)) }} To:
                  {{ date('d-m-Y', strtotime($lastSegment->to_date)) }}
                  @else
                  N/A
                  @endif
                </td>
                <td class="text-center">
                  @php $status = $payment->status ?? null; @endphp
                  @if ($status === 0)
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
                  <span class="badge bg-info/10 text-info">Confirmed</span>
                  @elseif($status === 6)
                  <span class="badge bg-default/10 text-default">Pending</span>
                  @elseif($status === 7)
                  <span class="badge bg-light/10 text-light">Rescheduled</span>
                  @elseif($status === 8)
                  <span class="badge bg-warning/10 text-warning">Approved</span>
                  @elseif($status === 9)
                  <span class="badge bg-danger/10 text-danger">Rejected</span>
                  @else
                  <span class="badge bg-default/10 text-default">N/A</span>
                  @endif
                </td>
                <td>
                  @php $productNames = $enquiry->product_names ?? []; @endphp
                  @if (!empty($productNames) && is_array($productNames))
                  {{ implode(', ', $productNames) }}
                  @else
                  N/A
                  @endif
                </td>
                <td
                  data-order="{{ $enquiry->updated_at ? $enquiry->updated_at->format('Y-m-d H:i:s') : '0000-00-00 00:00:00' }}">
                  {{ $enquiry->updated_at->format('d-m-Y H:i:s') }}
                </td>
                @php
                $received = (float) $payment->received_amount;
                $total = (float) $payment->total_amount;
                @endphp
                <td>{{ isset($received) ? number_format($received,2) : 0}}</td>
                <td>{{ isset($total) ? number_format($total,2) : 0}}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
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
  $(document).ready(function() {
            let currentLeadId = null;
 
            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
</script>
<script>
  $(document).ready(function() {
  
            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
</script>

<script>
  $(document).ready(function() {
            // Initialize DataTable (use existing initialization from header/custom.js if present)
            var leadsTable = $('.table-datatable').DataTable();

      // Ensure Buttons are available for this DataTable instance. Some pages initialize
      // DataTables without buttons (global init in header/custom.js). To make the
      // external export buttons (.export-*-btn) work we create invisible Buttons
      // for this instance if they don't already exist.
      try {
        var needButtons = false;

        if (typeof leadsTable.buttons !== 'function') {
          needButtons = true;
        } else {
          // buttons() exists — check if any are registered
          try {
            if (leadsTable.buttons().count() === 0) needButtons = true;
          } catch (err) {
            needButtons = true;
          }
        }

        if (needButtons) {
          new $.fn.dataTable.Buttons(leadsTable, {
            buttons: [
              { extend: 'excel', title: 'Report Export - ' + new Date().toLocaleDateString() },
              { extend: 'csv', title: 'Report Export - ' + new Date().toLocaleDateString() },
              { extend: 'pdf', orientation: 'landscape', pageSize: 'A4', title: 'Report Export - ' + new Date().toLocaleDateString() },
              { extend: 'print', title: 'Report Export - ' + new Date().toLocaleDateString() }
            ]
          });

          // Append the buttons container to a hidden element so it exists in DOM
          // (required for .button(...).trigger() to work) but doesn't show UI.
          var hiddenContainer = $('<div style="display:none;" />').appendTo('body');
          leadsTable.buttons().container().appendTo(hiddenContainer);
        }
      } catch (e) {
        // If anything goes wrong, at least log to console (no breaking the page)
        console.error('DataTable buttons init error:', e);
      }

            // Custom export button handlers - connect your buttons to DataTable export functions
            $('.export-excel-btn').on('click', function() {
              // trigger the excel button (named by Buttons extension)
              try { leadsTable.button('.buttons-excel').trigger(); } catch (e) { console.error(e); }
            });

            $('.export-csv-btn').on('click', function() {
              try { leadsTable.button('.buttons-csv').trigger(); } catch (e) { console.error(e); }
            });

            $('.export-pdf-btn').on('click', function() {
              try { leadsTable.button('.buttons-pdf').trigger(); } catch (e) { console.error(e); }
            });

            $('.export-print-btn').on('click', function() {
              try { leadsTable.button('.buttons-print').trigger(); } catch (e) { console.error(e); }
            });
        });
</script>
<script>
  window.addEventListener('DOMContentLoaded', () => {
            const containers = document.querySelectorAll('.followup-history');

            containers.forEach(container => {
                if (container.scrollHeight > 150) {
                    container.style.height = '150px';
                    container.style.overflowY = 'auto';
                } else {
                    container.style.height = 'auto';
                    container.style.overflowY = 'visible';
                }
            });
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = {!! json_encode(route('admin.report')) !!};
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

             
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