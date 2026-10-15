<section class="kpi-person-panel" id="kpi-person-{{ $personIndex }}" data-person-panel="{{ $personIndex }}" @if($personIndex !== 0) hidden @endif>
    <header class="kpi-person-heading">
        <span class="kpi-avatar kpi-avatar-large">{{ $presentation['initials'] }}</span>
        <div><h3>{{ $row['user']->name ?? '-' }}</h3><p>{{ $departmentTitle }} · {{ $filter['from']->format('d M') }} - {{ $filter['to']->format('d M Y') }}</p></div>
        <div class="kpi-overall">@if($row['configured'] ?? false)<strong>{{ (int) ($row['overall_score'] ?? 1) }}</strong><span>/5</span>@endif <span class="kpi-tag kpi-{{ $presentation['tone'] }}">{{ $presentation['status'] }}</span></div>
    </header>
    <div class="kpi-metric-list" data-kpi-dashboard-table>
        <div class="kpi-metric-row kpi-metric-head"><span>Metric</span><span>Weight</span><span>Rating bands (5 → 1)</span><span>Actual</span><span>Score</span><span>Next band</span></div>
        @forelse($matrixRows as $code => $metricMeta)
            @php
                $metric = collect($row['metrics'] ?? [])->firstWhere('code', $code);
                $definition = $metric ?? $metricMeta;
                $bands = $definition['rating_labels'] ?? $definition['score_rules'] ?? [];
                $nextScore = $metric['next_score'] ?? null;
            @endphp
            <div class="kpi-metric-row" data-kpi-dashboard-row data-search="{{ $metricSearch[$code] ?? '' }}">
                <div class="kpi-metric-name"><strong>{{ $definition['name'] ?? '-' }}</strong>@if(!empty($definition['description']))<p>{{ $definition['description'] }}</p>@endif</div>
                <div class="kpi-metric-weight"><span class="kpi-mobile-label">Weight: </span>{{ $metric ? rtrim(rtrim(number_format((float) ($metric['weightage'] ?? 0), 2), '0'), '.') : '-' }}</div>
                <div class="kpi-bands">@foreach([5,4,3,2,1] as $bandScore)<span class="kpi-band {{ $metric && (int) ($metric['score'] ?? 1) === $bandScore ? 'is-active' : '' }}" title="{{ $bandScore }}/5">{{ $bands[$bandScore] ?? '-' }}</span>@endforeach</div>
                <div class="kpi-metric-actual">{{ $metric['display_actual'] ?? '-' }}</div>
                <div class="kpi-metric-score">@if($metric)@include('admin.pages.kpi.partials.metric-score-button', ['metric' => $metric])@else - @endif</div>
                <div class="kpi-metric-next">@if($metric){{ $nextScore ? ($bands[$nextScore] ?? $metric['next_threshold'] ?? '-') : 'Maintain' }}@else - @endif</div>
            </div>
        @empty
            <p class="kpi-empty">No KPI template has been assigned.</p>
        @endforelse
        <div class="kpi-search-empty" hidden>No matching KPI rows</div>
    </div>
    @if($matrixRows->isNotEmpty())
        <footer class="kpi-weighted-total" data-kpi-dashboard-total-row><span>Weighted total · {{ rtrim(rtrim(number_format(collect($row['metrics'] ?? [])->sum('weightage'), 2), '0'), '.') }} weight</span><strong>@if($row['configured'] ?? false){{ isset($row['weighted_raw_score']) ? number_format($row['weighted_raw_score'], 2) : 'N/A' }} → {{ (int) ($row['overall_score'] ?? 1) }}/5 @else Not configured @endif</strong></footer>
    @endif
</section>
