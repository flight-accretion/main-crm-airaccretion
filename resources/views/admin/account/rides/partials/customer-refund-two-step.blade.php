<div id="customer-refund-two-step"
     class="cr2 crm-customer-refund"
     data-read-url="{{ route('admin.rides.customer-refund.two-step.show', ['rideId' => '__RIDE__']) }}"
     data-assess-url="{{ route('admin.rides.customer-refund.two-step.assessment', ['rideId' => '__RIDE__']) }}"
     data-pay-url="{{ route('admin.rides.customer-refund.two-step.payment', ['rideId' => '__RIDE__']) }}"
     data-correct-url="{{ route('admin.rides.customer-refund.two-step.correct-payment', ['rideId' => '__RIDE__']) }}">
    <div class="cr2-alert" id="cr2-alert" role="status" hidden></div>
    <div class="cr2-steps">
        <form id="cr2-assessment-form" class="cr2-band">
            @csrf
            <div class="cr2-heading"><strong>Step 1 · Customer Cancellation</strong><span class="cr2-badge">Assessment</span></div>
            <p class="cr2-description">Review customer payment, enter the cancellation amount and reason, then Save Cancellation. It will appear in the existing Customer Refund Queue.</p>
            <div class="cr2-grid">
                <label>Payment Received
                    <input id="cr2-paid" type="number" step="0.01" value="0.00" readonly>
                </label>
                <label>Cancellation Amount *
                    <input id="cr2-charge" name="cancellation_amount" type="number" min="0" step="0.01" required>
                </label>
                <label>Refund Due
                    <input id="cr2-due" type="text" value="0.00" readonly>
                </label>
                <label class="cr2-wide">Cancellation reason *
                    <textarea id="cr2-reason" name="reason" required maxlength="4000" rows="2"></textarea>
                </label>
            </div>
           <div class="cr2-edit-actions">
            <button
                type="button"
                id="cr2-edit-assessment"
                class="ti-btn ti-btn-outline-primary"
                hidden
            >
                <i class="ri-edit-line me-1"></i>
                Edit Cancellation
            </button>

            <button
                type="submit"
                id="cr2-assess-btn"
                class="ti-btn ti-btn-primary-full"
            >
                Save Cancellation
            </button>

            <button
                type="button"
                id="cr2-cancel-assessment-edit"
                class="ti-btn ti-btn-light"
                hidden
            >
                Cancel Edit
            </button>
        </div>
        </form>
        <form id="cr2-payment-form" class="cr2-band" enctype="multipart/form-data">
            @csrf
            <div class="cr2-heading"><strong>Step 2 · Customer Refund</strong><span class="cr2-badge" id="cr2-status">Assessment Pending</span></div>
            <p class="cr2-description">Record the actual amount paid back after Step 1. Partial payments are supported. Save Refund Payment records only this payment.</p>
            <div class="cr2-grid">
                <label>Refund Assessed
                    <input id="cr2-refund-total" type="text" readonly value="0.00">
                </label>
                <label>Already Refunded
                    <input id="cr2-already" type="text" readonly value="0.00">
                </label>
                <label>Remaining Refund
                    <input id="cr2-remaining" type="text" readonly value="0.00">
                </label>
                <label>Refund Amount *
                    <input name="amount" id="cr2-amount" min="0.01" step="0.01" type="number" required>
                </label>
                <label>Refund method *
                    <select name="refund_type" id="cr2-method" required>
                        <option value="">Select refund method</option>
                        @foreach(['Bank Transfer','UPI Payment','Cash','Cheque','Debit Card','Credit Card','Net Banking','Wallet','Payment Gateway','Other'] as $method)
                            <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Refund date *
                    <input name="refund_date" id="cr2-date" type="date" required>
                </label>
                <label class="cr2-wide">Refund proof (PDF/JPG/PNG, up to 2MB) *
                    <input name="refund_proof" id="cr2-proof" type="file" accept=".pdf,.png,.jpg,.jpeg" required>
                </label>
                <label class="cr2-wide">Remarks
                    <textarea name="remarks" id="cr2-remarks" rows="2" maxlength="2000"></textarea>
                </label>
            </div>
            <div class="cr2-actions">
                <button type="submit" class="ti-btn ti-btn-primary" id="cr2-pay-btn">Save Refund Payment</button>
            </div>
        </form>
    </div>
    <div class="cr2-history" id="cr2-history" aria-live="polite"></div>
    <a href="{{ route('admin.refunds.index') }}" class="ti-btn ti-btn-outline-primary ti-btn-sm" style="width:auto;">Open Customer Refund Queue</a>
</div>
