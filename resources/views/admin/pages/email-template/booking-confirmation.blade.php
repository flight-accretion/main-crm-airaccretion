@extends('admin.layouts.header')

@section('content')
    @php
        $revisions = $revisions ?? collect();
        $currentVersion = $currentVersion ?? 0;
        $historyReady = $historyReady ?? true;
        $previewData = ['subject'=>$template->subject, 'body'=>$template->body, 'variables'=>$variables, 'samples'=>\App\Services\BookingEmailTemplatePreview::samples(), 'testUrl'=>route('admin.booking-email-template.test')];
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/booking-email.css') }}">
    <div class="booking-email-page">
    <header class="be-heading">
        <div><h1>Booking Email Template</h1><p>Booking confirmation email</p></div>
        <div class="be-actions">
            <button type="button" class="be-button" id="booking-discard" disabled><i class="ri-arrow-go-back-line" aria-hidden="true"></i> Discard changes</button>
            <button type="submit" form="booking-template-form" class="be-button be-primary" @if(!$historyReady) disabled @endif><i class="ri-save-line" aria-hidden="true"></i> Save as v{{ max(1, $currentVersion) + 1 }}</button>
        </div>
    </header>

    @if (session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(!$historyReady)<div class="alert alert-warning" role="alert">Version history setup is pending. Run the booking email history migration to enable saving.</div>@endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="be-workspace">
        <aside class="be-templates" aria-labelledby="be-templates-title">
            <h2 id="be-templates-title">Templates</h2>
            <div class="be-template-selected" aria-current="true">
                <div><strong>Booking Confirmation</strong><span class="be-tag be-active">Active</span></div>
                <small>Booking confirmation email</small>
                @if($template->updated_at)<small>Edited {{ $template->updated_at->format('d M Y') }}</small>@endif
            </div>
        </aside>
        <section class="be-editor" aria-labelledby="be-editor-title">
            <div class="be-section-heading"><h2 id="be-editor-title">Booking Confirmation @if($currentVersion)&middot; v{{ $currentVersion }}@endif</h2><span id="booking-dirty" class="be-tag" role="status">Saved</span></div>
            <p class="be-meta">@if($template->updated_at)Last saved {{ $template->updated_at->format('d M Y, H:i') }}@else Default template @endif</p>
            <button type="button" class="be-button be-test-button" id="booking-send-test"><i class="ri-mail-send-line" aria-hidden="true"></i> Send test to me</button>
            <p id="booking-test-result" class="be-test-result" role="status" hidden></p>
                <form id="booking-template-form" method="POST" action="{{ route('admin.booking-email-template.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="expected_version" value="{{ old('expected_version', $currentVersion) }}">

                        <div class="be-field">
                            <label for="subject">Subject line *</label>
                            <input
                                id="subject"
                                type="text"
                                name="subject"
                                class="ti-form-input"
                                maxlength="500"
                                value="{{ old('subject', $template->subject) }}"
                                required
                            >
                        </div>

                        <div class="be-field">
                            <label for="body">Body * <small>Plain text with merge variables</small></label>
                            <textarea
                                id="body"
                                name="body"
                                rows="16"
                                maxlength="30000"
                                class="ti-form-input"
                                required
                            >{{ old('body', $template->body) }}</textarea>
                        </div>
                    <div class="be-variables" aria-label="Merge variables">
                        @foreach ($variables as $variable)
                            <button type="button" data-booking-variable="{{ $variable }}" title="Insert {{ $variable }}"><code>{{ '{'.'{'.$variable.'}'.'}' }}</code></button>
                        @endforeach
                    </div>
                    <p id="booking-variable-warning" class="be-variable-warning" role="status" hidden></p>
                    <div class="be-field"><label for="booking-change-note">Change note</label><input id="booking-change-note" name="change_note" maxlength="500" value="{{ old('change_note') }}"></div>
                    <div class="be-timing"><span>Send timing</span><strong>Existing booking confirmation action</strong></div>
                </form>
        </section>
    </div>
    <section class="be-preview" aria-labelledby="be-preview-title">
        <div class="be-section-heading"><h2 id="be-preview-title">Live preview</h2><span class="be-tag">Sample booking</span></div>
        <div class="be-mail">
            <dl class="be-mail-heading">
                <dt>From</dt><dd>{{ config('mail.from.address') ?: 'Not configured' }}</dd>
                <dt>To</dt><dd>customer@example.test</dd>
                <dt>Subject</dt><dd id="booking-preview-subject"></dd>
            </dl>
            <div class="be-mail-body" id="booking-preview-body"></div>
        </div>
        <div class="be-saved">
            <h2>Version history</h2>
            @forelse($revisions as $revision)
                <div class="be-version">
                    <div class="be-version-heading"><strong>v{{ $revision->version }} @if($revision->version === $currentVersion)<span class="be-tag be-active">Current</span>@endif</strong><time>{{ $revision->created_at->format('d M Y, H:i') }}</time></div>
                    <p>{{ optional($revision->author)->name ?: 'Previous editor' }} &middot; {{ $revision->change_note }}</p>
                    <details><summary>View version</summary><strong>{{ $revision->subject }}</strong><pre>{{ $revision->body }}</pre></details>
                    @if($revision->version !== $currentVersion)
                        <form method="POST" class="be-restore-form" action="{{ route('admin.booking-email-template.restore', $revision->id) }}" data-version="{{ $revision->version }}">
                            @csrf
                            <input type="hidden" name="expected_version" value="{{ $currentVersion }}">
                            <button type="submit" class="be-button"><i class="ri-history-line" aria-hidden="true"></i> Restore v{{ $revision->version }}</button>
                        </form>
                    @endif
                </div>
            @empty
            <div class="be-saved-row"><i class="ri-file-text-line" aria-hidden="true"></i><div><strong>Booking Confirmation</strong><small>@if($template->updated_at){{ $template->updated_at->format('d M Y, H:i') }}@else Default template @endif</small></div><span class="be-tag be-active">Current</span></div>
            @endforelse
            @if($revisions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $revisions->hasPages())<div class="be-history-pagination">{{ $revisions->links() }}</div>@endif
        </div>
    </section>
    <script type="application/json" id="booking-template-data">@json($previewData)</script>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/crm/booking-email.js') }}" defer></script>
@endpush
