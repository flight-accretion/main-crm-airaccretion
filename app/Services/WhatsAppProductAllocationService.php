<?php

namespace App\Services;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsAppProductAllocationService
{
    private const RETAIL_FALLBACK_PRODUCT_NAMES = [
        'empty',
        'no requirement',
        'incoming lead',
    ];

    public function __construct(
        private LeadAllocationService $leadAllocationService,
        private LeadProductRoutingService $productRouter
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

        return $this->findAvailableBalancedUser(
            $mappedUserIds
        );
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

    public function hasConfiguredCharterMapping(
        ?string $preferredProductId = null
    ): bool {
        $productIds =
            $this->productRouter
                ->charterProductIds();

        if ($preferredProductId) {
            $productIds =
                $productIds
                    ->prepend($preferredProductId)
                    ->filter()
                    ->unique()
                    ->values();
        }

        return $this->mappedUserIdsForProducts(
            $productIds
        )->isNotEmpty();
    }

    public function assignmentRoute(
        ?Product $product,
        ?string $sourceText
    ): string {
        $isCharter =
            $this->productRouter
                ->isCharterProduct(
                    $product,
                    $sourceText
                );

        if (
            $product
            && $this->hasConfiguredProductMapping(
                $product->id
            )
        ) {
            return $isCharter
                ? 'charter'
                : 'product';
        }

        if (
            $isCharter
        ) {
            return 'charter';
        }

        return 'retail';
    }

    public function findUserForAssignment(
        ?Product $product,
        ?string $sourceText
    ): ?User {
        if (
            $product
            && $this->hasConfiguredProductMapping(
                $product->id
            )
        ) {
            return $this->findUser(
                $product->id
            );
        }

        if (
            $this->assignmentRoute(
                $product,
                $sourceText
            ) === 'charter'
        ) {
            return $this->findCharterUser(
                $product ? $product->id : null
            );
        }

        return $this->findRetailUser();
    }

    public function findCharterUser(
        ?string $preferredProductId = null
    ): ?User {
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

        $productIds =
            $this->productRouter
                ->charterProductIds();

        if ($preferredProductId) {
            $productIds =
                $productIds
                    ->prepend($preferredProductId)
                    ->filter()
                    ->unique()
                    ->values();
        }

        if ($productIds->isEmpty()) {
            return null;
        }

        $mappedUserIds =
            $this->mappedUserIdsForProducts(
                $productIds
            );

        if ($mappedUserIds->isEmpty()) {

            Log::info(
                'WhatsApp charter lead has no configured charter salesperson mapping.',
                [
                    'product_ids' =>
                        $productIds->values()->all(),
                ]
            );

            return null;
        }

        return $this->findAvailableBalancedUser(
            $mappedUserIds
        );
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

        $fallbackProductIds =
            $this->retailFallbackProductIds();

        $fallbackMappedUserIds =
            $this->mappedUserIdsForProducts(
                $fallbackProductIds
            );

        if ($fallbackMappedUserIds->isNotEmpty()) {
            $user =
                $this->findAvailableBalancedUser(
                    $fallbackMappedUserIds
                );

            if (!$user) {
                Log::info(
                    'WhatsApp retail lead has fallback products configured but no eligible fallback salesperson today.',
                    [
                        'fallback_product_ids' =>
                            $fallbackProductIds
                                ->values()
                                ->all(),
                        'mapped_user_ids' =>
                            $fallbackMappedUserIds
                                ->values()
                                ->all(),
                    ]
                );
            }

            return $user;
        }

        Log::info(
            'WhatsApp retail lead has no Empty/No Requirement/Incoming Lead fallback product mapping configured. Keeping lead queued.'
        );

        return null;
    }

    public function emptyProduct(): ?Product
    {
        return Product::query()
            ->where('status', 1)
            ->get([
                'id',
                'product',
            ])
            ->first(function (Product $product) {
                return $this->productRouter
                    ->normalize($product->product)
                    === 'empty';
            });
    }

    private function retailFallbackProductIds(): Collection
    {
        return Product::query()
            ->where('status', 1)
            ->get([
                'id',
                'product',
            ])
            ->filter(function (Product $product) {
                return in_array(
                    $this->productRouter
                        ->normalize($product->product),
                    self::RETAIL_FALLBACK_PRODUCT_NAMES,
                    true
                );
            })
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();
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

    private function mappedUserIdsForProducts(
        Collection $productIds
    ): Collection {
        if ($productIds->isEmpty()) {
            return collect();
        }

        return EmailLeadProductUserAssignment::query()
            ->whereIn(
                'product_id',
                $productIds
            )
            ->where('is_active', true)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function findAvailableBalancedUser(
        Collection $mappedUserIds
    ): ?User {
        if ($mappedUserIds->isEmpty()) {
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
}
