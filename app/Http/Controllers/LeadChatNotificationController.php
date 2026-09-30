<?php

namespace App\Http\Controllers;

use App\Models\LeadChatNotification;
use App\Models\User;

class LeadChatNotificationController extends Controller
{
    public function index()
    {
        $user =
            $this->user();


        $notifications =
            LeadChatNotification::query()

                ->with(
                    'lead.client'
                )

                ->where(
                    'user_id',
                    $user->id
                )

                ->whereNull(
                    'cleared_at'
                )

                ->latest(
                    'created_at'
                )

                ->limit(40)

                ->get();


        $unread =
            LeadChatNotification::query()

                ->where(
                    'user_id',
                    $user->id
                )

                ->whereNull(
                    'read_at'
                )

                ->whereNull(
                    'cleared_at'
                )

                ->count();


        return response()->json([

            'success' =>
                true,

            'unread_count' =>
                $unread,

            'notifications' =>
                $notifications->map(

                    fn ($notification) => [

                        'id' =>
                            $notification->id,

                        'title' =>
                            $notification->title,

                        'body' =>
                            $notification->body,

                        'customer_name' =>
                            $notification
                                ->lead
                                ?->client
                                ?->name
                            ??
                            'Lead',

                        'is_read' =>
                            $notification
                                ->read_at
                            !==
                            null,

                        'created_at' =>
                            optional(
                                $notification
                                    ->created_at
                            )
                                ->toIso8601String(),

                        'open_url' =>
                            route(
                                'admin.chat-notifications.open',
                                $notification
                            ),

                        'read_url' =>
                            route(
                                'admin.chat-notifications.read',
                                $notification
                            ),

                        'clear_url' =>
                            route(
                                'admin.chat-notifications.clear',
                                $notification
                            ),
                    ]
                ),
        ]);
    }


    public function open(
        LeadChatNotification $notification
    ) {

        $notification =
            $this->owned(
                $notification
            );


        $notification->update([

            'read_at' =>
                $notification
                    ->read_at
                ??
                now(),
        ]);


        $query =
            http_build_query([

                'chat' =>
                    1,

                'message' =>
                    $notification
                        ->message_id,

                'task' =>
                    $notification
                        ->task_id,
            ]);


        return redirect(

            route(

                'admin.leads.follow-up.create',

                $notification
                    ->lead_id
            )

            .

            '?'

            .

            $query

            .

            '#lead-chat-panel'
        );
    }


    public function markRead(
        LeadChatNotification $notification
    ) {

        $notification =
            $this->owned(
                $notification
            );


        if (
            !$notification
                ->read_at
        ) {

            $notification->update([

                'read_at' =>
                    now(),
            ]);
        }


        return response()->json([

            'success' =>
                true,
        ]);
    }


    public function markAllRead()
    {
        LeadChatNotification::query()

            ->where(
                'user_id',
                auth()->id()
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


        return response()->json([

            'success' =>
                true,
        ]);
    }


    public function clear(
        LeadChatNotification $notification
    ) {

        $notification =
            $this->owned(
                $notification
            );


        $notification->update([

            'read_at' =>
                $notification
                    ->read_at
                ??
                now(),

            'cleared_at' =>
                now(),
        ]);


        return response()->json([

            'success' =>
                true,
        ]);
    }


    public function clearAll()
    {
        LeadChatNotification::query()

            ->where(
                'user_id',
                auth()->id()
            )

            ->whereNull(
                'cleared_at'
            )

            ->update([

                'read_at' =>
                    now(),

                'cleared_at' =>
                    now(),
            ]);


        return response()->json([

            'success' =>
                true,
        ]);
    }


    private function owned(
        LeadChatNotification $notification
    ): LeadChatNotification {

        abort_unless(

            (string)
            $notification->user_id

            ===

            (string)
            auth()->id(),

            404
        );


        return
            $notification;
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