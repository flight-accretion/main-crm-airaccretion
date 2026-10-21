<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserType;

class LeadDetailAccess
{
    public static function canViewAccountsActivity(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type,
            array_merge([UserType::SUPER_ADMIN], UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES), true);
    }
}
