<?php

namespace App\Http\Controllers;

use App\Models\UserType;
use App\Models\WhatsAppConversation;
use App\Services\WhatsAppConversationVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppInboxController extends Controller
{
    public function index(
        WhatsAppConversationVisibilityService $visibility
    ) {
        $this->ensureInboxUser();

        return view(
            'admin.pages.whatsapp.inbox',
            [
                'agents' =>
                    $visibility->agentFilterUsers(
                        Auth::user()
                    ),
            ]
        );
    }

    public function conversations(
        Request $request,
        WhatsAppConversationVisibilityService $visibility
    ) {
        $this->ensureInboxUser();

        $user = Auth::user();
        $query = $visibility
            ->visibleConversationsQuery($user);

        $search = trim((string) $request->query('search'));

        if ($search !== '') {
            $query->whereHas(
                'contact',
                function ($contactQuery) use ($search) {
                    $like = '%' . $search . '%';

                    $contactQuery
                        ->where('name', 'LIKE', $like)
                        ->orWhere(
                            'normalized_phone',
                            'LIKE',
                            $like
                        )
                        ->orWhere(
                            'raw_phone',
                            'LIKE',
                            $like
                        );
                }
            );
        }

        if ($request->query('status') === 'unread') {
            $query->where('unread_count', '>', 0);
        }

        if ($request->query('status') === 'read') {
            $query->where('unread_count', 0);
        }

        if ($request->filled('agent_id')) {
            $allowedAgentIds = $visibility
                ->agentFilterUsers($user)
                ->pluck('id')
                ->all();

            if (
                in_array(
                    $request->query('agent_id'),
                    $allowedAgentIds,
                    true
                )
            ) {
                $query->where(
                    'assigned_user_id',
                    $request->query('agent_id')
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $limit = min(
            100,
            max(10, (int) $request->query('limit', 50))
        );

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' =>
                $conversations->map(function ($conversation) {
                    return $this->conversationPayload(
                        $conversation
                    );
                })->values(),
        ]);
    }

    public function messages(
        string $conversation,
        WhatsAppConversationVisibilityService $visibility
    ) {
        $this->ensureInboxUser();

        abort_unless(
            $visibility->canAccessConversation(
                Auth::user(),
                $conversation
            ),
            403
        );

        $conversationModel = WhatsAppConversation::query()
            ->with([
                'contact',
                'assignedUser',
                'messages' => function ($query) {
                    $query
                        ->with('sender')
                        ->orderBy('message_at')
                        ->orderBy('created_at');
                },
            ])
            ->findOrFail($conversation);

        return response()->json([
            'conversation' =>
                $this->conversationPayload(
                    $conversationModel
                ),
            'read_cleared' => false,
            'messages' =>
                $conversationModel
                    ->messages
                    ->map(function ($message) use (
                        $conversationModel
                    ) {
                        return [
                            'id' => $message->id,
                            'direction' => $message->direction,
                            'sender_type' => $message->sender_type,
                            'sender_name' =>
                                $message->direction === 'incoming'
                                    ? (
                                        optional(
                                            $conversationModel->contact
                                        )->name
                                        ?: 'Customer'
                                    )
                                    : (
                                        optional(
                                            $message->sender
                                        )->name
                                        ?: optional(
                                            $conversationModel
                                                ->assignedUser
                                        )->name
                                        ?: 'Agent'
                                    ),
                            'body' => $message->body,
                            'message_type' => $message->message_type,
                            'provider_status' =>
                                $message->provider_status,
                            'message_at' =>
                                optional($message->message_at)
                                    ->format('d M Y, h:i A'),
                        ];
                    })
                    ->values(),
        ]);
    }

    public function read(
        string $conversation,
        WhatsAppConversationVisibilityService $visibility
    ) {
        $this->ensureInboxUser();

        abort_unless(
            $visibility->canAccessConversation(
                Auth::user(),
                $conversation
            ),
            403
        );

        return response()->json([
            'success' => true,
            'read_cleared' =>
                $visibility->markReadForUser(
                    Auth::user(),
                    $conversation
                ),
        ]);
    }

    private function conversationPayload(
        WhatsAppConversation $conversation
    ): array {
        return [
            'id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'lead_id' => $conversation->lead_id,
            'contact_name' =>
                optional($conversation->contact)->name
                ?: 'Unknown',
            'number' =>
                optional($conversation->contact)
                    ->normalized_phone
                ?: optional($conversation->contact)->raw_phone,
            'raw_phone' =>
                optional($conversation->contact)->raw_phone,
            'assigned_user_id' =>
                $conversation->assigned_user_id,
            'assigned_user_name' =>
                optional($conversation->assignedUser)->name,
            'last_message' => $conversation->last_message,
            'last_message_at' =>
                optional($conversation->last_message_at)
                    ->format('d M Y, h:i A'),
            'last_message_human' =>
                optional($conversation->last_message_at)
                    ->diffForHumans(),
            'unread_count' => (int) $conversation->unread_count,
            'status' => $conversation->status,
        ];
    }

    private function ensureInboxUser(): void
    {
        $role = optional(Auth::user()->userType)->user_type;

        abort_unless(
            $role === UserType::SUPER_ADMIN
            || in_array(
                $role,
                UserType::SALES_ROLES,
                true
            ),
            403
        );
    }
}
