@extends('admin.layouts.header')
@section('content')
    @php
        // Read-only overview for the queue + cards. The sync itself is unchanged:
        // "Sync all" pushes the products in $products (active, not yet synced, max 20 per run).
        $hasSyncColumn = \Illuminate\Support\Facades\Schema::hasColumn('products', 'sync_at');
        $nextRunIds = $products->pluck('id')->map(fn ($id) => (string) $id)->all();
        $allProducts = \App\Models\Product::orderBy('product')->get();
        $isSynced = fn ($p) => $hasSyncColumn && (int) $p->sync_at === 1;
        $activeProducts = $allProducts->where('status', 1);
        $syncedCount = $activeProducts->filter($isSynced)->count();
        $readyAll = $activeProducts->reject($isSynced);
        $inactiveCount = $allProducts->where('status', '!=', 1)->count();
        // Queue order: next run first, then other ready products, then synced, then inactive.
        $rank = function ($p) use ($nextRunIds, $isSynced) {
            if ((int) $p->status !== 1) return 3;
            if (in_array((string) $p->id, $nextRunIds, true)) return 0;
            return $isSynced($p) ? 2 : 1;
        };
        $queue = $allProducts->sortBy(fn ($p) => $rank($p))->values();
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/products/product-sync.css') }}?v=1">

    <div class="crm-kimi ps-page">
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

        <header class="ps-heading" id="psHeading">
            <div>
                <h1>Product Sync — Airpoints</h1>
                <p>Push active CRM products to the Airpoints loyalty system</p>
            </div>
            <div class="ps-heading-actions">
                <a href="{{ route('admin.products.index') }}" class="k-ghost"><i class="ri-arrow-left-line" aria-hidden="true"></i> Products</a>
                <button type="button" id="syncProductsBtn" class="k-primary" title="{{ $products->isEmpty() ? 'Nothing is waiting to sync right now' : 'Push ' . $products->count() . ' product(s) to Airpoints' }}">
                    <i class="ri-refresh-line" aria-hidden="true"></i> Sync all to Airpoints
                </button>
            </div>
        </header>
        <div id="psAlerts"></div>

        <section class="ps-stats" aria-label="Sync summary">
            <div class="ps-stat acc-brand">
                <strong>{{ $activeProducts->count() }}</strong>
                <div class="ps-label">Active products in CRM</div>
                <div class="ps-sub2">inactive products never sync</div>
            </div>
            <div class="ps-stat acc-green">
                <strong id="statSynced">{{ $hasSyncColumn ? $syncedCount : '—' }}</strong>
                <div class="ps-label">Synced with Airpoints</div>
                <div class="ps-sub2">pushed in an earlier run</div>
            </div>
            <div class="ps-stat acc-amber">
                <strong id="statReady">{{ $readyAll->count() }}</strong>
                <div class="ps-label">Ready to sync</div>
                <div class="ps-sub2">next run pushes <span id="statNextRun">{{ $products->count() }}</span> (max 20 per run)</div>
            </div>
            <div class="ps-stat acc-neutral">
                <strong>{{ $inactiveCount }}</strong>
                <div class="ps-label">Inactive</div>
                <div class="ps-sub2">won't sync until activated</div>
            </div>
        </section>

        <section class="k-section ps-info" aria-labelledby="ps-info-title">
            <div class="k-section-head"><h2 id="ps-info-title">How matching works</h2></div>
            <div class="k-section-meta ps-info-text">Matching is <b>case-insensitive</b> ("Mumbai Ride" = "mumbai ride"). Products with exact name matches are <b>skipped</b> — nothing is overwritten. <b>Sync all</b> pushes active products that are not synced yet, up to 20 per run.</div>
        </section>

        <!-- Sync log: result of the run started on this page (hidden until a run). -->
        <section class="k-section" aria-labelledby="ps-log-title" id="syncLogSection" hidden>
            <div class="k-section-head">
                <h2 id="ps-log-title">Sync log</h2>
                <span class="k-tag neutral">this run</span>
            </div>

            <!-- Loading Indicator -->
            <div id="syncLoading" class="hidden ps-loading">
                <span class="ps-spinner" aria-hidden="true"></span>
                Syncing products with Airpoints, please wait...
            </div>

            <!-- Summary -->
            <div id="syncSummary" class="hidden">
                <div class="ps-logline" id="syncLogLine"></div>
                <div class="ps-runstats">
                    <div><span>Total synced</span><strong id="summary-total">0</strong></div>
                    <div class="is-green"><span>Created new</span><strong id="summary-created">0</strong></div>
                    <div class="is-blue"><span>Already exists</span><strong id="summary-existing">0</strong></div>
                    <div class="is-red"><span>Failed</span><strong id="summary-failed">0</strong></div>
                </div>
            </div>

            <!-- Results Table -->
            <div id="syncResults" class="hidden">
                <h3 class="ps-subtitle">Sync details</h3>
                <div class="k-tblwrap">
                    <table class="ps-table ps-results no-horizontal-scroll">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product name (Accretion)</th>
                                <th>Sync status</th>
                                <th>Airpoints ID</th>
                                <th>Airpoints name</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="k-section" aria-labelledby="ps-queue-title">
            <div class="k-section-head">
                <h2 id="ps-queue-title">Sync queue</h2>
                <span class="k-tag amber" id="queueTag">{{ $products->count() }} in next run</span>
            </div>
            <div class="k-section-meta">Each product shows its Airpoints state. Products marked <b>Next run</b> are pushed when you click <b>Sync all to Airpoints</b>.</div>
            <div class="k-tblwrap">
                <table class="ps-table ps-queue no-horizontal-scroll">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>CRM status</th>
                            <th>Airpoints state</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($queue as $product)
                            @php
                                $rowRank = $rank($product);
                                $categories = \App\Http\Controllers\ProductController::productCategories($product);
                            @endphp
                            <tr class="product-list {{ $rowRank === 0 ? 'is-next' : '' }}" data-product-id="{{ $product->id }}">
                                <td data-label="Product"><div class="ps-main">{{ $product->product }}</div></td>
                                <td data-label="Type">
                                    <div class="ps-tags">
                                        @foreach ($categories as $category)
                                            @if ($category !== 'Other' && $category !== 'Ambulance')
                                                <span class="k-tag blue">{{ $category }}</span>
                                            @endif
                                        @endforeach
                                        @if ($product->is_private)
                                            <span class="k-tag neutral">Private Charter</span>
                                        @endif
                                        @if ($product->is_airambulance)
                                            <span class="k-tag amber">Air Ambulance</span>
                                        @endif
                                        @if ($categories === ['Other'] && !$product->is_private && !$product->is_airambulance)
                                            <span class="k-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="CRM status">
                                    @if ((int) $product->status === 1)
                                        <span class="k-tag green">Active</span>
                                    @else
                                        <span class="k-tag neutral">Inactive</span>
                                    @endif
                                </td>
                                <td data-label="Airpoints" class="ps-state">
                                    @if ($rowRank === 3)
                                        <span class="k-tag neutral">Won't sync — inactive</span>
                                    @elseif ($rowRank === 2)
                                        <span class="k-tag green">Synced</span>
                                    @elseif ($rowRank === 0)
                                        <span class="k-tag amber">Next run — will push</span>
                                    @else
                                        <span class="k-tag amber">Ready — later run</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ps-empty">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <nav class="ps-pager" id="queuePager" aria-label="Sync queue pages" hidden></nav>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        // Sync queue pagination: 10 products per page (rows stay in the page, only hidden).
        document.addEventListener('DOMContentLoaded', function() {
            const pager = document.getElementById('queuePager');
            const rows = Array.from(document.querySelectorAll('.ps-queue tbody tr.product-list'));
            const perPage = 10;
            const pages = Math.ceil(rows.length / perPage);
            if (pages <= 1) return;
            let current = 1;

            function button(label, page, opts = {}) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'ps-page-btn' + (opts.active ? ' is-active' : '');
                b.textContent = label;
                b.disabled = !!opts.disabled;
                if (opts.active) b.setAttribute('aria-current', 'page');
                if (opts.aria) b.setAttribute('aria-label', opts.aria);
                b.addEventListener('click', () => show(page, true));
                return b;
            }

            function show(page, focus) {
                current = Math.min(Math.max(page, 1), pages);
                rows.forEach((row, i) => { row.hidden = Math.floor(i / perPage) + 1 !== current; });
                const from = (current - 1) * perPage + 1, to = Math.min(current * perPage, rows.length);
                pager.replaceChildren();
                const info = document.createElement('span');
                info.className = 'ps-page-info';
                info.textContent = 'Showing ' + from + '–' + to + ' of ' + rows.length;
                const btns = document.createElement('div');
                btns.className = 'ps-page-btns';
                btns.append(button('Previous', current - 1, { disabled: current === 1 }));
                for (let p = 1; p <= pages; p++) {
                    if (pages > 7 && p !== 1 && p !== pages && Math.abs(p - current) > 1) {
                        if (!btns.lastChild.classList.contains('ps-page-gap')) {
                            const gap = document.createElement('span'); gap.className = 'ps-page-gap'; gap.textContent = '…'; btns.append(gap);
                        }
                        continue;
                    }
                    btns.append(button(String(p), p, { active: p === current, aria: 'Page ' + p }));
                }
                btns.append(button('Next', current + 1, { disabled: current === pages }));
                pager.append(info, btns);
                pager.hidden = false;
                if (focus) pager.querySelector('[aria-current]').focus();
            }
            show(1, false);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const syncBtn = document.getElementById('syncProductsBtn');
            const syncLoading = document.getElementById('syncLoading');
            const syncSummary = document.getElementById('syncSummary');
            const syncResults = document.getElementById('syncResults');
            const resultsTableBody = document.getElementById('resultsTableBody');
            const logSection = document.getElementById('syncLogSection');
            const runBy = @json(optional(auth()->user())->name);

            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            syncBtn.addEventListener('click', function() {
                // Reset UI
                syncBtn.disabled = true;
                logSection.hidden = false;
                syncLoading.classList.remove('hidden');
                syncSummary.classList.add('hidden');
                syncResults.classList.add('hidden');
                resultsTableBody.innerHTML = '';

                // CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                // Make sync request (unchanged)
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
                        const when = new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

                        if (data.success) {
                            // Update summary
                            document.getElementById('summary-total').textContent = data.summary.total;
                            document.getElementById('summary-created').textContent = data.summary.created;
                            document.getElementById('summary-existing').textContent = data.summary.existing;
                            document.getElementById('summary-failed').textContent = data.summary.failed;

                            const failed = Number(data.summary.failed) || 0;
                            document.getElementById('syncLogLine').innerHTML =
                                `<span class="k-tag ${failed ? 'amber' : 'green'}">${failed ? '! Finished with errors' : '✓ Success'}</span>` +
                                `<b>${esc(when)}</b>` +
                                `<span class="k-muted">${esc(data.summary.total)} pushed · ${esc(data.summary.created)} created · ${esc(data.summary.existing)} already existed · ${failed} failed${runBy ? ' · by ' + esc(runBy) : ''}</span>`;
                            syncSummary.classList.remove('hidden');

                            // Populate results table + update the queue rows that synced
                            let syncedNow = 0;
                            (data.results || []).forEach((result, index) => {
                                const row = document.createElement('tr');

                                let statusBadge = '';
                                if (result.status === 'created') {
                                    statusBadge = '<span class="k-tag green">Created</span>';
                                } else if (result.status === 'existing') {
                                    statusBadge = '<span class="k-tag blue">Already exists</span>';
                                } else if (result.status === 'error') {
                                    statusBadge = '<span class="k-tag red">Error</span>';
                                } else {
                                    statusBadge = '<span class="k-tag neutral">Unknown</span>';
                                }

                                row.innerHTML = `
                                    <td data-label="#">${index + 1}</td>
                                    <td data-label="Product">${esc(result.product_name)}</td>
                                    <td data-label="Status">${statusBadge}${result.status === 'error' && result.message ? `<div class="ps-sub">${esc(result.message)}</div>` : ''}</td>
                                    <td data-label="Airpoints ID">${result.airpoints_id ? '<code>' + esc(result.airpoints_id) + '</code>' : '-'}</td>
                                    <td data-label="Airpoints name">${esc(result.airpoints_name) || '-'}</td>
                                `;
                                resultsTableBody.appendChild(row);

                                const queueRow = document.querySelector(`.ps-queue tr[data-product-id="${CSS.escape(String(result.product_uuid))}"]`);
                                if (queueRow) {
                                    queueRow.classList.remove('is-next');
                                    queueRow.querySelector('.ps-state').innerHTML = result.success
                                        ? '<span class="k-tag green">Synced · just now</span>'
                                        : '<span class="k-tag red">Failed — try again</span>';
                                }
                                if (result.success) syncedNow++;
                            });

                            if (syncedNow) {
                                const synced = document.getElementById('statSynced');
                                const ready = document.getElementById('statReady');
                                if (!isNaN(Number(synced.textContent))) synced.textContent = Number(synced.textContent) + syncedNow;
                                ready.textContent = Math.max(0, Number(ready.textContent) - syncedNow);
                            }
                            document.getElementById('queueTag').textContent = 'reload to see the next run';

                            syncResults.classList.remove('hidden');

                            // Show success message
                            showAlert('success', data.message || 'Product sync completed successfully!');
                        } else {
                            document.getElementById('syncLogLine').innerHTML =
                                `<span class="k-tag red">✕ Failed</span><b>${esc(when)}</b><span class="k-muted">${esc(data.message || 'Failed to sync products')}</span>`;
                            syncSummary.classList.remove('hidden');
                            showAlert('danger', data.message || 'Failed to sync products');
                        }
                    })
                    .catch(error => {
                        syncLoading.classList.add('hidden');
                        syncBtn.disabled = false;
                        console.error('Sync error:', error);
                        document.getElementById('syncLogLine').innerHTML =
                            `<span class="k-tag red">✕ Failed</span><span class="k-muted">${esc(error.message)}</span>`;
                        syncSummary.classList.remove('hidden');
                        showAlert('danger', 'An error occurred during sync: ' + error.message);
                    });
            });

            function showAlert(type, message) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} mb-4`;
                alertDiv.textContent = message;

                document.getElementById('psAlerts').appendChild(alertDiv);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        });
    </script>
@endpush
