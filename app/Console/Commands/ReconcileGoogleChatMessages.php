<?php

namespace App\Console\Commands;

use App\Models\LeadChatConversation;
use App\Services\GoogleChat\{GoogleChatClient, GoogleChatInboundService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};

class ReconcileGoogleChatMessages extends Command
{
    protected $signature = 'google-chat:reconcile {--limit=50}';
    protected $description = 'Recover messages in mapped Google threads without exporting historical CRM messages';

    public function handle(GoogleChatClient $client, GoogleChatInboundService $inbound): int
    {
        if (!$client->enabled()) {
            return self::SUCCESS;
        }
        $failed = false;
        $conversations = LeadChatConversation::whereNotNull('google_thread_name')
            ->whereIn('google_space_name', config('services.google_chat.allowed_spaces', []))
            ->orderByRaw('CASE WHEN google_reconciled_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('google_reconciled_at')->limit(max(1, min(500, (int) $this->option('limit'))))->get();
        foreach ($conversations as $conversation) {
            try {
                $page = null;
                do {
                    $result = $client->listThreadMessages($conversation->google_space_name, $conversation->google_thread_name, $page);
                    foreach ($result['messages'] ?? [] as $message) {
                        if (data_get($message, 'thread.name') !== $conversation->google_thread_name) {
                            continue;
                        }
                        Cache::lock('google-chat:incoming:'.sha1($message['name']), 180)
                            ->block(5, fn () => $inbound->ingest($message));
                    }
                    $page = $result['nextPageToken'] ?? null;
                } while ($page);
                $conversation->update(['google_reconciled_at' => now()]);
            } catch (\Throwable $e) {
                $failed = true;
                Log::warning('Google thread reconciliation failed.', ['conversation_id' => $conversation->id, 'exception' => get_class($e)]);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
