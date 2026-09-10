@extends('admin.layouts.header')

@section('content')
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">KPI Dashboard</h3>
    </div>
    <div class="text-sm">As of {{ $asOf->format('d M Y') }}</div>
</div>

@if($mode === 'team')
    @php
        $columns = [
            'daily_outreach' => 'Outreach',
            'lead_conversion' => 'Conversion',
            'monthly_target' => 'Target',
            'response_time' => 'Response',
            'followup_sla' => 'Follow-Up',
            'payment_collection' => 'Payment',
            'attendance' => 'Attendance',
        ];
    @endphp

    <div class="box">
        <div class="box-header">
            <h5 class="box-title">Employee KPI Synopsis</h5>
        </div>
        <div class="box-body overflow-x-auto">
            <table class="table whitespace-nowrap min-w-full">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Overall /5</th>
                        @foreach($columns as $label)
                            <th class="text-center">{{ $label }} /5</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($team as $row)
                        @php
                            $metricsByCode = collect($row['metrics'] ?? [])->keyBy('code');
                        @endphp
                        <tr>
                            <td>{{ $row['user']->name }}</td>
                            <td>{{ optional($row['user']->userType)->user_type }}</td>
                            <td class="font-bold">
                                {{ $row['configured'] ? ((int) $row['overall_score']) . '/5' : 'Not configured' }}
                            </td>
                            @foreach($columns as $code => $label)
                                @php $metric = $metricsByCode->get($code); @endphp
                                <td class="text-center">
                                    @if($metric)
                                        @include('admin.pages.kpi.partials.metric-score-button', ['metric' => $metric])
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.pages.kpi.partials.metric-score-modal')
@else
    @if(!$result['configured'])
        <div class="alert alert-warning">KPI template has not been assigned to your login.</div>
    @else
        <div class="box mb-4">
            <div class="box-header flex justify-between items-center">
                <div>
                    <h5 class="box-title mb-0">My KPI</h5>
                    <small class="text-muted">
                        Click any KPI score to see exactly how it is calculated and how to improve it.
                    </small>
                </div>
                <div class="text-xl font-bold">
                    Overall KPI: {{ (int) $result['overall_score'] }}/5
                </div>
            </div>
            <div class="box-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Scope of Work</th>
                                <th class="text-center">Weight</th>
                                <th>Current Result</th>
                                <th class="text-center">KPI Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['metrics'] as $metric)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $metric['name'] }}</div>
                                        @if(!empty($metric['description']))
                                            <small class="text-muted">{{ $metric['description'] }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ rtrim(rtrim(number_format($metric['weightage'], 2), '0'), '.') }}
                                    </td>
                                    <td>{{ $metric['display_actual'] ?? '-' }}</td>
                                    <td class="text-center">
                                        @include('admin.pages.kpi.partials.metric-score-button', ['metric' => $metric])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <a class="ti-btn ti-btn-primary" href="{{ route('admin.kpi.outreach.index') }}">
            Open Daily Outreach
        </a>

        @include('admin.pages.kpi.partials.metric-score-modal')
    @endif
@endif
@endsection
