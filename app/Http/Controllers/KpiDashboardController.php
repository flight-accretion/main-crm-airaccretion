<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserType;
use App\Services\Kpi\KpiDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KpiDashboardController extends Controller
{
    public function index(
        Request $request,
        KpiDashboardService $dashboard
    ) {
        $current = $request->user()->load('userType');
        $asOf = Carbon::parse(
            $request->input('date', now()->toDateString())
        )->endOfDay();

        $role = $current->userType->user_type ?? '';
        $oversight = in_array($role, [
            UserType::SUPER_ADMIN,
            UserType::HR,
        ], true);

        if ($oversight) {
            $users = User::with('userType')
                ->where('status', 1)
                ->whereHas('userType', function ($query) {
                    $query->whereIn(
                        'user_type',
                        array_merge(
                            UserType::SALES_ROLES,
                            UserType::OPERATIONS_ROLES,
                            UserType::ACCOUNTS_ROLES
                        )
                    );
                })
                ->orderBy('name')
                ->get();

            return view('admin.pages.kpi.dashboard', [
                'mode' => 'team',
                'team' => $users->map(fn ($user) => $dashboard->forUser($user, $asOf)),
                'result' => null,
                'asOf' => $asOf,
            ]);
        }

        return view('admin.pages.kpi.dashboard', [
            'mode' => 'self',
            'team' => collect(),
            'result' => $dashboard->forUser($current, $asOf),
            'asOf' => $asOf,
        ]);
    }
}
