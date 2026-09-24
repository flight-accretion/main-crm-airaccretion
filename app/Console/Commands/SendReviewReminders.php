<?php

namespace App\Console\Commands;

use App\Jobs\Review\SendReviewReminder;
use App\Models\ReviewConversation;
use Illuminate\Console\Command;

class SendReviewReminders extends Command
{
    protected $signature = 'reviews:send-reminders';

    protected $description = 'Queue due post-ride review reminders';

    public function handle(): int
    {
        ReviewConversation::query()
            ->where('status', 'waiting_for_reply')
            ->where('customer_replied', false)
            ->where('reminder_count', '<', 5)
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->chunk(100, function ($reviews) {
                foreach ($reviews as $review) {
                    SendReviewReminder::dispatch($review->id);
                }
            });

        return self::SUCCESS;
    }
}
