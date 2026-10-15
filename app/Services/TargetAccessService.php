<?php

namespace App\Services;

use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;

class TargetAccessService
{
    public function canConfigure(User $user): bool
    {
        return in_array(optional($user->userType)->user_type, [UserType::SUPER_ADMIN, UserType::HR], true);
    }

    public function canAssign(User $user): bool
    {
        return in_array(optional($user->userType)->user_type, [UserType::SUPER_ADMIN, UserType::HR, UserType::ADMIN, UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER], true);
    }

    public function staff(User $user): Collection
    {
        $role = optional($user->userType)->user_type;
        if (in_array($role, [UserType::SUPER_ADMIN, UserType::HR, UserType::ADMIN], true)) {
            return User::with('userType')->where('status', 1)->orderBy('name')->get();
        }
        if (in_array($role, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER], true)) {
            return SalesExecutiveAssignment::getSalesExecutivesForManager($user->id)->filter()
                ->where('status', 1)->unique('id')->sortBy('name')->values();
        }
        return collect([$user])->filter(fn ($staff) => (int) $staff->status === 1);
    }
}
