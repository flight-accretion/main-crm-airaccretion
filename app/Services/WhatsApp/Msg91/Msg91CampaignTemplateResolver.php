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
            ->when(!$sender->isVerifiedActive() || (string) $sender->user_id !== (string) $user->id, fn ($q) => $q->whereRaw('1 = 0'))
            ->where('crm_enabled', true)
            ->whereNotIn('status', ['deleting', 'deleted'])
            ->whereIn('scope', ['central', 'personal'])
            ->where(function ($query) use ($user) {
                $query->where('scope', 'central')->orWhere('owner_user_id', $user->id);
            })
            ->whereHas('bindings', function ($query) use ($sender) {
                $query->where('sender_id', $sender->id)
                    ->where(function ($identity) use ($sender) {
                        $identity->whereNull('provider_meta->integrated_number')->orWhere('provider_meta->integrated_number', $sender->integrated_number);
                    })
                    ->where('provider_status', 'approved')
                    ->where('last_synced_at', '>=', now()->subMinutes((int) config('services.msg91_campaigns.template_stale_minutes', 20)));
            })
            ->orderBy('name')->orderBy('language');
    }

    public function assertApproved(User $user, Msg91WhatsAppSender $sender, string $templateId): Msg91WhatsAppTemplate
    {
        $template = $this->approvedFor($user, $sender)->whereKey($templateId)->firstOrFail();
        abort_unless($this->supportsMappings($template), 422, 'Template variable mappings are incomplete or unsupported.');
        return $template;
    }

    public function supportsMappings(Msg91WhatsAppTemplate $template): bool
    {
        if (!app(Msg91TemplateDefinition::class)->fingerprint($template->category, $template->components)) return false;
        $schema = $template->variable_schema ?? [];
        $mapped = [];
        foreach ($schema as $key => $mapping) {
            if (!is_array($mapping) || !preg_match('/^(body|header|button)_[1-9][0-9]*$/', $mapping['component'] ?? $key)
                || !in_array($mapping['field'] ?? $mapping['source'] ?? null, ['customer_name', 'customer_email', 'product_name', 'service_date', 'next_followup_date', 'salesperson_name'], true)
                || ($mapping['type'] ?? 'text') !== 'text') return false;
            $mapped[$mapping['component'] ?? $key] = true;
        }
        foreach ($template->components ?? [] as $component) {
            $type = strtolower($component['type'] ?? '');
            if ($type === 'header' && in_array(strtoupper($component['format'] ?? ''), ['IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) return false;
            if ($type === 'buttons') $type = 'button';
            preg_match_all('/{{([1-9][0-9]*)}}/', json_encode($component), $matches);
            foreach ($matches[1] as $index) if (!isset($mapped[$type.'_'.$index])) return false;
        }
        return true;
    }
}
