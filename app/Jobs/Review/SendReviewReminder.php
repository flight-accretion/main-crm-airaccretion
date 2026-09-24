<?php

namespace App\Jobs\Review;

use App\Models\ReviewConversation;
use App\Services\Review\ReviewWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendReviewReminder implements ShouldQueue
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
        DB::transaction(function () use ($whatsApp) {
            $review = ReviewConversation::query()
                ->lockForUpdate()
                ->find($this->reviewId);

            if (!$review
                || $review->customer_replied
                || $review->status !== 'waiting_for_reply'
                || $review->reminder_count >= 5) {
                return;
            }

            if (!$whatsApp->sendReminder($review)) {
                return;
            }

            $count = (int) $review->reminder_count + 1;

            $review->update([
                'reminder_count' => $count,
                'last_reminder_at' => now(),
                'next_reminder_at' => $count >= 5
                    ? null
                    : now('Asia/Kolkata')
                        ->addDay()
                        ->setTime(11, 0)
                        ->setTimezone(config('app.timezone')),
            ]);
        });
    }
}
