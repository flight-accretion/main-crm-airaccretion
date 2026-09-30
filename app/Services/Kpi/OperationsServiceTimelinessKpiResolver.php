<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsServiceTimelinessKpiResolver implements KpiMetricResolverInterface
{
    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth,
        ?Carbon $from = null
    ): array {
        $periodStart = ($from ?: $asOf->copy()->startOfMonth())
            ->copy()
            ->startOfDay();

        $periodEnd = $asOf->copy()->endOfDay();
        $slaMinutes = max(1, (int) config('kpi.operations.service_response_sla_minutes', 10));

        if (
            !Schema::hasTable('lead_chat_conversations')
            || !Schema::hasTable('lead_chat_messages')
            || !Schema::hasColumn('lead_chat_conversations', 'operations_user_id')
        ) {
            return $this->emptyResult(
                'Lead Chat assignment/message tables are not available yet.',
                $slaMinutes
            );
        }

        $conversationIds = DB::table('lead_chat_conversations')
            ->where('operations_user_id', $user->id)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return $this->emptyResult(
                'No Lead Chat conversations are assigned to this Operations member in the selected scope.',
                $slaMinutes
            );
        }

        $query = DB::table('lead_chat_messages')
            ->whereIn('conversation_id', $conversationIds)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        if (Schema::hasColumn('lead_chat_messages', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('lead_chat_messages', 'message_type')) {
            $query->where('message_type', '!=', 'system');
        }

        $messages = $query
            ->orderBy('conversation_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            return $this->emptyResult(
                'No qualifying Sales-to-Operations chat messages were found in the selected period.',
                $slaMinutes
            );
        }

        $senderIds = $messages
            ->pluck('sender_user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $roleByUser = $senderIds->isEmpty()
            ? collect()
            : DB::table('users')
                ->join('user_types', 'user_types.id', '=', 'users.user_type_id')
                ->whereIn('users.id', $senderIds)
                ->pluck('user_types.user_type', 'users.id');

        $eligible = 0;
        $responded = 0;
        $withinSla = 0;
        $outsideSla = 0;
        $unanswered = 0;
        $responseMinutes = [];

        foreach ($messages->groupBy('conversation_id') as $conversationMessages) {
            $pendingSalesQueryAt = null;

            foreach ($conversationMessages as $message) {
                $senderId = isset($message->sender_user_id)
                    ? (string) $message->sender_user_id
                    : '';

                $source = isset($message->source)
                    ? (string) $message->source
                    : 'crm';

                $role = $senderId !== ''
                    ? (string) ($roleByUser[$senderId] ?? '')
                    : '';

                $isSalesMessage = in_array(
                    $role,
                    UserType::SALES_ROLES,
                    true
                );

                $isOperationsReply = $senderId === (string) $user->id
                    || $source === 'google_chat';

                if ($isSalesMessage) {
                    if ($pendingSalesQueryAt === null) {
                        $pendingSalesQueryAt = Carbon::parse($message->created_at);
                    }

                    continue;
                }

                if ($isOperationsReply && $pendingSalesQueryAt !== null) {
                    $replyAt = Carbon::parse($message->created_at);
                    $minutes = max(
                        0,
                        $pendingSalesQueryAt->diffInSeconds($replyAt) / 60
                    );

                    $eligible++;
                    $responded++;
                    $responseMinutes[] = $minutes;

                    if ($minutes <= $slaMinutes) {
                        $withinSla++;
                    } else {
                        $outsideSla++;
                    }

                    $pendingSalesQueryAt = null;
                }
            }

            if ($pendingSalesQueryAt !== null) {
                $eligible++;
                $unanswered++;
                $outsideSla++;
            }
        }

        $achievement = $eligible > 0
            ? ($withinSla / $eligible) * 100
            : 0.0;

        $averageMinutes = count($responseMinutes) > 0
            ? array_sum($responseMinutes) / count($responseMinutes)
            : 0.0;

        return [
            'actual_value' => round($achievement, 4),
            'target_value' => 100,
            'achievement_percent' => round($achievement, 4),
            'evidence' => [
                'eligible_queries' => $eligible,
                'responded_queries' => $responded,
                'within_sla' => $withinSla,
                'outside_sla' => $outsideSla,
                'unanswered_queries' => $unanswered,
                'average_response_minutes' => round($averageMinutes, 2),
                'sla_minutes' => $slaMinutes,
            ],
        ];
    }

    private function emptyResult(string $note, int $slaMinutes): array
    {
        return [
            'actual_value' => 0,
            'target_value' => 100,
            'achievement_percent' => 0,
            'evidence' => [
                'eligible_queries' => 0,
                'responded_queries' => 0,
                'within_sla' => 0,
                'outside_sla' => 0,
                'unanswered_queries' => 0,
                'average_response_minutes' => 0,
                'sla_minutes' => $slaMinutes,
                'note' => $note,
            ],
        ];
    }
}
