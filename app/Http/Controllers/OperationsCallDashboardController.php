<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserType;
use App\Services\Operations\OperationsCallDashboardService;
use Illuminate\Http\Request;

class OperationsCallDashboardController extends Controller
{
    public function index(Request $request, OperationsCallDashboardService $dashboard)
    {
        $user = $request->user();
        $user?->loadMissing('userType');

        abort_unless(
            $user
            && (
                $user->isSuperAdmin()
                || in_array(optional($user->userType)->user_type, UserType::OPERATIONS_ROLES, true)
            ),
            403
        );

        return view('admin.pages.operations.customer-calls.index', [
            'rows' => $dashboard->rows($user),
            'operationsUsers' => User::query()
                ->whereHas('userType', function ($query) {
                    $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
                })
                ->where('status', 1)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
