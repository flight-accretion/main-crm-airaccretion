<?php

namespace App\Http\Controllers;

use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use App\Services\Kpi\KpiDashboardFilterService;
use App\Services\Kpi\KpiDashboardService;
use App\Services\Kpi\KpiDataScopeService;
use Illuminate\Http\Request;

class OperationsKpiDashboardController extends Controller
{
    public function index(
        Request $request,
        KpiDashboardService $dashboard,
        KpiDataScopeService $scope,
        KpiDashboardFilterService $filters
    ) {
        $current = $request->user()->load('userType');
        $filter = $filters->fromRequest($request);
        $department = 'operations';

        $users = $scope
            ->allowedUsers($current, $department)
            ->values();

        if ($users->isEmpty()) {
            abort(403, 'No Operations KPI users are available for this scope.');
        }

        $from = $filter['from'];
        $asOf = $filter['to'];

        $team = $users
            ->map(fn ($user) => $dashboard->forUser($user, $asOf, $from))
            ->values();

        $template = KpiTemplate::query()
            ->with(['metrics' => function ($query) {
                $query
                    ->where('active', true)
                    ->whereIn('measurement_type', ['manual_numeric', 'manual_rating'])
                    ->orderBy('sort_order');
            }])
            ->where('department', 'operations')
            ->where('active', true)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        $manualMetrics = $template?->metrics ?? collect();

        return view('admin.pages.kpi.dashboard', [
            'team' => $team,
            'asOf' => $asOf,
            'department' => $department,
            'filter' => $filter,
            'canSwitchDepartment' => false,
            'filterAction' => route('admin.operations.kpi'),
            'showOperationsManualEntry' => true,
            'operationsManualUsers' => $users,
            'operationsManualMetrics' => $manualMetrics,
        ]);
    }

    public function saveManualValue(
        Request $request,
        KpiDataScopeService $scope
    ) {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'metric_id' => 'required|uuid|exists:kpi_metrics,id',
            'year' => 'required|integer|min:2025|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'value' => 'required|numeric|min:0|max:100',
            'note' => 'nullable|string|max:2000',
        ]);

        $current = $request->user()->load('userType');

        $scope->assertRequestedUserAllowed(
            $current,
            $data['user_id'],
            'operations'
        );

        $metric = KpiMetric::query()
            ->with('template')
            ->findOrFail($data['metric_id']);

        abort_unless(
            $metric->template
                && $metric->template->department === 'operations'
                && in_array(
                    $metric->measurement_type,
                    ['manual_numeric', 'manual_rating'],
                    true
                ),
            422,
            'Selected KPI is not an Operations manual KPI.'
        );

        KpiManualValue::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'metric_id' => $metric->id,
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            [
                'value' => $metric->measurement_type === 'manual_numeric'
                    ? $data['value']
                    : null,
                'rating' => $metric->measurement_type === 'manual_rating'
                    ? min(5, max(1, (int) round($data['value'])))
                    : null,
                'note' => $data['note'] ?? null,
                'entered_by' => $current->id,
            ]
        );

        return back()->with('success', 'Operations KPI manual value saved.');
    }
}
