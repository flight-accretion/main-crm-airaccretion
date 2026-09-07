@extends('admin.layouts.header')

@section('content')
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white text-[1.125rem] font-semibold">
                Booking Email Template</h3>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12">
            <div class="box">
                <form method="POST" action="{{ route('admin.booking-email-template.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="box-header">
                        <div class="box-title">Email Content</div>
                    </div>

                    <div class="box-body">
                        <div class="mb-4">
                            <label for="subject" class="ti-form-label">Subject</label>
                            <input
                                id="subject"
                                type="text"
                                name="subject"
                                class="ti-form-input"
                                value="{{ old('subject', $template->subject) }}"
                                required
                            >
                        </div>

                        <div>
                            <label for="body" class="ti-form-label">Body</label>
                            <textarea
                                id="body"
                                name="body"
                                rows="24"
                                class="ti-form-input"
                                required
                            >{{ old('body', $template->body) }}</textarea>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button type="submit" class="ti-btn ti-btn-primary-full">
                            Save Template
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="xl:col-span-4 col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="box-title">Variables</div>
                </div>
                <div class="box-body">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($variables as $variable)
                            <code class="px-2 py-1 rounded-sm bg-gray-100 text-xs">
                                {{ '{' . '{' . $variable . '}' . '}' }}
                            </code>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
