{{-- Complete popup for the Operations queue. Buttons: .complete-case-btn[data-complete-url][data-client-name] --}}
<div id="complete-case-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="flex items-center justify-center min-h-screen w-full fixed inset-0 z-50"
        style="background: rgba(0,0,0,0.2);">
        <div class="ti-modal-box ti-modal-content bg-white rounded shadow-lg" style="max-width: 480px; width: 100%;">
            <form method="POST" action="" id="complete-case-form">
                @csrf

                <div class="ti-modal-header">
                    <h6 class="modal-title text-[1rem] font-semibold">Complete Operations Case</h6>
                    <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                        data-complete-close>
                        <span class="sr-only">Close</span>
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="ti-modal-body px-4 py-4">
                    <p class="text-gray-600 mb-4">
                        Mark the case for <span class="font-semibold" id="complete-case-client">this lead</span>
                        as completed. This only closes the Operations case.
                    </p>

                    <label for="complete-case-note" class="ti-form-label mb-1">Completion note (optional)</label>
                    <textarea id="complete-case-note" name="note" rows="4" maxlength="5000"
                        class="form-control" placeholder="Completion note"></textarea>
                </div>

                <div class="ti-modal-footer">
                    <button type="button" class="ti-btn ti-btn-outline-secondary" data-complete-close>
                        Cancel
                    </button>
                    <button type="submit" class="ti-btn ti-btn-success" id="complete-case-submit">
                        Complete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('complete-case-modal');
            const form = document.getElementById('complete-case-form');

            function openModal() {
                modal.classList.remove('hidden');
                modal.classList.add('open');

                const backdrop = document.createElement('div');
                backdrop.id = 'complete-case-backdrop';
                backdrop.className = 'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
                backdrop.onclick = closeModal;
                document.body.appendChild(backdrop);
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('open');

                const backdrop = document.getElementById('complete-case-backdrop');

                if (backdrop) {
                    backdrop.remove();
                }

                document.body.style.overflow = '';
            }

            $(document).on('click', '.complete-case-btn', function () {
                form.action = $(this).data('complete-url');
                document.getElementById('complete-case-client').textContent = $(this).data('client-name') || 'this lead';
                document.getElementById('complete-case-note').value = '';
                document.getElementById('complete-case-submit').disabled = false;
                openModal();
            });

            $(document).on('click', '[data-complete-close]', closeModal);

            // Prevent double submits.
            form.addEventListener('submit', function () {
                document.getElementById('complete-case-submit').disabled = true;
            });
        })();
    </script>
@endpush
