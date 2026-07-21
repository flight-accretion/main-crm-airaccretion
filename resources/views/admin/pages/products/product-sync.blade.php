@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Product Sync - Airpoints Integration
            </h3>
            <ol class="flex items-center whitespace-nowrap min-w-0">
                <li class="text-[0.813rem] ps-[0.5rem]">
                    <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                        href="{{ route('admin.products.index') }}">
                        Products
                        <i
                            class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                    </a>
                </li>
                <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:hover:text-primary"
                    aria-current="page">
                    Product Sync
                </li>
            </ol>
        </div>
    </div>
    <!-- Page Header Close -->

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

    <!-- Instructions Box -->
    <div class="grid grid-cols-12 gap-6 mb-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="flex items-center">
                        <div class="me-4">
                            <span class="avatar avatar-sm !rounded-full bg-info m-0">
                                <i class="ri-information-line text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="font-semibold mb-0 leading-none text-[1.125rem]">About Product Sync</h5>
                        </div>
                    </div>
                </div>
                <div class="box-body">
                    <div class="space-y-3">
                        <p class="text-[0.875rem] text-[#8c9097] dark:text-white/50">
                            <i class="ri-arrow-right-s-fill text-primary"></i>
                            This feature synchronizes your active products from Accretion Aviation to the Airpoints loyalty
                            system.
                        </p>
                        <p class="text-[0.875rem] text-[#8c9097] dark:text-white/50">
                            <i class="ri-arrow-right-s-fill text-primary"></i>
                            Product matching is <strong>case-insensitive</strong> (e.g., "Mumbai Ride" = "mumbai ride").
                        </p>
                        <p class="text-[0.875rem] text-[#8c9097] dark:text-white/50">
                            <i class="ri-arrow-right-s-fill text-primary"></i>
                            Products with exact name matches will be skipped.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Control Box -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                    <div class="flex items-center">
                        <div class="me-4">
                            <span class="avatar avatar-sm !rounded-full bg-success m-0">
                                <i class="ri-refresh-line text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <div class="flex-grow">
                            <div class="md:flex block items-center justify-between">
                                <div>
                                    <h5 class="font-semibold mb-0 leading-none text-[1.125rem]">Sync Products to Airpoints
                                    </h5>
                                    <p class="text-[0.75rem] text-[#8c9097] dark:text-white/50 mt-1">
                                        Total Active Products: <strong>{{ $products->count() }}</strong>
                                    </p>
                                </div>
                                <div class="mt-3 md:mt-0">
                                    <button type="button" id="syncProductsBtn"
                                        class="ti-btn bg-success text-white !py-1 !px-2 ti-btn-wave">
                                        <i class="ri-refresh-line me-2"></i>
                                        Sync All Products
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-body">
                    <!-- Loading Indicator -->
                    <div id="syncLoading" class="hidden text-center py-6">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Syncing products...</span>
                        </div>
                        <p class="mt-3 text-[0.875rem] text-[#8c9097] dark:text-white/50">
                            Syncing products with Airpoints, please wait...
                        </p>
                    </div>

                    <!-- Summary Box -->
                    <div id="syncSummary" class="hidden mb-4">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="xl:col-span-3 lg:col-span-6 col-span-12">
                                <div class="box !mb-0 bg-primary text-white">
                                    <div class="box-body">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-[0.75rem] opacity-80">Total Synced</div>
                                                <div class="text-[1.5rem] font-semibold" id="summary-total">0</div>
                                            </div>
                                            <div>
                                                <i class="ri-database-2-line text-[2rem] opacity-30"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 col-span-12">
                                <div class="box !mb-0 bg-success text-white">
                                    <div class="box-body">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-[0.75rem] opacity-80">Created New</div>
                                                <div class="text-[1.5rem] font-semibold" id="summary-created">0</div>
                                            </div>
                                            <div>
                                                <i class="ri-add-circle-line text-[2rem] opacity-30"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 col-span-12">
                                <div class="box !mb-0 bg-info text-white">
                                    <div class="box-body">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-[0.75rem] opacity-80">Already Exists</div>
                                                <div class="text-[1.5rem] font-semibold" id="summary-existing">0</div>
                                            </div>
                                            <div>
                                                <i class="ri-checkbox-circle-line text-[2rem] opacity-30"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 col-span-12">
                                <div class="box !mb-0 bg-danger text-white">
                                    <div class="box-body">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-[0.75rem] opacity-80">Failed</div>
                                                <div class="text-[1.5rem] font-semibold" id="summary-failed">0</div>
                                            </div>
                                            <div>
                                                <i class="ri-close-circle-line text-[2rem] opacity-30"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div id="syncResults" class="hidden">
                        <h6 class="font-semibold mb-3 text-[1rem]">Sync Details</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover whitespace-nowrap min-w-full">
                                <thead>
                                    <tr class="border-b border-defaultborder">
                                        <th scope="col" class="text-start">#</th>
                                        <th scope="col" class="text-start">Product Name (Accretion)</th>
                                        <th scope="col" class="text-start">Sync Status</th>
                                        <th scope="col" class="text-start">Airpoints ID</th>
                                        <th scope="col" class="text-start">Airpoints Name</th>
                                        <!-- <th scope="col" class="text-start">Message</th>
                                                    <th scope="col" class="text-start">Details</th> -->
                                    </tr>
                                </thead>
                                <tbody id="resultsTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Products List -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="flex items-center">
                        <div class="me-4">
                            <span class="avatar avatar-sm !rounded-full bg-primary m-0">
                                <i class="ri-list-check text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="font-semibold mb-0 leading-none text-[1.125rem]">Active Products (Ready to Sync)
                            </h5>
                        </div>
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover whitespace-nowrap min-w-full">
                            <thead>
                                <tr class="border-b border-defaultborder">
                                    <th scope="col" class="text-start">#</th>
                                    <!-- <th scope="col" class="text-start">Product UUID</th> -->
                                    <th scope="col" class="text-start">Product Name</th>
                                    <th scope="col" class="text-start">Private Charter</th>
                                    <th scope="col" class="text-start">Air Ambulance</th>
                                    <th scope="col" class="text-start">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $index => $product)
                                    <tr class="border-b border-defaultborder product-list">
                                        <td>{{ $index + 1 }}</td>
                                        <!-- <td><code class="text-[0.75rem]">{{ $product->id }}</code></td> -->
                                        <td class="font-semibold">{{ $product->product }}</td>
                                        <td>
                                            @if ($product->is_private)
                                                <span class="badge bg-info text-white">Yes</span>
                                            @else
                                                <span class="badge bg-secondary text-white">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($product->is_airambulance)
                                                <span class="badge bg-warning text-white">Yes</span>
                                            @else
                                                <span class="badge bg-secondary text-white">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-success text-white">Active</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-[#8c9097] dark:text-white/50">
                                            No active products available to sync.
                                        </td>
                                    </tr>
                                @endforelse
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
        document.addEventListener('DOMContentLoaded', function() {
            const syncBtn = document.getElementById('syncProductsBtn');
            const syncLoading = document.getElementById('syncLoading');
            const syncSummary = document.getElementById('syncSummary');
            const syncResults = document.getElementById('syncResults');
            const resultsTableBody = document.getElementById('resultsTableBody');

            syncBtn.addEventListener('click', function() {
                // Reset UI
                syncBtn.disabled = true;
                syncLoading.classList.remove('hidden');
                syncSummary.classList.add('hidden');
                syncResults.classList.add('hidden');
                resultsTableBody.innerHTML = '';

                // CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                // Make sync request
                fetch('{{ route('admin.product-sync.sync') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        syncLoading.classList.add('hidden');
                        syncBtn.disabled = false;

                        if (data.success) {
                            // Update summary
                            document.getElementById('summary-total').textContent = data.summary.total;
                            document.getElementById('summary-created').textContent = data.summary
                                .created;
                            document.getElementById('summary-existing').textContent = data.summary
                                .existing;
                            document.getElementById('summary-failed').textContent = data.summary.failed;

                            syncSummary.classList.remove('hidden');

                            // Populate results table
                            data.results.forEach((result, index) => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-defaultborder';

                                let statusBadge = '';
                                if (result.status === 'created') {
                                    statusBadge =
                                        '<span class="badge bg-success text-white">Created</span>';
                                } else if (result.status === 'existing') {
                                    statusBadge =
                                        '<span class="badge bg-info text-white">Already Exists</span>';
                                } else if (result.status === 'error') {
                                    statusBadge =
                                        '<span class="badge bg-danger text-white">Error</span>';
                                } else {
                                    statusBadge =
                                        '<span class="badge bg-secondary text-white">Unknown</span>';
                                }

                                const encoded = encodeURIComponent(JSON.stringify(result));
                                row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${result.product_name}</td>
                            <td>${statusBadge}</td>
                            <td>${result.airpoints_id ? '<code>' + result.airpoints_id + '</code>' : '-'}</td>
                            <td>${result.airpoints_name || '-'}</td>
                        `;

                                resultsTableBody.appendChild(row);
                            });

                            syncResults.classList.remove('hidden');

                            // Attach details button handlers
                            resultsTableBody.querySelectorAll('.details-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    try {
                                        const raw = decodeURIComponent(this.dataset
                                            .result);
                                        const obj = JSON.parse(raw);
                                        const win = window.open('', '_blank');
                                        win.document.write('<pre>' + JSON.stringify(obj,
                                            null, 4) + '</pre>');
                                        win.document.title = 'Sync Details';
                                    } catch (e) {
                                        alert('Unable to show details: ' + e.message);
                                    }
                                });
                            });

                            // Show success message
                            showAlert('success', data.message ||
                                'Product sync completed successfully!');
                        } else {
                            showAlert('danger', data.message || 'Failed to sync products');
                        }
                    })
                    .catch(error => {
                        syncLoading.classList.add('hidden');
                        syncBtn.disabled = false;
                        console.error('Sync error:', error);
                        showAlert('danger', 'An error occurred during sync: ' + error.message);
                    });
            });

            function showAlert(type, message) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} mb-4`;
                alertDiv.textContent = message;

                const pageHeader = document.querySelector('.page-header');
                pageHeader.insertAdjacentElement('afterend', alertDiv);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        });
    </script>
@endpush
