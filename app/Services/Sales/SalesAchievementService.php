<?php

namespace App\Services\Sales;

use App\Models\LeadFollowup;
use App\Models\LeadRefund;
use App\Services\SalesAmountCalculator;
use Carbon\Carbon;

class SalesAchievementService
{
    public function __construct(
        private SalesBookingCohortService $cohort
    ) {
    }


    public function amountForUsers(
        array $representativeIds,
        int $year,
        int $month
    ): float {
        $representativeIds =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'strval',
                            $representativeIds
                        )
                    )
                )
            );

        if (
            empty(
                $representativeIds
            )
        ) {
            return 0.0;
        }

        $from =
            Carbon::create(
                $year,
                $month,
                1
            )
            ->startOfMonth();

        $to =
            $from
                ->copy()
                ->endOfMonth();

        $leadIds =
            $this
                ->cohort
                ->leadIdsForPeriod(
                    $from,
                    $to,
                    $representativeIds
                );

        if (
            $leadIds
                ->isEmpty()
        ) {
            return 0.0;
        }

        $allFollowups =
            LeadFollowup::query()
                ->whereIn(
                    'lead_id',
                    $leadIds
                )
                ->whereHas(
                    'enquiry',
                    function ($query) use (
                        $representativeIds
                    ) {
                        $query->whereIn(
                            'representative_user_id',
                            $representativeIds
                        );
                    }
                )
                ->get();

        if (
            $allFollowups
                ->isEmpty()
        ) {
            return 0.0;
        }

        $followupIdsByLead =
            $allFollowups
                ->groupBy(
                    'lead_id'
                )
                ->map(
                    fn ($rows) =>
                        $rows->pluck(
                            'id'
                        )
                );

        $allFollowupIds =
            $followupIdsByLead
                ->flatten()
                ->unique();

        $refundsByFollowup =
            LeadRefund::query()
                ->whereIn(
                    'lead_followup_id',
                    $allFollowupIds
                )
                ->whereIn(
                    'status',
                    [
                        1,
                        2,
                    ]
                )
                ->select(
                    'lead_followup_id'
                )
                ->selectRaw(
                    'SUM(refund_amount) AS total_refund'
                )
                ->groupBy(
                    'lead_followup_id'
                )
                ->pluck(
                    'total_refund',
                    'lead_followup_id'
                );

        return (float)
            $allFollowups
                ->groupBy(
                    'lead_id'
                )
                ->map(
                    function (
                        $group
                    ) {
                        return $group
                            ->filter(
                                fn ($followup) =>
                                    in_array(
                                        (int) $followup->status,
                                        LeadFollowup::salesAmountStatuses(),
                                        true
                                    )
                            )
                            ->sortByDesc(
                                'created_at'
                            )
                            ->first();
                    }
                )
                ->filter()
                ->sum(
                    function (
                        $followup
                    ) use (
                        $followupIdsByLead,
                        $refundsByFollowup
                    ) {
                        $refund =
                            $followupIdsByLead
                                ->get(
                                    $followup->lead_id,
                                    collect()
                                )
                                ->sum(
                                    fn ($id) =>
                                        (float)
                                        $refundsByFollowup
                                            ->get(
                                                $id,
                                                0
                                            )
                                );

                        return SalesAmountCalculator
                            ::salesAmountForFollowup(
                                $followup,
                                (float) $refund
                            );
                    }
                );
    }
}