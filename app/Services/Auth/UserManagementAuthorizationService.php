<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserType;

class UserManagementAuthorizationService
{
    /**
     * Return the active roles the current staff manager is allowed to assign.
     */
    public function assignableUserTypes(User $actor)
    {
        $actorRole = optional($actor->userType)->user_type;

        $excludedRoles = match ($actorRole) {
            UserType::SUPER_ADMIN => [UserType::OWNER],
            UserType::OWNER => [UserType::SUPER_ADMIN],
            UserType::ADMIN, UserType::HR => [UserType::SUPER_ADMIN, UserType::OWNER],
            default => null,
        };

        abort_if($excludedRoles === null, 403, 'You are not authorized to manage staff roles.');

        return UserType::query()
            ->where('status', 1)
            ->whereNotIn('user_type', $excludedRoles)
            ->orderBy('user_type', 'asc')
            ->get();
    }

    public function assertCanAssignRole(User $actor, UserType $targetRole): void
    {
        $actorRole = optional($actor->userType)->user_type;
        $targetRoleName = $targetRole->user_type;

        if ($actorRole === UserType::SUPER_ADMIN && $targetRoleName === UserType::OWNER) {
            abort(403, 'Super Admin cannot assign the Owner role.');
        }

        if ($actorRole === UserType::OWNER && $targetRoleName === UserType::SUPER_ADMIN) {
            abort(403, 'Owner cannot assign the Super Admin role.');
        }

        if (
            in_array($actorRole, [UserType::ADMIN, UserType::HR], true)
            && in_array($targetRoleName, [UserType::SUPER_ADMIN, UserType::OWNER], true)
        ) {
            abort(403, 'Admin and HR cannot assign Owner or Super Admin roles.');
        }

        abort_unless(
            in_array($actorRole, [UserType::SUPER_ADMIN, UserType::OWNER, UserType::ADMIN, UserType::HR], true),
            403,
            'You are not authorized to assign staff roles.'
        );
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
