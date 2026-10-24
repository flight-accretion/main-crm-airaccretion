<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserType;
use App\Models\Lead;

class LeadDetailAccess
{
    public static function canViewRideBooking(?User $user, Lead $lead): bool
    {
        return $user && in_array($user->userType?->user_type, UserType::ACCOUNTS_ROLES, true)
            && Lead::whereKey($lead->getKey())->whereHas('client')->whereHas('rideSegments')
                ->whereHas('vouchers')->whereHas('leadFollowups')->exists();
    }

    public static function canViewAccountsActivity(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type,
            array_merge([UserType::SUPER_ADMIN], UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES), true);
    }
}
