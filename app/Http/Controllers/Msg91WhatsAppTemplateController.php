<?php

namespace App\Http\Controllers;

use App\Models\Msg91WhatsAppSender;
use App\Models\Msg91WhatsAppSenderTemplateBinding;
use App\Models\Msg91WhatsAppTemplate;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use App\Services\WhatsApp\Msg91\Msg91Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class Msg91WhatsAppTemplateController extends Controller
{
    private const VARIABLE_SOURCES = [
        'customer_name',
        'product_name',
        'service_date',
        'next_followup_date',
        'salesperson_name',
    ];

    public function __construct(
        private Msg91CampaignAccess $access,
        private Msg91Client $client
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);

        $canManageAll = $this->access->canManage($request->user());

        $senders = Msg91WhatsAppSender::query()
            ->with('user:id,name,email')
            ->where('status', 'active')
            ->whereNotNull('verified_at')
            ->when(!$canManageAll, fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderBy('integrated_number')
            ->get();

        $templates = $this->visibleTemplates($request, $canManageAll)
            ->with(['bindings.sender.user:id,name,email'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        $editingTemplate = null;
        if ($request->filled('edit')) {
            $editingTemplate = $this->visibleTemplates($request, $canManageAll)
                ->whereKey($request->query('edit'))
                ->firstOrFail();
            abort_unless($canManageAll || (string) $editingTemplate->owner_user_id === (string) $request->user()->id, 403);
        }

        return view('admin.pages.whatsapp.admin.templates', compact('senders', 'templates', 'editingTemplate', 'canManageAll'));
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);

        $data = $request->validate([
            'template_id' => ['nullable', 'uuid', 'exists:msg91_whatsapp_templates,id'],
            'name' => ['required', 'string', 'max:191'],
            'language' => ['required', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:64'],
            'provider_template_id' => ['nullable', 'string', 'max:191'],
            'provider_status' => ['required', 'in:approved,pending,rejected,disabled,unknown'],
            'sender_id' => ['required', 'uuid', 'exists:msg91_whatsapp_senders,id'],
            'crm_enabled' => ['nullable', 'boolean'],
            'body_1' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_2' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_3' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_4' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_5' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
        ]);

        $sender = Msg91WhatsAppSender::query()->findOrFail($data['sender_id']);
        abort_unless($sender->isVerifiedActive(), 422, 'Choose a verified, active MSG91 sender.');
        abort_unless($this->access->canManage($request->user()) || (string) $sender->user_id === (string) $request->user()->id, 403);

        $template = !empty($data['template_id'])
            ? Msg91WhatsAppTemplate::query()->findOrFail($data['template_id'])
            : Msg91WhatsAppTemplate::query()->firstOrNew([
                'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
                'name' => $data['name'],
                'language' => $data['language'],
            ]);
        abort_unless(
            $this->access->canManage($request->user())
                || (string) $template->owner_user_id === (string) $request->user()->id,
            403
        );

        $template->fill([
            'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
            'scope' => $this->access->canManage($request->user()) ? 'central' : 'personal',
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'] ?? null,
            'provider_template_id' => $data['provider_template_id'] ?? null,
            'status' => $data['provider_status'],
            'crm_enabled' => $request->boolean('crm_enabled'),
            'variable_schema' => $this->variableSchema($data),
            'last_synced_at' => now(),
        ]);
        $template->save();

        Msg91WhatsAppSenderTemplateBinding::query()->updateOrCreate(
            ['sender_id' => $sender->id, 'template_id' => $template->id],
            [
                'provider_template_id' => $data['provider_template_id'] ?? null,
                'provider_status' => $data['provider_status'],
                'last_synced_at' => now(),
                'provider_meta' => ['source' => 'manual-admin-registration'],
            ]
        );

        return back()->with('success', 'Template mapping saved. Only approved, enabled, fresh mappings can be used in campaigns.');
    }

    public function sync(Request $request, Msg91WhatsAppSender $sender): RedirectResponse
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);
        abort_unless($sender->isVerifiedActive(), 422, 'The selected sender is not verified and active.');
        abort_unless($this->access->canManage($request->user()) || (string) $sender->user_id === (string) $request->user()->id, 403);

        try {
            $rows = $this->client->fetchTemplates($sender->integrated_number);
        } catch (Throwable $exception) {
            return back()->withErrors(['msg91_sync' => $exception->getMessage()]);
        }

        $count = 0;
        foreach ($rows as $row) {
            $name = trim((string) data_get($row, 'template_name', data_get($row, 'name', '')));
            if ($name === '') {
                continue;
            }

            // MSG91 returns one template with one or more language variants.
            // Approval, provider ID, and components are on the language row.
            $variants = data_get($row, 'languages');
            if (!is_array($variants) || $variants === []) {
                $variants = [$row];
            }

            foreach ($variants as $variant) {
                $language = trim((string) data_get($variant, 'language', data_get($row, 'language', 'en')));
                $providerStatus = strtolower(trim((string) data_get($variant, 'status', data_get($row, 'status', 'unknown'))));
                $providerId = data_get($variant, 'msg91_template_id', data_get($variant, 'template_id', data_get($variant, 'id', data_get($row, 'template_id', data_get($row, 'id')))));

                $template = Msg91WhatsAppTemplate::query()->firstOrNew([
                    'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
                    'name' => $name,
                    'language' => $language ?: 'en',
                ]);
                $template->fill([
                    'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
                    'scope' => $this->access->canManage($request->user()) ? 'central' : 'personal',
                    'name' => $name,
                    'language' => $language ?: 'en',
                    'category' => data_get($row, 'category'),
                    'provider_template_id' => $providerId,
                    'status' => $providerStatus ?: 'unknown',
                    'components' => data_get($variant, 'code', data_get($row, 'components')),
                    'last_synced_at' => now(),
                ]);
                $template->save();

                Msg91WhatsAppSenderTemplateBinding::query()->updateOrCreate(
                    ['sender_id' => $sender->id, 'template_id' => $template->id],
                    [
                        'provider_template_id' => $providerId,
                        'provider_status' => $providerStatus ?: 'unknown',
                        'last_synced_at' => now(),
                        'provider_meta' => ['template' => $row, 'language' => $variant],
                    ]
                );
                $count++;
            }
        }

        return back()->with('success', "Synced {$count} MSG91 template mapping(s) for {$sender->integrated_number}.");
    }

    public function create(Request $request): RedirectResponse
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);

        $data = $request->validate([
            'sender_id' => ['required', 'uuid', 'exists:msg91_whatsapp_senders,id'],
            'name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:191'],
            'language' => ['required', 'string', 'max:32'],
            'category' => ['required', 'in:UTILITY,MARKETING,AUTHENTICATION'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'body_1' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_2' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_3' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_4' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
            'body_5' => ['nullable', 'in:' . implode(',', self::VARIABLE_SOURCES)],
        ]);

        $sender = Msg91WhatsAppSender::query()->findOrFail($data['sender_id']);
        abort_unless($sender->isVerifiedActive(), 422, 'Choose a verified, active MSG91 sender.');
        abort_unless($this->access->canManage($request->user()) || (string) $sender->user_id === (string) $request->user()->id, 403);

        $schema = $this->variableSchema($data);
        preg_match_all('/{{([1-9][0-9]*)}}/', $data['body'], $matches);
        $expected = array_map('intval', $matches[1] ?? []);
        sort($expected);
        $expected = array_values(array_unique($expected));
        if ($expected !== ($expected ? range(1, count($expected)) : [])) {
            return back()->withInput()->withErrors(['body' => 'Body variables must start at {{1}} and continue without gaps.']);
        }
        $provided = array_map(static fn (string $key) => (int) substr($key, 5), array_keys($schema));
        sort($provided);
        if ($provided !== $expected) {
            return back()->withInput()->withErrors(['body' => 'Map every body variable in order. For example, {{1}} requires Body 1.']);
        }

        $components = [[
            'type' => 'BODY',
            'text' => $data['body'],
            'example' => [
                'body_text' => [
                    array_map(fn (int $index) => $this->sampleValue($schema["body_{$index}"]['field']), $expected),
                ],
            ],
        ]];
        if (!empty($data['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer']];
        }

        try {
            $provider = $this->client->createTemplate([
                'integrated_number' => $sender->integrated_number,
                'template_name' => $data['name'],
                'language' => $data['language'],
                'category' => $data['category'],
                'button_url' => false,
                'components' => $components,
            ]);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['msg91_template' => $exception->getMessage()]);
        }

        $template = Msg91WhatsAppTemplate::query()->firstOrNew([
            'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
            'name' => $data['name'],
            'language' => $data['language'],
        ]);
        $template->fill([
            'owner_user_id' => $this->access->canManage($request->user()) ? null : $request->user()->id,
            'scope' => $this->access->canManage($request->user()) ? 'central' : 'personal',
            'category' => $data['category'],
            'provider_template_id' => $provider['template_id'] ?? null,
            'status' => 'pending',
            'crm_enabled' => false,
            'components' => $components,
            'variable_schema' => $schema,
            'last_synced_at' => now(),
        ]);
        $template->save();

        Msg91WhatsAppSenderTemplateBinding::query()->updateOrCreate(
            ['sender_id' => $sender->id, 'template_id' => $template->id],
            [
                'provider_template_id' => $provider['template_id'] ?? null,
                'provider_status' => 'pending',
                'last_synced_at' => now(),
                'provider_meta' => ['source' => 'crm-provider-create', 'response' => $provider['payload'] ?? []],
            ]
        );

        return redirect()->route('admin.msg91.templates.index')->with('success', 'Template submitted to MSG91. It will appear in the campaign list only after MSG91 approves it and it is synced.');
    }

    private function variableSchema(array $data): array
    {
        $schema = [];
        foreach (range(1, 5) as $index) {
            $source = Arr::get($data, "body_{$index}");
            if ($source) {
                $schema["body_{$index}"] = [
                    'component' => "body_{$index}",
                    'field' => $source,
                    'type' => 'text',
                ];
            }
        }

        return $schema;
    }

    private function visibleTemplates(Request $request, bool $canManageAll)
    {
        return Msg91WhatsAppTemplate::query()->when(!$canManageAll, function ($query) use ($request) {
            $query->where(function ($templates) use ($request) {
                $templates->where('scope', 'central')->orWhere('owner_user_id', $request->user()->id);
            });
        });
    }

    private function sampleValue(string $source): string
    {
        return [
            'customer_name' => 'Customer',
            'product_name' => 'Accretion Aviation service',
            'service_date' => now()->addWeek()->format('d M Y'),
            'next_followup_date' => now()->addDay()->format('d M Y'),
            'salesperson_name' => 'Sales team',
        ][$source] ?? 'Sample';
    }
}
