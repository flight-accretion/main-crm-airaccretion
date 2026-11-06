<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Msg91WhatsAppSender;
use App\Models\Msg91WhatsAppSenderTemplateBinding;
use App\Models\Msg91WhatsAppTemplate;
use App\Models\User;

class Msg91TemplateSyncService
{
    private const DEFAULT_VARIABLE_SOURCES = [
        'customer_name',
        'customer_email',
        'product_name',
        'service_date',
        'next_followup_date',
        'salesperson_name',
    ];

    public function __construct(private Msg91Client $client) {}

    /**
     * Import every MSG91 language variant for a verified sender. Approved
     * templates become campaign-ready automatically unless Super Admin has
     * explicitly hidden that template from CRM.
     */
    public function syncSender(Msg91WhatsAppSender $sender, ?User $actor = null, bool $central = false): int
    {
        $rows = $this->client->fetchTemplates($sender->integrated_number);
        $count = 0;

        foreach ($rows as $row) {
            $name = trim((string) data_get($row, 'template_name', data_get($row, 'name', '')));
            if ($name === '') {
                continue;
            }

            $variants = data_get($row, 'languages');
            if (!is_array($variants) || $variants === []) {
                $variants = [$row];
            }

            foreach ($variants as $variant) {
                $language = trim((string) data_get($variant, 'language', data_get($row, 'language', 'en'))) ?: 'en';
                $status = strtolower(trim((string) data_get($variant, 'status', data_get($row, 'status', 'unknown')))) ?: 'unknown';
                $providerId = data_get($variant, 'msg91_template_id', data_get($variant, 'template_id', data_get($variant, 'id', data_get($row, 'template_id', data_get($row, 'id')))));

                
                $template = Msg91WhatsAppTemplate::query()
                    ->where('name', $name)
                    ->where('language', $language)
                    ->whereHas('bindings', function ($bindings) use ($sender) {
                        $bindings->where('sender_id', $sender->id);
                    })
                    ->first();

                // Reuse an existing central template when the same
                // template name and language exist on another sender.
                if (!$template) {
                    $template = Msg91WhatsAppTemplate::query()
                        ->where('scope', 'central')
                        ->whereNull('owner_user_id')
                        ->where('name', $name)
                        ->where('language', $language)
                        ->first();
                }

                if (!$template) {
                    $template = new Msg91WhatsAppTemplate([
                        'owner_user_id' => $central ? null : $sender->user_id,
                        'scope' => $central ? 'central' : 'personal',
                        'name' => $name,
                        'language' => $language,
                    ]);
                }


                if (!$template) {
                    $template = new Msg91WhatsAppTemplate([
                        'owner_user_id' => $central ? null : $sender->user_id,
                        'scope' => $central ? 'central' : 'personal',
                        'name' => $name,
                        'language' => $language,
                    ]);
                }

                
            $autoVisible = !$template->exists
                || !(bool) $template->crm_visibility_overridden;

            // Preserve the existing CRM variable mapping.
            // MSG91 provides placeholder positions but not our CRM field choices.
            $existingSchema = $template->variable_schema;

            $variableSchema = (
                $template->exists
                && is_array($existingSchema)
                && !empty($existingSchema)
            )
                ? $existingSchema
                : $this->schemaForVariant($variant);

                $hasUnsupportedDynamicComponents =
                $this->hasUnsupportedDynamicComponents(
                    $variant,
                    $variableSchema
                );

            $template->fill([
                'category' => data_get($row, 'category'),
                'provider_template_id' => $providerId,
                'status' => $status,
                'components' => data_get(
                    $variant,
                    'code',
                    data_get($row, 'components')
                ),
                'variable_schema' => $variableSchema,
                'crm_enabled' => $hasUnsupportedDynamicComponents
                ? false
                : (
                    $autoVisible
                        ? $status === 'approved'
                        : $template->crm_enabled
                ),
                'last_synced_at' => now(),
            ]);

            $template->save();


                Msg91WhatsAppSenderTemplateBinding::query()->updateOrCreate(
                    ['sender_id' => $sender->id, 'template_id' => $template->id],
                    [
                        'provider_template_id' => $providerId,
                        'provider_status' => $status,
                        'last_synced_at' => now(),
                        'provider_meta' => ['template' => $row, 'language' => $variant],
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    public function schemaForBody(string $body): array
    {
        preg_match_all('/{{([1-9][0-9]*)}}/', $body, $matches);
        return $this->schemaForIndexes($matches[1] ?? []);
    }

    private function schemaForVariant(array $variant): array
    {
        $variables = data_get($variant, 'variables', []);
        if (!is_array($variables)) {
            $variables = [];
        }

        $indexes = [];
        foreach ($variables as $variable) {
            if (preg_match('/^body_([1-9][0-9]*)$/', (string) $variable, $matches)) {
                $indexes[] = $matches[1];
            }
        }

        if ($indexes === []) {
            foreach ((array) data_get($variant, 'code', []) as $component) {
                if (strtoupper((string) data_get($component, 'type')) === 'BODY') {
                    preg_match_all('/{{([1-9][0-9]*)}}/', (string) data_get($component, 'text', ''), $matches);
                    $indexes = array_merge($indexes, $matches[1] ?? []);
                }
            }
        }

        return $this->schemaForIndexes($indexes);
    }

    private function hasUnsupportedDynamicComponents(
    array $variant,
    array $schema
): bool {
    $requiredMappings = [];

    foreach ((array) data_get($variant, 'variables', []) as $variable) {
        if (preg_match(
            '/^(header|button)_([1-9][0-9]*)$/i',
            (string) $variable,
            $matches
        )) {
            $requiredMappings[] =
                strtolower($matches[1]) . '_' . $matches[2];
        }
    }

    foreach ((array) data_get($variant, 'code', []) as $component) {
        $type = strtolower(
            (string) data_get($component, 'type', '')
        );

        if (!in_array($type, ['header', 'button'], true)) {
            continue;
        }

        preg_match_all(
            '/{{([1-9][0-9]*)}}/',
            json_encode($component) ?: '',
            $matches
        );

        foreach ($matches[1] ?? [] as $index) {
            $requiredMappings[] = $type . '_' . $index;
        }
    }

    foreach (array_unique($requiredMappings) as $componentKey) {
        if (empty($schema[$componentKey])) {
            return true;
        }
    }

    return false;
}

    private function schemaForIndexes(array $indexes): array
    {
        $indexes = array_values(array_unique(array_map('intval', $indexes)));
        sort($indexes);
        $schema = [];
        foreach ($indexes as $index) {
            $schema["body_{$index}"] = [
                'component' => "body_{$index}",
                'field' => self::DEFAULT_VARIABLE_SOURCES[$index - 1] ?? 'customer_name',
                'type' => 'text',
            ];
        }

        return $schema;
    }

    public function sampleValue(string $field): string
    {
        return [
            'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com',
            'product_name' => 'Accretion Aviation service',
            'service_date' => now()->addWeek()->format('d M Y'),
            'next_followup_date' => now()->addDay()->format('d M Y'),
            'salesperson_name' => 'Sales team',
        ][$field] ?? 'Customer';
    }
}
