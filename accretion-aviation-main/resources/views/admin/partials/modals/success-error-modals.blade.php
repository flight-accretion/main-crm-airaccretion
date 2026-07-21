<!-- Reusable Success / Error / Confirmation Modals and helper JS -->
<!-- Success Message Modal -->
<div id="success-message-modal" class="hs-overlay hidden ti-modal">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-blue-600 mb-2" id="success-message-title">Success!</h3>
                <p class="text-gray-600 mb-1" id="success-message-text">Action completed successfully.</p>
                <p class="text-sm text-gray-500" id="success-message-date" style="display:none">Date</p>
            </div>
            <div class="ti-modal-footer justify-center">
                <button type="button" class="ti-btn bg-primary text-white !font-medium" data-hs-overlay="#success-message-modal" id="success-modal-ok-btn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Message Modal -->
<div id="error-message-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-red-600 mb-2" id="error-message-title">Error</h3>
                <p class="text-gray-600 mb-1" id="error-message-text">An error occurred.</p>
            </div>
            <div class="ti-modal-footer justify-center">
                <button type="button" class="ti-btn bg-red-600 text-white" data-hs-overlay="#error-message-modal" id="error-modal-ok-btn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="hs-overlay hidden ti-modal">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M6.938 20h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2" id="confirmation-title">Confirm Action</h3>
                <p class="text-gray-600 mb-1" id="confirmation-text">Are you sure you want to proceed?</p>
            </div>
            <div class="ti-modal-footer justify-center space-x-3">
                <button type="button" class="ti-btn bg-gray-500 text-white" data-hs-overlay="#confirmation-modal">Cancel</button>
                <button type="button" class="ti-btn bg-primary text-white" id="confirm-action-btn">Confirm</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Reusable function to show success message modal
    function showSuccessMessage(action, message) {
        const currentDate = new Date();
        const dateStr = currentDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const timeStr = currentDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: true });

        $('#success-message-title').text('Success!');
        $('#success-message-text').text(message || 'Action completed successfully.');
        $('#success-message-date').text(`Date: ${dateStr} | ${timeStr}`).show();

        // Ensure OK button reloads by default
        $('#success-modal-ok-btn').off('click').on('click', function() {
            location.reload();
        });

        window.HSOverlay.open(document.getElementById('success-message-modal'));
    }

    // Wrapper compatible function used across the app: showSuccessModal(title, message, onCloseCallback)
    function showSuccessModal(title, message, onCloseCallback) {
        const currentDate = new Date();
        const dateStr = currentDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const timeStr = currentDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: true });

        $('#success-message-title').text(title || 'Success!');
        $('#success-message-text').text(message || 'Action completed successfully.');
        $('#success-message-date').text(`Date: ${dateStr} | ${timeStr}`).show();

        $('#success-modal-ok-btn').off('click').on('click', function() {
            var modalEl = document.getElementById('success-message-modal');
            // Close via HSOverlay if available, otherwise toggle classes
            if (window.HSOverlay && typeof window.HSOverlay.close === 'function') {
                try { window.HSOverlay.close(modalEl); } catch (e) { modalEl.classList.add('hidden'); }
            } else {
                modalEl.classList.add('hidden');
            }
            if (typeof onCloseCallback === 'function') {
                try { onCloseCallback(); } catch (e) { console.error(e); }
            } else {
                // default behavior: reload
                location.reload();
            }
        });

        var successModalEl = document.getElementById('success-message-modal');
        if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
            window.HSOverlay.open(successModalEl);
        } else {
            successModalEl.classList.remove('hidden');
            successModalEl.classList.add('open');
        }
    }

    // Reusable function to show error modal and optionally run a callback when closed
    function showErrorModal(title, message, onCloseCallback) {
        $('#error-message-title').text(title || 'Error');
        $('#error-message-text').text(message || 'An error occurred. Please try again.');

        $('#error-modal-ok-btn').off('click').on('click', function() {
            var modalEl = document.getElementById('error-message-modal');
            if (window.HSOverlay && typeof window.HSOverlay.close === 'function') {
                try { window.HSOverlay.close(modalEl); } catch (e) { modalEl.classList.add('hidden'); }
            } else {
                modalEl.classList.add('hidden');
            }

            if (typeof onCloseCallback === 'function') {
                try { onCloseCallback(); } catch (e) { console.error(e); }
            }
        });

        var errorModalEl = document.getElementById('error-message-modal');
        if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
            window.HSOverlay.open(errorModalEl);
        } else {
            errorModalEl.classList.remove('hidden');
            errorModalEl.classList.add('open');
        }
    }

    // Friendly wrapper used across the app: prefer error modal, fallback to alert if missing
    function showFriendlyError(title, message, onCloseCallback) {
        try {
            if (typeof showErrorModal === 'function') {
                showErrorModal(title, message, onCloseCallback);
                return;
            }
        } catch (e) {
            // ignore and fallback
        }

        // Final fallback to browser alert
        try {
            alert((title ? title + ': ' : '') + (message || 'An error occurred'));
        } catch (e) {
            console.error('Failed to show friendly error', e);
        }
    }

    // Reusable confirmation modal
    function showConfirmationModal(title, message, onConfirm) {
        $('#confirmation-title').text(title || 'Confirm Action');
        $('#confirmation-text').text(message || 'Are you sure you want to proceed?');
        $('#confirm-action-btn').off('click').on('click', function() {
            window.HSOverlay.close(document.getElementById('confirmation-modal'));
            if (typeof onConfirm === 'function') onConfirm();
        });
        window.HSOverlay.open(document.getElementById('confirmation-modal'));
    }
</script>
@endpush
