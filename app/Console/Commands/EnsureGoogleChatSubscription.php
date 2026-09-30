<?php

namespace App\Console\Commands;

use App\Services\GoogleChat\GoogleChatSubscriptionService;
use Illuminate\Console\Command;

class EnsureGoogleChatSubscription extends Command
{
    protected $signature = 'google-chat:ensure-subscription';
    protected $description = 'Create or renew Google Chat subscriptions for approved Spaces';

    public function handle(GoogleChatSubscriptionService $service): int
    {
        if (!config('services.google_chat.enabled')) {
            return self::SUCCESS;
        }
        $failed = false;
        foreach (array_unique(config('services.google_chat.allowed_spaces', [])) as $space) {
            try {
                $subscription = $service->ensureForSpace($space);
                $this->info($space.': active until '.$subscription->expire_time?->toIso8601String());
            } catch (\Throwable $e) {
                $failed = true;
                $this->error($space.': renewal failed ('.get_class($e).').');
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
