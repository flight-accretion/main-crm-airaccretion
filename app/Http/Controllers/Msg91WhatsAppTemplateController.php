<?php

namespace App\Http\Controllers;

use App\Models\Msg91WhatsAppSender;
use App\Models\Msg91WhatsAppSenderTemplateBinding;
use App\Models\Msg91WhatsAppTemplate;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use App\Services\WhatsApp\Msg91\Msg91Client;
use App\Services\WhatsApp\Msg91\Msg91TemplateSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use App\Services\WhatsApp\Msg91\Msg91ProviderException;
use Illuminate\Support\Facades\Log;
use App\Models\Msg91WhatsAppCampaign;
use Illuminate\Support\Facades\DB;

class Msg91WhatsAppTemplateController extends Controller
{
    public function __construct(
        private Msg91CampaignAccess $access,
        private Msg91Client $client,
        private Msg91TemplateSyncService $sync
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);

        $canManageAll = $this->access->canManage($request->user());
        $canManageVisibility = $this->access->canManageTemplateVisibility($request->user());

        $senders = Msg91WhatsAppSender::query()
            ->with('user:id,name,email')
            ->where('status', 'active')
            ->where('verified_at', '>=', now()->subMinutes(
            (int) config('services.msg91_campaigns.sender_verification_ttl_minutes', 15)
            ))
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

        return view('admin.pages.whatsapp.admin.templates', compact('senders', 'templates', 'editingTemplate', 'canManageAll', 'canManageVisibility'));
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->access->canManageTemplateVisibility($request->user()), 403);

        $data = $request->validate([
            'template_id' => ['required', 'uuid', 'exists:msg91_whatsapp_templates,id'],
            'crm_enabled' => ['required', 'boolean'],
        ]);
        $template = Msg91WhatsAppTemplate::query()->findOrFail($data['template_id']);
        $template->fill([
            'crm_enabled' => (bool) $data['crm_enabled'],
            'crm_visibility_overridden' => true,
        ]);
        $template->save();
        return back()->with('success', $template->crm_enabled ? 'Template is visible in CRM campaigns.' : 'Template is hidden from CRM campaigns.');
    }

    public function destroy(
    Request $request,
    Msg91WhatsAppTemplate $template
) {
    abort_unless(
        $this->access->canManageTemplateVisibility($request->user()),
        403
    );

    $hasOpenCampaign = Msg91WhatsAppCampaign::query()
        ->where('template_id', $template->id)
        ->whereIn('status', ['queued', 'processing'])
        ->exists();

    if ($hasOpenCampaign) {
        return $this->templateDeleteFailure(
            $request,
            'This template has an active campaign. Cancel or complete that campaign before deleting it.'
        );
    }

    $bindings = $template->bindings()
        ->with('sender')
        ->get();

    if ($bindings->isEmpty()) {
        return $this->templateDeleteFailure(
            $request,
            'No MSG91 sender is linked to this template. The CRM record was kept for safety.'
        );
    }

    foreach ($bindings as $binding) {
        if (!$binding->sender || blank($binding->sender->integrated_number)) {
            return $this->templateDeleteFailure(
                $request,
                'A linked MSG91 sender is missing. The template was not deleted.'
            );
        }
    }

    foreach ($bindings as $binding) {
        if ($binding->provider_status === 'deleted') {
            continue;
        }

        try {
            $this->client->deleteTemplate(
                $binding->sender->integrated_number,
                $template->name
            );

            $binding->forceFill([
                'provider_status' => 'deleted',
                'last_synced_at' => now(),
                'provider_meta' => array_merge(
                    is_array($binding->provider_meta)
                        ? $binding->provider_meta
                        : [],
                    [
                        'deleted_at' => now()->toIso8601String(),
                        'deleted_by' => $request->user()->id,
                    ]
                ),
            ])->save();
        } catch (Msg91ProviderException $exception) {

    $providerReason =
        $this->providerRejectionReason(
            $exception->payload
        );

    Log::warning(
        'MSG91 template deletion failed.',
        [
            'template_id' =>
                $template->id,

            'template_name' =>
                $template->name,

            'sender_id' =>
                $binding->sender_id,

            'integrated_number' =>
                $binding->sender->integrated_number,

            'http_status' =>
                $exception->httpStatus,

            'provider_payload' =>
                $exception->payload,

            'provider_reason' =>
                $providerReason,
        ]
    );

    return $this->templateDeleteFailure(
        $request,
        'MSG91 could not delete template "'
            . $template->name
            . '" for sender '
            . $binding->sender->integrated_number
            . '. MSG91 response: '
            . $providerReason
            . ' The CRM template was kept for safety.'
    );
} catch (Throwable $exception) {
            Log::error('MSG91 template deletion failed unexpectedly.', [
                'template_id' => $template->id,
                'sender_id' => $binding->sender_id,
                'exception' => $exception,
            ]);

            return $this->templateDeleteFailure(
                $request,
                'The template could not be deleted from MSG91. The CRM template was kept.'
            );
        }
    }

    DB::transaction(function () use ($template) {
        $template->bindings()->delete();

        $template->forceFill([
            'status' => 'deleted',
            'crm_enabled' => false,
            'crm_visibility_overridden' => true,
            'provider_template_id' => null,
            'last_synced_at' => now(),
        ])->save();
    });

    $message = 'Template deleted successfully from MSG91 and removed from CRM campaigns.';

    if ($request->expectsJson()) {
        return response()->json([
            'message' => $message,
            'template_id' => $template->id,
        ]);
    }

    return redirect()
        ->route('admin.msg91.templates.index')
        ->with('success', $message);
}

