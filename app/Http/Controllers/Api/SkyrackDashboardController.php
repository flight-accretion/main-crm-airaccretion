<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SkyrackDashboardController extends Controller
{
    /**
     * GET /api/skyrack/today-followups
     *
     * Query:
     * agent_number = assigned sales agent mobile number
     * page         = page number
     * per_page     = records per page, max 100
     *
     * Example:
     * /api/skyrack/today-followups?agent_number=9981901043&page=1&per_page=20
     */
    public function todayFollowups(Request $request): JsonResponse
    {
        try {
            $now = Carbon::now('Asia/Kolkata');
            $currentDate = $now->toDateString();

            /*
             * ---------------------------------------------
             * PAGINATION
             * ---------------------------------------------
             */
            $page = max(
                1,
                (int) $request->input('page', 1)
            );

            $perPage = min(
                100,
                max(
                    1,
                    (int) $request->input('per_page', 20)
                )
            );

            /*
             * ---------------------------------------------
             * AGENT NUMBER
             * ---------------------------------------------
             *
             * This is the assigned sales agent's number.
             *
             * Accepted examples:
             *
             * 9981901043
             * 919981901043
             * +91 99819 01043
             */
            $agentNumber = $request->filled('agent_number')
                ? preg_replace(
                    '/\D+/',
                    '',
                    (string) $request->input('agent_number')
                )
                : null;

            /*
             * Make agent_number mandatory.
             *
             * This prevents SkyRack from accidentally
             * retrieving follow-ups for every salesperson.
             */
            if (!$agentNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'agent_number is required.',
                ], 422);
            }

            /*
             * Normalize to last 10 digits.
             */
            $agentDigits = $this->lastTenDigits(
                $agentNumber
            );

            if (strlen($agentDigits) !== 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid agent number.',
                ], 422);
            }

            /*
             * ---------------------------------------------
             * FIND CRM AGENT
             * ---------------------------------------------
             *
             * User mobile column:
             * users.contact_number
             */
            $assignedAgent = User::query()
                ->whereRaw(
                    "RIGHT(
                        REGEXP_REPLACE(
                            COALESCE(contact_number, ''),
                            '[^0-9]',
                            '',
                            'g'
                        ),
                        10
                    ) = ?",
                    [$agentDigits]
                )
                ->first();

            /*
             * Unknown number:
             *
             * Return zero records.
             *
             * IMPORTANT:
             * Never fall back to all agents.
             */
            if (!$assignedAgent) {
                return $this->emptyTodayFollowupsResponse(
                    $currentDate,
                    $agentNumber,
                    $page,
                    $perPage
                );
            }

            $agentId = (string) $assignedAgent->id;

            /*
             * ---------------------------------------------
             * BASE QUERY
             * ---------------------------------------------
             *
             * Only leads CURRENTLY assigned to this agent.
             */
            $followUpQuery = LeadFollowup::query()
                ->with([
                    'enquiry',
                    'enquiry.representative',
                    'enquiry.client',
                    'followedBy',
                ])
                ->whereHas(
                    'enquiry',
                    function ($query) use ($agentId) {
                        $query->where(
                            'representative_user_id',
                            $agentId
                        );
                    }
                );

            /*
             * ---------------------------------------------
             * TODAY CANDIDATES
             * ---------------------------------------------
             */
            $todayLeadIds = (clone $followUpQuery)
                ->whereDate(
                    'next_followup_date',
                    '=',
                    $currentDate
                )
                ->whereNotIn(
                    'status',
                    LeadFollowup::TODAY_FOLLOWUP_HIDDEN_STATUSES
                )
                ->pluck('lead_id')
                ->unique();

            /*
             * ---------------------------------------------
             * MISSED CANDIDATES
             * ---------------------------------------------
             *
             * CRM considers these statuses open for
             * missed follow-ups:
             *
             * 0 = initiated
             * 1 = active
             * 4 = partial payment received
             */
            $missedLeadIds = (clone $followUpQuery)
                ->whereDate(
                    'next_followup_date',
                    '<',
                    $currentDate
                )
                ->whereIn(
                    'status',
                    LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES
                )
                ->pluck('lead_id')
                ->unique();

            /*
             * Merge today + missed candidate lead IDs.
             */
            $allLeadIds = $todayLeadIds
                ->merge($missedLeadIds)
                ->unique()
                ->values();

            /*
             * ---------------------------------------------
             * GET LATEST FOLLOW-UP PER LEAD
             * ---------------------------------------------
             */
            $latestFollowups = collect();

            if ($allLeadIds->isNotEmpty()) {
                $allFollowupsForLeads = LeadFollowup::query()
                    ->with([
                        'enquiry',
                        'enquiry.representative',
                        'enquiry.client',
                        'followedBy',
                    ])
                    ->whereIn(
                        'lead_id',
                        $allLeadIds
                    )

                    /*
                     * Recheck current assigned agent.
                     */
                    ->whereHas(
                        'enquiry',
                        function ($query) use ($agentId) {
                            $query->where(
                                'representative_user_id',
                                $agentId
                            );
                        }
                    )

                    /*
                     * Same latest logic as CRM dashboard.
                     */
                    ->orderByDesc('created_at')
                    ->orderByDesc('next_followup_date')
                    ->get()
                    ->groupBy('lead_id')
                    ->map(
                        fn ($group) => $group->first()
                    );

                /*
                 * -----------------------------------------
                 * APPLY CRM TODAY FOLLOW-UP RULES
                 * -----------------------------------------
                 */
                foreach (
                    $allFollowupsForLeads as $latest
                ) {
                    /*
                     * Hidden statuses:
                     *
                     * 2 = cancelled
                     * 5 = confirmed
                     * 9 = rejected
                     */
                    if (
                        LeadFollowup::hiddenFromTodayFollowups(
                            $latest->status
                        )
                    ) {
                        continue;
                    }

                    /*
                     * No next date = not actionable.
                     */
                    if (!$latest->next_followup_date) {
                        continue;
                    }

                    $latestDate = $latest
                        ->next_followup_date
                        ->toDateString();

                    /*
                     * Today.
                     */
                    if ($latestDate === $currentDate) {
                        $latest->is_missed = false;

                        $latestFollowups->push(
                            $latest
                        );

                        continue;
                    }

                    /*
                     * Missed but still open.
                     */
                    if (
                        $latestDate < $currentDate &&
                        in_array(
                            (int) $latest->status,
                            LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES,
                            true
                        )
                    ) {
                        $latest->is_missed = true;

                        $latestFollowups->push(
                            $latest
                        );
                    }

                    /*
                     * Future dates are ignored.
                     */
                }
            }

            /*
             * ---------------------------------------------
             * SORT TODAY
             * ---------------------------------------------
             *
             * Today's follow-ups:
             * earliest first.
             */
            $todayFollowups = $latestFollowups
                ->filter(
                    fn ($followup) =>
                        !$followup->is_missed
                )
                ->sortBy(
                    fn ($followup) =>
                        $followup
                            ->next_followup_date
                            ?->timestamp
                        ?? PHP_INT_MAX
                )
                ->values();

            /*
             * ---------------------------------------------
             * SORT MISSED
             * ---------------------------------------------
             *
             * Most recently missed first.
             */
            $missedFollowups = $latestFollowups
                ->filter(
                    fn ($followup) =>
                        $followup->is_missed
                )
                ->sortByDesc(
                    fn ($followup) =>
                        $followup
                            ->next_followup_date
                            ?->timestamp
                        ?? PHP_INT_MIN
                )
                ->values();

            /*
             * Today first, then missed.
             */
            $allSorted = $todayFollowups
                ->merge($missedFollowups)
                ->values();

            /*
             * ---------------------------------------------
             * COUNTS FOR THIS AGENT
             * ---------------------------------------------
             *
             * Counts are BEFORE pagination.
             */
            $total = $allSorted->count();

            $todayCount = $todayFollowups->count();

            $missedCount = $missedFollowups->count();

            /*
             * ---------------------------------------------
             * PAGINATION
             * ---------------------------------------------
             */
            $lastPage = max(
                1,
                (int) ceil(
                    $total / $perPage
                )
            );

            $offset = ($page - 1) * $perPage;

            $paginatedFollowups = $allSorted
                ->slice(
                    $offset,
                    $perPage
                )
                ->values();

            /*
             * ---------------------------------------------
             * RESPONSE DATA
             * ---------------------------------------------
             */
            $data = $paginatedFollowups
                ->map(
                    function (
                        LeadFollowup $followup
                    ) {
                        $lead = $followup->enquiry;

                        $client = $lead?->client;

                        $representative =
                            $lead?->representative;

                        return [
                            'followup_id' =>
                                $followup->id,

                            'lead_id' =>
                                $followup->lead_id,

                            /*
                             * Customer information is returned
                             * as data, but is NOT a filter.
                             */
                            'customer' => [
                                'id' =>
                                    $client?->id,

                                'name' =>
                                    $client?->name,

                                'phone' =>
                                    $client?->contact_number,

                                'alternate_phone' =>
                                    $client?->alternate_number,

                                'email' =>
                                    $client?->email,
                            ],

                            /*
                             * Assigned agent.
                             */
                            'representative' => [
                                'id' =>
                                    $lead
                                        ?->representative_user_id,

                                'name' =>
                                    $representative?->name,

                                'number' =>
                                    $representative
                                        ?->contact_number,
                            ],

                            /*
                             * User who actually recorded this
                             * particular follow-up.
                             */
                            'followed_by' => [
                                'id' =>
                                    $followup->followed_by,

                                'name' =>
                                    $followup
                                        ->followedBy
                                        ?->name,

                                'number' =>
                                    $followup
                                        ->followedBy
                                        ?->contact_number,
                            ],

                            'status' =>
                                (int) $followup->status,

                            'next_followup_date' =>
                                $followup
                                    ->next_followup_date
                                    ?->timezone(
                                        'Asia/Kolkata'
                                    )
                                    ->format(
                                        'Y-m-d H:i:s'
                                    ),

                            'followup_note' =>
                                $followup
                                    ->followup_note,

                            'contact_outcome' =>
                                $followup
                                    ->contact_outcome,

                            'customer_not_picked_up' =>
                                (bool) $followup
                                    ->customer_not_picked_up,

                            'is_missed' =>
                                (bool) $followup
                                    ->is_missed,

                            'created_at' =>
                                $followup
                                    ->created_at
                                    ?->timezone(
                                        'Asia/Kolkata'
                                    )
                                    ->format(
                                        'Y-m-d H:i:s'
                                    ),
                        ];
                    }
                )
                ->values();

            /*
             * ---------------------------------------------
             * FINAL RESPONSE
             * ---------------------------------------------
             */
            return response()->json([
                'success' => true,

                'date' => $currentDate,

                'timezone' => 'Asia/Kolkata',

                /*
                 * Only agent number is a business filter.
                 */
                'filter' => [
                    'agent_number' =>
                        $agentNumber,
                ],

                /*
                 * Resolved assigned CRM agent.
                 */
                'agent' => [
                    'id' =>
                        $assignedAgent->id,

                    'name' =>
                        $assignedAgent->name,

                    'number' =>
                        $assignedAgent
                            ->contact_number,
                ],

                /*
                 * These counts belong ONLY to
                 * the selected assigned agent.
                 */
                'count' => $total,

                'today_count' =>
                    $todayCount,

                'missed_count' =>
                    $missedCount,

                /*
                 * Pagination.
                 */
                'pagination' => [
                    'current_page' =>
                        $page,

                    'per_page' =>
                        $perPage,

                    'total' =>
                        $total,

                    'last_page' =>
                        $lastPage,

                    'from' =>
                        $total > 0 &&
                        $offset < $total
                            ? $offset + 1
                            : null,

                    'to' =>
                        $total > 0 &&
                        $offset < $total
                            ? min(
                                $offset + $perPage,
                                $total
                            )
                            : null,

                    'has_more' =>
                        $page < $lastPage,
                ],

                'data' => $data,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to fetch today follow-ups.',
            ], 500);
        }
    }

    /**
     * Normalize phone numbers to the last 10 digits.
     */
    private function lastTenDigits(
        ?string $number
    ): string {
        if ($number === null) {
            return '';
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $number
        );

        if (strlen($digits) > 10) {
            return substr(
                $digits,
                -10
            );
        }

        return $digits;
    }

    /**
     * Return empty data when the supplied agent
     * number does not belong to a CRM user.
     */
    private function emptyTodayFollowupsResponse(
        string $date,
        string $agentNumber,
        int $page,
        int $perPage
    ): JsonResponse {
        return response()->json([
            'success' => true,

            'date' => $date,

            'timezone' => 'Asia/Kolkata',

            'filter' => [
                'agent_number' =>
                    $agentNumber,
            ],

            'agent' => null,

            'count' => 0,

            'today_count' => 0,

            'missed_count' => 0,

            'pagination' => [
                'current_page' =>
                    $page,

                'per_page' =>
                    $perPage,

                'total' => 0,

                'last_page' => 1,

                'from' => null,

                'to' => null,

                'has_more' => false,
            ],

            'data' => [],
        ]);
    }


    /*
     * KEEP YOUR EXISTING dailyKpiOutreach()
     * AND ITS HELPER METHODS BELOW HERE.
     */
}