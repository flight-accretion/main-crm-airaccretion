<?php

namespace App\Services;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsAppProductAllocationService
{
    public function __construct(
        private LeadAllocationService $leadAllocationService
    ) {
    }

    /**
     * Find a salesperson dynamically from the SAME
     * product-user configuration used for Email leads.
     */
    public function findUser(
        string $productId
    ): ?User {
        $settings =
            LeadAllocationSetting::getActiveSettings();

        /*
         * Respect existing CRM office hours.
         */
        if (
            !$this->leadAllocationService
                ->isOfficeOpenForDebug(
                    $settings,
                    now()
                )
        ) {
            return null;
        }

        /*
         * IMPORTANT:
         * No salesperson is hardcoded.
         *
         * Same product-user mapping configured
         * from CRM Email Lead Product Assignment.
         */
        $mappedUserIds =
            $this->mappedUserIdsForProduct(
                $productId
            );

        if ($mappedUserIds->isEmpty()) {

            Log::info(
                'WhatsApp product has no salesperson mapping',
                [
                    'product_id' => $productId,
                ]
            );

            return null;
        }

        /*
         * Only salespeople who confirmed YES today.
         */
        $availableIds =
            SalespersonAvailability::query()
                ->whereIn(
                    'user_id',
                    $mappedUserIds
                )
                ->where('is_available', true)
                ->where('is_opted_in', true)
                ->whereDate(
                    'last_response_at',
                    Carbon::today()
                )
                ->pluck('user_id');

        if ($availableIds->isEmpty()) {
            return null;
        }

        /*
         * User itself must still be active.
         */
        $users = User::query()
            ->whereIn('id', $availableIds)
            ->where('status', 1)
            ->get();

        if ($users->isEmpty()) {
            return null;
        }

        return $this->balancedUser($users);
    }

    public function hasConfiguredProductMapping(
        string $productId
    ): bool {
        return $this
            ->mappedUserIdsForProduct(
                $productId
            )
            ->isNotEmpty();
    }

    public function findRetailUser(): ?User
    {
        $settings =
            LeadAllocationSetting::getActiveSettings();

        if (
            !$this->leadAllocationService
                ->isOfficeOpenForDebug(
                    $settings,
                    now()
                )
        ) {
            return null;
        }

        $mappedUserIds =
            EmailLeadProductUserAssignment::query()
                ->where('is_active', true)
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values();

        $users = User::query()
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn(
                    'user_type',
                    UserType::SALES_ROLES
                );
            })
            ->when(
                $mappedUserIds->isNotEmpty(),
                function ($query) use ($mappedUserIds) {
                    $query->whereNotIn(
                        'id',
                        $mappedUserIds
                    );
                }
            )
            ->get();

        $availableUserIds =
            SalespersonAvailability::query()
                ->whereIn(
                    'user_id',
                    $users->pluck('id')
                )
                ->where('is_available', true)
                ->where('is_opted_in', true)
                ->whereDate(
                    'last_response_at',
                    Carbon::today()
                )
                ->pluck('user_id');

        $users =
            $users
                ->whereIn(
                    'id',
                    $availableUserIds
                )
                ->values();

        if ($users->isEmpty()) {

            Log::info(
                'WhatsApp retail lead has no eligible empty-product salesperson today.'
            );

            return null;
        }

        return $this->balancedUser($users);
    }


    /**
     * Balance only between users mapped to this product.
     */
    private function balancedUser(
        Collection $users
    ): ?User {
        return $users
            ->map(function ($user) {

                $user->whatsapp_allocation_count =
                    Lead::query()
                        ->where(
                            'representative_user_id',
                            $user->id
                        )
                        ->whereDate(
                            'created_at',
                            Carbon::today()
                        )
                        ->count();

                return $user;
            })
            ->sortBy(function ($user) {
                return sprintf(
                    '%010d-%s',
                    $user->whatsapp_allocation_count,
                    $user->id
                );
            })
            ->first();
    }

    private function mappedUserIdsForProduct(
        string $productId
    ): Collection {
        return EmailLeadProductUserAssignment::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
    }
}
