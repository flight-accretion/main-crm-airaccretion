@php
    $payload = [
        'name' => $metric['name'] ?? 'KPI',
        'description' => $metric['description'] ?? '',
        'score' => (int) ($metric['score'] ?? 1),
        'weightage' => (float) ($metric['weightage'] ?? 0),
        'display_actual' => $metric['display_actual'] ?? '-',
        'next_score' => $metric['next_score'] ?? null,
        'next_threshold' => $metric['next_threshold'] ?? null,
        'improvement_line' => $metric['improvement_line'] ?? '',
        'evidence' => $metric['evidence'] ?? [],
    ];
@endphp

<button
    type="button"
    class="ti-btn ti-btn-sm ti-btn-outline-primary js-kpi-score"
    data-kpi='@json($payload)'
>
    <strong>{{ (int) ($metric['score'] ?? 1) }}/5</strong>
</button>
