<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Models\LeadFollowup;

class SkyrackDashboardController extends Controller
{
    /**
     * GET /api/skyrack/today-followups
     */
public function todayFollowups(Request $request): JsonResponse
{
    try {
        $now = Carbon::now('Asia/Kolkata');
        $currentDate = $now->toDateString();

        /*
         * -----------------------------
         * Request filters
         * -----------------------------
         */
        $agentId = $request->filled('agent_id')
            ? (string) $request->input('agent_id')
            : null;

        /*
         * Keep only digits from the mobile filter.
         *
         * Example:
         * +91 99819-01043
         * becomes:
         * 919981901043
         */
        $mobile = $request->filled('mobile')
            ? preg_replace('/\D+/', '', (string) $request->input('mobile'))
            : null;

        /*
         * Pagination.
         *
         * Default = 20
         * Maximum = 100 to prevent very large API responses.
         */
        $page = max(
            1,
            (int) $request->input('page', 1)
        );

        $perPage = min(
            100,
            max(1, (int) $request->input('per_page', 20))
        );

        /*
         * -----------------------------
         * Base follow-up query
         * -----------------------------
         *
         * Same relationships used by CRM DashboardController.
         */
        $followUpQuery = LeadFollowup::with([
            'enquiry',
            'enquiry.representative',
            'enquiry.client',
            'followedBy',
        ]);

        /*
         * Representative filter.
         */
        if ($agentId !== null) {
            $followUpQuery->whereHas(
                'enquiry',
                function ($query) use ($agentId) {
                    $query->where(
                        'representative_user_id',
                        $agentId
                    );
                }
            );
        }

        /*
         * -----------------------------
         * Mobile filter
         * -----------------------------
         *
         * LeadFollowup
         *   -> enquiry (Lead)
         *      -> client (Client)
         *
         * Client fields verified:
         * contact_number
         * alternate_number
         */
        if ($mobile !== null && $mobile !== '') {
            $followUpQuery->whereHas(
                'enquiry.client',
                function ($query) use ($mobile) {
                    /*
                     * PostgreSQL-safe normalization.
                     *
                     * Remove everything except digits before comparison,
                     * so numbers stored with +91, spaces, dashes etc.
                     * can still be searched.
                     */
                    $query->where(function ($q) use ($mobile) {
                        $q->whereRaw(
                            "REGEXP_REPLACE(COALESCE(contact_number, ''), '[^0-9]', '', 'g') LIKE ?",
                            ['%' . $mobile . '%']
                        )
                        ->orWhereRaw(
                            "REGEXP_REPLACE(COALESCE(alternate_number, ''), '[^0-9]', '', 'g') LIKE ?",
                            ['%' . $mobile . '%']
                        );
                    });
                }
            );
        }

        /*
         * -----------------------------
         * STEP 1
         * Today's candidate leads
         * -----------------------------
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
         * -----------------------------
         * STEP 2
         * Missed/open candidate leads
         * -----------------------------
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

        $allLeadIds = $todayLeadIds
            ->merge($missedLeadIds)
            ->unique()
            ->values();

        /*
         * -----------------------------
         * STEP 3
         * Absolute latest follow-up
         * per lead
         * -----------------------------
         */
        $latestFollowups = collect();

        if ($allLeadIds->isNotEmpty()) {
            $latestQuery = LeadFollowup::with([
                'enquiry',
                'enquiry.representative',
                'enquiry.client',
                'followedBy',
            ])
                ->whereIn('lead_id', $allLeadIds);

            /*
             * Apply representative again.
             *
             * This protects against a lead being transferred
             * after an older follow-up was created.
             */
            if ($agentId !== null) {
                $latestQuery->whereHas(
                    'enquiry',
                    function ($query) use ($agentId) {
                        $query->where(
                            'representative_user_id',
                            $agentId
                        );
                    }
                );
            }

            /*
             * Apply mobile filter again against the current
             * lead/client relationship.
             */
            if ($mobile !== null && $mobile !== '') {
                $latestQuery->whereHas(
                    'enquiry.client',
                    function ($query) use ($mobile) {
                        $query->where(function ($q) use ($mobile) {
                            $q->whereRaw(
                                "REGEXP_REPLACE(COALESCE(contact_number, ''), '[^0-9]', '', 'g') LIKE ?",
                                ['%' . $mobile . '%']
                            )
                            ->orWhereRaw(
                                "REGEXP_REPLACE(COALESCE(alternate_number, ''), '[^0-9]', '', 'g') LIKE ?",
                                ['%' . $mobile . '%']
                            );
                        });
                    }
                );
            }

            $allFollowupsForLeads = $latestQuery
                ->orderByDesc('created_at')
                ->orderByDesc('next_followup_date')
                ->get()
                ->groupBy('lead_id')
                ->map(fn ($group) => $group->first());

            /*
             * Apply exact CRM Today Followups rules.
             */
            foreach ($allFollowupsForLeads as $latest) {
                if (
                    LeadFollowup::hiddenFromTodayFollowups(
                        $latest->status
                    )
                ) {
                    continue;
                }

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
            }
        }

        /*
         * -----------------------------
         * STEP 4
         * Same sorting as CRM
         * -----------------------------
         */

        // Today's follow-ups:
        // earliest scheduled time first.
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

        // Missed:
        // most recently missed first.
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

        $allSorted = $todayFollowups
            ->merge($missedFollowups)
            ->values();

        /*
         * -----------------------------
         * STEP 5
         * Pagination
         * -----------------------------
         *
         * Important:
         * Pagination happens AFTER:
         *
         * - latest follow-up resolution
         * - hidden-status filtering
         * - missed filtering
         * - sorting
         *
         * Therefore total/count remain correct.
         */
        $total = $allSorted->count();

        $lastPage = max(
            1,
            (int) ceil($total / $perPage)
        );

        /*
         * If someone asks for page 999, returning an empty data
         * array is standard API pagination behaviour.
         */
        $offset = ($page - 1) * $perPage;

        $paginatedFollowups = $allSorted
            ->slice($offset, $perPage)
            ->values();

        /*
         * -----------------------------
         * STEP 6
         * SkyRack response mapping
         * -----------------------------
         */
        $data = $paginatedFollowups
            ->map(function (LeadFollowup $followup) {
                $lead = $followup->enquiry;
                $client = $lead?->client;

                return [
                    'followup_id' => $followup->id,

                    'lead_id' => $followup->lead_id,

                    'customer' => [
                        'id' => $client?->id,
                        'name' => $client?->name,
                        'phone' => $client?->contact_number,
                        'alternate_phone' =>
                            $client?->alternate_number,
                        'email' => $client?->email,
                    ],

                    'representative' => [
                        'id' =>
                            $lead?->representative_user_id,

                        'name' =>
                            $lead?->representative?->name,
                    ],

                    'followed_by' => [
                        'id' => $followup->followed_by,

                        'name' =>
                            $followup->followedBy?->name,
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
                        (bool) $followup
                            ->customer_not_picked_up,

                    'is_missed' =>
                        (bool) $followup->is_missed,

                    'created_at' =>
                        $followup->created_at
                            ?->timezone('Asia/Kolkata')
                            ->format('Y-m-d H:i:s'),
                ];
            })
            ->values();

        /*
         * -----------------------------
         * Final response
         * -----------------------------
         */
        return response()->json([
            'success' => true,

            'date' => $currentDate,
            'timezone' => 'Asia/Kolkata',

            'filters' => [
                'agent_id' => $agentId,
                'mobile' => $mobile,
            ],

            /*
             * Counts BEFORE pagination.
             */
            'count' => $total,

            'today_count' =>
                $todayFollowups->count(),

            'missed_count' =>
                $missedFollowups->count(),

            /*
             * Pagination metadata.
             */
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,

                'from' => $total > 0 && $offset < $total
                    ? $offset + 1
                    : null,

                'to' => $total > 0 && $offset < $total
                    ? min(
                        $offset + $perPage,
                        $total
                    )
                    : null,

                'has_more' => $page < $lastPage,
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
     * GET /api/skyrack/daily-kpi-outreach
     */
    public function dailyKpiOutreach(Request $request): JsonResponse
    {
        try {
            $today = Carbon::now('Asia/Kolkata')->toDateString();

            /*
             * IMPORTANT:
             *
             * This should ultimately call the SAME service/query used by
             * your CRM Daily Outreach dashboard.
             *
             * The queries below demonstrate the API response structure.
             * Map the status IDs/columns to your existing CRM definitions.
             */

            $agentId = $request->filled('agent_id')
                ? (int) $request->agent_id
                : null;

            /*
             * Leads assigned today.
             */
            $assignedQuery = DB::table('leads')
                ->whereDate('created_at', $today);

            if ($agentId) {
                $assignedQuery->where('user_id', $agentId);
            }

            $assigned = $assignedQuery->count();

            /*
             * Leads having follow-up/activity today.
             *
             * Count DISTINCT leads, not number of follow-up rows.
             */
            $conversationQuery = DB::table('lead_follow_ups as f')
                ->join('leads as l', 'l.id', '=', 'f.lead_id')
                ->whereDate('f.created_at', $today);

            if ($agentId) {
                $conversationQuery->where('l.user_id', $agentId);
            }

            $conversations = $conversationQuery
                ->distinct('f.lead_id')
                ->count('f.lead_id');

            /*
             * IMPORTANT:
             * Replace these with your CRM's existing status/payment logic.
             */
            $partialPayments = $this->countStatusChangedToday(
                $today,
                $agentId,
                'partial_payment'
            );

            $fullPayments = $this->countStatusChangedToday(
                $today,
                $agentId,
                'full_payment'
            );

            $cancelled = $this->countStatusChangedToday(
                $today,
                $agentId,
                'cancelled'
            );

            $active = max(
                0,
                $assigned - $cancelled - $fullPayments
            );

            return response()->json([
                'success' => true,

                'date' => $today,

                'timezone' => 'Asia/Kolkata',

                'agent_id' => $agentId,

                'data' => [
                    'assigned' => $assigned,

                    'conversation' => $conversations,

                    'payment' => [
                        'partial' => $partialPayments,
                        'full' => $fullPayments,
                        'total' => $partialPayments + $fullPayments,
                    ],

                    'cancelled' => $cancelled,

                    'active' => $active,
                ],
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
     * Temporary adapter.
     *
     * Replace this with the SAME status-history/payment source
     * currently used by your KPI dashboard.
     */
    private function countStatusChangedToday(
        string $date,
        ?int $agentId,
        string $status
    ): int {
        $query = DB::table('lead_status_histories as h')
            ->join('leads as l', 'l.id', '=', 'h.lead_id')
            ->whereDate('h.created_at', $date)
            ->where('h.status', $status);

        if ($agentId) {
            $query->where('l.user_id', $agentId);
        }

        return $query
            ->distinct('h.lead_id')
            ->count('h.lead_id');
    }
}