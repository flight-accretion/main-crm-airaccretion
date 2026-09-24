<?php

namespace App\Services\Review;

use App\Jobs\Review\ProcessReviewInboundMessage;
use App\Jobs\Review\StoreReviewMediaToGoogleDrive;
use App\Models\ReviewConversation;
use App\Models\WhatsAppMessage;

class ReviewInboundRouter
{
    public function handle(WhatsAppMessage $message): void
    {
        if ($message->direction !== 'incoming') {
            return;
        }

        $review = ReviewConversation::query()
            ->where('whatsapp_conversation_id', $message->conversation_id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if (!$review) {
            return;
        }

        $review->update([
            'customer_replied' => true,
            'next_reminder_at' => null,
        ]);

        if (in_array($message->message_type, ['image', 'video', 'document', 'audio'], true)) {
            StoreReviewMediaToGoogleDrive::dispatch($message->id, $review->id);
        }

        ProcessReviewInboundMessage::dispatch($review->id, $message->id);
    }
}
