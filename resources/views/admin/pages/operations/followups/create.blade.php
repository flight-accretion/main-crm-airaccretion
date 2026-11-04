@extends('admin.layouts.header')

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-followup.css') }}">

    @php
        $caseLabel = ucwords(str_replace('_', ' ', $operationCase->type));
        $caseStatus = ucwords(str_replace('_', ' ', $operationCase->status));
    @endphp

    <div class="operations-followup-page">
        <header class="of-heading">
            <div>
                <p class="of-eyebrow">Operations Queue</p>
                <h1>Add Follow-up</h1>
                <p>Record the outcome and set the next action for this operations case.</p>
            </div>
            <a class="of-button of-secondary" href="{{ route('admin.operations.queue', $operationCase->type) }}"><i class="ri-arrow-left-line" aria-hidden="true"></i> Back to queue</a>
        </header>

        @if (session('success'))<div class="of-notice" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="of-notice of-notice-error" role="alert">{{ $errors->first() }}</div>@endif

        <div class="of-layout"><main>
            <section class="of-card">
                <header class="of-card-header"><div><h2>Case Summary</h2><p>Information is read-only. This screen only records an Operations follow-up.</p></div></header>
                <div class="of-summary-grid">
                    <div><small>Customer</small><strong>{{ $client?->name ?: 'Not recorded' }}</strong></div>
                    <div><small>Phone</small><strong>{{ $client?->contact_number ?: 'Not recorded' }}</strong></div>
                    <div><small>Case type</small><strong>{{ $caseLabel }}</strong></div>
                    <div><small>Current status</small><strong><span class="of-status">{{ $caseStatus }}</span></strong></div>
                    <div><small>Assigned employee</small><strong>{{ $operationCase->assignee?->name ?: 'Unassigned' }}</strong></div>
                    <div><small>Next follow-up</small><strong>{{ $operationCase->next_followup_at?->format('d M Y · H:i') ?: 'Not scheduled' }}</strong></div>
                </div>
            </section>

            <section class="of-card">
                <header class="of-card-header"><div><h2>Follow-up Details</h2><p>Save only the Operations outcome. Customer and lead details are not edited here.</p></div></header>
                <form method="POST" action="{{ route('admin.operations.followups.store', $operationCase) }}" class="of-form">
                    @csrf
                    <label for="notes">Follow-up note <span>Required unless customer did not pick up</span><textarea id="notes" name="notes" rows="5" maxlength="1000" placeholder="What happened? Record the customer response, issue, and next action.">{{ old('notes', $operationCase->note) }}</textarea></label>
                    <label class="of-checkbox" for="customer_not_picked_up"><input id="customer_not_picked_up" type="checkbox" name="customer_not_picked_up" value="1" {{ old('customer_not_picked_up') ? 'checked' : '' }}><span><strong>Customer did not pick up</strong><small>You can save without a note when no contact was made.</small></span></label>
                    <div class="of-form-grid">
                        <label for="operation_status">Operations status<select id="operation_status" name="operation_status" required>@foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" {{ old('operation_status', $operationCase->status ?: 'in_progress') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
                        <label for="next_followup_at">Next follow-up date and time <span>Optional for completed cases</span><input id="next_followup_at" type="datetime-local" name="next_followup_at" value="{{ old('next_followup_at', $operationCase->next_followup_at?->format('Y-m-d\TH:i')) }}"></label>
                    </div>
                    @if ($operationCase->type === \App\Models\OperationCase::TYPE_REVIEW)<p class="of-help"><i class="ri-information-line" aria-hidden="true"></i> A review case can be marked completed only after both Review and Image Collection are resolved through the Review Queue.</p>@endif
                    <footer class="of-actions"><a class="of-button of-secondary" href="{{ route('admin.operations.queue', $operationCase->type) }}">Cancel</a><button class="of-button of-primary" type="submit"><i class="ri-save-line" aria-hidden="true"></i> Save Follow-up</button></footer>
                </form>
            </section>
        </main></div>
    </div>
@endsection
