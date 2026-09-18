<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KpiOutreachAssignment;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Services\Kpi\KpiOutreachService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SkyrackDashboardController extends Controller
{
    /**
     * GET /api/skyrack/today-followups
     *
     * Required:
     * agent_number = assigned sales agent mobile number
     *
     * Optional:
     * page     = page number
     * per_page = records per page, max 100
     *
     * Example:
     * /api/skyrack/today-followups?agent_number=9981901043&page=1&per_page=20
     */
    public function todayFollowups(Request $request): JsonResponse
    {
        try {
            $now = Carbon::now('Asia/Kolkata');
            $currentDate = $now->toDateString();

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
             * -----------------------------------------------------
             * Resolve assigned agent from agent_number
             * -----------------------------------------------------
             */

            $agentResult = $this->resolveAgentFromRequest($request);

            if ($agentResult['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $agentResult['message'],
                ], 422);
            }

            $agentNumber = $agentResult['agent_number'];
            $assignedAgent = $agentResult['agent'];

            /*
             * Unknown agent:
             * return zero records and never fall back to all agents.
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
             * -----------------------------------------------------
             * Base query
             * -----------------------------------------------------
             *
             * IMPORTANT:
             * Follow-ups are filtered by the CURRENTLY assigned
             * representative:
             *
             * leads.representative_user_id
             *
             * We do NOT filter by lead_followups.followed_by.
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
             * -----------------------------------------------------
             * Today's candidate leads
             * -----------------------------------------------------
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
             * -----------------------------------------------------
             * Missed candidate leads
             * -----------------------------------------------------
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
             * Combine today + missed candidate lead IDs.
             */

            $allLeadIds = $todayLeadIds
                ->merge($missedLeadIds)
                ->unique()
                ->values();

            /*
             * -----------------------------------------------------
             * Find absolute latest follow-up for every lead
             * -----------------------------------------------------
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
                    ->whereHas(
                        'enquiry',
                        function ($query) use ($agentId) {
                            $query->where(
                                'representative_user_id',
                                $agentId
                            );
                        }
                    )
                    ->orderByDesc('created_at')
                    ->orderByDesc('next_followup_date')
                    ->get()
                    ->groupBy('lead_id')
                    ->map(
                        fn ($group) => $group->first()
                    );

                /*
                 * -------------------------------------------------
                 * Apply exact CRM Today Follow-up rules
                 * -------------------------------------------------
                 */

                foreach ($allFollowupsForLeads as $latest) {
                    /*
                     * Hide terminal statuses such as cancelled,
                     * confirmed and rejected.
                     */
                    if (
                        LeadFollowup::hiddenFromTodayFollowups(
                            $latest->status
                        )
                    ) {
                        continue;
                    }

                    /*
                     * No next follow-up date means it is not
                     * actionable in Today Followups.
                     */
                    if (!$latest->next_followup_date) {
                        continue;
                    }

                    $latestDate = $latest
                        ->next_followup_date
                        ->toDateString();

                    /*
                     * Today's follow-up.
                     */
                    if ($latestDate === $currentDate) {
                        $latest->is_missed = false;

                        $latestFollowups->push($latest);

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

                        $latestFollowups->push($latest);
                    }

                    /*
                     * Future dates are intentionally ignored.
                     */
                }
            }

            /*
             * -----------------------------------------------------
             * Sort today's follow-ups
             * -----------------------------------------------------
             *
             * Earliest due first.
             */

            $todayFollowups = $latestFollowups
                ->filter(
                    fn ($followup) => !$followup->is_missed
                )
                ->sortBy(
                    fn ($followup) =>
                        $followup->next_followup_date?->timestamp
                        ?? PHP_INT_MAX
                )
                ->values();

            /*
             * -----------------------------------------------------
             * Sort missed follow-ups
             * -----------------------------------------------------
             *
             * Most recently missed first.
             */

            $missedFollowups = $latestFollowups
                ->filter(
                    fn ($followup) => $followup->is_missed
                )
                ->sortByDesc(
                    fn ($followup) =>
                        $followup->next_followup_date?->timestamp
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
             * Counts are calculated BEFORE pagination.
             */

            $total = $allSorted->count();
            $todayCount = $todayFollowups->count();
            $missedCount = $missedFollowups->count();

            /*
             * -----------------------------------------------------
             * Pagination
             * -----------------------------------------------------
             */

            $lastPage = max(
                1,
                (int) ceil($total / $perPage)
            );

            $offset = ($page - 1) * $perPage;

            $paginatedFollowups = $allSorted
                ->slice(
                    $offset,
                    $perPage
                )
                ->values();

            /*
             * -----------------------------------------------------
             * Format response records
             * -----------------------------------------------------
             */

            $data = $paginatedFollowups
                ->map(
                    function (LeadFollowup $followup) {
                        $lead = $followup->enquiry;
                        $client = $lead?->client;
                        $representative = $lead?->representative;

                        return [
                            'followup_id' => $followup->id,

                            'lead_id' => $followup->lead_id,

                            /*
                             * Customer information is response data.
                             * It is NOT used as an API filter.
                             */
                            'customer' => [
                                'id' => $client?->id,
                                'name' => $client?->name,
                                'phone' => $client?->contact_number,
                                'alternate_phone' => $client?->alternate_number,
                                'email' => $client?->email,
                            ],

                            /*
                             * Currently assigned sales representative.
                             */
                            'representative' => [
                                'id' => $lead?->representative_user_id,
                                'name' => $representative?->name,
                                'number' => $representative?->contact_number,
                            ],

                            /*
                             * User who recorded this particular
                             * follow-up.
                             */
                            'followed_by' => [
                                'id' => $followup->followed_by,
                                'name' => $followup->followedBy?->name,
                                'number' => $followup->followedBy?->contact_number,
                            ],

                            'status' => (int) $followup->status,

                            'next_followup_date' =>
                                $followup->next_followup_date
                                    ?->timezone('Asia/Kolkata')
                                    ->format('Y-m-d H:i:s'),

                            'followup_note' =>
                                $followup->followup_note,

                            'contact_outcome' =>
                                $followup->contact_outcome,

                            'customer_not_picked_up' =>
                                (bool) $followup->customer_not_picked_up,

                            'is_missed' =>
                                (bool) $followup->is_missed,

                            'created_at' =>
                                $followup->created_at
                                    ?->timezone('Asia/Kolkata')
                                    ->format('Y-m-d H:i:s'),
                        ];
                    }
                )
                ->values();

            /*
             * -----------------------------------------------------
             * Final response
             * -----------------------------------------------------
             */

            return response()->json([
                'success' => true,

                'date' => $currentDate,

                'timezone' => 'Asia/Kolkata',

                'filter' => [
                    'agent_number' => $agentNumber,
                ],

                'agent' => [
                    'id' => $assignedAgent->id,
                    'name' => $assignedAgent->name,
                    'number' => $assignedAgent->contact_number,
                ],

                /*
                 * Counts for this assigned agent only.
                 */
                'count' => $total,
                'today_count' => $todayCount,
                'missed_count' => $missedCount,

                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,

                    'from' =>
                        $total > 0 && $offset < $total
                            ? $offset + 1
                            : null,

                    'to' =>
                        $total > 0 && $offset < $total
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
                'message' => 'Unable to fetch today follow-ups.',
            ], 500);
        }
    }

    /**
     * GET /api/skyrack/daily-kpi-outreach
     *
     * Required:
     * agent_number = sales agent mobile number
     *
     * Optional:
     * page     = page number
     * per_page = records per page, max 100
     *
     * Example:
     * /api/skyrack/daily-kpi-outreach?agent_number=9981901043&page=1&per_page=20
     */
    public function dailyKpiOutreach(Request $request): JsonResponse
    {
        try {
            $now = Carbon::now('Asia/Kolkata');
            $today = $now->toDateString();

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
             * -----------------------------------------------------
             * Resolve agent from agent_number
             * -----------------------------------------------------
             */

            $agentResult = $this->resolveAgentFromRequest($request);

            if ($agentResult['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $agentResult['message'],
                ], 422);
            }

            $agentNumber = $agentResult['agent_number'];
            $assignedAgent = $agentResult['agent'];

            /*
             * Unknown agent:
             * return zero data and never expose all agents.
             */
            if (!$assignedAgent) {
                return $this->emptyKpiOutreachResponse(
                    $today,
                    $agentNumber,
                    $page,
                    $perPage
                );
            }

            $agentId = (string) $assignedAgent->id;

            /*
             * -----------------------------------------------------
             * Real KPI daily target
             * -----------------------------------------------------
             *
             * Do NOT hard-code the target.
             */

            $outreachService = app(
                KpiOutreachService::class
            );

            $dailyTarget = (int) $outreachService->dailyTarget(
                $assignedAgent
            );

            /*
             * -----------------------------------------------------
             * Completed today
             * -----------------------------------------------------
             *
             * ALL completed outreach assignments:
             * standard + extra.
             */

            $completedToday = KpiOutreachAssignment::query()
                ->where('user_id', $agentId)
                ->where('status', 'completed')
                ->whereDate(
                    'completed_at',
                    $today
                )
                ->count();

            /*
             * Standard completions separately.
             */

            $standardCompletedToday = KpiOutreachAssignment::query()
                ->where('user_id', $agentId)
                ->where(
                    'allocation_type',
                    'standard'
                )
                ->where(
                    'status',
                    'completed'
                )
                ->whereDate(
                    'completed_at',
                    $today
                )
                ->count();

            /*
             * Extra completions separately.
             */

            $extraCompletedToday = KpiOutreachAssignment::query()
                ->where('user_id', $agentId)
                ->where(
                    'allocation_type',
                    'extra'
                )
                ->where(
                    'status',
                    'completed'
                )
                ->whereDate(
                    'completed_at',
                    $today
                )
                ->count();

            /*
             * Remaining KPI target.
             */

            $remainingToday = max(
                0,
                $dailyTarget - $completedToday
            );

            /*
             * -----------------------------------------------------
             * Current pending KPI queue
             * -----------------------------------------------------
             *
             * IMPORTANT:
             * This API is read-only.
             *
             * It does NOT:
             * - allocate new KPI records
             * - release records
             * - request extra records
             * - modify the agent queue
             */

            $pendingQuery = KpiOutreachAssignment::query()
                ->with('pool')
                ->where(
                    'user_id',
                    $agentId
                )
                ->where(
                    'status',
                    'pending'
                );

            /*
             * -----------------------------------------------------
             * Pending counts
             * -----------------------------------------------------
             */

            $pendingCount = (clone $pendingQuery)
                ->count();

            $standardPendingCount = (clone $pendingQuery)
                ->where(
                    'allocation_type',
                    'standard'
                )
                ->count();

            $extraPendingCount = (clone $pendingQuery)
                ->where(
                    'allocation_type',
                    'extra'
                )
                ->count();

            /*
             * -----------------------------------------------------
             * Pagination
             * -----------------------------------------------------
             */

            $lastPage = max(
                1,
                (int) ceil(
                    $pendingCount / $perPage
                )
            );

            $offset = ($page - 1) * $perPage;

            /*
             * Same basic queue order:
             * oldest assigned first.
             */

            $assignments = (clone $pendingQuery)
                ->orderBy('assigned_at')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            /*
             * -----------------------------------------------------
             * Format KPI queue records
             * -----------------------------------------------------
             */

            $data = $assignments
                ->map(
                    function (
                        KpiOutreachAssignment $assignment
                    ) {
                        return [
                            'assignment_id' =>
                                $assignment->id,

                            'pool_id' =>
                                $assignment->pool_id,

                            /*
                             * Customer/outreach number.
                             *
                             * Returned as data only.
                             * It is NOT an API filter.
                             */
                            'number' =>
                                $assignment->normalized_phone,

                            /*
                             * standard / extra
                             */
                            'allocation_type' =>
                                $assignment->allocation_type,

                            'status' =>
                                $assignment->status,

                            'assigned_at' =>
                                $assignment->assigned_at
                                    ?->timezone('Asia/Kolkata')
                                    ->format('Y-m-d H:i:s'),

                            'customer' => [
                                'name' =>
                                    $assignment
                                        ->pool
                                        ?->display_name,
                            ],
                        ];
                    }
                )
                ->values();

            /*
             * -----------------------------------------------------
             * Final KPI response
             * -----------------------------------------------------
             */

            return response()->json([
                'success' => true,

                'date' => $today,

                'timezone' => 'Asia/Kolkata',

                /*
                 * Only the agent number is a filter.
                 */
                'filter' => [
                    'agent_number' => $agentNumber,
                ],

                'agent' => [
                    'id' =>
                        $assignedAgent->id,

                    'name' =>
                        $assignedAgent->name,

                    'number' =>
                        $assignedAgent->contact_number,
                ],

                /*
                 * -------------------------------------------------
                 * KPI summary for this agent
                 * -------------------------------------------------
                 */

                'daily_target' =>
                    $dailyTarget,

                'completed_today' =>
                    $completedToday,

                'remaining_today' =>
                    $remainingToday,

                'standard_completed_today' =>
                    $standardCompletedToday,

                'extra_completed_today' =>
                    $extraCompletedToday,

                /*
                 * Current pending queue.
                 */

                'pending_count' =>
                    $pendingCount,

                'standard_pending_count' =>
                    $standardPendingCount,

                'extra_pending_count' =>
                    $extraPendingCount,

                /*
                 * Standard target completed.
                 */
                'standard_locked' =>
                    $dailyTarget > 0 &&
                    $standardCompletedToday >= $dailyTarget,

                /*
                 * -------------------------------------------------
                 * Pagination
                 * -------------------------------------------------
                 */

                'pagination' => [
                    'current_page' =>
                        $page,

                    'per_page' =>
                        $perPage,

                    'total' =>
                        $pendingCount,

                    'last_page' =>
                        $lastPage,

                    'from' =>
                        $pendingCount > 0 &&
                        $offset < $pendingCount
                            ? $offset + 1
                            : null,

                    'to' =>
                        $pendingCount > 0 &&
                        $offset < $pendingCount
                            ? min(
                                $offset + $perPage,
                                $pendingCount
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
                'message' => 'Unable to fetch daily KPI outreach.',
            ], 500);
        }
    }

    /**
     * Resolve CRM agent from agent_number.
     *
     * Both SkyRack endpoints use this exact same logic.
     *
     * User mobile field:
     * users.contact_number
     */
    private function resolveAgentFromRequest(
        Request $request
    ): array {
        $agentNumber = $request->filled('agent_number')
            ? preg_replace(
                '/\D+/',
                '',
                (string) $request->input('agent_number')
            )
            : '';

        /*
         * agent_number is mandatory.
         */
        if ($agentNumber === '') {
            return [
                'error' => true,
                'message' => 'agent_number is required.',
                'agent_number' => '',
                'agent' => null,
            ];
        }

        /*
         * Normalize:
         *
         * +91 99819 01043
         * 919981901043
         * 9981901043
         *
         * all become:
         *
         * 9981901043
         */
        $agentDigits = $this->lastTenDigits(
            $agentNumber
        );

        if (strlen($agentDigits) !== 10) {
            return [
                'error' => true,
                'message' => 'Invalid agent number.',
                'agent_number' => $agentNumber,
                'agent' => null,
            ];
        }

        /*
         * PostgreSQL:
         *
         * Remove any non-numeric characters from
         * users.contact_number and compare the last 10 digits.
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

        return [
            'error' => false,
            'message' => null,
            'agent_number' => $agentNumber,
            'agent' => $assignedAgent,
        ];
    }

    /**
     * Normalize a phone number to its last 10 digits.
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
     * Empty Today Followups response when the supplied
     * agent number does not match a CRM user.
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
                'agent_number' => $agentNumber,
            ],

            'agent' => null,

            'count' => 0,

            'today_count' => 0,

            'missed_count' => 0,

            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
                'has_more' => false,
            ],

            'data' => [],
        ]);
    }

    /**
     * Empty Daily KPI Outreach response when the supplied
     * agent number does not match a CRM user.
     */
    private function emptyKpiOutreachResponse(
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
                'agent_number' => $agentNumber,
            ],

            'agent' => null,

            'daily_target' => 0,

            'completed_today' => 0,

            'remaining_today' => 0,

            'standard_completed_today' => 0,

            'extra_completed_today' => 0,

            'pending_count' => 0,

            'standard_pending_count' => 0,

            'extra_pending_count' => 0,

            'standard_locked' => false,

            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
                'has_more' => false,
            ],

            'data' => [],
        ]);
    }
}