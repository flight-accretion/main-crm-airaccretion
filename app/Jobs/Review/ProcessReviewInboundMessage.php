<?php

namespace App\Jobs\Review;

use App\Models\ReviewConversation;
use App\Models\WhatsAppMessage;
use App\Services\Operations\OperationCaseService;
use App\Services\Review\ReviewAiService;
use App\Services\Review\ReviewWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReviewInboundMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $reviewId,
        public string $messageId
    ) {
    }

    public function handle(
        ReviewAiService $ai,
        OperationCaseService $operations,
        ReviewWhatsAppService $whatsApp
    ): void {
        $review = ReviewConversation::with('lead')->find($this->reviewId);
        $message = WhatsAppMessage::find($this->messageId);

        if (!$review || !$message || $review->completed_at) {
            return;
        }

        $review->update([
            'customer_replied' => true,
            'next_reminder_at' => null,
        ]);

        $result = $ai->process($review, (string) ($message->body ?? ''));

        $review->update([
            'sentiment' => $result['sentiment'],
            'intent' => $result['intent'],
            'needs_human' => $result['needs_human'],
            'ai_state' => $result,
        ]);

        if ($result['needs_human'] && $result['operations_action'] && $review->lead) {
            $case = $operations->open($review->lead, $result['operations_action'], [
                'source' => 'review_agent',
                'review_conversation_id' => $review->id,
                'ai_summary' => $result['summary'],
            ]);

            $whatsApp->notifyOperations(
                $review->fresh(['lead.client']),
                $case->type,
                $result['summary']
            );
        }

        if ($result['reply'] !== '') {
            $whatsApp->sendText($review, $result['reply']);
        }

        if ($result['review_eligible'] && !$review->review_link_sent_at) {
            if ($whatsApp->sendReviewLink($review)) {
                $review->update(['review_link_sent_at' => now()]);
            }
        }
    }
}
