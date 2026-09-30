<?php

namespace App\Jobs;

use App\Models\GoogleChatEvent;
use App\Services\GoogleChat\GoogleChatInboundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Bus, Cache, Log};

class ProcessGoogleChatEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public string $eventId)
    {
        $this->onConnection(config('services.google_chat.queue_connection', 'google_chat'));
        $this->onQueue(config('services.google_chat.queue', 'google-chat'));
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
            Log::warning('Google Chat inbox dispatch deferred.', ['event_id' => $id, 'exception' => get_class($e)]);
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(GoogleChatInboundService $inbound): void
    {
        if (!config('services.google_chat.enabled')) {
            return;
        }
        Cache::lock('google-chat:event:'.$this->eventId, 180)->block(5, function () use ($inbound) {
            $event = GoogleChatEvent::findOrFail($this->eventId);
            if ($event->status === 'processed') {
                return;
            }
            $event->update(['status' => 'processing', 'attempted_at' => now(), 'attempts' => $event->attempts + 1]);
            try {
                $names = $inbound->messageNames($event->payload);
                foreach (array_slice($names, $event->processed_count) as $name) {
                    $inbound->handle($event->event_type, ['message' => ['name' => $name]], $event->event_time?->toIso8601String());
                    $event->update(['processed_count' => $event->processed_count + 1]);
                }
                $event->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null]);
            } catch (\Throwable $e) {
                $event->update(['status' => 'failed', 'last_error' => get_class($e)]);
                throw $e;
            }
        });
    }
}
