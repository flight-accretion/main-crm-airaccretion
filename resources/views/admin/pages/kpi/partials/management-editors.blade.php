<section class="km-editors">
@foreach($templates as $template)
    <details id="km-template-{{ $template->id }}" class="km-editor">
        <summary>{{ $template->name }} · Edit template and metrics</summary>
        <form method="POST" action="{{ route('admin.kpi.templates.update', $template) }}" class="km-editor-fields">
            @csrf @method('PUT')
            <label>Name<input name="name" value="{{ $template->name }}" maxlength="150" required></label>
            <label>Department<select name="department">@foreach(['sales','operations','accounts'] as $department)<option value="{{ $department }}" {{ $template->department === $department ? 'selected' : '' }}>{{ ucfirst($department) }}</option>@endforeach</select></label>
            <label>Working days/month<input name="working_days_per_month" type="number" min="1" max="31" value="{{ $template->working_days_per_month }}" required></label>
            <label>Status<select name="active"><option value="1" {{ $template->active ? 'selected' : '' }}>Active</option><option value="0" {{ !$template->active ? 'selected' : '' }}>Inactive</option></select></label>
            <label>Effective from<input type="date" name="effective_from" value="{{ optional($template->effective_from)->format('Y-m-d') }}"></label>
            <label>Effective to<input type="date" name="effective_to" value="{{ optional($template->effective_to)->format('Y-m-d') }}"></label>
            <button class="ti-btn ti-btn-primary">Save template</button>
        </form>
        @foreach($template->metrics->sortBy('sort_order') as $metric)
        <details class="km-editor">
            <summary>{{ $metric->name }} · {{ $metric->measurement_type === 'automatic' ? 'Automatic' : 'Manual' }}</summary>
            <form method="POST" action="{{ route('admin.kpi.metrics.update', $metric) }}" class="km-editor-fields">
                @csrf @method('PUT')<input type="hidden" name="template_id" value="{{ $template->id }}">
                <label>Code<input name="code" value="{{ $metric->code }}" required></label>
                <label>Name<input name="name" value="{{ $metric->name }}" required></label>
                <label>Description<input name="description" value="{{ $metric->description }}"></label>
                <label>Weight<input type="number" name="weightage" min="0" max="100" step="0.01" value="{{ $metric->weightage }}" required></label>
                <label>Measurement<select name="measurement_type">@foreach(['automatic','manual_numeric','manual_rating'] as $type)<option value="{{ $type }}" {{ $metric->measurement_type === $type ? 'selected' : '' }}>{{ $type }}</option>@endforeach</select></label>
                <label>Source key<input name="source_key" value="{{ $metric->source_key }}"></label>
                <label>Target value<input type="number" name="target_value" step="0.01" value="{{ $metric->target_value }}"></label>
                <label>Direction<select name="direction">@foreach(['higher_better','lower_better'] as $direction)<option value="{{ $direction }}" {{ $metric->direction === $direction ? 'selected' : '' }}>{{ $direction }}</option>@endforeach</select></label>
                <label>Status<select name="active"><option value="1" {{ $metric->active ? 'selected' : '' }}>Active</option><option value="0" {{ !$metric->active ? 'selected' : '' }}>Inactive</option></select></label>
                @foreach([5,4,3,2,1] as $score)<label>{{ $score }}/5 rule<input type="number" name="score_{{ $score }}" step="0.01" value="{{ $metric->score_rules[$score] ?? 0 }}" required></label>@endforeach
                <label>Sort order<input type="number" name="sort_order" value="{{ $metric->sort_order }}"></label>
                <button class="ti-btn ti-btn-primary">Save metric</button>
            </form>
        </details>
        @endforeach
    </details>
@endforeach
</section>
