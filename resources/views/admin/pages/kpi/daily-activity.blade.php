@extends('admin.layouts.header')

@section('content')
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">
            {{ ucfirst($department) }} Daily Activity
        </h3>
        <p class="text-sm text-gray-500">
            Existing KPI actuals as of {{ $asOf->format('d M Y') }}.
        </p>
    </div>
</div>

<div class="box">
    <div class="box-header">
        <h5 class="box-title">Team KPI Activity</h5>
    </div>
    <div class="box-body overflow-x-auto">
        @php
            $columns = collect($team)
                ->flatMap(function ($row) {
                    return collect($row['metrics'] ?? [])->mapWithKeys(function ($metric) {
                        return [$metric['code'] => $metric['name']];
                    });
                })
                ->all();
        @endphp

        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Role</th>
                    <th>Overall /5</th>
                    @foreach($columns as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($team as $row)
                    @php $metrics = collect($row['metrics'] ?? [])->keyBy('code'); @endphp
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ optional($row['user']->userType)->user_type }}</td>
                        <td>
                            {{ $row['configured'] ? ((int) $row['overall_score']) . '/5' : 'Not configured' }}
                        </td>
                        @foreach($columns as $code => $label)
                            <td>
                                @if($metrics->has($code))
                                    {{ $metrics->get($code)['display_actual'] ?? (int) ($metrics->get($code)['actual_value'] ?? 0) }}
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($columns) }}">No KPI team members available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
