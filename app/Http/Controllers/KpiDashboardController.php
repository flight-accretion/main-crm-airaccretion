<?php

namespace App\Http\Controllers;

use App\Services\Kpi\KpiDashboardDetailService;
use App\Services\Kpi\KpiDashboardFilterService;
use App\Services\Kpi\KpiDashboardService;
use App\Services\Kpi\KpiDataScopeService;
use Illuminate\Http\Request;

class KpiDashboardController extends Controller
{
public function index(
    Request $request,
    KpiDashboardService $dashboard,
    KpiDataScopeService $scope
) {
    $current = $request->user()->load('userType');

    /*
     * Super Admin / Admin:
     * KPI Dashboard defaults to Retail Sales.
     *
     * Sales Manager:
     * own + assigned team.
     *
     * Sales Executive:
     * own KPI only.
     *
     * Accounts / Operations:
     * own configured department scope.
     */
    $department = $scope->isAdmin($current)
        ? 'sales'
        : $scope->departmentFor($current);

    if (!$department) {
        abort(
            403,
            'No KPI department is configured for this role.'
        );
    }

    $users = $scope
        ->allowedUsers($current, $department)
        ->values();

    if ($users->isEmpty()) {
        abort(
            403,
            'No KPI users are available for this scope.'
        );
    }

    /*
     * KPI Dashboard now shows current KPI status only.
     * There is no date/filter/dashboard-card layer here.
     */
    $asOf = now()->endOfDay();

    $team = $users
        ->map(function ($user) use ($dashboard, $asOf) {
            return $dashboard->forUser(
                $user,
                $asOf
            );
        })
        ->values();

    return view(
        'admin.pages.kpi.dashboard',
        [
            'team' => $team,
            'asOf' => $asOf,
            'department' => $department,
        ]
    );
}

    public function details(
        Request $request,
        string $metric,
        KpiDashboardFilterService $filters,
        KpiDataScopeService $scope,
        KpiDashboardDetailService $details
    ) {
        $current = $request->user()->load('userType');
        $filter = $filters->fromRequest($request);

        $department = $scope->isAdmin($current)
            ? ($filter['department'] ?: 'sales')
            : $scope->departmentFor($current);

        if ($department !== 'sales') {
            return response()->json([
                'columns' => [],
                'rows' => [],
                'message' => 'Detailed drill-down is not configured for this department metric yet.',
            ]);
        }

        $scope->assertRequestedUserAllowed(
            $current,
            $filter['user_id'],
            $department
        );

        $ids = $scope->allowedUserIds($current, $department);

        if ($filter['user_id']) {
            $ids = [(string) $filter['user_id']];
        }

        return response()->json(
            $details->sales(
                $metric,
                $ids,
                $filter['from'],
                $filter['to'],
                $filter['lead_source']
            )
        );
    }
}
