@extends('admin.layouts.header')
@section('content')
    <div class="block justify-between page-header md:flex"></div>

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

    <div id="flash-messages"></div>

    <div class="grid grid-cols-12">
        <div class="xl:col-span-12  col-span-12">
            <div class="box">
                <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                    <div class="flex items-center">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                <i class="las la-exclamation-triangle" style="color:white"></i>
                            </span>
                        </div>
                        <div class="flex-grow">
                            <div class="flex items-center justify-between">
                                <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Exceptional Dashboard</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">Entries where Received &gt; Total</div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>S.No</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Service Date</th>
                                    <th>Received / Total</th>
                                    <th>Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $index => $p)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>{{ $p->first_name }}</td>
                                        <td class="text-center">{{ $p->phone_number }}</td>
                                        <td class="text-center">{{ $p->from_date ? \Carbon\Carbon::parse($p->from_date)->format('d-m-Y') : 'N/A' }}</td>
                                        <td class="text-center">₹{{ number_format($p->received_amount,2) }} / ₹{{ number_format($p->total_amount,2) }}</td>
                                        <td class="text-center">₹{{ number_format($p->balance,2) }}</td>
                                        <td>
                                            <div class="flex gap-2">
                                                {{-- Add to Sales button --}}
                                                @if(!empty($p->added_to_sales))
                                                    <button type="button" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full" disabled title="Already added to sales">
                                                        <i class="ri-check-double-line"></i>
                                                    </button>
                                                @else
                                                    <button type="button"
                                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full add-to-sales-btn"
                                                        data-followup-id="{{ $p->followup_id }}"
                                                        data-url="{{ route('admin.account.exceptional.add-to-sales', $p->followup_id) }}"
                                                        data-extra="{{ $p->received_amount - $p->total_amount }}"
                                                        title="Add extra amount to Sales">
                                                        <i class="ri-add-circle-line"></i>
                                                    </button>
                                                @endif

                                                {{-- Refund button --}}
                                                @if(!empty($p->has_refund_note))
                                                    <a href="{{ route('admin.refunds.index') }}?followup_id={{ $p->followup_id }}" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full" title="View Refund Note">
                                                        <i class="ri-check-line"></i>
                                                    </a>
                                                @else
                                                    <button type="button" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-warning-full process-refund-btn"
                                                        data-lead-id="{{ $p->lead_id }}"
                                                        data-followup-id="{{ $p->followup_id }}"
                                                        data-total-amount="{{ $p->total_amount }}"
                                                        data-received-amount="{{ $p->received_amount }}"
                                                        data-service-ids='@json($p->service_ids)'
                                                        data-from-date="{{ $p->from_date }}"
                                                        title="Process Refund">
                                                        <i class="ri-refund-2-line"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center">No exceptional entries found.</td></tr>
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
        document.addEventListener('DOMContentLoaded', function () {

            // ── Custom popup helpers (same style as vendor-payments page) ────────
            function showSuccessPopup(title, message, onClose) {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML =
                    `<div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                        <div style="margin-bottom:20px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#dcfce7;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" fill="#22c55e"/>
                                    <path d="M7.5 12.5L10.5 15.5L16.5 9.5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">${title}</h5>
                        <p style="color:#6b7280;margin-bottom:20px;font-size:0.9rem;">${message}</p>
                        <button class="popup-ok-btn" style="background:#2B53A9;color:#fff;border:none;border-radius:8px;padding:10px 40px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>
                    </div>`;
                document.body.appendChild(overlay);
                overlay.querySelector('.popup-ok-btn').onclick = function() {
                    overlay.remove();
                    if (onClose) onClose();
                };
            }

            function showErrorPopup(title, message) {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML =
                    `<div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                        <div style="margin-bottom:20px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#fee2e2;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" fill="#ef4444"/>
                                    <path d="M8 8L16 16M16 8L8 16" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">${title}</h5>
                        <p style="color:#6b7280;margin-bottom:20px;font-size:0.9rem;">${message}</p>
                        <button class="popup-ok-btn" style="background:#2B53A9;color:#fff;border:none;border-radius:8px;padding:10px 40px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>
                    </div>`;
                document.body.appendChild(overlay);
                overlay.querySelector('.popup-ok-btn').onclick = function() { overlay.remove(); };
            }

            function showConfirmPopup(title, message, confirmText, confirmColor, onConfirm) {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML =
                    `<div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                        <div style="margin-bottom:20px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#d2dffd;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" fill="#2B53A9"/>
                                    <path d="M12 8v4" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
                                    <circle cx="12" cy="16" r="1.25" fill="#fff"/>
                                </svg>
                            </span>
                        </div>
                        <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">${title}</h5>
                        <p style="color:#6b7280;margin-bottom:20px;font-size:0.9rem;">${message}</p>
                        <div style="display:flex;gap:12px;justify-content:center;">
                            <button class="popup-confirm-btn" style="background:${confirmColor};color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:1rem;font-weight:600;cursor:pointer;">${confirmText}</button>
                            <button class="popup-cancel-btn" style="background:#6c757d;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:1rem;font-weight:600;cursor:pointer;">Cancel</button>
                        </div>
                    </div>`;
                document.body.appendChild(overlay);
                overlay.querySelector('.popup-cancel-btn').onclick = function() { overlay.remove(); };
                overlay.querySelector('.popup-confirm-btn').onclick = function() {
                    overlay.remove();
                    if (onConfirm) onConfirm();
                };
            }

            // ── Add to Sales button handler ──────────────────────────────────────
            document.querySelectorAll('.add-to-sales-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const self         = this;
                    const url          = self.getAttribute('data-url');
                    const extra        = parseFloat(self.getAttribute('data-extra') || 0);
                    const originalHTML = self.innerHTML;

                    showConfirmPopup(
                        'Add to Sales?',
                        `The extra amount of <strong>₹${extra.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong> will be permanently added to the sales amount.<br><br>This action <u>cannot be undone</u>.`,
                        'Yes, Add to Sales',
                        '#2B53A9',
                        async function () {
                            self.disabled  = true;
                            self.innerHTML = '<i class="ri-loader-4-line animate-spin"></i>';

                            try {
                                const response = await fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({}),
                                });

                                const data = await response.json();

                                if (data.success) {
                                    self.classList.remove('ti-btn-primary-full', 'add-to-sales-btn');
                                    self.classList.add('ti-btn-success-full');
                                    self.innerHTML = '<i class="ri-check-double-line"></i>';
                                    self.title     = 'Already added to sales';
                                    self.disabled  = true;

                                    showSuccessPopup(
                                        'Added to Sales!',
                                        data.message || 'Amount successfully added to sales.'
                                    );
                                } else {
                                    self.disabled  = false;
                                    self.innerHTML = originalHTML;
                                    showErrorPopup('Failed!', data.message || 'Error adding to sales. Please try again.');
                                }
                            } catch (err) {
                                console.error('Add to Sales error:', err);
                                self.disabled  = false;
                                self.innerHTML = originalHTML;
                                showErrorPopup('Network Error', 'Could not connect to the server. Please try again.');
                            }
                        }
                    );
                });
            });

            // ── Process Refund button handler ────────────────────────────────────
            document.querySelectorAll('.process-refund-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const self           = this;
                    const followupId     = self.getAttribute('data-followup-id');
                    const totalAmount    = parseFloat(self.getAttribute('data-total-amount'));
                    const receivedAmount = parseFloat(self.getAttribute('data-received-amount'));
                    const extraAmount    = receivedAmount - totalAmount;
                    const originalHTML   = self.innerHTML;

                    if (extraAmount <= 0) {
                        showErrorPopup('No Excess Amount', 'There is no excess amount to refund for this entry.');
                        return;
                    }

                    showConfirmPopup(
                        'Create Refund Note?',
                        `A refund note for <strong>₹${extraAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong> will be created and you will be redirected to the refund page.`,
                        'Yes, Create Refund',
                        '#2B53A9',
                        async function () {
                            self.disabled  = true;
                            self.innerHTML = '<i class="ri-loader-4-line animate-spin"></i>';

                            try {
                                const response = await fetch('{{ route("admin.account.exceptional.create-refund-note") }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        followup_id: followupId,
                                        refund_amount: extraAmount,
                                        refund_reason: `Excess payment refund. Received: ₹${receivedAmount.toFixed(2)}, Total: ₹${totalAmount.toFixed(2)}, Extra: ₹${extraAmount.toFixed(2)}`,
                                    }),
                                });

                                const data = await response.json();

                                if (data.success) {
                                    showSuccessPopup(
                                        'Refund Note Created!',
                                        'Redirecting to refund details...',
                                        function () {
                                            window.location.href = `/admin/refunds?followup_id=${followupId}`;
                                        }
                                    );
                                } else {
                                    self.disabled  = false;
                                    self.innerHTML = originalHTML;
                                    showErrorPopup('Failed!', data.message || 'Error creating refund note. Please try again.');
                                }
                            } catch (error) {
                                console.error('Refund error:', error);
                                self.disabled  = false;
                                self.innerHTML = originalHTML;
                                showErrorPopup('Network Error', 'Could not connect to the server. Please try again.');
                            }
                        }
                    );
                });
            });
        });
    </script>
@endpush
