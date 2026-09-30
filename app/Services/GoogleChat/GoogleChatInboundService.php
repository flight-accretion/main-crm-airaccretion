<?php

namespace App\Services\GoogleChat;

use App\Models\{GoogleChatIdentity, LeadChatConversation, LeadChatMessage};
use App\Services\LeadChat\LeadChatNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class GoogleChatInboundService
{
    public function __construct(private GoogleChatClient $client, private LeadChatNotificationService $notifications)
    {
    }

    public function handle(string $eventType, array $eventData, ?string $eventTime = null): void
    {
        $prefix = 'google.workspace.chat.message.v1.';
        $actions = ['created' => 'create', 'updated' => 'update', 'deleted' => 'delete',
            'batchCreated' => 'create', 'batchUpdated' => 'update', 'batchDeleted' => 'delete'];
        $action = str_starts_with($eventType, $prefix) ? ($actions[substr($eventType, strlen($prefix))] ?? null) : null;
        if (!$action) {
            return;
        }
        foreach ($this->messageNames($eventData) as $name) {
            if (!preg_match('~^(spaces/[A-Za-z0-9_-]+)/messages/[A-Za-z0-9_.-]+$~D', $name, $match)
                || !in_array($match[1], config('services.google_chat.allowed_spaces', []), true)) {
                continue;
            }
            Cache::lock('google-chat:incoming:'.sha1($name), 180)->block(5, function () use ($name, $action, $eventTime) {
                if ($action === 'delete') {
                    $this->deleted($name, $eventTime);
                } elseif (!DB::table('google_chat_tombstones')->where('message_name', $name)->exists()) {
                    try {
                        $google = $this->client->getMessage($name);
                    } catch (\Illuminate\Http\Client\RequestException $e) {
                        if ($e->response->status() === 404) {
                            return;
                        }
                        throw $e;
                    }
                    $this->ingest($google);
                }
            });
        }
    }

    public function ingest(array $google): void
    {
        $name = $google['name'] ?? '';
        if (!preg_match('~^(spaces/[A-Za-z0-9_-]+)/messages/[A-Za-z0-9_.-]+$~D', $name, $match)) {
            return;
        }
        $space = $match[1];
        if (!in_array($space, config('services.google_chat.allowed_spaces', []), true)) {
            return;
        }
        if (!empty($google['deleteTime'])) {
            $this->deleted($name, $google['deleteTime']);
            return;
        }
        if (DB::table('google_chat_tombstones')->where('message_name', $name)->exists()) {
            return;
        }
        $clientId = $google['clientAssignedMessageId'] ?? basename($name);
        if (str_starts_with($clientId, 'client-')
            && LeadChatMessage::whereKey(substr($clientId, 7))->where('source', 'crm')->exists()) {
            return;
        }
        $conversation = LeadChatConversation::where('google_space_name', $space)
            ->where('google_thread_name', data_get($google, 'thread.name'))->first();
        if (!$conversation || !data_get($google, 'thread.name') || data_get($google, 'sender.type') !== 'HUMAN') {
            return;
        }
        $canonicalSender = (string) data_get($google, 'sender.name', '');
        $members = collect($this->client->listSpaceMembers($space));
        if (!$members->contains(fn ($m) => ($m['state'] ?? '') === 'JOINED'
            && data_get($m, 'member.name') === $canonicalSender)) {
            return;
        }
        $identity = GoogleChatIdentity::where('google_user_name', $canonicalSender)->whereNotNull('verified_at')->first();
        $sender = data_get($google, 'sender.displayName') ?: $canonicalSender;
        $created = GoogleChatTime::parse($google['createTime'] ?? now());
        $version = GoogleChatTime::parse($google['lastUpdateTime'] ?? $google['createTime'] ?? now());
        DB::transaction(function () use ($conversation, $google, $name, $identity, $sender, $created, $version) {
            LeadChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if (DB::table('google_chat_tombstones')->where('message_name', $name)->exists()) {
                return;
            }
            $existing = LeadChatMessage::where('google_message_name', $name)->lockForUpdate()->first();
            $existingVersion = $existing?->google_event_version
                ? GoogleChatTime::parse($existing->google_event_version) : $existing?->google_update_time;
            if ($existing && ($existing->source === 'crm' || $existing->deleted_at
                || ($existingVersion && $existingVersion->gte($version)))) {
                return;
            }
            $attributes = [
                'body' => trim((string) ($google['text'] ?? $google['argumentText'] ?? '')) ?: '[Google Chat message]',
                'google_sender_name' => $sender, 'google_update_time' => $version,
                'google_event_version' => $version->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ];
            if ($existing) {
                LeadChatMessage::withoutEvents(fn () => $existing->update($attributes + ['edited_at' => $version]));
                return;
            }
            $message = LeadChatMessage::withoutEvents(fn () => LeadChatMessage::create($attributes + [
                'id' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'lead_id' => $conversation->lead_id,
                'sender_user_id' => $identity?->user_id, 'source' => 'google_chat', 'message_type' => 'text',
                'google_message_name' => $name, 'google_create_time' => $created, 'google_sync_status' => 'not_required',
            ]));
            $conversation->update(['google_synced_at' => now(),
                'last_message_at' => $conversation->last_message_at && $conversation->last_message_at->gt($created)
                    ? $conversation->last_message_at : $created]);
            $this->notifications->notifyGoogleMessage($conversation->lead, $conversation, $message, $sender);
        });
    }

    private function deleted(string $name, ?string $eventTime): void
    {
        DB::transaction(function () use ($name, $eventTime) {
            DB::table('google_chat_tombstones')->insertOrIgnore([
                'message_name' => $name, 'deleted_at' => GoogleChatTime::parse($eventTime ?? now()),
            ]);
            LeadChatMessage::where('google_message_name', $name)->where('source', 'google_chat')
                ->update(['body' => null, 'deleted_at' => GoogleChatTime::parse($eventTime ?? now()), 'google_update_time' => now()]);
        });
    }

    public function messageNames(array $data): array
    {
        $names = [data_get($data, 'message.name')];
        foreach ($data['messages'] ?? [] as $item) {
            $names[] = data_get($item, 'message.name') ?: data_get($item, 'name');
        }
        return array_values(array_unique(array_filter($names, 'is_string')));
    }
}
