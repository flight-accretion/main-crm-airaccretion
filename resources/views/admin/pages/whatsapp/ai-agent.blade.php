@extends('admin.layouts.header')

@section('content')
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="text-xl font-semibold">
                WhatsApp AI Agent
            </h3>
            <p class="text-sm text-gray-500 mt-1">
                OpenAI auto-reply configuration
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.whatsapp.ai-agent.update') }}"
    >
        @csrf
        @method('PUT')

        <div class="box">
            <div class="box-header">
                <div class="box-title">
                    Agent Settings
                </div>
            </div>

            <div class="box-body">
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                {{ old('enabled', $setting->enabled) ? 'checked' : '' }}
                            >
                            <span>Enable AI Agent</span>
                        </label>
                    </div>

                    <div class="col-span-12">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                name="auto_reply_enabled"
                                value="1"
                                {{ old('auto_reply_enabled', $setting->auto_reply_enabled) ? 'checked' : '' }}
                            >
                            <span>Enable Auto Reply</span>
                        </label>
                    </div>

                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label" for="provider">
                            Provider
                        </label>
                        <select
                            name="provider"
                            id="provider"
                            class="ti-form-select"
                        >
                            <option
                                value="openai"
                                {{ old('provider', $setting->provider) === 'openai' ? 'selected' : '' }}
                            >
                                OpenAI
                            </option>
                        </select>
                    </div>

                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label" for="model">
                            Model
                        </label>
                        <input
                            type="text"
                            name="model"
                            id="model"
                            class="form-control"
                            value="{{ old('model', $setting->model) }}"
                            required
                        >
                    </div>

                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label" for="buffer_seconds">
                            Buffer Seconds
                        </label>
                        <input
                            type="number"
                            min="1"
                            max="300"
                            name="buffer_seconds"
                            id="buffer_seconds"
                            class="form-control"
                            value="{{ old('buffer_seconds', $setting->buffer_seconds) }}"
                            required
                        >
                    </div>

                    <div class="col-span-12">
                        <label class="ti-form-label" for="prompt">
                            Prompt
                        </label>
                        <textarea
                            name="prompt"
                            id="prompt"
                            class="form-control"
                            rows="10"
                            required
                        >{{ old('prompt', $setting->prompt) }}</textarea>
                    </div>

                    <div class="xl:col-span-8 md:col-span-8 col-span-12">
                        <label class="ti-form-label" for="api_key">
                            OpenAI API Key
                        </label>
                        <input
                            type="password"
                            name="api_key"
                            id="api_key"
                            class="form-control"
                            placeholder="{{ $setting->api_key_status === 'configured' ? 'Configured' : 'Missing' }}"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="xl:col-span-4 md:col-span-4 col-span-12 flex items-end">
                        <label class="flex items-center gap-2 mb-2">
                            <input
                                type="checkbox"
                                name="clear_api_key"
                                value="1"
                            >
                            <span>Clear saved key</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="box-footer text-end">
                <button
                    type="submit"
                    class="ti-btn ti-btn-primary"
                >
                    Save Settings
                </button>
            </div>
        </div>
    </form>
@endsection
