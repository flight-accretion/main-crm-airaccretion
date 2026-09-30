<?php

namespace App\Services\LeadChat;

use App\Models\Lead;
use App\Models\LeadChatConversation;
use App\Models\LeadChatMessage;
use App\Models\LeadChatNotification;
use App\Models\LeadChatTask;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadChatNotificationService
{
    public function notifyMessage(
        Lead $lead,
        LeadChatConversation $conversation,
        LeadChatMessage $message,
        User $sender
    ): void {

        $recipients =
            $this->counterpartUsers(
                $lead,
                $sender
            );


        $isReply =
            !empty(
                $message
                    ->reply_to_message_id
            );


        $title =
            $isReply
                ? 'New reply from ' . $sender->name
                : 'New message from ' . $sender->name;


        $body =
            trim(
                (string)
                $message->body
            );


        if (
            $body === ''
        ) {

            $body =
                'Sent an attachment.';
        }


        foreach (
            $recipients
            as
            $recipient
        ) {

            LeadChatNotification::create([

                'user_id' =>
                    $recipient->id,

                'lead_id' =>
                    $lead->id,

                'conversation_id' =>
                    $conversation->id,

                'message_id' =>
                    $message->id,

                'type' =>
                    $isReply
                        ? 'reply'
                        : 'new_message',

                'title' =>
                    $title,

                'body' =>
                    Str::limit(
                        $body,
                        180
                    ),
            ]);
        }
    }


    public function notifyTaskCreated(
        Lead $lead,
        LeadChatConversation $conversation,
        LeadChatTask $task,
        User $creator
    ): void {

        if (
            $task->assigned_role
            ===
            'operations'
        ) {

            $recipients =
                $this->operationsUsers();

        } else {

            $recipients =
                $this->salesOwner(
                    $lead
                );
        }


        foreach (
            $recipients
            ->reject(
                fn ($user) =>
                    (string) $user->id
                    ===
                    (string) $creator->id
            )
            as
            $recipient
        ) {

            LeadChatNotification::create([

                'user_id' =>
                    $recipient->id,

                'lead_id' =>
                    $lead->id,

                'conversation_id' =>
                    $conversation->id,

                'task_id' =>
                    $task->id,

                'type' =>
                    $task->priority
                    ===
                    LeadChatTask::PRIORITY_URGENT

                        ? 'urgent_task'
                        : 'task_created',

                'title' =>
                    (
                        $task->priority
                        ===
                        LeadChatTask::PRIORITY_URGENT

                        ? 'Urgent task: '
                        : 'New task: '
                    )
                    .
                    $task->title,

                'body' =>
                    $creator->name
                    .
                    ' created this task.',
            ]);
        }
    }


    private function counterpartUsers(
        Lead $lead,
        User $sender
    ): Collection {

        $role =
            $sender
                ->userType
                ?->user_type;


        if (
            $role
            ===
            UserType::SALES_EXECUTIVE
        ) {

            return
                $this
                    ->operationsUsers();
        }


        if (
            in_array(
                $role,
                UserType::OPERATIONS_ROLES,
                true
            )
        ) {

            return
                $this->salesOwner(
                    $lead
                );
        }


        return collect();
    }


    private function operationsUsers(): Collection
    {
        return User::query()

            ->where(
                'status',
                1
            )

            ->whereHas(
                'userType',
                function ($query) {

                    $query->whereIn(
                        'user_type',
                        UserType::OPERATIONS_ROLES
                    );
                }
            )

            ->get();
    }


    private function salesOwner(
        Lead $lead
    ): Collection {

        if (
            !$lead
                ->representative_user_id
        ) {

            return collect();
        }


        return User::query()

            ->with(
                'userType'
            )

            ->where(
                'id',
                $lead
                    ->representative_user_id
            )

            ->where(
                'status',
                1
            )

            ->get()

            ->filter(
                fn ($user) =>

                    $user
                        ->userType
                        ?->user_type

                    ===

                    UserType::SALES_EXECUTIVE
            )

            ->values();
    }

    public function notifyGoogleMessage(
    \App\Models\Lead $lead,
    \App\Models\LeadChatConversation $conversation,
    \App\Models\LeadChatMessage $message,
    string $senderName
): void {

    if (
        !$lead
            ->representative_user_id
    ) {

        return;
    }


    $recipient =
        \App\Models\User::query()

            ->with(
                'userType'
            )

            ->where(
                'id',
                $lead
                    ->representative_user_id
            )

            ->where(
                'status',
                1
            )

            ->first();


    if (
        !$recipient
    ) {

        return;
    }


    if (
        $recipient
            ->userType
            ?->user_type

        !==

        \App\Models\UserType::SALES_EXECUTIVE
    ) {

        return;
    }


    \App\Models\LeadChatNotification::create([

        'user_id' =>
            $recipient->id,

        'lead_id' =>
            $lead->id,

        'conversation_id' =>
            $conversation->id,

        'message_id' =>
            $message->id,

        'type' =>
            'new_message',

        'title' =>
            'Google Chat reply from '
            .
            $senderName,

        'body' =>
            \Illuminate\Support\Str::limit(

                trim(
                    (string)
                    $message->body
                ),

                180
            ),
    ]);
}

}