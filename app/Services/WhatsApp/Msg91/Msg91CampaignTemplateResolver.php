<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Msg91WhatsAppSender;
use App\Models\Msg91WhatsAppTemplate;
use App\Models\Msg91WhatsAppSenderTemplateBinding;
use App\Models\User;

class Msg91CampaignTemplateResolver
{
    public function approvedFor(User $user, Msg91WhatsAppSender $sender)
    {
        return Msg91WhatsAppTemplate::query()
            ->where('crm_enabled', true)
            ->whereIn('scope', ['central', 'personal'])
            ->where(function ($query) use ($user) {
                $query->where('scope', 'central')->orWhere('owner_user_id', $user->id);
            })
            ->whereHas('bindings', function ($query) use ($sender) {
                $query->where('sender_id', $sender->id)
                    ->where('provider_status', 'approved')
                    ->where('last_synced_at', '>=', now()->subMinutes((int) config('services.msg91_campaigns.template_stale_minutes', 20)));
            })
            ->orderBy('name')->orderBy('language');
    }

    public function assertApproved(User $user, Msg91WhatsAppSender $sender, string $templateId): Msg91WhatsAppTemplate
    {
        return $this->approvedFor($user, $sender)->whereKey($templateId)->firstOrFail();
    }
}
