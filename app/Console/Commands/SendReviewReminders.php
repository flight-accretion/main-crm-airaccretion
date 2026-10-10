<?php

namespace App\Console\Commands;

use App\Jobs\Review\SendReviewReminder;
use App\Models\ReviewConversation;
use Illuminate\Console\Command;

class SendReviewReminders extends Command
{
    protected $signature = 'reviews:send-reminders {--limit= : Maximum review reminders to queue in this run}';

    protected $description = 'Queue due post-ride review reminders';

    public function handle(): int
    {
        $limit = max(
            1,
            min(
                500,
                (int) ($this->option('limit') ?: config('crm.review_reminder_batch_size', 100))
            )
        );

        $queued = 0;

        ReviewConversation::query()
            ->where('status', 'waiting_for_reply')
            ->where('customer_replied', false)
            ->where('reminder_count', '<', 5)
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->orderBy('next_reminder_at')
            ->limit($limit)
            ->get()
            ->each(function ($review) use (&$queued) {
                SendReviewReminder::dispatch($review->id);
                $queued++;
            });

        $this->info('Review reminder jobs queued: ' . $queued);

        return self::SUCCESS;
    }
}
