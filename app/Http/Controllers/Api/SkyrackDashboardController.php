<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SkyrackDashboardController extends Controller
{
    /**
     * GET /api/skyrack/today-followups
     */
    public function todayFollowups(Request $request): JsonResponse
    {
        try {
            $today = Carbon::now('Asia/Kolkata')->toDateString();

            /*
             * IMPORTANT:
             * Replace table/column names below only if your CRM uses
             * different names.
             *
             * Do NOT use created_at to decide today's follow-up.
             * Use the actual follow-up date column.
             */
            $query = DB::table('lead_follow_ups as f')
                ->join('leads as l', 'l.id', '=', 'f.lead_id')
                ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->whereDate('f.follow_up_date', $today);

            /*
             * Optional SkyRack filtering:
             *
             * /today-followups?agent_id=123
             */
            if ($request->filled('agent_id')) {
                $query->where('l.user_id', (int) $request->agent_id);
            }

            $followups = $query
                ->select([
                    'f.id as followup_id',
                    'f.lead_id',
                    'l.name as customer_name',
                    'l.phone as customer_phone',
                    'l.status as lead_status',
                    'l.user_id as agent_id',
                    'u.name as agent_name',
                    'f.follow_up_date',
                    'f.created_at',
                ])
                ->orderBy('f.follow_up_date')
                ->get();

            return response()->json([
                'success' => true,
                'date' => $today,
                'timezone' => 'Asia/Kolkata',
                'count' => $followups->count(),
                'data' => $followups,
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