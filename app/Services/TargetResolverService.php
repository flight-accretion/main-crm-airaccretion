<?php

namespace App\Services;

use App\Models\Target;

class TargetResolverService
{
    /**
     * Resolve the effective target for one salesperson.
     *
     * Rules:
     *
     * 1. Exact month target has highest priority.
     * 2. If exact month target is active, use it.
     * 3. If exact month target is inactive, no target applies.
     * 4. If exact month has no record, inspect the latest
     *    previous target record.
     * 5. Carry it forward only when that latest record is active.
     *
     * This means an inactive target acts as a stop marker
     * until a new active target is created.
     */
    public function targetForUser(
        string $userId,
        int $year,
        int $month
    ): ?Target {

        /*
         * Exact month always wins.
         */
        $exactTarget = Target::where(
                'sales_executive_id',
                $userId
            )
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($exactTarget) {
            return $exactTarget->status === 'active'
                ? $exactTarget
                : null;
        }

        /*
         * No exact target.
         *
         * Get latest previous target regardless of status.
         * Status must be inspected AFTER finding the latest record.
         */
        $previousTarget = Target::where(
                'sales_executive_id',
                $userId
            )
            ->where(function ($query) use ($year, $month) {

                $query
                    ->where('year', '<', $year)

                    ->orWhere(function ($query) use (
                        $year,
                        $month
                    ) {

                        $query
                            ->where('year', $year)
                            ->where('month', '<', $month);
                    });
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if (!$previousTarget) {
            return null;
        }

        /*
         * Latest previous inactive target means
         * carry-forward has been intentionally stopped.
         */
        return $previousTarget->status === 'active'
            ? $previousTarget
            : null;
    }

    /**
     * Effective target amount for one salesperson.
     */
    public function amountForUser(
        string $userId,
        int $year,
        int $month
    ): float {

        $target = $this->targetForUser(
            $userId,
            $year,
            $month
        );

        return $target
            ? (float) $target->target_amount
            : 0.0;
    }

    /**
     * Combined effective target for multiple salespeople.
     */
    public function amountForUsers(
        array $userIds,
        int $year,
        int $month
    ): float {

        return (float) collect($userIds)
            ->filter()
            ->unique()
            ->sum(function ($userId) use (
                $year,
                $month
            ) {

                return $this->amountForUser(
                    $userId,
                    $year,
                    $month
                );
            });
    }
}