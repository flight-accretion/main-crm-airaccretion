<?php

namespace App\Http\Controllers;

use App\Models\LeadChatConversation;
use App\Models\LeadChatMessage;
use App\Models\LeadChatTask;
use Illuminate\Http\Request;

class LeadChatOverviewController extends Controller
{
    /**
     * Read-only conversation monitor. It gives oversight roles a useful
     * landing page instead of routing the sidebar to the JSON notification API.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'in:all,needs_attention'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $needsAttention = ($filters['state'] ?? 'all') === 'needs_attention';

        $latestMessage = LeadChatMessage::query()
            ->select('body')
            ->whereColumn('conversation_id', 'lead_chat_conversations.id')
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->limit(1);

        $conversations = LeadChatConversation::query()
            ->select('lead_chat_conversations.*')
            ->addSelect(['latest_message_body' => $latestMessage])
            ->with(['lead.client', 'lead.representative'])
            ->withCount([
                'tasks as active_tasks_count' => fn ($query) => $query
                    ->where('status', LeadChatTask::STATUS_ACTIVE),
            ])
            ->whereHas('lead')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('lead', function ($lead) use ($search) {
                    $lead->where('id', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($needsAttention, fn ($query) => $query->whereHas(
                'tasks',
                fn ($task) => $task->where('status', LeadChatTask::STATUS_ACTIVE)
            ))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return response()
            ->view('admin.pages.lead-chat.overview', compact('conversations', 'search', 'needsAttention'))
            ->header('Cache-Control', 'private, no-store');
    }
}
