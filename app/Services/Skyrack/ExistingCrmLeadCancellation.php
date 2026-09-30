<?php

namespace App\Services\Skyrack;

use App\Models\Lead;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * INTEGRATION BOUNDARY: Wire this to the CRM's ACTUAL existing cancel action.
 * It intentionally fails closed rather than guessing status IDs, payment rules or
 * creating a parallel cancellation flow. The caller holds the lead row lock.
 * All writes must use the same DB connection/transaction; defer external effects
 * until commit through the existing CRM mechanism.
 */
class ExistingCrmLeadCancellation
{
    public function actor($request): ?Authenticatable
    {
        // Map using the SAME principal exposed by existing Skyrack token middleware.
        // A shared service token alone is NOT evidence of a particular salesperson.
        return $request->user();
    }

    public function actorKey(Authenticatable $actor): string
    {
        return (string) $actor->getAuthIdentifier();
    }

    public function assertMayCancel(Authenticatable $actor, Lead $lead): void
    {
        // REQUIRED: invoke the exact existing CRM ownership/role authorization.
        // Example only: Gate::forUser($actor)->authorize('cancel', $lead);
        throw new RuntimeException('CRM_CANCEL_INTEGRATION_NOT_CONFIGURED');
    }

    public function isAlreadyCancelled(Lead $lead): bool
    {
        // REQUIRED: use current CRM's actual cancellation predicate.
        throw new RuntimeException('CRM_CANCEL_INTEGRATION_NOT_CONFIGURED');
    }

    public function hasAnyPayment(Lead $lead): bool
    {
        // REQUIRED: reuse actual payment/receipt relationships and partial/full rules.
        // Do not equate pending amount=0 to paid without checking existing rules.
        throw new RuntimeException('CRM_CANCEL_INTEGRATION_NOT_CONFIGURED');
    }

    public function cancel(Lead $lead, Authenticatable $actor, string $reason, ?string $remark): void
    {
        // REQUIRED: delegate to existing CRM cancel action/service with same validation,
        // status transition, Follow-up/history and KPI/Operations side effects.
        // Must NOT merely $lead->update(['status' => 'cancelled']).
        throw new RuntimeException('CRM_CANCEL_INTEGRATION_NOT_CONFIGURED');
    }
}
