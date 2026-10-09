<?php

namespace App\Services\GoogleChat;

use App\Models\{GoogleChatIdentity, Lead, LeadChatConversation, LeadChatMessage, LeadChatTask, User, UserType};
use App\Services\LeadChat\LeadChatAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadGoogleChatConnectionService
{
    public function __construct(private GoogleChatClient $client, private LeadChatAccessService $access)
    {
    }

    public function connect(Lead $lead, User $actor, User $operationsUser, string $spaceName): LeadChatConversation
    {
        $this->access->authorize($actor, $lead);
        abort_unless($this->client->enabled(), 422, 'Google Chat is disabled.');
        abort_unless(in_array($spaceName, config('services.google_chat.allowed_spaces', []), true), 422, 'Space is not approved.');
        $operationsUser->loadMissing('userType');
        abort_unless((int) $operationsUser->status === 1 && in_array($operationsUser->userType?->user_type,
            UserType::OPERATIONS_ROLES, true), 422, 'Select an active Operations user.');
        $identity = GoogleChatIdentity::where('user_id', $operationsUser->id)->whereNotNull('verified_at')->first();
        abort_unless($identity, 422, 'Operations Google identity has not been verified.');
        $members = collect($this->client->listSpaceMembers($spaceName))
            ->filter(fn ($m) => ($m['state'] ?? '') === 'JOINED')->pluck('member.name');
        abort_unless($members->contains($identity->google_user_name)
            && $members->contains(config('services.google_chat.integration_user')), 422,
            'Operations user and integration account must both be joined Space members.');

        return DB::transaction(function () use ($lead, $actor, $operationsUser, $spaceName) {
            // Lock the parent even before the unique conversation exists.
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $conversation = LeadChatConversation::firstOrCreate(['lead_id' => $lead->id]);
            $conversation = LeadChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if ($conversation->google_space_name) {
                abort_unless($conversation->google_space_name === $spaceName, 409, 'This lead is already connected to another Space.');
                abort_unless(!$conversation->operations_user_id || $conversation->operations_user_id === $operationsUser->id,
                    409, 'This conversation already has an Operations assignee.');
                if ($conversation->google_thread_name || $conversation->google_template_message_id) {
                    return $conversation;
                }
            }
            $templateId = (string) Str::uuid();
            $conversation->update([
                'operations_user_id' => $operationsUser->id, 'google_space_name' => $spaceName,
                'google_thread_key' => $conversation->google_thread_key ?: 'lead-'.strtolower($lead->id),
                'google_template_message_id' => $templateId, 'google_connection_status' => 'pending',
            ]);
            $taskTitle = $this->operationsTaskTitle($lead);
            $task = LeadChatTask::create([
                'conversation_id' => $conversation->id,
                'lead_id' => $lead->id,
                'title' => $taskTitle,
                'description' => 'Operations task created from Google Chat connection.',
                'priority' => LeadChatTask::PRIORITY_NORMAL,
                'status' => LeadChatTask::STATUS_ACTIVE,
                'assigned_role' => 'operations',
                'assigned_user_id' => $operationsUser->id,
                'created_by' => $actor->id,
            ]);

            $message = LeadChatMessage::create([
                'id' => $templateId, 'conversation_id' => $conversation->id, 'lead_id' => $lead->id,
                'sender_user_id' => $actor->id, 'source' => 'crm', 'message_type' => LeadChatMessage::TYPE_TASK,
                'body' => $taskTitle, 'task_id' => $task->id,
                'google_sync_status' => 'pending', 'google_sync_version' => 1,
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);
            return $conversation->fresh();
        });
    }

    private function operationsTaskTitle(Lead $lead): string
    {
        $parts = [];

        $customer = trim((string) ($lead->client?->name ?? ''));
        if ($customer !== '') {
            $parts[] = $customer;
        }

        $services = $lead->service_names ?? [];
        $serviceText = is_array($services)
            ? trim(implode(', ', array_filter($services)))
            : trim((string) $services);

        if ($serviceText !== '') {
            $parts[] = $serviceText;
        }

        if (!empty($lead->number_of_passengers)) {
            $parts[] = (int) $lead->number_of_passengers . ' People';
        }

        $occasion = trim((string) ($lead->occasion ?? ''));
        if ($occasion !== '') {
            $parts[] = $occasion;
        }

        $dates = $lead->ride_dates ?? null;
        if (is_array($dates)) {
            $from = trim((string) ($dates['from_date'] ?? ''));
            $to = trim((string) ($dates['to_date'] ?? ''));

            if ($from !== '' && $to !== '' && $from !== $to) {
                $parts[] = $from . ' to ' . $to;
            } elseif ($from !== '') {
                $parts[] = $from;
            } elseif ($to !== '') {
                $parts[] = $to;
            }
        }

        return !empty($parts)
            ? implode(' / ', $parts)
            : 'Operations task';
    }
}
