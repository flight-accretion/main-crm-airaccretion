<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserType;
use App\Support\LeadDetailAccess;
use PHPUnit\Framework\TestCase;

class LeadDetailAccessTest extends TestCase
{
    public function test_accounts_activity_is_restricted_to_explicit_roles(): void
    {
        foreach (array_merge(UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES, UserType::SALES_ROLES,
            [UserType::SUPER_ADMIN, UserType::ADMIN, UserType::HR]) as $role) {
            $user = new User();
            $user->setRelation('userType', new UserType(['user_type' => $role]));
            $expected = in_array($role, array_merge(UserType::OPERATIONS_ROLES, UserType::ACCOUNTS_ROLES, [UserType::SUPER_ADMIN]), true);
            $this->assertSame($expected, LeadDetailAccess::canViewAccountsActivity($user), $role);
        }
        $this->assertFalse(LeadDetailAccess::canViewAccountsActivity(null));
        // A user without a role; the relation is preset so this unit test never touches a database.
        $this->assertFalse(LeadDetailAccess::canViewAccountsActivity((new User())->setRelation('userType', null)));
    }
}
