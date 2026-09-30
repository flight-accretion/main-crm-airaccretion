<?php

namespace App\Http\Controllers;

use App\Jobs\SyncLeadChatMessageToGoogleChat;
use App\Models\{GoogleChatIdentity, Lead, LeadChatConversation, LeadChatMessage, User, UserType};
use App\Services\GoogleChat\LeadGoogleChatConnectionService;
use App\Services\LeadChat\LeadChatAccessService;
use Illuminate\Http\Request;

class LeadGoogleChatConnectionController extends Controller
{
    public function options(Request $request, Lead $lead, LeadChatAccessService $access)
    {
        $access->authorize($request->user(), $lead);
        $spaces = array_values(config('services.google_chat.allowed_spaces', []));
        $labels = config('services.google_chat.space_labels', []);
        return response()->json([
            'enabled' => (bool) config('services.google_chat.enabled'),
            'connection' => LeadChatConversation::where('lead_id', $lead->id)->first([
                'google_connection_status', 'google_space_name', 'google_thread_name', 'operations_user_id',
            ]),
            'spaces' => $spaces,
            'space_options' => array_map(function ($id) use ($labels) {
                $label = is_array($labels) ? ($labels[$id] ?? null) : null;
                return ['id' => $id, 'name' => is_string($label) && trim($label) !== '' ? $label : $id];
            }, $spaces),
            'users' => User::where('status', 1)->whereHas('userType', fn ($q) => $q->whereIn('user_type', UserType::OPERATIONS_ROLES))
                ->whereIn('id', GoogleChatIdentity::whereNotNull('verified_at')->select('user_id'))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function connect(Request $request, Lead $lead, LeadGoogleChatConnectionService $service)
    {
        $data = $request->validate(['operations_user_id' => 'required|uuid|exists:users,id', 'space_name' => 'required|string|max:255']);
        $conversation = $service->connect($lead, $request->user(), User::findOrFail($data['operations_user_id']), $data['space_name']);
        return response()->json(['success' => true, 'connection' => [
            'google_connection_status' => $conversation->google_connection_status,
            'google_space_name' => $conversation->google_space_name,
            'google_thread_name' => $conversation->google_thread_name,
            'operations_user_id' => $conversation->operations_user_id,
        ]]);
    }

    public function retry(Request $request, Lead $lead, LeadChatMessage $message, LeadChatAccessService $access)
    {
        $access->authorize($request->user(), $lead);
        abort_unless($message->lead_id === $lead->id, 404);
        abort_unless(config('services.google_chat.enabled') && $message->source === 'crm'
            && in_array($message->google_sync_status, ['pending', 'failed', 'syncing'], true), 422);
        SyncLeadChatMessageToGoogleChat::enqueue($message->id);
        return response()->json(['success' => true]);
    }
}
