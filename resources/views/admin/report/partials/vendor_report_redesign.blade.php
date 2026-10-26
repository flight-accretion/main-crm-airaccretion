@php
  $money = static fn ($value) => 'Rs ' . number_format((float) $value, 2);
  $hasFilters = request()->filled('search') || request()->filled('from_payment_date') || request()->filled('to_payment_date')
    || request()->filled('service_date_from') || request()->filled('service_date_to') || request()->filled('vendor_id')
    || request()->filled('service_id') || request()->filled('status');
  $summary = $monthlySummary ?? ['month_label' => now()->format('F Y'), 'record_count' => 0, 'final_vendor_payable' => 0, 'net_paid_to_vendors' => 0, 'vendor_payments_outstanding' => 0, 'refunds_to_recover' => 0];
@endphp

<style>
  .vendor-report-page { --vr-navy:#0f2b46; --vr-green:#15803d; --vr-amber:#b45309; --vr-red:#c62f3b; --vr-border:#e3e8ef; --vr-muted:#708096; }
  .vendor-report-page .vr-heading,.vendor-report-page .vr-section__head,.vendor-report-page .vr-mobile-card__head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
  .vendor-report-page .vr-heading { margin-bottom:1rem; }.vendor-report-page h3,.vendor-report-page h4 { margin:0; color:var(--vr-navy); font-weight:700; }.vendor-report-page h3 { font-size:1.45rem; }.vendor-report-page h4 { font-size:1rem; }
  .vendor-report-page .vr-heading p,.vendor-report-page .vr-section__head p { margin:.25rem 0 0; color:var(--vr-muted); font-size:.8rem; }.vendor-report-page .vr-actions,.vendor-report-page .vr-pills,.vendor-report-page .vr-filter-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
  .vendor-report-page .vr-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; margin:1rem 0; }.vendor-report-page .vr-stat { background:#fff; border:1px solid var(--vr-border); border-top:3px solid var(--vr-navy); border-radius:.6rem; padding:1rem; min-width:0; }.vendor-report-page .vr-stat--green{border-top-color:var(--vr-green)}.vendor-report-page .vr-stat--amber{border-top-color:var(--vr-amber)}.vendor-report-page .vr-stat--red{border-top-color:var(--vr-red)}
  .vendor-report-page .vr-stat__value{display:block;font-size:1.35rem;font-weight:700;color:#172033;overflow-wrap:anywhere}.vendor-report-page .vr-stat__label{display:block;margin-top:.25rem;color:#4b5b70;font-size:.8rem;font-weight:600}.vendor-report-page .vr-stat__help{display:block;margin-top:.25rem;color:var(--vr-muted);font-size:.7rem;line-height:1.35}
  .vendor-report-page .vr-pills{margin:0 0 1rem}.vendor-report-page .vr-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .75rem;border:1px solid var(--vr-border);border-radius:99px;color:#526176;background:#fff;font-size:.78rem}.vendor-report-page .vr-pill.is-active{background:var(--vr-navy);border-color:var(--vr-navy);color:#fff;font-weight:600}
  .vendor-report-page .vr-filter-card{display:none;margin-bottom:1rem}.vendor-report-page .vr-filter-card.is-open{display:block}.vendor-report-page .vr-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;align-items:end}.vendor-report-page .vr-field{min-width:0}.vendor-report-page .vr-field label{display:block;margin:0 0 .35rem;color:var(--vr-muted);font-size:.75rem;font-weight:600}.vendor-report-page .vr-field .ti-form-input,.vendor-report-page .vr-field .ti-form-select{width:100%;min-height:2.45rem}.vendor-report-page .vr-filter-actions{align-items:end;height:2.45rem}
  .vendor-report-page .vr-section{border:1px solid var(--vr-border);border-radius:.7rem;background:#fff;padding:1rem}.vendor-report-page .vr-badge{display:inline-block;padding:.25rem .5rem;border-radius:.35rem;background:#eff3f8;color:#536278;font-size:.68rem;font-weight:700;white-space:nowrap}.vendor-report-page .vr-table{width:100%;table-layout:fixed!important;border-collapse:separate;border-spacing:0;font-size:.78rem}.vendor-report-page .vr-table th{padding:.7rem .5rem;color:#64748b;background:#f1f4f8;font-size:.68rem;font-weight:700;text-align:left;line-height:1.3;white-space:normal!important}.vendor-report-page .vr-table td{padding:.75rem .5rem;border-bottom:1px solid var(--vr-border);vertical-align:top;overflow:hidden;overflow-wrap:anywhere;word-break:break-word;white-space:normal!important;color:#27354a}.vendor-report-page .vr-table tbody tr:last-child td{border-bottom:0}.vendor-report-page .vr-line{display:block;color:#152238;font-weight:700;line-height:1.35;white-space:normal!important}.vendor-report-page .vr-sub{display:block;margin-top:.2rem;color:var(--vr-muted);font-size:.69rem;line-height:1.35;white-space:normal!important}.vendor-report-page .vr-money{font-variant-numeric:tabular-nums;font-weight:700;white-space:normal}.vendor-report-page .vr-status{display:inline-block;padding:.2rem .45rem;border-radius:.3rem;font-size:.67rem;font-weight:700;white-space:nowrap}.vendor-report-page .vr-status--paid{color:var(--vr-green);background:#e4f5eb}.vendor-report-page .vr-status--partial{color:var(--vr-amber);background:#fff0d9}.vendor-report-page .vr-status--unpaid{color:var(--vr-red);background:#fbe7e9}.vendor-report-page .vr-empty{padding:1.5rem;text-align:center;color:var(--vr-muted)}
  .vendor-report-page .vr-mobile-list{display:none}.vendor-report-page .vr-mobile-card{border:1px solid var(--vr-border);border-radius:.55rem;padding:.85rem;margin-bottom:.65rem}.vendor-report-page .vr-mobile-card__grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin:.85rem 0}.vendor-report-page .vr-mobile-card__grid span{display:block;margin-bottom:.2rem;color:var(--vr-muted);font-size:.66rem}.vendor-report-page .vr-mobile-card__grid b{display:block;color:#213048;font-size:.76rem;overflow-wrap:anywhere}.vendor-report-page .dataTables_wrapper .dataTables_filter{display:none}.vendor-report-page .dataTables_wrapper .dataTables_length,.vendor-report-page .dataTables_wrapper .dataTables_info{color:var(--vr-muted);font-size:.75rem}.vendor-report-page .dataTables_wrapper .dataTables_paginate .paginate_button{border-radius:.35rem!important;padding:.25rem .55rem!important}
  @media(max-width:1100px){.vendor-report-page .vr-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.vendor-report-page h3{font-size:1.25rem}.vendor-report-page .vr-actions{width:100%}.vendor-report-page .vr-actions .ti-btn{flex:1}.vendor-report-page .vr-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}.vendor-report-page .vr-stat{padding:.75rem}.vendor-report-page .vr-stat__value{font-size:1.08rem}.vendor-report-page .vr-filters{grid-template-columns:1fr;gap:.7rem}.vendor-report-page .vr-filter-actions{justify-content:flex-end}.vendor-report-page .vr-section{padding:.8rem}.vendor-report-page .vr-desktop-table{display:none}.vendor-report-page .vr-mobile-list{display:block}}
</style>

<div class="vendor-report-page">
  <div class="vr-heading">
    <div><h3>Vendor Report</h3><p>Vendor liability, payments, refunds and service settlements in one view.</p></div>
    <div class="vr-actions">
      <button type="button" class="ti-btn ti-btn-outline-primary" id="toggle-vendor-report-filters" aria-expanded="{{ $hasFilters ? 'true' : 'false' }}"><i class="ri-filter-3-line"></i> Filters</button>
      <button type="button" class="ti-btn ti-btn-success-full export-excel-btn"><i class="ri-file-excel-line"></i> Excel</button>
      <button type="button" class="ti-btn ti-btn-info-full export-csv-btn"><i class="ri-file-text-line"></i> CSV</button>
    </div>
  </div>

  <div class="vr-stats">
    <div class="vr-stat"><span class="vr-stat__value">{{ $money($summary['final_vendor_payable']) }}</span><span class="vr-stat__label">Final vendor payable</span><span class="vr-stat__help">{{ $summary['record_count'] }} services in {{ $summary['month_label'] }}</span></div>
    <div class="vr-stat vr-stat--green"><span class="vr-stat__value">{{ $money($summary['net_paid_to_vendors']) }}</span><span class="vr-stat__label">Net paid to vendors</span><span class="vr-stat__help">Payments less refunds received</span></div>
    <div class="vr-stat vr-stat--amber"><span class="vr-stat__value">{{ $money($summary['vendor_payments_outstanding']) }}</span><span class="vr-stat__label">Vendor payments outstanding</span><span class="vr-stat__help">Amount still payable to vendors</span></div>
    <div class="vr-stat vr-stat--red"><span class="vr-stat__value">{{ $money($summary['refunds_to_recover']) }}</span><span class="vr-stat__label">Refunds to recover</span><span class="vr-stat__help">Amount vendors still need to return</span></div>
  </div>

  <div class="vr-pills" aria-label="Payment status shortcuts">
    <a href="{{ route('admin.report.vendor') }}" class="vr-pill {{ !request('status') ? 'is-active' : '' }}">All records <strong>{{ $rows->count() }}</strong></a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'unpaid']) }}" class="vr-pill {{ request('status') === 'unpaid' ? 'is-active' : '' }}">Unpaid</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'partial']) }}" class="vr-pill {{ request('status') === 'partial' ? 'is-active' : '' }}">Partial paid</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'paid']) }}" class="vr-pill {{ request('status') === 'paid' ? 'is-active' : '' }}">Fully paid</a>
  </div>

  <div id="vendor-report-filters" class="box vr-filter-card {{ $hasFilters ? 'is-open' : '' }}"><div class="box-body">
    <form method="GET" action="{{ route('admin.report.vendor') }}" class="vr-filters">
      <div class="vr-field"><label for="vendor-report-search">Name or number</label><input id="vendor-report-search" type="search" name="search" class="ti-form-input" value="{{ request('search') }}" placeholder="Search client or vendor name/number"></div>
      <div class="vr-field"><label for="vendor-report-service-from">Service date from</label><input id="vendor-report-service-from" type="date" name="service_date_from" class="ti-form-input" value="{{ request('service_date_from') }}"></div>
      <div class="vr-field"><label for="vendor-report-service-to">Service date to</label><input id="vendor-report-service-to" type="date" name="service_date_to" class="ti-form-input" value="{{ request('service_date_to') }}"></div>
      <div class="vr-field"><label for="vendor-report-vendor">Vendor</label><select id="vendor-report-vendor" name="vendor_id" class="ti-form-select"><option value="">All vendors</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((string) request('vendor_id') === (string) $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>
      <div class="vr-field"><label for="vendor-report-service">Service</label><select id="vendor-report-service" name="service_id" class="ti-form-select"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $service->service }}</option>@endforeach</select></div>
      <div class="vr-field"><label for="vendor-report-status">Payment status</label><select id="vendor-report-status" name="status" class="ti-form-select"><option value="">All statuses</option><option value="paid" @selected(request('status') === 'paid')>Full paid</option><option value="partial" @selected(request('status') === 'partial')>Partial paid</option><option value="unpaid" @selected(request('status') === 'unpaid')>Unpaid</option></select></div>
      <div class="vr-field"><label for="vendor-report-payment-from">Payment date from</label><input id="vendor-report-payment-from" type="date" name="from_payment_date" class="ti-form-input" value="{{ request('from_payment_date') }}"></div>
      <div class="vr-field"><label for="vendor-report-payment-to">Payment date to</label><input id="vendor-report-payment-to" type="date" name="to_payment_date" class="ti-form-input" value="{{ request('to_payment_date') }}"></div>
      <div class="vr-filter-actions"><a href="{{ route('admin.report.vendor') }}" class="ti-btn ti-btn-outline-secondary">Reset</a><button type="submit" class="ti-btn ti-btn-primary-full">Apply</button></div>
    </form>
  </div></div>

  <section class="vr-section">
    <div class="vr-section__head"><div><h4>Vendor settlement ledger</h4><p>One row per vendor booking. Exports retain the complete payment, refund and settlement breakdown.</p></div><span class="vr-badge">{{ $rows->count() }} records</span></div>
    <div class="vr-desktop-table"><table id="vendor-report-table" class="vr-table" width="100%"><thead><tr><th style="width:19%">Vendor / booking</th><th style="width:18%">Client / number</th><th style="width:18%">Product / service date</th><th style="width:18%">Vendor settlement</th><th style="width:15%">Outstanding</th><th style="width:12%">Ride / payment</th></tr></thead><tbody>
      @forelse($rows as $row)
        @php($paymentClass = $row->status === 'Full Paid' ? 'paid' : ($row->status === 'Partial Paid' ? 'partial' : 'unpaid'))
        @php($outstanding = $row->refund_due_amount > 0 ? $row->refund_due_amount : $row->balance_amount)
        @php($outstandingLabel = $row->refund_due_amount > 0 ? 'Refund due' : ($row->balance_amount > 0 ? 'Payment due' : 'Settled'))
        <tr><td><span class="vr-line">{{ $row->vendor_name }}</span><span class="vr-sub">{{ $row->booking_slip_number }}</span></td><td><span class="vr-line">{{ $row->client_name }}</span><span class="vr-sub">{{ $row->client_contact }}</span></td><td><span class="vr-line">{{ $row->product }}</span><span class="vr-sub">Service: {{ $row->service_date }}</span></td><td><span class="vr-money">Payable {{ $money($row->vendor_service_cost) }}</span><span class="vr-sub">Net paid {{ $money($row->paid_amount) }} · Original {{ $money($row->original_vendor_amount) }}</span></td><td><span class="vr-money">{{ $money($outstanding) }}</span><span class="vr-sub">{{ $outstandingLabel }}</span></td><td><span class="vr-status vr-status--{{ $paymentClass }}">{{ $row->status }}</span><span class="vr-sub">{{ $row->ride_status }} · {{ $row->paid_date }}</span></td></tr>
      @empty
        <tr><td colspan="6" class="vr-empty">No vendor payments match these filters.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="vr-mobile-list">
      @forelse($rows as $row)
        @php($paymentClass = $row->status === 'Full Paid' ? 'paid' : ($row->status === 'Partial Paid' ? 'partial' : 'unpaid'))
        @php($outstanding = $row->refund_due_amount > 0 ? $row->refund_due_amount : $row->balance_amount)
        @php($outstandingLabel = $row->refund_due_amount > 0 ? 'Refund due' : ($row->balance_amount > 0 ? 'Payment due' : 'Settled'))
        <article class="vr-mobile-card"><div class="vr-mobile-card__head"><div><span class="vr-line">{{ $row->vendor_name }}</span><span class="vr-sub">{{ $row->booking_slip_number }} · {{ $row->product }}</span></div><span class="vr-status vr-status--{{ $paymentClass }}">{{ $row->status }}</span></div><div class="vr-mobile-card__grid"><div><span>Client</span><b>{{ $row->client_name }}</b><span class="vr-sub">{{ $row->client_contact }}</span></div><div><span>Service date</span><b>{{ $row->service_date }}</b><span class="vr-sub">{{ $row->ride_status }}</span></div><div><span>Final payable</span><b>{{ $money($row->vendor_service_cost) }}</b><span class="vr-sub">Original {{ $money($row->original_vendor_amount) }}</span></div><div><span>{{ $outstandingLabel }}</span><b>{{ $money($outstanding) }}</b><span class="vr-sub">Net paid {{ $money($row->paid_amount) }}</span></div></div></article>
      @empty
        <p class="vr-empty">No vendor payments match these filters.</p>
      @endforelse
    </div>
  </section>
</div>

@push('scripts')
<script>
  $(function () {
    const filterPanel = $('#vendor-report-filters');
    const filterButton = $('#toggle-vendor-report-filters');
    filterButton.on('click', function () { const isOpen = filterPanel.toggleClass('is-open').hasClass('is-open'); filterButton.attr('aria-expanded', isOpen ? 'true' : 'false'); });
    function exportVendorReport(format) { const exportUrl = new URL('{{ route('admin.report.vendor.export') }}', window.location.origin); exportUrl.search = new URLSearchParams(window.location.search); exportUrl.searchParams.set('format', format); window.location.href = exportUrl.toString(); }
    $('.export-excel-btn').on('click', function () { exportVendorReport('xlsx'); });
    $('.export-csv-btn').on('click', function () { exportVendorReport('csv'); });
    const table = $('#vendor-report-table');
    if (table.length && $.fn.DataTable && !$.fn.DataTable.isDataTable(table[0])) { table.DataTable({ pageLength: 10, lengthMenu: [[10, 25, 50], [10, 25, 50]], searching: false, ordering: false, info: true, scrollX: false, language: { emptyTable: 'No vendor payments match these filters.' } }); }
  });
</script>
@endpush
