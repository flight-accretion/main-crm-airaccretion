<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class WhatsAppAiEligibilityService
{
    public function __construct(
        private ActiveLeadService $activeLeadService
    ) {
    }

    public function activeLead(WhatsAppConversation $conversation): ?Lead
    {
        $conversation->loadMissing('contact');

        $contact = $conversation->contact;

        if (!$contact) {
            return null;
        }

        $phone = $contact->normalized_phone ?: $contact->raw_phone;

        if (!$phone) {
            return null;
        }

        return $this->activeLeadService->findByPhone($phone);
    }

    public function canAiOwn(WhatsAppConversation $conversation): bool
    {
        $conversation->refresh();

        if (
            $this->hasColumn('conversation_owner')
            && strtoupper((string) $conversation->conversation_owner) === 'HUMAN'
        ) {
            return false;
        }

        $activeLead = $this->activeLead($conversation);

        if (!$activeLead) {
            return true;
        }

        $this->markHumanOwned(
            $conversation,
            $activeLead,
            'active_lead_found'
        );

        return false;
    }

    public function markHumanOwned(
        WhatsAppConversation $conversation,
        Lead $lead,
        ?string $reason = null
    ): void {
        $conversation->lead_id = $lead->id;

        if ($this->hasColumn('conversation_owner')) {
            $conversation->conversation_owner = 'HUMAN';
        }

        $conversation->assigned_user_id = $lead->representative_user_id;

        if ($reason && $this->hasColumn('handoff_reason')) {
            $conversation->handoff_reason = $conversation->handoff_reason ?: $reason;
        }

        $conversation->save();
    }

    private function hasColumn(string $column): bool
    {
        return Schema::hasTable('whatsapp_conversations')
            && Schema::hasColumn('whatsapp_conversations', $column);
    }
}
