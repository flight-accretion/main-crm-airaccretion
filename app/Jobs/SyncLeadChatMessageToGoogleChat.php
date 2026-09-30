<?php

namespace App\Jobs;

use App\Models\LeadChatMessage;
use App\Services\GoogleChat\GoogleChatBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{Bus, Log};

class SyncLeadChatMessageToGoogleChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public string $messageId, public string $action = 'create')
    {
        $this->onQueue(config('services.google_chat.queue', 'google-chat'));
        $this->onConnection(config('services.google_chat.queue_connection', 'google_chat'));
    }

    public static function enqueue(string $id): void
    {
        try {
            $job = new self($id);
            if (config('queue.connections.'.$job->connection.'.driver') === 'sync') {
                throw new \RuntimeException('Google Chat requires an asynchronous queue connection.');
            }
            Bus::dispatch($job);
        } catch (\Throwable $e) {
            // Pending state is already durable; scheduler redispatches after queue recovery.
            Log::warning('Google Chat dispatch deferred.', ['message_id' => $id, 'exception' => get_class($e)]);
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(GoogleChatBridgeService $bridge): void
    {
        $bridge->sync($this->messageId);
    }

    public function failed(\Throwable $exception): void
    {
        if ($message = LeadChatMessage::find($this->messageId)) {
            app(GoogleChatBridgeService::class)->markFailed($message, $exception);
        }
    }
}
