<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Approval is tracked alongside the existing refund flow; it never blocks "Mark as done".
 */
class RefundApproval
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public static function canDecide(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type,
            array_merge([UserType::SUPER_ADMIN], UserType::ACCOUNTS_ROLES, UserType::OPERATIONS_ROLES), true);
    }

    public static function available(string $table): bool
    {
        // Lets code ship before the migration runs without failing refund creation.
        return Schema::hasTable($table) && Schema::hasColumn($table, 'approval_status');
    }

    public static function markPendingOnCreate(Model $refund): void
    {
        if (self::available($refund->getTable()) && $refund->getAttribute('approval_status') === null) {
            $refund->setAttribute('approval_status', self::PENDING);
        }
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::PENDING => 'Waiting approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            default => 'Not required',
        };
    }

    /** Combined state for a vendor payment that can hold several open refund rows. */
    public static function aggregate(iterable $statuses): ?string
    {
        $statuses = collect($statuses)->filter()->values();
        foreach ([self::PENDING, self::REJECTED, self::APPROVED] as $status) {
            if ($statuses->contains($status)) return $status;
        }
        return null;
    }
}
