<?php

namespace App\Services\Kpi;

use App\Models\CallSummaryIntegration;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;

class SalesKpiCommunicationService
{
    public function firstResponseAt(
        Lead $lead,
        User $user,
        Carbon $after
    ): ?Carbon {
        $callAt = CallSummaryIntegration::query()
            ->where('lead_id', $lead->id)
            ->where('agent_user_id', $user->id)
            ->where('direction', 'outgoing')
            ->where('call_start_at', '>=', $after)
            ->orderBy('call_start_at')
            ->value('call_start_at');

        $followupIds = $lead->leadFollowups()
            ->pluck('id');

        $whatsappAt = null;

        if ($followupIds->isNotEmpty() && class_exists(WhatsAppMessage::class)) {
            $whatsappAt = WhatsAppMessage::query()
                ->whereIn('lead_followup_id', $followupIds)
                ->where('direction', 'outgoing')
                ->where('sender_user_id', $user->id)
                ->where('message_at', '>=', $after)
                ->orderBy('message_at')
                ->value('message_at');
        }

        return $this->earliest([
            $callAt,
            $whatsappAt,
        ]);
    }

    private function earliest(array $values): ?Carbon
    {
        $dates = collect($values)
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sortBy(fn (Carbon $value) => $value->timestamp)
            ->values();

        return $dates->isEmpty()
            ? null
            : $dates->first();
    }
}
