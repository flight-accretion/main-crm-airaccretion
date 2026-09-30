<?php

namespace App\Observers;

use App\Jobs\SyncLeadChatMessageToGoogleChat;
use App\Models\LeadChatMessage;
use Illuminate\Support\Facades\DB;

class LeadChatMessageObserver
{
    public function saving(LeadChatMessage $message): void
    {
        if ($message->source !== 'crm' || $message->message_type !== 'text'
            || !config('services.google_chat.enabled')) {
            return;
        }
        if ($message->exists && !$message->isDirty(['body', 'deleted_at'])) {
            return;
        }
        $conversation = $message->conversation;
        if (!$conversation || (!$conversation->google_thread_name && !$conversation->google_template_message_id)) {
            $message->google_sync_status = 'not_required';
            return;
        }
        // Connecting a conversation must not backfill its historical local messages.
        if ($message->exists && !$message->google_message_name && !(int) $message->google_sync_version) {
            return;
        }
        $message->google_sync_version = ((int) $message->getOriginal('google_sync_version')) + 1;
        $message->google_sync_status = 'pending';
        $message->google_sync_error = null;
    }

    public function saved(LeadChatMessage $message): void
    {
        if ($message->source !== 'crm' || $message->google_sync_status !== 'pending'
            || (!$message->wasRecentlyCreated && !$message->wasChanged('google_sync_version'))) {
            return;
        }
        $id = $message->id;
        DB::afterCommit(static function () use ($id) {
            SyncLeadChatMessageToGoogleChat::enqueue($id);
        });
    }
}
