<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadChatAttachment;
use App\Models\LeadChatConversation;
use App\Models\LeadChatMessage;
use App\Models\LeadChatNotification;
use App\Models\LeadChatReaction;
use App\Models\LeadChatRead;
use App\Models\LeadChatTask;
use App\Models\User;
use App\Services\LeadChat\LeadChatAccessService;
use App\Services\LeadChat\LeadChatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class LeadChatController extends Controller
{
    public function __construct(
        private LeadChatAccessService $access,
        private LeadChatNotificationService $notifications
    ) {
    }


    public function index(
        Lead $lead
    ) {

        $user =
            $this->user();


        $this->access
            ->authorize(
                $user,
                $lead
            );


        $conversation =
            $this->conversation(
                $lead
            );


        $this->markRead(
            $conversation,
            $user
        );


        $messageLimit =
            max(
                1,
                min(
                    200,
                    (int) config(
                        'crm.chat_page_size',
                        50
                    )
                )
            );


        $messages =
            LeadChatMessage::query()

                ->with([
                    'conversation',

                    'sender.userType',

                   'replyTo.sender.userType',

                    'attachments',

                    'task.creator',

                    'task.completedBy',

                    'reactions.user',
                ])

                ->where(
                    'conversation_id',
                    $conversation->id
                )

                ->latest(
                    'created_at'
                )

                ->limit(
                    $messageLimit
                )

                ->get()

                ->sortBy(
                    'created_at'
                )

                ->values();


        return response()->json([

            'success' =>
                true,

            'conversation_id' =>
                $conversation->id,

            'google_connection' => [
                'google_connection_status' => $conversation->google_connection_status ?? 'unmapped',
                'google_space_name' => $conversation->google_space_name,
                'google_thread_name' => $conversation->google_thread_name,
                'operations_user_id' => $conversation->operations_user_id,
            ],

            'active_tasks' =>
                LeadChatTask::query()

                    ->where(
                        'conversation_id',
                        $conversation->id
                    )

                    ->where(
                        'status',
                        LeadChatTask::STATUS_ACTIVE
                    )

                    ->count(),

            'pinned_count' =>
                LeadChatMessage::query()

                    ->where(
                        'conversation_id',
                        $conversation->id
                    )

                    ->where(
                        'is_pinned',
                        true
                    )

                    ->whereNull(
                        'deleted_at'
                    )

                    ->count(),

            'messages' =>
                $messages->map(

                    fn ($message) =>

                        $this->messagePayload(
                            $message,
                            $user
                        )
                ),
        ]);
    }


public function store(
    Request $request,
    Lead $lead
) {
    $user =
        $this->user();


    $this->access
        ->authorize(
            $user,
            $lead
        );


    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'txt',
    ];


    $data =
        $request->validate([

            'message' =>
                'nullable|string|max:5000',

            'reply_to_message_id' =>
                'nullable|uuid',

            'attachments' =>
                'nullable|array|max:5',

            'attachments.*' => [

                'file',

                'max:10240',

                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt',

                function (
                    $attribute,
                    $file,
                    $fail
                ) use (
                    $allowedExtensions
                ) {

                    $extension =
                        strtolower(
                            (string)
                            $file
                                ->getClientOriginalExtension()
                        );


                    /*
                     * Laravel "mimes" validates file content.
                     * Also validate the ORIGINAL filename extension.
                     */

                    if (
                        !in_array(
                            $extension,
                            $allowedExtensions,
                            true
                        )
                    ) {

                        $fail(
                            'Unsupported attachment file extension.'
                        );
                    }
                },
            ],
        ]);


    $body =
        trim(
            (string)
            (
                $data['message']
                ??
                ''
            )
        );


    $files =
        $request->file(
            'attachments',
            []
        );


    if (
        $body === ''
        &&
        empty(
            $files
        )
    ) {

        throw ValidationException::withMessages([

            'message' =>
                'Type a message or attach a file.',
        ]);
    }


    $conversation =
        $this->conversation(
            $lead
        );


    $replyTo =
        null;


    if (
        !empty(
            $data[
                'reply_to_message_id'
            ]
        )
    ) {

        $replyTo =
            LeadChatMessage::query()

                ->where(
                    'id',
                    $data[
                        'reply_to_message_id'
                    ]
                )

                ->where(
                    'conversation_id',
                    $conversation->id
                )

                ->firstOrFail();
    }


    /*
     * Keep physical files so we can remove them
     * if DB transaction rolls back.
     */

    $storedFiles =
        [];


    try {

        $message =
            DB::transaction(
                function () use (
                    $lead,
                    $user,
                    $conversation,
                    $body,
                    $replyTo,
                    $files,
                    &$storedFiles
                ) {

                    $message =
                        LeadChatMessage::create([

                            'conversation_id' =>
                                $conversation->id,

                            'lead_id' =>
                                $lead->id,

                            'sender_user_id' =>
                                $user->id,

                            'message_type' =>
                                LeadChatMessage::TYPE_TEXT,

                            'body' =>
                                $body !== ''
                                    ? $body
                                    : null,

                            'reply_to_message_id' =>
                                $replyTo?->id,
                        ]);


                    foreach (
                        $files
                        as
                        $file
                    ) {

                        $extension =
                            strtolower(
                                (string)
                                $file
                                    ->getClientOriginalExtension()
                            );


                        $storedName =
                            (string)
                            Str::uuid()

                            .

                            (
                                $extension !== ''
                                    ? '.' . $extension
                                    : ''
                            );


                        $directory =
                            'lead-chat/'
                            .
                            $lead->id
                            .
                            '/'
                            .
                            $message->id;


                        $path =
                            $file->storeAs(

                                $directory,

                                $storedName,

                                'local'
                            );


                        $storedFiles[] = [

                            'disk' =>
                                'local',

                            'path' =>
                                $path,
                        ];


                        LeadChatAttachment::create([

                            'message_id' =>
                                $message->id,

                            'uploaded_by' =>
                                $user->id,

                            'file_name' =>
                                $file
                                    ->getClientOriginalName(),

                            'disk' =>
                                'local',

                            'path' =>
                                $path,

                            'mime_type' =>
                                $file
                                    ->getMimeType(),

                            'size_bytes' =>
                                $file
                                    ->getSize(),
                        ]);
                    }


                    $conversation->update([

                        'last_message_at' =>
                            now(),
                    ]);


                    /*
                     * IMPORTANT:
                     *
                     * Notification is inside SAME DB transaction.
                     * If this throws, message/attachment DB rows roll back.
                     */

                    $this
                        ->notifications
                        ->notifyMessage(

                            $lead,

                            $conversation,

                            $message,

                            $user
                        );


                    return $message;
                }
            );


    } catch (\Throwable $e) {

        /*
         * DB rollback cannot rollback physical files.
         * Clean those manually.
         */

        foreach (
            $storedFiles
            as
            $storedFile
        ) {

            try {

                Storage::disk(
                    $storedFile['disk']
                )
                    ->delete(
                        $storedFile['path']
                    );

            } catch (\Throwable $cleanupException) {

                /*
                 * Do not hide original exception.
                 */
            }
        }


        throw $e;
    }


    return response()->json([

        'success' =>
            true,

        'message_id' =>
            $message->id,
    ]);
}


    public function update(
        Request $request,
        LeadChatMessage $message
    ) {
        abort_if($message->source === 'google_chat', 403, 'Manage this message in Google Chat.');
        abort_if($message->conversation?->google_template_message_id === $message->id, 422, 'The connection message is preserved.');

        $user =
            $this->user();


        $message->loadMissing(
            'lead'
        );


        $this->access
            ->authorize(
                $user,
                $message->lead
            );


        abort_unless(

            (string)
            $message->sender_user_id

            ===

            (string)
            $user->id,

            403
        );


        abort_if(
            $message->deleted_at,
            422
        );


        abort_unless(

            $message->message_type
            ===
            LeadChatMessage::TYPE_TEXT,

            422
        );


        $data =
            $request->validate([

                'message' =>
                    'required|string|max:5000',
            ]);


        $message->update([

            'body' =>
                trim(
                    $data['message']
                ),

            'edited_at' =>
                now(),
        ]);


        return response()->json([

            'success' =>
                true,
        ]);
    }


    public function destroy(
        LeadChatMessage $message
    ) {
        abort_if($message->source === 'google_chat', 403, 'Manage this message in Google Chat.');
        abort_if($message->conversation?->google_template_message_id === $message->id, 422, 'The connection message is preserved.');

        $user =
            $this->user();


        $message->loadMissing(
            'lead'
        );


        $this->access
            ->authorize(
                $user,
                $message->lead
            );


        abort_unless(

            (string)
            $message->sender_user_id

            ===

            (string)
            $user->id,

            403
        );


        abort_unless(

            $message->message_type
            ===
            LeadChatMessage::TYPE_TEXT,

            422
        );


        $message->update([

            'body' =>
                null,

            'deleted_at' =>
                now(),

            'edited_at' =>
                null,
        ]);


        return response()->json([

            'success' =>
                true,
        ]);
    }


    public function togglePin(
        LeadChatMessage $message
    ) {

        $user =
            $this->user();


        $message->loadMissing(
            'lead'
        );


        $this->access
            ->authorize(
                $user,
                $message->lead
            );


        $pin =
            !$message
                ->is_pinned;


        $message->update([

            'is_pinned' =>
                $pin,

            'pinned_by' =>
                $pin
                    ? $user->id
                    : null,

            'pinned_at' =>
                $pin
                    ? now()
                    : null,
        ]);


        return response()->json([

            'success' =>
                true,

            'is_pinned' =>
                $pin,
        ]);
    }


    public function react(
        Request $request,
        LeadChatMessage $message
    ) {

        $user =
            $this->user();


        $message->loadMissing(
            'lead'
        );


        $this->access
            ->authorize(
                $user,
                $message->lead
            );


        $data =
            $request->validate([

                'reaction' => [

                    'required',

                    Rule::in([

                        '👍',

                        '❤️',

                        '✅',

                        '🙏',

                        '😂',
                    ]),
                ],
            ]);


        $existing =
            LeadChatReaction::query()

                ->where(
                    'message_id',
                    $message->id
                )

                ->where(
                    'user_id',
                    $user->id
                )

                ->where(
                    'reaction',
                    $data['reaction']
                )

                ->first();


        if (
            $existing
        ) {

            $existing->delete();

        } else {

            LeadChatReaction::create([

                'message_id' =>
                    $message->id,

                'user_id' =>
                    $user->id,

                'reaction' =>
                    $data['reaction'],
            ]);
        }


        return response()->json([

            'success' =>
                true,
        ]);
    }


  public function createTask(
    Request $request,
    Lead $lead
) {
    $user =
        $this->user();


    $this->access
        ->authorize(
            $user,
            $lead
        );


    $data =
        $request->validate([

            'title' =>
                'required|string|max:255',

            'description' =>
                'nullable|string|max:3000',

            'priority' => [

                'required',

                Rule::in([
                    LeadChatTask::PRIORITY_LOW,
                    LeadChatTask::PRIORITY_NORMAL,
                    LeadChatTask::PRIORITY_HIGH,
                    LeadChatTask::PRIORITY_URGENT,
                ]),
            ],

            'due_at' =>
                'nullable|date',
        ]);


    $conversation =
        $this->conversation(
            $lead
        );


    $currentRole =
        $this->access
            ->roleGroup(
                $user
            );


    /*
     * Sales creates for Operations.
     * Operations creates for Sales.
     */

    $assignedRole =
        $currentRole === 'sales'
            ? 'operations'
            : 'sales';


    [$task, $message] =
        DB::transaction(
            function () use (
                $lead,
                $user,
                $conversation,
                $data,
                $assignedRole
            ) {

                $task =
                    LeadChatTask::create([

                        'conversation_id' =>
                            $conversation->id,

                        'lead_id' =>
                            $lead->id,

                        'title' =>
                            trim(
                                $data['title']
                            ),

                        'description' =>
                            trim(
                                (string)
                                (
                                    $data['description']
                                    ??
                                    ''
                                )
                            )
                            ?: null,

                        'priority' =>
                            $data['priority'],

                        'status' =>
                            LeadChatTask::STATUS_ACTIVE,

                        'assigned_role' =>
                            $assignedRole,

                        'created_by' =>
                            $user->id,

                        'due_at' =>
                            $data['due_at']
                            ??
                            null,
                    ]);


                $message =
                    LeadChatMessage::create([

                        'conversation_id' =>
                            $conversation->id,

                        'lead_id' =>
                            $lead->id,

                        'sender_user_id' =>
                            $user->id,

                        'message_type' =>
                            LeadChatMessage::TYPE_TASK,

                        'body' =>
                            $task->title,

                        'task_id' =>
                            $task->id,
                    ]);


                $conversation->update([

                    'last_message_at' =>
                        now(),
                ]);


                /*
                 * Same transaction.
                 */

                $this
                    ->notifications
                    ->notifyTaskCreated(

                        $lead,

                        $conversation,

                        $task,

                        $user
                    );


                return [

                    $task,

                    $message,
                ];
            }
        );


    return response()->json([

        'success' =>
            true,

        'task_id' =>
            $task->id,

        'message_id' =>
            $message->id,
    ]);
}


   public function completeTask(
    LeadChatTask $task
) {
    $user =
        $this->user();


    $result =
        DB::transaction(
            function () use (
                $task,
                $user
            ) {

                /*
                 * Lock prevents two simultaneous completion requests
                 * from both producing an audit message.
                 */

                $lockedTask =
                    LeadChatTask::query()

                        ->where(
                            'id',
                            $task->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                $lockedTask->loadMissing(
                    'lead'
                );


                $this->access
                    ->authorize(
                        $user,
                        $lockedTask->lead
                    );


                /*
                 * Idempotent:
                 * already completed = no duplicate system message.
                 */

                if (
                    $lockedTask->status
                    ===
                    LeadChatTask::STATUS_COMPLETED
                ) {

                    return [

                        'changed' =>
                            false,

                        'task' =>
                            $lockedTask,
                    ];
                }


                $lockedTask->update([

                    'status' =>
                        LeadChatTask::STATUS_COMPLETED,

                    'completed_by' =>
                        $user->id,

                    'completed_at' =>
                        now(),
                ]);


                LeadChatMessage::create([

                    'conversation_id' =>
                        $lockedTask
                            ->conversation_id,

                    'lead_id' =>
                        $lockedTask
                            ->lead_id,

                    'sender_user_id' =>
                        $user->id,

                    'message_type' =>
                        LeadChatMessage::TYPE_SYSTEM,

                    'body' =>
                        $user->name
                        .
                        ' completed task: '
                        .
                        $lockedTask->title,

                    'task_id' =>
                        $lockedTask->id,
                ]);


                LeadChatConversation::query()

                    ->where(
                        'id',
                        $lockedTask
                            ->conversation_id
                    )

                    ->update([

                        'last_message_at' =>
                            now(),
                    ]);


                /*
                 * Preserve task-status notification
                 * if your service already implements it.
                 */

                if (
                    method_exists(
                        $this->notifications,
                        'notifyTaskStatus'
                    )
                ) {

                    $conversation =
                        LeadChatConversation::query()

                            ->where(
                                'id',
                                $lockedTask
                                    ->conversation_id
                            )

                            ->first();


                    $this
                        ->notifications
                        ->notifyTaskStatus(

                            $lockedTask->lead,

                            $conversation,

                            $lockedTask,

                            $user,

                            'task_completed'
                        );
                }


                return [

                    'changed' =>
                        true,

                    'task' =>
                        $lockedTask,
                ];
            }
        );


    return response()->json([

        'success' =>
            true,

        'changed' =>
            $result['changed'],

        'status' =>
            LeadChatTask::STATUS_COMPLETED,
    ]);
}


 public function reopenTask(
    LeadChatTask $task
) {
    $user =
        $this->user();


    $result =
        DB::transaction(
            function () use (
                $task,
                $user
            ) {

                $lockedTask =
                    LeadChatTask::query()

                        ->where(
                            'id',
                            $task->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                $lockedTask->loadMissing(
                    'lead'
                );


                $this->access
                    ->authorize(
                        $user,
                        $lockedTask->lead
                    );


                /*
                 * Idempotent:
                 * already active = no duplicate reopen message.
                 */

                if (
                    $lockedTask->status
                    ===
                    LeadChatTask::STATUS_ACTIVE
                ) {

                    return [

                        'changed' =>
                            false,

                        'task' =>
                            $lockedTask,
                    ];
                }


                $lockedTask->update([

                    'status' =>
                        LeadChatTask::STATUS_ACTIVE,

                    'completed_by' =>
                        null,

                    'completed_at' =>
                        null,
                ]);


                LeadChatMessage::create([

                    'conversation_id' =>
                        $lockedTask
                            ->conversation_id,

                    'lead_id' =>
                        $lockedTask
                            ->lead_id,

                    'sender_user_id' =>
                        $user->id,

                    'message_type' =>
                        LeadChatMessage::TYPE_SYSTEM,

                    'body' =>
                        $user->name
                        .
                        ' reopened task: '
                        .
                        $lockedTask->title,

                    'task_id' =>
                        $lockedTask->id,
                ]);


                LeadChatConversation::query()

                    ->where(
                        'id',
                        $lockedTask
                            ->conversation_id
                    )

                    ->update([

                        'last_message_at' =>
                            now(),
                    ]);


                if (
                    method_exists(
                        $this->notifications,
                        'notifyTaskStatus'
                    )
                ) {

                    $conversation =
                        LeadChatConversation::query()

                            ->where(
                                'id',
                                $lockedTask
                                    ->conversation_id
                            )

                            ->first();


                    $this
                        ->notifications
                        ->notifyTaskStatus(

                            $lockedTask->lead,

                            $conversation,

                            $lockedTask,

                            $user,

                            'task_reopened'
                        );
                }


                return [

                    'changed' =>
                        true,

                    'task' =>
                        $lockedTask,
                ];
            }
        );


    return response()->json([

        'success' =>
            true,

        'changed' =>
            $result['changed'],

        'status' =>
            LeadChatTask::STATUS_ACTIVE,
    ]);
}


    public function attachment(
        LeadChatAttachment $attachment
    ) {

        $user =
            $this->user();


        $attachment->loadMissing(
            'message.lead'
        );


        $lead =
            $attachment
                ->message
                ?->lead;


        abort_unless(
            $lead,
            404
        );


        $this->access
            ->authorize(
                $user,
                $lead
            );


        abort_unless(

            Storage::disk(
                $attachment->disk
            )
                ->exists(
                    $attachment->path
                ),

            404
        );


        return response()->file(

            Storage::disk(
                $attachment->disk
            )
                ->path(
                    $attachment->path
                ),

            [

                'Content-Type' =>
                    $attachment->mime_type
                    ?:
                    'application/octet-stream',
            ]
        );
    }


    private function conversation(
        Lead $lead
    ): LeadChatConversation {

        return
            LeadChatConversation::query()

                ->firstOrCreate([

                    'lead_id' =>
                        $lead->id,
                ]);
    }


    private function markRead(
        LeadChatConversation $conversation,
        User $user
    ): void {

        $latestMessage =
            LeadChatMessage::query()

                ->where(
                    'conversation_id',
                    $conversation->id
                )

                ->latest(
                    'created_at'
                )

                ->first();


        LeadChatRead::query()

            ->updateOrCreate(

                [

                    'conversation_id' =>
                        $conversation->id,

                    'user_id' =>
                        $user->id,
                ],

                [

                    'last_read_message_id' =>
                        $latestMessage?->id,

                    'last_read_at' =>
                        now(),
                ]
            );


        /*
         * Opening the Lead chat marks its
         * notifications read, not cleared.
         */

        LeadChatNotification::query()

            ->where(
                'user_id',
                $user->id
            )

            ->where(
                'conversation_id',
                $conversation->id
            )

            ->whereNull(
                'read_at'
            )

            ->whereNull(
                'cleared_at'
            )

            ->update([

                'read_at' =>
                    now(),
            ]);
    }


   private function messagePayload(
    LeadChatMessage $message,
    User $viewer
): array {

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | CRM-origin message:
    |   sender_user_id points to a CRM User.
    |
    | Google-origin message:
    |   sender_user_id can be NULL.
    |   google_sender_name contains the Google Chat user.
    |
    */

    $isGoogleMessage =
        $message->source
        ===
        'google_chat';


    $senderName =
        $isGoogleMessage

            ? (
                $message
                    ->google_sender_name

                ?:

                'Operations'
            )

            : (
                $message
                    ->sender
                    ?->name

                ?:

                'System'
            );


    $senderRole =
        $isGoogleMessage

            ? 'Google Chat'

            : $message
                ->sender
                ?->userType
                ?->user_type;


    /*
    |--------------------------------------------------------------------------
    | Reply Sender
    |--------------------------------------------------------------------------
    |
    | A CRM message can reply to a Google Chat imported message,
    | therefore replyTo->sender can also be NULL.
    |
    */

    $replySenderName =
        null;


    if (
        $message->replyTo
    ) {

        $replySenderName =

            $message
                ->replyTo
                ->source
            ===
            'google_chat'

                ? (
                    $message
                        ->replyTo
                        ->google_sender_name

                    ?:

                    'Operations'
                )

                : (
                    $message
                        ->replyTo
                        ->sender
                        ?->name

                    ?:

                    'System'
                );
    }


    return [

        /*
        |--------------------------------------------------------------------------
        | Basic
        |--------------------------------------------------------------------------
        */

        'id' =>
            $message->id,


        'type' =>
            $message->message_type,


        'message_type' =>
            $message->message_type,


        'source' =>
            $message->source
            ?:
            'crm',


        /*
        |--------------------------------------------------------------------------
        | Message body
        |--------------------------------------------------------------------------
        */

        'body' =>
            $message->deleted_at

                ? 'This message was deleted.'

                : (
                    $message->body
                    ??
                    ''
                ),


        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        'sender' => [

            'id' =>
                $message
                    ->sender
                    ?->id,

            'name' =>
                $senderName,

            'role' =>
                $senderRole,
        ],


        /*
         * Backward-compatible fields used by current Blade JS.
         */

        'sender_name' =>
            $senderName,


        'sender_role' =>
            $senderRole,


        /*
        |--------------------------------------------------------------------------
        | Mine
        |--------------------------------------------------------------------------
        |
        | Google-origin messages can never be "mine" because
        | sender_user_id is NULL.
        |
        */

        'mine' =>

            !$isGoogleMessage

            &&

            (
                (string)
                $message
                    ->sender_user_id

                ===

                (string)
                $viewer->id
            ),


        'is_mine' =>

            !$isGoogleMessage

            &&

            (
                (string)
                $message
                    ->sender_user_id

                ===

                (string)
                $viewer->id
            ),


        /*
        |--------------------------------------------------------------------------
        | State
        |--------------------------------------------------------------------------
        */

        'deleted' =>
            $message->deleted_at
            !==
            null,


        'is_deleted' =>
            $message->deleted_at
            !==
            null,


        'edited' =>
            $message->edited_at
            !==
            null,


        'is_edited' =>
            $message->edited_at
            !==
            null,


        'pinned' =>
            (bool)
            $message->is_pinned,


        'is_pinned' =>
            (bool)
            $message->is_pinned,


        /*
        |--------------------------------------------------------------------------
        | Google sync status
        |--------------------------------------------------------------------------
        */

        'google_sync_status' =>
            $message
                ->google_sync_status,


        /*
         * Do NOT expose google_sync_error to ordinary browser UI.
         * It can contain technical API information.
         */


        /*
        |--------------------------------------------------------------------------
        | Timestamp
        |--------------------------------------------------------------------------
        |
        | For imported Google messages use Google's create time
        | when available.
        |
        */

        'created_at' =>

            optional(

                $isGoogleMessage
                &&
                $message
                    ->google_create_time

                    ? $message
                        ->google_create_time

                    : $message
                        ->created_at
            )
                ->toIso8601String(),


        /*
        |--------------------------------------------------------------------------
        | Reply
        |--------------------------------------------------------------------------
        */

        'reply_to' =>

            $message->replyTo

                ? [

                    'id' =>
                        $message
                            ->replyTo
                            ->id,


                    'sender' =>
                        $replySenderName,


                    'sender_name' =>
                        $replySenderName,


                    'body' =>
                        Str::limit(

                            (
                                $message
                                    ->replyTo
                                    ->deleted_at

                                    ? 'This message was deleted.'

                                    : (
                                        $message
                                            ->replyTo
                                            ->body
                                        ??
                                        ''
                                    )
                            ),

                            140
                        ),
                ]

                : null,


        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */

        'attachments' =>

            $message
                ->attachments
                ->map(

                    fn ($attachment) => [

                        'id' =>
                            $attachment->id,


                        'name' =>
                            $attachment
                                ->file_name,


                        'file_name' =>
                            $attachment
                                ->file_name,


                        'mime' =>
                            $attachment
                                ->mime_type,


                        'mime_type' =>
                            $attachment
                                ->mime_type,


                        'size' =>
                            $attachment
                                ->size_bytes,


                        'size_bytes' =>
                            $attachment
                                ->size_bytes,


                        'url' =>
                            route(

                                'admin.lead-chat.attachment',

                                $attachment
                            ),
                    ]
                )
                ->values(),


        /*
        |--------------------------------------------------------------------------
        | Task
        |--------------------------------------------------------------------------
        */

        'task' =>

            $message->task

                ? [

                    'id' =>
                        $message
                            ->task
                            ->id,


                    'title' =>
                        $message
                            ->task
                            ->title,


                    'description' =>
                        $message
                            ->task
                            ->description,


                    'priority' =>
                        $message
                            ->task
                            ->priority,


                    'status' =>
                        $message
                            ->task
                            ->status,


                    'assigned_role' =>
                        $message
                            ->task
                            ->assigned_role,


                    'due_at' =>
                        optional(
                            $message
                                ->task
                                ->due_at
                        )
                            ->toIso8601String(),
                ]

                : null,


        /*
        |--------------------------------------------------------------------------
        | Reactions
        |--------------------------------------------------------------------------
        */

        'reactions' =>

            $message
                ->reactions
                ->groupBy(
                    'reaction'
                )
                ->map(

                    fn (
                        $items,
                        $reaction
                    ) => [

                        'reaction' =>
                            $reaction,


                        'count' =>
                            $items->count(),


                        'mine' =>
                            $items->contains(

                                fn ($item) =>

                                    (string)
                                    $item
                                        ->user_id

                                    ===

                                    (string)
                                    $viewer
                                        ->id
                            ),
                    ]
                )
                ->values(),


        /*
        |--------------------------------------------------------------------------
        | UI permissions
        |--------------------------------------------------------------------------
        |
        | Only original CRM sender can edit/delete CRM messages.
        | Google-origin messages should be managed from Google Chat.
        |
        */

        'can_edit' =>

            $message->conversation?->google_template_message_id !== $message->id &&

            !$isGoogleMessage

            &&

            !$message->deleted_at

            &&

            $message->message_type
            ===
            LeadChatMessage::TYPE_TEXT

            &&

            (string)
            $message->sender_user_id

            ===

            (string)
            $viewer->id,


        'can_delete' =>

            $message->conversation?->google_template_message_id !== $message->id &&

            !$isGoogleMessage

            &&

            !$message->deleted_at

            &&

            $message->message_type
            ===
            LeadChatMessage::TYPE_TEXT

            &&

            (string)
            $message->sender_user_id

            ===

            (string)
            $viewer->id,
    ];
}


    private function user(): User
    {
        /** @var User|null $user */

        $user =
            auth()->user();


        abort_unless(
            $user,
            401
        );


        return $user;
    }
}
