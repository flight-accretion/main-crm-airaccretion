<?php

namespace App\Services\GoogleChat;

use App\Models\GoogleChatIdentity;
use App\Models\LeadChatConversation;
use App\Models\LeadChatMessage;
use App\Services\LeadChat\LeadChatNotificationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleChatInboundService
{
    public function __construct(
        private GoogleChatClient $client,
        private LeadChatNotificationService $notifications
    ) {
    }

    /**
     * Handle Google Workspace Events / PubSub message events.
     *
     * Supported:
     * - created
     * - updated
     * - deleted
     * - batchCreated
     * - batchUpdated
     * - batchDeleted
     */
    public function handle(
        string $eventType,
        array $eventData,
        ?string $eventTime = null
    ): void {
        $prefix = 'google.workspace.chat.message.v1.';

        $actions = [
            'created' => 'create',
            'updated' => 'update',
            'deleted' => 'delete',

            'batchCreated' => 'create',
            'batchUpdated' => 'update',
            'batchDeleted' => 'delete',
        ];

        $eventSuffix = str_starts_with(
            $eventType,
            $prefix
        )
            ? substr(
                $eventType,
                strlen($prefix)
            )
            : null;

        $action = $eventSuffix
            ? ($actions[$eventSuffix] ?? null)
            : null;

        if (!$action) {
            return;
        }

        foreach (
            $this->messageNames($eventData)
            as
            $name
        ) {
            /*
             * Only accept valid Google Chat message resource names.
             */
            if (
                !preg_match(
                    '~^(spaces/[A-Za-z0-9_-]+)/messages/[A-Za-z0-9_.-]+$~D',
                    $name,
                    $match
                )
            ) {
                continue;
            }

            $space = $match[1];

            /*
             * Never ingest messages from a Space that has not explicitly
             * been approved in CRM configuration.
             */
            if (
                !in_array(
                    $space,
                    config(
                        'services.google_chat.allowed_spaces',
                        []
                    ),
                    true
                )
            ) {
                continue;
            }

            /*
             * Prevent the PubSub worker and reconciliation worker from
             * processing the same Google message at the same time.
             */
            Cache::lock(
                'google-chat:incoming:' . sha1($name),
                180
            )->block(
                5,
                function () use (
                    $name,
                    $action,
                    $eventTime
                ) {
                    if (
                        $action === 'delete'
                    ) {
                        $this->deleted(
                            $name,
                            $eventTime
                        );

                        return;
                    }

                    /*
                     * Once Google reports a message as deleted, do not
                     * resurrect it because of an older/stale event.
                     */
                    if (
                        DB::table(
                            'google_chat_tombstones'
                        )
                            ->where(
                                'message_name',
                                $name
                            )
                            ->exists()
                    ) {
                        return;
                    }

                    try {
                        /*
                         * Workspace Events contains the resource name.
                         * Read the current message from Google before
                         * importing/updating CRM.
                         */
                        $google =
                            $this->client
                                ->getMessage(
                                    $name
                                );

                    } catch (
                        RequestException $exception
                    ) {
                        /*
                         * Google may return 404 when a message was deleted
                         * between the event being published and our fetch.
                         */
                        if (
                            $exception
                                ->response
                                ->status()
                            ===
                            404
                        ) {
                            return;
                        }

                        throw $exception;
                    }

                    $this->ingest(
                        $google
                    );
                }
            );
        }
    }

    /**
     * Import a Google Chat message into the CRM lead conversation.
     *
     * IMPORTANT:
     *
     * Google Chat parent messages and every reply in the same thread have
     * different message names but share the same thread.name.
     *
     * Therefore we map:
     *
     * google_space_name
     * +
     * google_thread_name
     *
     * back to one LeadChatConversation.
     *
     * This is what allows replies made by Operations in Google Chat to
     * appear inside the correct CRM lead chat.
     */
    public function ingest(
        array $google
    ): void {
        $name =
            (string) (
                $google['name']
                ??
                ''
            );

        /*
         * Validate message resource and extract Space.
         */
        if (
            !preg_match(
                '~^(spaces/[A-Za-z0-9_-]+)/messages/[A-Za-z0-9_.-]+$~D',
                $name,
                $match
            )
        ) {
            return;
        }

        $space =
            $match[1];

        /*
         * Security boundary:
         * only approved Google Spaces may feed CRM.
         */
        if (
            !in_array(
                $space,
                config(
                    'services.google_chat.allowed_spaces',
                    []
                ),
                true
            )
        ) {
            return;
        }

        /*
         * Google can return deleted resources during reconciliation.
         */
        if (
            !empty(
                $google['deleteTime']
            )
        ) {
            $this->deleted(
                $name,
                $google['deleteTime']
            );

            return;
        }

        /*
         * Never resurrect a message after deletion.
         */
        if (
            DB::table(
                'google_chat_tombstones'
            )
                ->where(
                    'message_name',
                    $name
                )
                ->exists()
        ) {
            return;
        }

        /*
         * CRM outbound messages use deterministic Google client IDs:
         *
         * client-{lead_chat_message_uuid}
         *
         * If Google later sends that same CRM-originated message back through
         * PubSub/reconciliation, ignore it. Otherwise CRM would display its
         * own outbound message twice.
         */
        $clientId =
            (string) (
                $google[
                    'clientAssignedMessageId'
                ]
                ??
                basename($name)
            );

        if (
            str_starts_with(
                $clientId,
                'client-'
            )
        ) {
            $crmMessageId =
                substr(
                    $clientId,
                    7
                );

            if (
                $crmMessageId !== ''
                &&
                LeadChatMessage::query()
                    ->whereKey(
                        $crmMessageId
                    )
                    ->where(
                        'source',
                        'crm'
                    )
                    ->exists()
            ) {
                return;
            }
        }

        /*
         * A thread is mandatory.
         *
         * The CRM task/message created in Google stores its thread name
         * on LeadChatConversation.
         *
         * Every human reply in that Google thread carries the same
         * thread.name and therefore maps back to the same lead.
         */
        $threadName =
            trim(
                (string)
                data_get(
                    $google,
                    'thread.name',
                    ''
                )
            );

        if (
            $threadName === ''
        ) {
            return;
        }

        /*
         * We import human messages only.
         *
         * This avoids Google system/bot resources being inserted as
         * Operations chat messages.
         */
        if (
            data_get(
                $google,
                'sender.type'
            )
            !==
            'HUMAN'
        ) {
            return;
        }

        /*
         * Find the exact CRM conversation associated with this
         * Google Space + Google thread.
         *
         * This is the core reply-mapping logic.
         */
        $conversation =
            LeadChatConversation::query()
                ->where(
                    'google_space_name',
                    $space
                )
                ->where(
                    'google_thread_name',
                    $threadName
                )
                ->first();

        if (
            !$conversation
        ) {
            /*
             * Do not guess which Lead owns an unknown Google thread.
             */
            return;
        }

        /*
         * Canonical Google identity.
         *
         * Example:
         * users/123456789...
         */
        $canonicalSender =
            trim(
                (string)
                data_get(
                    $google,
                    'sender.name',
                    ''
                )
            );

        if (
            $canonicalSender === ''
        ) {
            return;
        }

        /*
         * Confirm that the sender is currently a joined member of the
         * configured Google Space.
         *
         * Keep this validation because it is part of the existing
         * identity/security flow.
         */
        $members =
            collect(
                $this->client
                    ->listSpaceMembers(
                        $space
                    )
            );

        $senderIsJoined =
            $members->contains(
                function (
                    $membership
                ) use (
                    $canonicalSender
                ) {
                    return
                        (
                            $membership['state']
                            ??
                            ''
                        )
                        ===
                        'JOINED'
                        &&
                        data_get(
                            $membership,
                            'member.name'
                        )
                        ===
                        $canonicalSender;
                }
            );

        if (
            !$senderIsJoined
        ) {
            return;
        }

        /*
         * A verified mapping gives the Google sender its CRM User ID.
         *
         * If no verified identity exists we still store the Google message,
         * but sender_user_id remains NULL and google_sender_name is retained.
         *
         * This preserves existing behavior.
         */
        $identity =
            GoogleChatIdentity::query()
                ->where(
                    'google_user_name',
                    $canonicalSender
                )
                ->whereNotNull(
                    'verified_at'
                )
                ->first();

        $sender =
            trim(
                (string)
                data_get(
                    $google,
                    'sender.displayName',
                    ''
                )
            );

        if (
            $sender === ''
        ) {
            $sender =
                $canonicalSender;
        }

        /*
         * Google timestamps are normalized through the existing helper.
         */
        $created =
            GoogleChatTime::parse(
                $google['createTime']
                ??
                now()
            );

        $version =
            GoogleChatTime::parse(
                $google['lastUpdateTime']
                ??
                $google['createTime']
                ??
                now()
            );

        /*
         * Process message atomically.
         */
        DB::transaction(
            function () use (
                $conversation,
                $google,
                $name,
                $identity,
                $sender,
                $created,
                $version
            ) {
                /*
                 * Serialize updates for this lead conversation.
                 */
                LeadChatConversation::query()
                    ->whereKey(
                        $conversation->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Check tombstone again inside transaction to avoid a race
                 * where deletion arrives while this message is being fetched.
                 */
                if (
                    DB::table(
                        'google_chat_tombstones'
                    )
                        ->where(
                            'message_name',
                            $name
                        )
                        ->exists()
                ) {
                    return;
                }

                /*
                 * Each Google reply has its own unique message resource name.
                 *
                 * This gives us idempotency between:
                 * - PubSub
                 * - reconciliation
                 * - duplicate Workspace Events
                 */
                $existing =
                    LeadChatMessage::query()
                        ->where(
                            'google_message_name',
                            $name
                        )
                        ->lockForUpdate()
                        ->first();

                $existingVersion =
                    $existing
                        ?->google_event_version
                        ? GoogleChatTime::parse(
                            $existing
                                ->google_event_version
                        )
                        : $existing
                            ?->google_update_time;

                /*
                 * Never overwrite:
                 *
                 * - CRM-originated message
                 * - deleted Google message
                 * - same/newer Google version
                 */
                if (
                    $existing
                    &&
                    (
                        $existing->source === 'crm'
                        ||
                        $existing->deleted_at
                        ||
                        (
                            $existingVersion
                            &&
                            $existingVersion
                                ->gte(
                                    $version
                                )
                        )
                    )
                ) {
                    return;
                }

                $body =
                    trim(
                        (string) (
                            $google['text']
                            ??
                            $google['argumentText']
                            ??
                            ''
                        )
                    );

                if (
                    $body === ''
                ) {
                    $body =
                        '[Google Chat message]';
                }

                $attributes = [
                    'body' =>
                        $body,

                    'google_sender_name' =>
                        $sender,

                    'google_update_time' =>
                        $version,

                    'google_event_version' =>
                        $version
                            ->copy()
                            ->utc()
                            ->format(
                                'Y-m-d\TH:i:s.u\Z'
                            ),
                ];

                /*
                 * Google message already exists:
                 * treat it as an edit/update.
                 */
                if (
                    $existing
                ) {
                    LeadChatMessage::withoutEvents(
                        function () use (
                            $existing,
                            $attributes,
                            $version
                        ) {
                            $existing->update(
                                $attributes
                                +
                                [
                                    'edited_at' =>
                                        $version,
                                ]
                            );
                        }
                    );

                    return;
                }

                /*
                 * New Google Chat message/reply.
                 *
                 * Every reply in the matching Google thread becomes an
                 * independent LeadChatMessage under the SAME CRM conversation.
                 */
                $message =
                    LeadChatMessage::withoutEvents(
                        function () use (
                            $attributes,
                            $conversation,
                            $identity,
                            $name,
                            $created
                        ) {
                            return
                                LeadChatMessage::create(
                                    $attributes
                                    +
                                    [
                                        'id' =>
                                            (string)
                                            Str::uuid(),

                                        'conversation_id' =>
                                            $conversation->id,

                                        'lead_id' =>
                                            $conversation->lead_id,

                                        'sender_user_id' =>
                                            $identity?->user_id,

                                        'source' =>
                                            'google_chat',

                                        'message_type' =>
                                            LeadChatMessage::TYPE_TEXT,

                                        'google_message_name' =>
                                            $name,

                                        'google_create_time' =>
                                            $created,

                                        'google_sync_status' =>
                                            'not_required',
                                    ]
                                );
                        }
                    );

                /*
                 * Keep conversation activity timestamp accurate.
                 *
                 * Do not move last_message_at backwards if reconciliation
                 * imports an older Google reply.
                 */
                $lastMessageAt =
                    $conversation
                        ->last_message_at;

                $conversation->update([
                    'google_synced_at' =>
                        now(),

                    'last_message_at' =>
                        $lastMessageAt
                        &&
                        $lastMessageAt
                            ->gt(
                                $created
                            )
                            ? $lastMessageAt
                            : $created,
                ]);

                /*
                 * Preserve the existing CRM notification flow.
                 */
                $this->notifications
                    ->notifyGoogleMessage(
                        $conversation->lead,
                        $conversation,
                        $message,
                        $sender
                    );
            }
        );
    }

    /**
     * Mark a Google message deleted.
     *
     * Tombstones prevent stale PubSub/reconciliation data from
     * recreating a deleted Google message.
     */
    private function deleted(
        string $name,
        ?string $eventTime
    ): void {
        DB::transaction(
            function () use (
                $name,
                $eventTime
            ) {
                $deletedAt =
                    GoogleChatTime::parse(
                        $eventTime
                        ??
                        now()
                    );

                DB::table(
                    'google_chat_tombstones'
                )
                    ->insertOrIgnore([
                        'message_name' =>
                            $name,

                        'deleted_at' =>
                            $deletedAt,
                    ]);

                LeadChatMessage::query()
                    ->where(
                        'google_message_name',
                        $name
                    )
                    ->where(
                        'source',
                        'google_chat'
                    )
                    ->update([
                        'body' =>
                            null,

                        'deleted_at' =>
                            $deletedAt,

                        'google_update_time' =>
                            now(),
                    ]);
            }
        );
    }

    /**
     * Extract Google message resource names from both single and
     * batch Workspace Event payload formats.
     *
     * @return array<int, string>
     */
    public function messageNames(
        array $data
    ): array {
        $names = [
            data_get(
                $data,
                'message.name'
            ),
        ];

        foreach (
            $data['messages']
            ??
            []
            as
            $item
        ) {
            $name =
                data_get(
                    $item,
                    'message.name'
                )
                ?:
                data_get(
                    $item,
                    'name'
                );

            if (
                is_string($name)
                &&
                $name !== ''
            ) {
                $names[] =
                    $name;
            }
        }

        return
            array_values(
                array_unique(
                    array_filter(
                        $names,
                        static function (
                            $name
                        ) {
                            return
                                is_string($name)
                                &&
                                $name !== '';
                        }
                    )
                )
            );
    }
}