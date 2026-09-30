<?php

namespace App\Services\LeadChat;

use App\Models\Lead;
use App\Models\User;
use App\Models\UserType;

class LeadChatAccessService
{
    public function canAccess(
        User $user,
        Lead $lead
    ): bool {

        $user->loadMissing(
            'userType'
        );


        $role =
            $user
                ->userType
                ?->user_type;


        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        |
        | Super Admin can access every Lead chat.
        |
        */

        if (
            $user->isSuperAdmin()
        ) {

            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Sales Executive
        |--------------------------------------------------------------------------
        |
        | Sales Executive can access only the Lead
        | currently assigned to them.
        |
        */

        if (
            $role
            ===
            UserType::SALES_EXECUTIVE
        ) {

            return
                (string)
                $lead
                    ->representative_user_id

                ===

                (string)
                $user->id;
        }


        /*
        |--------------------------------------------------------------------------
        | Operations
        |--------------------------------------------------------------------------
        |
        | Operations users can access Lead chat.
        |
        */

        if (
            in_array(
                $role,
                UserType::OPERATIONS_ROLES,
                true
            )
        ) {

            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Everyone else
        |--------------------------------------------------------------------------
        */

        return false;
    }


    public function authorize(
        User $user,
        Lead $lead
    ): void {

        abort_unless(
            $this->canAccess(
                $user,
                $lead
            ),
            403,
            'You do not have access to this Lead chat.'
        );
    }


    public function roleGroup(
        User $user
    ): ?string {

        $user->loadMissing(
            'userType'
        );


        $role =
            $user
                ->userType
                ?->user_type;


        /*
         * Super Admin is oversight only.
         *
         * They can view/use Chat for testing/admin purposes,
         * but we do not classify them as Sales or Operations.
         */

        if (
            $user->isSuperAdmin()
        ) {

            return 'admin';
        }


        if (
            $role
            ===
            UserType::SALES_EXECUTIVE
        ) {

            return 'sales';
        }


        if (
            in_array(
                $role,
                UserType::OPERATIONS_ROLES,
                true
            )
        ) {

            return 'operations';
        }


        return null;
    }
}