<?php

namespace App\Services\Review;

use App\Models\AiAgent;
use App\Models\ReviewConversation;
use App\Models\WhatsAppMessage;
use App\Services\Ai\AiProviderManager;

class ReviewAiService
{
    public function __construct(
        private AiProviderManager $providers
    ) {
    }

    public function process(ReviewConversation $review, string $message): array
    {
        $agent = $this->agent($review);
        $profile = $agent->modelProfile;

        if (!$profile) {
            throw new \RuntimeException('Review Agent has no AI Model Profile.');
        }

        $input = json_encode([
            'crm_context' => [
                'lead_id' => $review->lead_id,
                'review_conversation_id' => $review->id,
            ],
            'conversation_history' => $this->history($review),
            'current_message' => $message,
        ], JSON_PRETTY_PRINT);

        $raw = $this->providers->generate(
            $profile,
            $this->instructions($agent),
            $input
        );

        return $this->normalize($this->decodeJson($raw));
    }

    private function agent(ReviewConversation $review): AiAgent
    {
        $agent = $review->ai_agent_id
            ? AiAgent::with('modelProfile')->find($review->ai_agent_id)
            : null;

        $agent = $agent ?: AiAgent::query()
            ->where('agent_type', 'review')
            ->where('enabled', true)
            ->with('modelProfile')
            ->orderBy('name')
            ->first();

        if (!$agent || !$agent->enabled) {
            throw new \RuntimeException('Active Review Agent is not configured.');
        }

        return $agent;
    }

    private function instructions(AiAgent $agent): string
    {
        return trim((string) $agent->prompt) . "\n\n"
            . 'Return JSON only with these keys: sentiment, intent, needs_human, '
            . 'review_eligible, operations_action, summary, reply. '
            . 'Allowed operations_action values: review, refund, reschedule, cancelled, null. '
            . 'Never change CRM lead, payment, ride, refund, or representative status.';
    }

    private function history(ReviewConversation $review): array
    {
        if (!$review->whatsapp_conversation_id) {
            return [];
        }

        return WhatsAppMessage::query()
            ->where('conversation_id', $review->whatsapp_conversation_id)
            ->orderByDesc('message_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->map(fn (WhatsAppMessage $message) => [
                'direction' => $message->direction,
                'type' => $message->message_type,
                'body' => $message->body,
                'message_at' => optional($message->message_at)->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function decodeJson(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalize(array $raw): array
    {
        $sentiments = ['positive', 'negative', 'neutral', 'uncertain'];
        $intents = ['feedback', 'complaint', 'refund', 'reschedule', 'cancellation', 'general'];
        $actions = ['review', 'refund', 'reschedule', 'cancelled', null];

        $action = $raw['operations_action'] ?? null;
        if ($action === 'null' || $action === '') {
            $action = null;
        }

        return [
            'sentiment' => in_array($raw['sentiment'] ?? null, $sentiments, true)
                ? $raw['sentiment']
                : 'uncertain',
            'intent' => in_array($raw['intent'] ?? null, $intents, true)
                ? $raw['intent']
                : 'general',
            'needs_human' => (bool) ($raw['needs_human'] ?? false),
            'review_eligible' => (bool) ($raw['review_eligible'] ?? false),
            'operations_action' => in_array($action, $actions, true) ? $action : null,
            'summary' => trim((string) ($raw['summary'] ?? '')),
            'reply' => trim((string) ($raw['reply'] ?? '')),
        ];
    }
}
