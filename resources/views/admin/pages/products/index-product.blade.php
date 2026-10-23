@extends('admin.layouts.header')
@section('content')
    @php
        $serviceCounts = $serviceCounts ?? [];
        $productCategories = $productCategories ?? [];
        $productSummary = $productSummary ?? ['total' => count($products), 'active' => 0, 'inactive' => 0, 'services_linked' => 0, 'service_links' => 0];
        $hasSyncColumn = $hasSyncColumn ?? false;
        $canSyncProducts = $canSyncProducts ?? false;

        // Chip counts for the products currently listed (after the name / status filter).
        $chipOrder = ['Helicopter', 'Plane', 'Yacht & boat', 'Ambulance', 'Other'];
        $chipCounts = array_fill_keys($chipOrder, 0);
        foreach ($products as $listed) {
            foreach ($productCategories[(string) $listed->id] ?? ['Other'] as $category) {
                $chipCounts[$category] = ($chipCounts[$category] ?? 0) + 1;
            }
        }
        $avgServices = $productSummary['total'] > 0 ? round($productSummary['service_links'] / $productSummary['total'], 1) : 0;
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/products/products.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/products/products-mobile.css') }}?v=1">

    <div class="crm-kimi pd-page">
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

        <header class="pd-heading">
            <div>
                <h1>Products</h1>
                <p>The catalog that drives lead forms, service pricing and the Airpoints sync</p>
            </div>
            <div class="pd-heading-actions">
                @if ($canSyncProducts)
                    <a href="{{ route('admin.product-sync.index') }}" class="k-ghost"><i class="ri-refresh-line" aria-hidden="true"></i> Airpoints sync</a>
                @endif
                <button type="button" class="k-primary" id="open-add-product"><i class="ri-add-line" aria-hidden="true"></i> Add product</button>
            </div>
        </header>

        <section class="pd-stats" aria-label="Product summary">
            <div class="pd-stat acc-brand">
                <strong>{{ $productSummary['total'] }}</strong>
                <div class="pd-label">Total products</div>
                <div class="pd-sub2">across {{ collect($chipCounts)->filter()->count() }} categories</div>
            </div>
            <div class="pd-stat acc-green">
                <strong>{{ $productSummary['active'] }}</strong>
                <div class="pd-label">Active</div>
                <div class="pd-sub2">visible on lead forms</div>
            </div>
            <div class="pd-stat acc-amber">
                <strong>{{ $productSummary['inactive'] }}</strong>
                <div class="pd-label">Inactive</div>
                <div class="pd-sub2">hidden but keep history</div>
            </div>
            <div class="pd-stat acc-brand">
                <strong>{{ $productSummary['services_linked'] }}</strong>
                <div class="pd-label">Services linked</div>
                <div class="pd-sub2">avg {{ $avgServices }} services per product</div>
            </div>
        </section>

        {{-- Category chips filter the list in the browser (category is read from the product name). --}}
        <nav class="pd-chips" aria-label="Filter by category">
            <button type="button" class="pd-chip on" data-category="">All · {{ count($products) }}</button>
            @foreach ($chipCounts as $category => $count)
                @if ($count > 0)
                    <button type="button" class="pd-chip" data-category="{{ $category }}">{{ $category }} · {{ $count }}</button>
                @endif
            @endforeach
        </nav>

        <section class="k-section" aria-labelledby="pd-list-title">
            <div class="k-section-head">
                <h2 id="pd-list-title">Product list</h2>
                {{-- Same GET filters as before (product name + status). --}}
                <form class="product-filters pd-tools" method="GET" action="{{ route('admin.products.index') }}" id="filter-form" novalidate>
                    <label class="pd-search">
                        <i class="ri-search-line" aria-hidden="true"></i>
                        <span class="sr-only">Search by product name</span>
                        <input type="search" name="product" id="product-name" value="{{ request('product') }}" placeholder="Search by product name" autocomplete="off">
                    </label>
                    <select name="status" id="status" class="pd-select" aria-label="Status">
                        <option value="">All statuses</option>
                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @if (request()->filled('product') || request()->filled('status'))
                        <button type="button" class="k-ghost k-sm" onclick="clearFilters()" title="Clear filters"><i class="ri-refresh-line" aria-hidden="true"></i> Reset</button>
                    @endif
                </form>
            </div>
            <div class="k-section-meta">Private Charter / Air Ambulance are shown as tags. Click a product name to view it; the services count opens the Services page for that product.</div>

            <div class="k-tblwrap pd-tblwrap">
                <table class="pd-table no-horizontal-scroll" width="100%" data-empty-msg="No products found">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Services under it</th>
                            <th>Airpoints</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php
                                $categories = $productCategories[(string) $product->id] ?? ['Other'];
                                $servicesUnder = $serviceCounts[(string) $product->id] ?? 0;
                                $isActive = $product->status == 1;
                            @endphp
                            <tr data-product-row="{{ $product->id }}" data-categories="{{ implode('|', $categories) }}">
                                <td data-label="Product">
                                    <div class="pd-cell">
                                        <a href="javascript:void(0);" class="pd-main view-product" data-id="{{ $product->id }}"
                                            title="View product">{{ Str::limit($product->product, 50) }}</a>
                                        <div class="pd-sub">Added {{ optional($product->created_at)->format('d M Y') }}@if ($product->booking_email_note) · <span class="pd-note">email note</span>@endif</div>
                                    </div>
                                </td>
                                <td data-label="Type">
                                    <div class="pd-tags">
                                        @foreach ($categories as $category)
                                            @if ($category !== 'Other' && $category !== 'Ambulance')
                                                <span class="k-tag blue">{{ $category }}</span>
                                            @endif
                                        @endforeach
                                        @if ($product->is_private == 1)
                                            <span class="k-tag neutral">Private Charter</span>
                                        @endif
                                        @if ($product->is_airambulance == 1)
                                            <span class="k-tag amber">Air Ambulance</span>
                                        @elseif (in_array('Ambulance', $categories, true))
                                            <span class="k-tag blue">Ambulance</span>
                                        @endif
                                        @if ($categories === ['Other'] && $product->is_private != 1)
                                            <span class="k-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Services" data-order="{{ $servicesUnder }}">
                                    @if ($servicesUnder > 0)
                                        <a class="pd-link" href="{{ route('admin.services.index', ['product_id' => $product->id]) }}">{{ $servicesUnder }} {{ Str::plural('service', $servicesUnder) }}</a>
                                    @else
                                        <span class="k-muted">no services yet</span>
                                    @endif
                                </td>
                                <td data-label="Airpoints">
                                    @if (!$hasSyncColumn)
                                        <span class="k-muted">—</span>
                                    @elseif ((int) $product->sync_at === 1)
                                        <span class="k-tag green">Synced</span>
                                    @elseif ($isActive)
                                        @if ($canSyncProducts)
                                            <a href="{{ route('admin.product-sync.index') }}" class="k-tag amber" title="Open Product Sync">Ready to sync</a>
                                        @else
                                            <span class="k-tag amber">Ready to sync</span>
                                        @endif
                                    @else
                                        <span class="k-tag neutral">Not synced</span>
                                    @endif
                                </td>
                                <td data-label="Status" class="pd-status-cell">
                                    @if ($isActive)
                                        <span class="k-tag green">Active</span>
                                    @else
                                        <span class="k-tag neutral">Inactive</span>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="pd-actions">
                                        <button type="button" class="k-rowbtn edit-product" data-id="{{ $product->id }}"
                                            title="{{ $isActive ? 'Edit' : 'Activate the product to edit it' }}" {{ $isActive ? '' : 'disabled' }}>Edit</button>
                                        <button type="button" class="k-rowbtn toggle-product-status {{ $isActive ? '' : 'solid' }}"
                                            data-id="{{ $product->id }}" data-status="{{ $product->status }}" data-name="{{ $product->product }}"
                                            title="{{ $isActive ? 'Deactivate' : 'Activate' }}">{{ $isActive ? 'Deactivate' : 'Activate' }}</button>
                                        @if ($isSuperAdmin)
                                            <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" class="delete-product-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="pd-iconbtn is-danger delete-product-btn"
                                                    title="Delete" aria-label="Delete {{ $product->product }}" data-product-name="{{ $product->product }}">
                                                    <i class="ri-delete-bin-line" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Add Product Modal -->
    <div id="add-product-modal" class="hs-overlay hidden ti-modal pd-modal" tabindex="-1" aria-labelledby="add-product-title">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out pd-modal-box">
            <div class="ti-modal-content crm-kimi pd-modal-content">
                <div class="pd-modal-head">
                    <div>
                        <h3 id="add-product-title">Add product</h3>
                        <p>New products are created as Active.</p>
                    </div>
                    <button type="button" class="pd-modal-close" data-hs-overlay="#add-product-modal" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <form id="add-product-form-element">
                    @csrf
                    <div class="pd-modal-body pd-formgrid">
                        <div class="k-fg pd-full">
                            <label for="add-product-name">Product name <span class="pd-req">*</span></label>
                            <input type="text" name="product" id="add-product-name" required>
                            <span class="pd-error error-message" id="add-product-error"></span>
                        </div>
                        <div class="k-fg pd-full">
                            <label>Flags</label>
                            <div class="pd-checkline">
                                <label><input type="checkbox" name="is_private" id="add-is_private" value="1"> Private Charter</label>
                                <label><input type="checkbox" name="is_airambulance" id="add-is_airambulance" value="1"> Air Ambulance</label>
                            </div>
                            <span class="pd-hint">Choose one at most — a product can't be both.</span>
                            <span class="pd-error error-message" id="add-private-error"></span>
                            <span class="pd-error error-message" id="add-airambulance-error"></span>
                        </div>
                        <div class="k-fg pd-full">
                            <label for="add-booking-email-note">Registration Email Note</label>
                            <textarea name="booking_email_note" id="add-booking-email-note" rows="3"></textarea>
                            <span class="pd-error error-message" id="add-booking-email-note-error"></span>
                        </div>
                    </div>
                    <div class="pd-modal-foot">
                        <button type="button" class="k-ghost" id="cancel-add-product">Cancel</button>
                        <button type="submit" class="k-primary" id="save-add-product">Save product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Product Modal -->
    <div id="view-product" class="hs-overlay hidden ti-modal pd-modal" tabindex="-1" aria-labelledby="view-product-title">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out pd-modal-box">
            <div class="ti-modal-content crm-kimi pd-modal-content">
                <div class="pd-modal-head">
                    <div>
                        <h3 id="view-product-title">View product</h3>
                    </div>
                    <button type="button" class="pd-modal-close" data-hs-overlay="#view-product" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="pd-modal-body">
                    <div id="view-product-content">
                        <!-- Product details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-product" class="hs-overlay hidden ti-modal pd-modal" tabindex="-1" aria-labelledby="edit-product-title">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out pd-modal-box">
            <div class="ti-modal-content crm-kimi pd-modal-content">
                <div class="pd-modal-head">
                    <div>
                        <h3 id="edit-product-title">Edit product</h3>
                    </div>
                    <button type="button" class="pd-modal-close" data-hs-overlay="#edit-product" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <form class="ti-custom-validation" id="edit-product-form" method="POST" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit-product-id" name="product_id">
                    <div class="pd-modal-body pd-formgrid">
                        <div class="k-fg pd-full">
                            <label for="edit-product-name">Product name <span class="pd-req">*</span></label>
                            <input type="text" name="product" id="edit-product-name" required>
                            @error('product', 'edit')
                                <span class="pd-error">{{ $message }}</span>
                            @enderror
                            <span class="pd-error error-message" id="edit-product-error"></span>
                        </div>
                        <div class="k-fg pd-full">
                            <label>Flags</label>
                            <div class="pd-checkline">
                                <label><input type="checkbox" name="is_private" id="edit-is_private" value="1"> Private Charter</label>
                                <label><input type="checkbox" name="is_airambulance" id="edit-is_airambulance" value="1"> Air Ambulance</label>
                            </div>
                            @error('is_private', 'edit')
                                <span class="pd-error">{{ $message }}</span>
                            @enderror
                            @error('is_airambulance', 'edit')
                                <span class="pd-error">{{ $message }}</span>
                            @enderror
                            <span class="pd-error error-message" id="edit-private-error"></span>
                            <span class="pd-error error-message" id="edit-airambulance-error"></span>
                        </div>
                        <div class="k-fg pd-full">
                            <label for="edit-booking-email-note">Registration Email Note</label>
                            <textarea name="booking_email_note" id="edit-booking-email-note" rows="3"></textarea>
                            <span class="pd-error error-message" id="edit-booking-email-note-error"></span>
                        </div>
                    </div>
                    <div class="pd-modal-foot">
                        <button type="button" class="k-ghost" data-hs-overlay="#edit-product">Cancel</button>
                        <button type="submit" class="k-primary ti-custom-validate-btn">Update product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Toggle Confirmation Modal -->
    <div id="toggle-status-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-toggle-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Status Change</h5>
                <p class="mb-4 text-gray-600" id="status-modal-message"></p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="cancel-toggle">Cancel</button>
                    <button type="button" class="ti-btn bg-primary text-white px-4 py-1"
                        id="confirm-status-toggle">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div id="delete-product-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-delete-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Delete</h5>
                <p class="mb-4 text-gray-600" id="delete-modal-message"></p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="cancel-delete">Cancel</button>
                    <button type="button" class="ti-btn bg-danger text-white px-4 py-1"
                        id="confirm-delete-product">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let productIdToToggle = null;
            let currentStatus = null;
            let $buttonElement = null;
            let $deleteForm = null;
            let $deleteButton = null;
            let deleteProductName = '';

            function openOverlay(id) {
                const el = document.getElementById(id);
                if (window.HSOverlay && window.HSOverlay.open) {
                    window.HSOverlay.open(el);
                } else {
                    $(el).removeClass('hidden');
                }
            }

            function closeOverlay(id) {
                const el = document.getElementById(id);
                if (window.HSOverlay && window.HSOverlay.close) {
                    window.HSOverlay.close(el);
                } else {
                    $(el).addClass('hidden');
                }
            }

            // ---- List: DataTable sorting/paging, search box, category chips ----
            const $table = $('.pd-table');
            let activeCategory = '';
            let productsTable = null;
            if ($.fn.DataTable) {
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    if (settings.nTable !== $table[0] || !activeCategory) return true;
                    const row = settings.aoData[dataIndex].nTr;
                    return (row.getAttribute('data-categories') || '').split('|').includes(activeCategory);
                });
                productsTable = $table.DataTable({
                    responsive: false,
                    scrollX: false,
                    autoWidth: false,
                    dom: 'rt<"pd-tfoot"lip>',
                    order: [],
                    columnDefs: [{
                        orderable: false,
                        targets: [1, 3, 5]
                    }],
                    language: {
                        emptyTable: $table.data('empty-msg'),
                        zeroRecords: 'No matching products found'
                    }
                });
                // Typing filters the list straight away; Enter still submits the server filter.
                productsTable.column(0).search($('#product-name').val() || '').draw();
                $('#product-name').on('input search', function() {
                    productsTable.column(0).search(this.value).draw();
                });
            }
            $('#status').on('change', function() {
                $('#filter-form').trigger('submit');
            });
            $('.pd-chip').on('click', function() {
                $('.pd-chip').removeClass('on');
                $(this).addClass('on');
                activeCategory = $(this).data('category') || '';
                if (productsTable) productsTable.draw();
            });

            // ---- Add product (popup modal) ----
            $('#open-add-product').on('click', function() {
                clearErrorMessages();
                openOverlay('add-product-modal');
                setTimeout(() => $('#add-product-name').trigger('focus'), 150);
            });

            // Add Product Form Submission
            $('#add-product-form-element').on('submit', function(e) {
                e.preventDefault();
                clearErrorMessages();

                const formData = new FormData(this);

                $.ajax({
                    url: "{{ route('admin.products.store') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            $('#add-product-form-element')[0].reset();
                            closeOverlay('add-product-modal');
                            // Reload the page to show the new product
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        if (response && response.errors) {
                            displayValidationErrors(response.errors, 'add');
                        } else {
                            showToast('error', response?.message || 'An error occurred');
                        }
                    }
                });
            });

            // Checkbox validation for add form
            $('#add-is_private, #add-is_airambulance').on('change', function() {
                const isPrivate = $('#add-is_private').is(':checked');
                const isAirambulance = $('#add-is_airambulance').is(':checked');

                if (isPrivate && isAirambulance) {
                    $(this).prop('checked', false);
                    showToast('error', 'You cannot select both Private Charter and Air Ambulance');
                }
            });

            $('#cancel-add-product').on('click', function() {
                $('#add-product-form-element')[0].reset();
                clearErrorMessages();
                closeOverlay('add-product-modal');
            });

            // ---- View product (product name click) ----
            $(document).on('click', '.view-product', function(e) {
                e.preventDefault();
                const productId = $(this).data('id');

                $.ajax({
                    url: "{{ route('admin.products.view', ':id') }}".replace(':id', productId),
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            const product = response.product;
                            const bookingEmailNote = product.booking_email_note
                                ? escapeHtml(product.booking_email_note)
                                : 'N/A';
                            const content = `
                                <dl class="pd-view">
                                    <div class="pd-full"><dt>Product name</dt><dd class="pd-view-name">${escapeHtml(product.product)}</dd></div>
                                    <div><dt>Private Charter</dt><dd>${product.is_private == 1 ? '<span class="k-tag green">Yes</span>' : '<span class="k-tag neutral">No</span>'}</dd></div>
                                    <div><dt>Air Ambulance</dt><dd>${product.is_airambulance == 1 ? '<span class="k-tag amber">Yes</span>' : '<span class="k-tag neutral">No</span>'}</dd></div>
                                    <div><dt>Status</dt><dd>${product.status == 1 ? '<span class="k-tag green">Active</span>' : '<span class="k-tag neutral">Inactive</span>'}</dd></div>
                                    <div><dt>Created date</dt><dd>${new Date(product.created_at).toLocaleDateString()}</dd></div>
                                    <div class="pd-full"><dt>Registration Email Note</dt><dd class="pd-view-note">${bookingEmailNote}</dd></div>
                                </dl>
                            `;
                            $('#view-product-content').html(content);
                            openOverlay('view-product');
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        showToast('error', response?.message || 'Failed to load product data');
                    }
                });
            });

            // ---- Edit product ----
            $(document).on('click', '.edit-product', function(e) {
                e.preventDefault();
                const productId = $(this).data('id');

                $.ajax({
                    url: "{{ route('admin.products.edit', ':id') }}".replace(':id', productId),
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            const product = response.product;
                            $('#edit-product-id').val(product.id);
                            $('#edit-product-name').val(product.product);
                            $('#edit-booking-email-note').val(product.booking_email_note || '');
                            $('#edit-is_private').prop('checked', product.is_private == 1);
                            $('#edit-is_airambulance').prop('checked', product.is_airambulance == 1);
                            clearErrorMessages();
                            openOverlay('edit-product');
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        showToast('error', response?.message || 'Failed to load product data');
                    }
                });
            });

            // Edit Product Form Submission
            $('#edit-product-form').on('submit', function(e) {
                e.preventDefault();
                clearErrorMessages();

                const productId = $('#edit-product-id').val();
                const formData = new FormData(this);

                $.ajax({
                    url: "{{ route('admin.products.update', ':id') }}".replace(':id', productId),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            closeOverlay('edit-product');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        if (response && response.errors) {
                            displayValidationErrors(response.errors, 'edit');
                        } else {
                            showToast('error', response?.message || 'An error occurred');
                        }
                    }
                });
            });

            // Checkbox validation for edit form
            $('#edit-is_private, #edit-is_airambulance').on('change', function() {
                const isPrivate = $('#edit-is_private').is(':checked');
                const isAirambulance = $('#edit-is_airambulance').is(':checked');

                if (isPrivate && isAirambulance) {
                    $(this).prop('checked', false);
                    showToast('error', 'You cannot select both Private Charter and Air Ambulance');
                }
            });

            // ---- Activate / Deactivate ----
            $(document).on('click', '.toggle-product-status', function(e) {
                e.preventDefault();
                productIdToToggle = $(this).data('id');
                currentStatus = Number($(this).data('status')) === 1;
                $buttonElement = $(this);
                const productName = $(this).data('name');

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(
                    `Are you sure you want to ${action} "${productName}" product?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!productIdToToggle) return;

                const url = "{{ route('admin.products.toggle-status', ':id') }}".replace(':id',
                    productIdToToggle);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        _method: 'PATCH'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            const newStatus = response.new_status ? 1 : 0;
                            const $row = $buttonElement.closest('tr');

                            $buttonElement
                                .text(newStatus ? 'Deactivate' : 'Activate')
                                .toggleClass('solid', !newStatus)
                                .attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            $row.find('.pd-status-cell').html(newStatus ?
                                '<span class="k-tag green">Active</span>' :
                                '<span class="k-tag neutral">Inactive</span>');

                            // Inactive products cannot be edited (same rule as the server).
                            $row.find('.edit-product')
                                .prop('disabled', !newStatus)
                                .attr('title', newStatus ? 'Edit' : 'Activate the product to edit it');
                        } else {
                            showToast('error', response.message || "Operation failed");
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message ||
                            "Server error occurred");
                        $('#toggle-status-modal').addClass('hidden');
                    }
                });
            });

            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                productIdToToggle = null;
                currentStatus = null;
                $buttonElement = null;
            });

            // ---- Delete (Super Admin) ----
            $(document).on('click', '.delete-product-btn', function(e) {
                e.preventDefault();
                $deleteButton = $(this);
                $deleteForm = $deleteButton.closest('form');
                deleteProductName = $deleteButton.data('product-name');
                $('#delete-modal-message').text(
                    `Are you sure you want to delete "${deleteProductName}" product?`);
                $('#delete-product-modal').removeClass('hidden');
            });

            $('#confirm-delete-product').click(function() {
                if ($deleteForm) {
                    $deleteForm.submit();
                }
                $('#delete-product-modal').addClass('hidden');
                $deleteForm = null;
                $deleteButton = null;
                deleteProductName = '';
            });

            $('#cancel-delete, #close-delete-modal').click(function() {
                $('#delete-product-modal').addClass('hidden');
                $deleteForm = null;
                $deleteButton = null;
                deleteProductName = '';
            });

            // Utility functions
            function clearErrorMessages() {
                $('.error-message').text('');
            }

            function displayValidationErrors(errors, prefix) {
                $.each(errors, function(field, messages) {
                    $(`#${prefix}-${field.replace(/_/g, '-')}-error`).text(messages[0]);
                });
            }

            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-[100] px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.products.index') }}";
        }
    </script>
@endpush
