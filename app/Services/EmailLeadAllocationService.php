<?php

namespace App\Services;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailLeadAllocationService
{
    /**
     * Select salesperson for EMAIL LEAD only.
     */
    public function pickSalesperson(
        Lead $lead,
        LeadAllocationSetting $settings
    ): ?User {

        /*
        |--------------------------------------------------------------------------
        | Find Email Source
        |--------------------------------------------------------------------------
        */

        $emailLog = $lead
            ->emailLeadLogs()
            ->orderByDesc('received_at')
            ->first();


        if (!$emailLog) {

            Log::warning(
                'Email allocation skipped: email log not found.',
                [
                    'lead_id' => $lead->id,
                ]
            );

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Resolve Product
        |--------------------------------------------------------------------------
        */

        $product = $this->resolveEmailProduct(
            $lead,
            $emailLog->service_name
        );


        /*
        |--------------------------------------------------------------------------
        | Product couldn't be identified
        |--------------------------------------------------------------------------
        |
        | Treat as ordinary retail email lead.
        |
        */

        if (!$product) {

            Log::info(
                'Email product not resolved. Using retail allocation.',
                [
                    'lead_id' => $lead->id,
                    'service_name' =>
                        $emailLog->service_name,
                ]
            );

            return $this->pickRetailSalesperson();
        }


        /*
        |--------------------------------------------------------------------------
        | Find users specifically mapped to product
        |--------------------------------------------------------------------------
        */

        $mappedUserIds =
            EmailLeadProductUserAssignment::query()

                ->where(
                    'product_id',
                    $product->id
                )

                ->where(
                    'is_active',
                    true
                )

                ->pluck(
                    'user_id'
                );


        /*
        |--------------------------------------------------------------------------
        | Product has NO special mapping
        |--------------------------------------------------------------------------
        |
        | This is considered a retail product.
        |
        */

        if ($mappedUserIds->isEmpty()) {

            Log::info(
                'Email product has no configured salesperson. Using retail allocation.',
                [
                    'lead_id' => $lead->id,
                    'product_id' => $product->id,
                    'product' => $product->product,
                ]
            );

            return $this->pickRetailSalesperson();
        }


        /*
        |--------------------------------------------------------------------------
        | Product IS configured
        |--------------------------------------------------------------------------
        |
        | Only mapped users can receive it.
        |
        */

        $eligibleUsers = User::query()

            ->with('userType')

            ->whereIn(
                'id',
                $mappedUserIds
            )

            ->where(
                'status',
                1
            )

            ->get()

            ->filter(function ($user) {

                return $this->isEligibleToday(
                    $user
                );

            })

            ->values();


        /*
        |--------------------------------------------------------------------------
        | Nobody mapped to product is available today
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Do not send this configured product to an unrelated
        | salesperson.
        |
        | Return NULL => lead remains queued.
        |
        */

        if ($eligibleUsers->isEmpty()) {

            Log::info(
                'Configured email product has no eligible salesperson today. Keeping lead queued.',
                [
                    'lead_id' => $lead->id,
                    'product_id' => $product->id,
                    'product' => $product->product,
                    'mapped_user_ids' =>
                        $mappedUserIds->values()->all(),
                ]
            );

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Balanced assignment
        |--------------------------------------------------------------------------
        */

        return $this->pickBalanced(
            $eligibleUsers
        );
    }


    /**
     * Resolve incoming email product.
     */
    private function resolveEmailProduct(
        Lead $lead,
        ?string $emailServiceName
    ): ?Product {

        /*
        |--------------------------------------------------------------------------
        | 1. Prefer Product ID already stored on Lead
        |--------------------------------------------------------------------------
        */

        $productIds = $this->getLeadProductIds(
            $lead
        );


        if (!empty($productIds)) {

            $product = Product::query()
                ->whereIn(
                    'id',
                    $productIds
                )
                ->first();


            if ($product) {
                return $product;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Fallback to Email Service name
        |--------------------------------------------------------------------------
        */

        $emailName = $this->normalize(
            $emailServiceName
        );


        if ($emailName === '') {
            return null;
        }


        $products = Product::query()
            ->where('status', 1)
            ->get([
                'id',
                'product',
            ]);


        /*
         * Exact match first.
         */

        foreach ($products as $product) {

            $productName = $this->normalize(
                $product->product
            );


            if (
                $productName !== ''
                &&
                $emailName === $productName
            ) {
                return $product;
            }
        }


        /*
         * Controlled phrase match second.
         *
         * Example:
         *
         * CRM:
         * Private Jet
         *
         * Email:
         * Private Jet Mumbai
         */

        foreach ($products as $product) {

            $productName = $this->normalize(
                $product->product
            );


            if ($productName === '') {
                continue;
            }


            if (
                Str::contains(
                    $emailName,
                    $productName
                )
            ) {
                return $product;
            }
        }


        return null;
    }


    /**
     * Safely read lead product IDs regardless of
     * whether product_ids is JSON/string/array.
     */
    private function getLeadProductIds(
        Lead $lead
    ): array {

        /*
         * If Lead model already provides helper.
         */

        if (
            isset($lead->product_ids_array)
            &&
            is_array($lead->product_ids_array)
        ) {
            return array_values(
                array_filter(
                    $lead->product_ids_array
                )
            );
        }


        $raw = $lead->product_ids;


        if (empty($raw)) {
            return [];
        }


        if (is_array($raw)) {
            return array_values(
                array_filter($raw)
            );
        }


        if (is_string($raw)) {

            $decoded = json_decode(
                $raw,
                true
            );


            if (is_array($decoded)) {
                return array_values(
                    array_filter($decoded)
                );
            }


            /*
             * Single UUID stored directly.
             */

            return [$raw];
        }


        return [];
    }


    /**
     * Retail email lead.
     *
     * Product has no configured mapping.
     */
    private function pickRetailSalesperson(): ?User
    {
        $users = User::query()

            ->with('userType')

            ->where('status', 1)

            ->whereHas(
                'userType',
                function ($query) {

                    $query->whereIn(
                        'user_type',
                        UserType::SALES_ROLES
                    );

                }
            )

            ->get()

            ->filter(function ($user) {

                return $this->isEligibleToday(
                    $user
                );

            })

            ->values();


        if ($users->isEmpty()) {

            Log::info(
                'Retail email lead has no eligible salesperson today.'
            );

            return null;
        }


        return $this->pickBalanced(
            $users
        );
    }


    /**
     * Salesperson must have confirmed availability TODAY.
     */
    private function isEligibleToday(
        User $user
    ): bool {

        /*
         * Must be active.
         */

        if ((int) $user->status !== 1) {
            return false;
        }


        /*
         * Must have Sales role.
         */

        if (
            !$user->userType
            ||
            !in_array(
                $user->userType->user_type,
                UserType::SALES_ROLES,
                true
            )
        ) {
            return false;
        }


        /*
         * Must have availability record.
         */

        $availability =
            SalespersonAvailability::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        if (!$availability) {
            return false;
        }


        /*
         * Must have clicked YES.
         */

        if (
            !$availability->is_available
            ||
            !$availability->is_opted_in
        ) {
            return false;
        }


        /*
         * Yesterday's YES must NOT count today.
         */

        if (
            !$availability->last_response_at
            ||
            !$availability
                ->last_response_at
                ->isToday()
        ) {
            return false;
        }


        return true;
    }


    /**
     * Balance leads between eligible mapped users.
     *
     * Example:
     *
     * Private Jet:
     * A = 3 leads today
     * B = 1 lead today
     *
     * Next lead -> B
     */
    private function pickBalanced(
        Collection $users
    ): ?User {

        if ($users->isEmpty()) {
            return null;
        }


        return $users

            ->sortBy(function ($user) {

                return Lead::query()

                    ->where(
                        'representative_user_id',
                        $user->id
                    )

                    ->whereDate(
                        'created_at',
                        now()->toDateString()
                    )

                    ->count();

            })

            ->first();
    }


    /**
     * Normalize product/service text.
     */
    private function normalize(
        ?string $value
    ): string {

        $value = trim(
            (string) $value
        );


        if ($value === '') {
            return '';
        }


        $value = preg_replace(
            '/\s+/',
            ' ',
            $value
        );


        return Str::lower(
            $value
        );
    }

    public function findUserForProduct(string $productId): ?\App\Models\User
{
    $mappedUserIds =
        \App\Models\EmailLeadProductUserAssignment::query()
            ->where('product_id', $productId)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

    if ($mappedUserIds->isEmpty()) {
        return null;
    }

    $availableUserIds =
        \App\Models\SalespersonAvailability::query()
            ->whereIn('user_id', $mappedUserIds)
            ->where('is_available', true)
            ->where('is_opted_in', true)
            ->whereDate('last_response_at', today())
            ->pluck('user_id')
            ->unique()
            ->values();

    if ($availableUserIds->isEmpty()) {
        return null;
    }

    $users =
        \App\Models\User::query()
            ->whereIn('id', $availableUserIds)
            ->where('status', 1)
            ->get();

    if ($users->isEmpty()) {
        return null;
    }

    if ($users->count() === 1) {
        return $users->first();
    }

    return $users
        ->map(function ($user) {
            $user->today_lead_count =
                \App\Models\Lead::query()
                    ->where('representative_user_id', $user->id)
                    ->whereDate('created_at', today())
                    ->count();

            return $user;
        })
        ->sortBy(function ($user) {
            return sprintf(
                '%010d-%s',
                $user->today_lead_count,
                $user->id
            );
        })
        ->first();
}
}