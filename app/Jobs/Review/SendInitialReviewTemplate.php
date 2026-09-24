<?php

namespace App\Jobs\Review;

use App\Models\ReviewConversation;
use App\Services\Review\ReviewWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInitialReviewTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $reviewId)
    {
    }

    public function handle(ReviewWhatsAppService $whatsApp): void
    {
        $review = ReviewConversation::find($this->reviewId);

        if (!$review || $review->initial_message_sent_at) {
            return;
        }

        if (!$whatsApp->sendInitialTemplate($review)) {
            return;
        }

        $review->update([
            'initial_message_sent_at' => now(),
            'next_reminder_at' => now('Asia/Kolkata')
                ->addDay()
                ->setTime(11, 0)
                ->setTimezone(config('app.timezone')),
        ]);
    }
}
