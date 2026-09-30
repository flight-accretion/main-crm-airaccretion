<?php

namespace App\Console\Commands;

use App\Jobs\{ProcessGoogleChatEvent, SyncLeadChatMessageToGoogleChat};
use App\Models\{GoogleChatEvent, LeadChatMessage};
use Illuminate\Console\Command;

class RetryGoogleChatSync extends Command
{
    protected $signature = 'google-chat:retry-pending';
    protected $description = 'Redispatch recoverable Google Chat messages and inbound events';

    public function handle(): int
    {
        if (!config('services.google_chat.enabled')) {
            return self::SUCCESS;
        }
        LeadChatMessage::where('source', 'crm')->whereIn('google_sync_status', ['pending', 'failed', 'syncing'])
            ->where(fn ($q) => $q->whereNull('google_sync_attempted_at')->orWhere('google_sync_attempted_at', '<', now()->subMinutes(5)))
            ->orderBy('created_at')->limit(100)->get()->each(function ($message) {
                LeadChatMessage::whereKey($message->id)->update(['google_sync_attempted_at' => now()]);
                SyncLeadChatMessageToGoogleChat::enqueue($message->id);
            });
        GoogleChatEvent::whereIn('status', ['pending', 'failed', 'processing'])
            ->where(fn ($q) => $q->whereNull('attempted_at')->orWhere('attempted_at', '<', now()->subMinutes(5)))
            ->orderBy('created_at')->limit(100)->get()->each(function ($event) {
                $event->update(['attempted_at' => now()]);
                ProcessGoogleChatEvent::enqueue($event->id);
            });
        return self::SUCCESS;
    }
}
