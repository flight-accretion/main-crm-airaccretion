<?php

namespace App\Services\Kpi;

use App\Models\KpiTeamMembership;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;

class KpiDataScopeService
{
    public function departmentFor(User $user): ?string
    {
        $role = $user->userType->user_type ?? null;

        if (in_array($role, UserType::SALES_ROLES, true)) {
            return 'sales';
        }

        if (in_array($role, UserType::ACCOUNTS_ROLES, true)) {
            return 'accounts';
        }

        if (in_array($role, UserType::OPERATIONS_ROLES, true)) {
            return 'operations';
        }

        return null;
    }

    public function isAdmin(User $user): bool
    {
        return in_array(
            $user->userType->user_type ?? '',
            UserType::ADMIN_ROLES,
            true
        );
    }

    public function allowedUsers(
        User $current,
        ?string $requestedDepartment = null
    ): Collection {
        $current->loadMissing('userType');
        $role = $current->userType->user_type ?? '';

        if ($this->isAdmin($current)) {
            $roles = match ($requestedDepartment) {
                'sales' => UserType::SALES_ROLES,
                'accounts' => UserType::ACCOUNTS_ROLES,
                'operations' => UserType::OPERATIONS_ROLES,
                default => array_merge(
                    UserType::SALES_ROLES,
                    UserType::ACCOUNTS_ROLES,
                    UserType::OPERATIONS_ROLES
                ),
            };

            return User::with('userType')
                ->where('status', 1)
                ->whereHas('userType', function ($query) use ($roles) {
                    $query->whereIn('user_type', $roles);
                })
                ->orderBy('name')
                ->get();
        }

        if (in_array($role, [
            UserType::SENIOR_SALES_MANAGER,
            UserType::SALES_MANAGER,
        ], true)) {
            $ids = SalesExecutiveAssignment::query()
                ->where('manager_id', $current->id)
                ->where('status', 1)
                ->pluck('sales_executive_id')
                ->push($current->id)
                ->unique()
                ->values();

            return User::with('userType')
                ->where('status', 1)
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get();
        }

        $department = $this->departmentFor($current);

        $isAccountsManager = in_array($role, [
            UserType::SENIOR_ACCOUNTS_MANAGER,
            UserType::ACCOUNTS_MANAGER,
        ], true);

        $isOperationsManager = in_array($role, [
            UserType::SENIOR_OPERATIONS_MANAGER,
            UserType::OPERATIONS_MANAGER,
        ], true);

        if ($isAccountsManager || $isOperationsManager) {
            $ids = KpiTeamMembership::query()
                ->where('department', $department)
                ->where('manager_user_id', $current->id)
                ->where('active', true)
                ->pluck('member_user_id')
                ->push($current->id)
                ->unique()
                ->values();

            return User::with('userType')
                ->where('status', 1)
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get();
        }

        return User::with('userType')
            ->where('id', $current->id)
            ->get();
    }

    public function allowedUserIds(
        User $current,
        ?string $requestedDepartment = null
    ): array {
        return $this->allowedUsers($current, $requestedDepartment)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function assertRequestedUserAllowed(
        User $current,
        ?string $requestedUserId,
        ?string $department = null
    ): void {
        if (!$requestedUserId) {
            return;
        }

        if (!in_array(
            (string) $requestedUserId,
            $this->allowedUserIds($current, $department),
            true
        )) {
            abort(
                403,
                'You are not allowed to view KPI data for this user.'
            );
        }
    }
}
