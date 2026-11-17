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
        if (!$sender->isVerifiedActive()) throw new \RuntimeException('Sender must be verified and active before template reconciliation.');
        $number = $sender->integrated_number;
        $sender->forceFill(['template_sync_status' => 'pending', 'template_sync_error' => null])->save();
        try {
            // Provider IO occurs outside the transaction. Recheck the sender identity under lock.
            $definition = app(Msg91TemplateDefinition::class);
            $rows = $definition->variants($this->client->fetchTemplates($number));
            return \Illuminate\Support\Facades\DB::transaction(function () use ($sender, $number, $rows, $central, $definition) {
                $locked = Msg91WhatsAppSender::whereKey($sender->id)->lockForUpdate()->firstOrFail();
                if ($locked->integrated_number !== $number || !$locked->isVerifiedActive()) throw new \RuntimeException('Sender changed during template reconciliation. Retry verification.');
                $seen = []; $count = 0; $skipped = 0;
                $groups = collect($rows)->groupBy(fn ($row) => $row['name'].'|'.$row['language']);
                foreach ($groups as $variants) {
                    $row = $variants->first();
                    $fingerprint = $definition->fingerprint($row['category'], $row['components']);
                    if ($variants->count() !== 1 || !preg_match('/^[a-z0-9_]+$/', $row['name']) || !$row['language']
                        || !is_scalar($row['provider_id']) || is_bool($row['provider_id']) || trim((string) $row['provider_id']) === '' || !$fingerprint) { $skipped++; continue; }
                    if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                        \Illuminate\Support\Facades\DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['msg91-template:'.$row['name'].'|'.$row['language']]);
                    }
                    $candidates = Msg91WhatsAppTemplate::where('name', $row['name'])->where('language', $row['language'])
                        ->where(fn ($q) => $q->where(fn ($c) => $c->where('scope', 'central')->whereNull('owner_user_id'))
                            ->orWhere('owner_user_id', $locked->user_id))->lockForUpdate()->get();
                    if ($candidates->contains(fn ($item) => in_array($item->status, ['deleting', 'deleted'], true))) { $skipped++; continue; }
                    $existingBinding = Msg91WhatsAppSenderTemplateBinding::where('sender_id', $locked->id)
                        ->whereHas('template', fn ($q) => $q->where('name', $row['name'])->where('language', $row['language']))->first();
                    $template = $existingBinding ? $candidates->firstWhere('id', $existingBinding->template_id) : null;
                    if (!$template) {
                        $centralCandidates = $candidates->where('scope', 'central');
                        if ($centralCandidates->count() > 1) { $skipped++; continue; }
                        $template = $centralCandidates->first() ?? $candidates->first();
                    }
                    if ($template && $definition->fingerprint($template->category, $template->components) !== $fingerprint) { $skipped++; continue; }
                    $variant = ['code' => $row['components'], 'variables' => $row['variables']];
                    if (!$template) {
                        $schema = $this->schemaForVariant($variant);
                        $template = Msg91WhatsAppTemplate::create(['owner_user_id' => $central ? null : $locked->user_id,
                            'scope' => $central ? 'central' : 'personal', 'name' => $row['name'], 'language' => $row['language'],
                            'category' => $row['category'], 'provider_template_id' => $row['provider_id'], 'status' => $row['status'],
                            'components' => $row['components'], 'variable_schema' => $schema,
                            'crm_enabled' => $row['status'] === 'approved' && !$this->hasUnsupportedDynamicComponents($variant, $schema),
                            'last_synced_at' => now()]);
                    }
                    // Shared definition, mappings and visibility are immutable during sender sync.
                    $status = $this->hasUnsupportedDynamicComponents($variant, $template->variable_schema ?? []) ? 'unsupported' : $row['status'];
                    $binding = Msg91WhatsAppSenderTemplateBinding::updateOrCreate(['sender_id' => $locked->id, 'template_id' => $template->id],
                        ['provider_template_id' => $row['provider_id'], 'provider_status' => $status, 'last_synced_at' => now(),
                            'provider_meta' => ['integrated_number' => $number, 'definition_fingerprint' => $fingerprint, 'template' => $row]]);
                    $seen[] = $binding->id; $count++;
                }
                $locked->bindings()->whereNotIn('id', $seen)->whereNotIn('provider_status', ['deleted', 'deleting'])
                    ->update(['provider_status' => 'unavailable', 'last_synced_at' => now()]);
                $locked->forceFill(['template_sync_status' => $skipped ? 'partial' : 'synced', 'templates_synced_at' => now(),
                    'template_sync_error' => $skipped ? $skipped.' provider template variant(s) need compatible, unambiguous definitions.' : null])->save();
                return $count;
            });
        } catch (\Throwable $exception) {
            Msg91WhatsAppSender::whereKey($sender->id)->where('integrated_number', $number)->update([
                'template_sync_status' => 'failed', 'template_sync_error' => 'Provider template reconciliation failed. Retry verified sender sync.']);
            throw $exception;
        }
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
        if ($type === 'header' && in_array(strtoupper((string) data_get($component, 'format')), ['IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) return true;
        if ($type === 'buttons') $type = 'button';

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