private function templateDeleteFailure(
    Request $request,
    string $message,
    int $status = 422
) {
    if ($request->expectsJson()) {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    return back()->withErrors([
        'msg91_template' => $message,
    ]);
}

    public function sync(Request $request, Msg91WhatsAppSender $sender): RedirectResponse
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);
        abort_unless($sender->isVerifiedActive(), 422, 'The selected sender is not verified and active.');
        abort_unless($this->access->canManage($request->user()) || (string) $sender->user_id === (string) $request->user()->id, 403);

        try {
            $count = $this->sync->syncSender($sender, $request->user(), $this->access->canManage($request->user()));
        } catch (Throwable $exception) {
            return back()->withErrors(['msg91_sync' => $exception->getMessage()]);
        }

        return back()->with('success', "Updated {$count} template(s) from MSG91 for {$sender->integrated_number}.");
    }

    public function create(Request $request)
    {
        abort_unless($this->access->canManageTemplates($request->user()), 403);

        $data = $request->validate([
            'sender_ids' => ['required', 'array', 'min:1', 'max:100'],
            'sender_ids.*' => ['required', 'uuid', 'distinct', 'exists:msg91_whatsapp_senders,id'],
            'name' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:191'],
            'language' => ['required', 'in:en,hi'],
            'category' => ['required', 'in:UTILITY,MARKETING,AUTHENTICATION'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'body_1' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
            'body_2' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
            'body_3' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
            'body_4' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
            'body_5' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
            'body_6' => ['nullable', 'in:customer_name,customer_email,product_name,service_date,next_followup_date,salesperson_name'],
        ], [
            'name.required' => 'Enter a template name.',
            'name.regex' => 'Template name may contain only lowercase letters, numbers, and underscores. Do not use spaces, hyphens, or uppercase letters.',
            'name.max' => 'Template name cannot be longer than 191 characters.',
            'sender_ids.required' => 'Choose a verified sender before submitting the template.',
            'sender_ids.min' => 'Choose at least one verified sender before submitting the template.',
            'sender_ids.*.exists' => 'Choose a verified sender from the available list.',
            'body.required' => 'Enter the template message.',
        ]);

        $senders = Msg91WhatsAppSender::query()
            ->whereIn('id', $data['sender_ids'])
            ->get();
        abort_unless($senders->count() === count($data['sender_ids']), 422, 'Choose valid MSG91 senders.');
        abort_unless($senders->every->isVerifiedActive(), 422, 'Every selected member must have a verified, active MSG91 sender.');
        abort_unless(
            $this->access->canManage($request->user())
                || ($senders->count() === 1 && (string) $senders->first()->user_id === (string) $request->user()->id),
            403
        );

        preg_match_all('/{{([1-9][0-9]*)}}/', $data['body'], $matches);
        $expected = array_map('intval', $matches[1] ?? []);
        sort($expected);
        $expected = array_values(array_unique($expected));
        if ($expected !== ($expected ? range(1, count($expected)) : [])) {
            return back()->withInput()->withErrors(['body' => 'Body variables must start at {{1}} and continue without gaps.']);
        }
        if (count($expected) > 6) {
            return back()->withInput()->withErrors(['body' => 'A template can use up to six body variables.']);
        }

        $schema = [];
        foreach ($expected as $index) {
            $field = $data["body_{$index}"] ?? null;
            if (!$field) {
                return back()->withInput()->withErrors(["body_{$index}" => 'Choose the CRM value for {{' . $index . '}}.']);
            }

            $schema["body_{$index}"] = [
                'component' => "body_{$index}",
                'field' => $field,
                'type' => 'text',
            ];
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $data['body'],
        ];
        if ($expected !== []) {
            $bodyComponent['example'] = [
                'body_text' => [
                    array_map(fn (int $index) => $this->sync->sampleValue($schema["body_{$index}"]['field']), $expected),
                ],
            ];
        }
        $components = [$bodyComponent];
        if (!empty($data['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer']];
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
            'provider_template_id' => null,
            'status' => 'pending',
            'crm_enabled' => true,
            'crm_visibility_overridden' => false,
            'components' => $components,
            'variable_schema' => $schema,
            'last_synced_at' => now(),
        ]);

        $submitted = [];
        $failed = [];
        foreach ($senders as $sender) {
            try {
                $provider = $this->client->createTemplate([
                    'integrated_number' => $sender->integrated_number,
                    'template_name' => $data['name'],
                    'language' => $data['language'],
                    'category' => $data['category'],
                    'button_url' => false,
                    'components' => $components,
                ]);

                if (!$template->exists || $submitted === []) {
                    $template->save();
                }

                Msg91WhatsAppSenderTemplateBinding::query()->updateOrCreate(
                    ['sender_id' => $sender->id, 'template_id' => $template->id],
                    [
                        'provider_template_id' => $provider['template_id'] ?? null,
                        'provider_status' => 'pending',
                        'last_synced_at' => now(),
                        'provider_meta' => ['source' => 'crm-provider-create', 'response' => $provider['payload'] ?? []],
                    ]
                );
                $submitted[] = $sender->user?->name ?: $sender->integrated_number;
                if (!$template->provider_template_id && !empty($provider['template_id'])) {
                    $template->provider_template_id = $provider['template_id'];
                    $template->save();
                }
            } catch (Msg91ProviderException $exception) {
                $reason = $this->providerRejectionReason($exception->payload);

                Log::warning('MSG91 template rejected', [
                    'sender_id' => $sender->id,
                    'template_name' => $data['name'],
                    'http_status' => $exception->httpStatus,
                    'reason' => $reason,
                ]);

                $failed[] = ($sender->user?->name ?: 'Selected sender')
                    . ': MSG91 HTTP '
                    . $exception->httpStatus
                    . ' — '
                    . $reason;
            } catch (Throwable $exception) {
                Log::error('MSG91 template creation failed', [
                    'sender_id' => $sender->id,
                    'exception_type' => get_class($exception),
                ]);

                $failed[] = 'Unable to create template due to a server error.';
            }
        }

        if ($submitted === []) {
            $message = implode(' ', $failed) ?: 'MSG91 could not accept this template. Check the template details and try again.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Template was not submitted to MSG91.',
                    'errors' => ['msg91_template' => [$message]],
                ], 422);
            }

            return back()
                ->withInput()
                ->with('msg91_open_create_template', true)
                ->withErrors(['msg91_template' => $message]);
        }

        $message = 'Template submitted to MSG91 for ' . implode(', ', $submitted) . '. It will become available for each member automatically after MSG91 approval is synced.';
        if ($failed !== []) {
            $message .= ' Not submitted for: ' . implode(' | ', $failed);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'warning' => $failed !== [] ? 'Some selected members were not submitted. Review the message above.' : null,
            ], 201);
        }

        return redirect()->route('admin.msg91.templates.index')->with('success', $message);
    }

    private function providerRejectionReason(array $payload): string
    {
        $candidates = [
            data_get($payload, 'errors.0.message'),
            data_get($payload, 'errors.0'),
            data_get($payload, 'errors.message'),
            data_get($payload, 'error.message'),
            data_get($payload, 'error_description'),
            data_get($payload, 'data.message'),
            data_get($payload, 'message'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = data_get($candidate, 'message')
                    ?? data_get($candidate, 'description');
            }

            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $reason = strip_tags((string) $candidate);
                $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $reason) ?? '';
                $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');

                if ($reason !== '') {
                    return mb_substr($reason, 0, 300);
                }
            }
        }

        return 'MSG91 rejected the template. Check the template fields and sender configuration.';
    }

 private function visibleTemplates(Request $request, bool $canManageAll)
{
    return Msg91WhatsAppTemplate::query()
        ->where('status', '!=', 'deleted')
        ->when(!$canManageAll, function ($query) use ($request) {
            $query->where(function ($templates) use ($request) {
                $templates
                    ->where('scope', 'central')
                    ->orWhere('owner_user_id', $request->user()->id);
            });
        });
}

}
