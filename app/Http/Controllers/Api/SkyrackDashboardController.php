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
         * Use the CRM's real LeadFollowup model.
         *
         * Important:
         * A lead may have several followups. We first find candidate
         * lead IDs, then fetch the latest followup for each lead.
         */
        $followUpQuery = LeadFollowup::query();

        /*
         * Optional SkyRack agent filter.
         *
         * Uses the same enquiry relationship already used by the CRM.
         */
        if ($request->filled('agent_id')) {
            $agentId = (string) $request->input('agent_id');

            $followUpQuery->whereHas('enquiry', function ($query) use ($agentId) {
                $query->where('assigned_to', $agentId);
            });
        }

        /*
         * 1. Followups actually scheduled for today.
         *
         * Cancelled/confirmed/rejected/etc. statuses that the CRM hides
         * from Today's Followups must also be hidden from SkyRack.
         */
        $todayLeadIds = (clone $followUpQuery)
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '=', $currentDate)
            ->whereNotIn(
                'status',
                LeadFollowup::TODAY_FOLLOWUP_HIDDEN_STATUSES
            )
            ->pluck('lead_id');

        /*
         * 2. Previous followups that are still open/missed.
         *
         * These are intentionally included because the CRM dashboard
         * treats them as outstanding Today's Followups.
         */
        $missedLeadIds = (clone $followUpQuery)
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', $currentDate)
            ->whereIn(
                'status',
                LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES
            )
            ->pluck('lead_id');

        $candidateLeadIds = $todayLeadIds
            ->merge($missedLeadIds)
            ->filter()
            ->unique()
            ->values();

        if ($candidateLeadIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'date' => $currentDate,
                'timezone' => 'Asia/Kolkata',
                'count' => 0,
                'today_count' => 0,
                'missed_count' => 0,
                'data' => [],
            ]);
        }

        /*
         * Fetch all relevant followups.
         *
         * We need the latest followup per lead because an older row can
         * still have today's date even though the lead's latest status
         * has subsequently changed.
         */
        $latestFollowups = LeadFollowup::query()
            ->with([
                'enquiry',
            ])
            ->whereIn('lead_id', $candidateLeadIds)
            ->orderByDesc('next_followup_date')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lead_id')
            ->map(function ($followups) {
                return $followups
                    ->sortByDesc('created_at')
                    ->first();
            })
            ->filter(function ($latest) use ($currentDate) {

                if (!$latest) {
                    return false;
                }

                if (LeadFollowup::hiddenFromTodayFollowups($latest->status)) {
                    return false;
                }

                if (!$latest->next_followup_date) {
                    return false;
                }

                $latestDate = $latest->next_followup_date->toDateString();

                /*
                 * Scheduled today.
                 */
                if ($latestDate === $currentDate) {
                    $latest->is_missed = false;

                    return true;
                }

                /*
                 * Previous date but still an open/missed status.
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

                    return true;
                }

                return false;
            })
            ->values();

        /*
         * Apply agent filter again against the latest lead assignment.
         * This prevents stale followups from another salesperson being
         * returned after a lead transfer.
         */
        if ($request->filled('agent_id')) {
            $agentId = (string) $request->input('agent_id');

            $latestFollowups = $latestFollowups
                ->filter(function ($followup) use ($agentId) {
                    return (string) optional($followup->enquiry)->assigned_to
                        === $agentId;
                })
                ->values();
        }

        /*
         * Match dashboard ordering:
         *
         * Today's followups -> nearest first
         * Missed followups  -> most recent missed first
         */
        $todayFollowups = $latestFollowups
            ->where('is_missed', false)
            ->sortBy(function ($followup) {
                return $followup->next_followup_date?->timestamp
                    ?? PHP_INT_MAX;
            })
            ->values();

        $missedFollowups = $latestFollowups
            ->where('is_missed', true)
            ->sortByDesc(function ($followup) {
                return $followup->next_followup_date?->timestamp
                    ?? PHP_INT_MIN;
            })
            ->values();

        $allFollowups = $todayFollowups
            ->concat($missedFollowups)
            ->values();

        /*
         * API response.
         *
         * Keep the response explicit rather than returning entire
         * Eloquent models to SkyRack.
         */
        $data = $allFollowups
            ->map(function ($followup) {

                $lead = $followup->enquiry;

                return [
                    'followup_id' => $followup->id,
                    'lead_id' => $followup->lead_id,

                    'customer_name' => $lead->name ?? null,
                    'customer_phone' => $lead->phone ?? null,

                    'agent_id' => $lead->assigned_to ?? null,

                    'status' => $followup->status,

                    'next_followup_date' =>
                        $followup->next_followup_date?->toIso8601String(),

                    'followup_note' => $followup->followup_note,

                    'is_missed' => (bool) $followup->is_missed,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,

            'date' => $currentDate,
            'timezone' => 'Asia/Kolkata',

            'count' => $data->count(),
            'today_count' => $todayFollowups->count(),
            'missed_count' => $missedFollowups->count(),

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