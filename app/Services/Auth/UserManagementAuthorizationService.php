<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserType;

class UserManagementAuthorizationService
{
    public function assertCanAssignRole(User $actor, UserType $targetRole): void
    {
        if (
            $targetRole->user_type === UserType::SUPER_ADMIN
            && optional($actor->userType)->user_type !== UserType::SUPER_ADMIN
        ) {
            abort(403, 'Only Super Admin can assign the Super Admin role.');
        }
    }

    public function assertCanManageUser(User $actor, User $target): void
    {
        if (
            optional($target->userType)->user_type === UserType::SUPER_ADMIN
            && optional($actor->userType)->user_type !== UserType::SUPER_ADMIN
        ) {
            abort(403, 'You are not authorized to modify this user.');
        }
    }
}
