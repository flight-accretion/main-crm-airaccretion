<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class WhatsAppResponseTimeService
{
    public const WINDOW_DAYS = 7;

    /**
     * Average minutes from the first customer message of a turn to the next outgoing reply
     * (agent or AI), over visible conversations in the last 7 days. Unanswered turns are excluded.
     */
    public function summary(Builder $visibleConversations, string $cacheKey): array
    {
        // The inbox list polls this endpoint, so the scan is cached briefly per viewer.
        return Cache::remember('wa-first-response:'.$cacheKey, now()->addMinutes(5), function () use ($visibleConversations) {
            $conversationIds = (clone $visibleConversations)->setEagerLoads([])->select('whatsapp_conversations.id');
            $pending = [];
            $total = 0;
            $count = 0;
            $messages = WhatsAppMessage::query()
                ->whereIn('conversation_id', $conversationIds)
                ->whereIn('direction', ['incoming', 'outgoing'])
                ->where('message_at', '>=', now()->subDays(self::WINDOW_DAYS))
                ->orderBy('conversation_id')->orderBy('message_at')->orderBy('id')
                ->toBase()->select(['conversation_id', 'direction', 'message_at'])->cursor();

            foreach ($messages as $message) {
                $at = strtotime((string) $message->message_at);
                if ($message->direction === 'incoming') {
                    $pending[$message->conversation_id] ??= $at;
                } elseif (isset($pending[$message->conversation_id])) {
                    $total += max(0, $at - $pending[$message->conversation_id]);
                    $count++;
                    unset($pending[$message->conversation_id]);
                }
            }

            return [
                'avg_first_response_minutes' => $count ? round($total / $count / 60, 1) : null,
                'responded_turns' => $count,
                'awaiting_reply' => count($pending),
            ];
        });
    }
}
