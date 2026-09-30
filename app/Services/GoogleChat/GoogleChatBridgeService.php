<?php

namespace App\Services\GoogleChat;

use App\Models\LeadChatMessage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GoogleChatBridgeService
{
    public function sync(string $messageId): void
    {
        if (!$this->client->enabled()) {
            return;
        }
        $message = LeadChatMessage::find($messageId);
        if (!$message || $message->source !== 'crm') {
            return;
        }
        \Illuminate\Support\Facades\Cache::lock('google-chat:conversation:'.$message->conversation_id, 180)
            ->block(5, function () use ($messageId) {
                $message = LeadChatMessage::with('conversation')->findOrFail($messageId);
                $conversation = $message->conversation;
                if (!$conversation || (!$conversation->google_thread_name && !$conversation->google_template_message_id)) {
                    return;
                }
                if (!$conversation->google_thread_name && $conversation->google_template_message_id !== $message->id) {
                    return;
                }
                if ($message->google_sync_status === 'synced'
                    && $message->google_synced_version === $message->google_sync_version) {
                    return;
                }
                $version = $message->google_sync_version;
                $body = $message->body;
                $deleted = $message->getRawOriginal('deleted_at');
                LeadChatMessage::whereKey($message->id)->update(['google_sync_attempted_at' => now()]);
                try {
                    if (!in_array($conversation->google_space_name, config('services.google_chat.allowed_spaces', []), true)) {
                        throw new \RuntimeException('Google Chat space is no longer approved.');
                    }
                    if ($message->deleted_at) {
                        $this->delete($message);
                    } elseif ($message->google_message_name) {
                        $this->update($message);
                    } else {
                        $this->create($message);
                    }
                    \Illuminate\Support\Facades\DB::transaction(function () use ($message, $version, $body, $deleted) {
                        $fresh = LeadChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
                        $changed = $fresh->google_sync_version !== $version || $fresh->body !== $body
                            || $fresh->getRawOriginal('deleted_at') !== $deleted;
                        LeadChatMessage::whereKey($message->id)->update([
                            'google_synced_version' => $version,
                            'google_sync_status' => $changed ? 'pending' : $fresh->google_sync_status,
                        ]);
                    });
                } catch (\Throwable $e) {
                    $this->markFailed($message, $e);
                    throw $e;
                }
            });
    }
    public function __construct(
        private GoogleChatClient $client
    ) {
    }


    public function create(
        LeadChatMessage $message
    ): void {

        if (
            !$this->client->enabled()
        ) {

            $this->markNotRequired(
                $message
            );

            return;
        }


        $message->loadMissing([

            'conversation',

            'lead.client',

            'sender.userType',

            'attachments',

            'task',
        ]);


        if (
            $message->source
            !==
            'crm'
        ) {

            return;
        }


        if (
            $message->google_message_name
        ) {

            return;
        }


        $conversation =
            $message->conversation;


        if (
            !$conversation
        ) {

            return;
        }


        $threadKey =
            $conversation
                ->google_thread_key

            ?:

            'lead-'
            .
            strtolower(
                $message->lead_id
            );


        if (
            !$conversation
                ->google_thread_key
        ) {

            $conversation->update([

                'google_thread_key' =>
                    $threadKey,
            ]);
        }


        $this->markSyncing(
            $message
        );


        $google =
            $this->client
                ->createMessage(

                    $this->formatMessage(
                        $message
                    ),

                    $threadKey,

                    $message->id,
                    $conversation->google_space_name,
                    $conversation->google_thread_name
                );


        $threadName =
            data_get(
                $google,
                'thread.name'
            );

        $space = $conversation->google_space_name;
        if (!is_string($threadName) || !str_starts_with($threadName, $space.'/threads/')
            || !is_string(data_get($google, 'name')) || !str_starts_with($google['name'], $space.'/messages/')
            || ($conversation->google_thread_name && $conversation->google_thread_name !== $threadName)) {
            throw new \RuntimeException('Google Chat returned an invalid message mapping.');
        }


        LeadChatMessage::withoutEvents(
            function () use (
                $message,
                $google
            ) {

                $message->update([

                    'google_message_name' =>
                        data_get(
                            $google,
                            'name'
                        ),

                    'google_sender_name' =>
                        data_get(
                            $google,
                            'sender.name'
                        ),

                    'google_create_time' =>
                        data_get(
                            $google,
                            'createTime'
                        )
                            ? GoogleChatTime::parse(
                                data_get(
                                    $google,
                                    'createTime'
                                )
                            )
                            : now(),

                    'google_update_time' =>
                        data_get(
                            $google,
                            'lastUpdateTime'
                        )
                            ? GoogleChatTime::parse(
                                data_get(
                                    $google,
                                    'lastUpdateTime'
                                )
                            )
                            : null,

                    'google_sync_status' =>
                        'synced',

                    'google_sync_error' =>
                        null,
                ]);
            }
        );


        $conversation->update([

            'google_space_name' =>
                $conversation->google_space_name ?: $this->client->spaceName(),
            'google_connection_status' => 'ready',

            'google_thread_name' =>
                $threadName
                ?:
                $conversation
                    ->google_thread_name,

            'google_thread_key' =>
                $threadKey,

            'google_synced_at' =>
                now(),
        ]);
    }


    public function update(
        LeadChatMessage $message
    ): void {

        if (
            $message->source
            !==
            'crm'
        ) {
            return;
        }


        if (
            !$message
                ->google_message_name
        ) {

            $this->create(
                $message
            );

            return;
        }


        $message->loadMissing([

            'conversation',

            'lead.client',

            'sender.userType',

            'attachments',

            'task',
        ]);


        $this->markSyncing(
            $message
        );


        $google =
            $this->client
                ->updateMessage(

                    $message
                        ->google_message_name,

                    $this->formatMessage(
                        $message
                    )
                );


        LeadChatMessage::withoutEvents(
            function () use (
                $message,
                $google
            ) {

                $message->update([

                    'google_update_time' =>
                        data_get(
                            $google,
                            'lastUpdateTime'
                        )
                            ? GoogleChatTime::parse(
                                data_get(
                                    $google,
                                    'lastUpdateTime'
                                )
                            )
                            : now(),

                    'google_sync_status' =>
                        'synced',

                    'google_sync_error' =>
                        null,
                ]);
            }
        );
    }


    public function delete(
        LeadChatMessage $message
    ): void {

        if (
            $message->source
            !==
            'crm'
        ) {
            return;
        }


        if (
            !$message
                ->google_message_name
        ) {

            $this->markNotRequired(
                $message
            );

            return;
        }


        $this->client
            ->deleteMessage(

                $message
                    ->google_message_name
            );


        LeadChatMessage::withoutEvents(
            function () use (
                $message
            ) {

                $message->update([

                    'google_sync_status' =>
                        'synced',

                    'google_sync_error' =>
                        null,

                    'google_update_time' =>
                        now(),
                ]);
            }
        );
    }


    public function markFailed(
        LeadChatMessage $message,
        \Throwable $exception
    ): void {

        LeadChatMessage::withoutEvents(
            function () use (
                $message,
                $exception
            ) {

                $message->update([

                    'google_sync_status' =>
                        'failed',

                    'google_sync_error' =>
                        'Google delivery failed; retry pending. '.get_class($exception),
                ]);
            }
        );
    }


    private function formatMessage(
        LeadChatMessage $message
    ): string {

        $lead =
            $message->lead;


        $sender =
            $message
                ->sender
                ?->name
            ??
            'CRM';


        $role =
            $message
                ->sender
                ?->userType
                ?->user_type
            ??
            'CRM';


        $customer =
            $lead
                ?->client
                ?->name
            ??
            'Lead';


        $serviceNames =
            $lead
                ?->service_names
            ??
            [];


        $services =
            is_array(
                $serviceNames
            )

                ? implode(
                    ', ',
                    array_filter(
                        $serviceNames
                    )
                )

                : trim(
                    (string)
                    $serviceNames
                );


        if (
            $services === ''
        ) {

            $services =
                'N/A';
        }


        if (
            $message->message_type
            ===
            LeadChatMessage::TYPE_TASK
        ) {

            $task =
                $message->task;


            $text =
                "📋 CRM TASK\n"
                .
                "Lead: {$customer}\n"
                .
                "Service: {$services}\n"
                .
                "Created by: {$sender} · {$role}\n\n"
                .
                "Task: "
                .
                (
                    $task?->title
                    ?:
                    $message->body
                    ?:
                    'Task'
                )
                .
                "\nPriority: "
                .
                (
                    $task?->priority
                    ?:
                    'normal'
                );


            if (
                $task?->due_at
            ) {

                $text .=
                    "\nDue: "
                    .
                    $task
                        ->due_at
                        ->timezone(
                            'Asia/Kolkata'
                        )
                        ->format(
                            'd M Y h:i A'
                        );
            }


            if (
                $task?->description
            ) {

                $text .=
                    "\n\n"
                    .
                    $task->description;
            }


            return
                $this->withCrmLink(
                    $text,
                    $message
                );
        }


        if (
            $message->message_type
            ===
            LeadChatMessage::TYPE_SYSTEM
        ) {

            return
                $this->withCrmLink(

                    "🔄 CRM UPDATE\n"
                    .
                    "Lead: {$customer}\n\n"
                    .
                    (
                        $message->body
                        ??
                        ''
                    ),

                    $message
                );
        }


        $text =
            "💬 {$sender} · {$role}\n";


        if (
            !$message
                ->conversation
                ?->google_thread_name
        ) {

            $text .=

                "Lead: {$customer}\n"

                .

                "Service: {$services}\n"

                .

                "Lead ID: {$message->lead_id}\n\n";
        }


        $text .=
            $message->body
            ??
            '';


        if (
            $message
                ->attachments
                ->count() > 0
        ) {

            $count =
                $message
                    ->attachments
                    ->count();


            $text .=

                "\n\n📎 {$count} attachment"

                .

                (
                    $count > 1
                    ? 's'
                    : ''
                )

                .

                ' available in CRM.';
        }


        return
            $this->withCrmLink(
                $text,
                $message
            );
    }


    private function withCrmLink(
        string $text,
        LeadChatMessage $message
    ): string {

        try {

            $url =
                route(

                    'admin.leads.follow-up.create',

                    $message->lead_id
                )

                .

                '?chat=1&message='

                .

                urlencode(
                    $message->id
                )

                .

                '#lead-chat-panel';


            return
                $text
                .
                "\n\nCRM: "
                .
                $url;


        } catch (\Throwable $e) {

            return $text;
        }
    }


    private function markSyncing(
        LeadChatMessage $message
    ): void {

        LeadChatMessage::withoutEvents(
            function () use (
                $message
            ) {

                $message->update([

                    'google_sync_status' =>
                        'syncing',

                    'google_sync_error' =>
                        null,
                ]);
            }
        );
    }


    private function markNotRequired(
        LeadChatMessage $message
    ): void {

        LeadChatMessage::withoutEvents(
            function () use (
                $message
            ) {

                $message->update([

                    'google_sync_status' =>
                        'not_required',

                    'google_sync_error' =>
                        null,
                ]);
            }
        );
    }
}
