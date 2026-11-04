<?php

namespace App\Services\WhatsApp\Msg91;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Msg91Client
{
    public function createTemplate(array $payload): array
    {
        if (!(bool) config('services.msg91_campaigns.template_create_enabled', false)) {
            throw new RuntimeException('MSG91 template creation is disabled.');
        }

        $authKey = trim((string) config('services.msg91_campaigns.auth_key'));
        if ($authKey === '') {
            throw new RuntimeException('MSG91 campaign credentials are not configured.');
        }

        // A dry run must never submit a provider template for review.
        if ((bool) config('services.msg91_campaigns.dry_run', true)) {
            return [
                'accepted' => true,
                'template_id' => 'dry-run-template-' . uniqid('', true),
                'payload' => ['dry_run' => true, 'request' => $payload],
            ];
        }

        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['authkey' => $authKey])
                ->withOptions(['connect_timeout' => (int) config('services.msg91_campaigns.connect_timeout', 10)])
                ->timeout((int) config('services.msg91_campaigns.timeout', 30))
                ->post(rtrim((string) config('services.msg91_campaigns.template_create_url'), '/') . '/api/v5/whatsapp/client-panel-template/', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('MSG91 template creation did not receive a response.', 0, $exception);
        }

        if (!$response->successful()) {
            throw new Msg91ProviderException('MSG91 template creation failed with HTTP ' . $response->status(), $response->status(), $response->json() ?: []);
        }

        $body = $response->json() ?: [];
        if (data_get($body, 'hasError') === true || strtolower((string) data_get($body, 'status')) === 'error') {
            throw new Msg91ProviderException('MSG91 rejected the template.', $response->status(), $body);
        }

        return [
            'accepted' => true,
            'template_id' => data_get($body, 'data.template_id') ?? data_get($body, 'template_id') ?? data_get($body, 'id'),
            'payload' => $body,
        ];
    }

    public function fetchTemplates(string $integratedNumber): array
    {
        if (!(bool) config('services.msg91_campaigns.template_sync_enabled', false)) {
            throw new RuntimeException('MSG91 template sync is disabled.');
        }

        $authKey = trim((string) config('services.msg91_campaigns.auth_key'));
        if ($authKey === '') {
            throw new RuntimeException('MSG91 campaign credentials are not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['authkey' => $authKey])
                ->withOptions(['connect_timeout' => (int) config('services.msg91_campaigns.connect_timeout', 10)])
                ->timeout((int) config('services.msg91_campaigns.timeout', 30))
                ->get(rtrim((string) config('services.msg91_campaigns.control_url'), '/') . '/api/v5/whatsapp/get-template-client/' . rawurlencode($integratedNumber));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('MSG91 template sync did not receive a response.', 0, $exception);
        }

        if (!$response->successful()) {
            throw new Msg91ProviderException('MSG91 template sync failed with HTTP ' . $response->status(), $response->status(), $response->json() ?: []);
        }

        $body = $response->json() ?: [];
        $templates = data_get($body, 'data.templates', data_get($body, 'data', data_get($body, 'templates', [])));
        if (!is_array($templates)) {
            throw new RuntimeException('MSG91 template sync returned an unexpected response.');
        }

        return $templates;
    }

    public function sendTemplate(string $integratedNumber, array $template, string $phone, array $components): array
    {
        if (!(bool) config('services.msg91_campaigns.enabled', false)) {
            throw new RuntimeException('MSG91 campaigns are disabled.');
        }
        if ((bool) config('services.msg91_campaigns.dry_run', true)) {
            return ['accepted' => true, 'request_id' => 'dry-run-' . uniqid('', true), 'payload' => ['dry_run' => true]];
        }

        $authKey = trim((string) config('services.msg91_campaigns.auth_key'));
        if ($authKey === '') { throw new RuntimeException('MSG91 campaign credentials are not configured.'); }

        try {
            $response = Http::acceptJson()->asJson()->withHeaders(['authkey' => $authKey])
                ->withOptions(['connect_timeout' => (int) config('services.msg91_campaigns.connect_timeout', 10)])
                ->timeout((int) config('services.msg91_campaigns.timeout', 30))
                ->post(rtrim((string) config('services.msg91_campaigns.control_url'), '/') . '/api/v5/whatsapp/whatsapp-outbound-message/bulk/', [
                    'integrated_number' => $integratedNumber,
                    'content_type' => 'template',
                    'payload' => [
                        'messaging_product' => 'whatsapp',
                        'type' => 'template',
                        'template' => [
                            'name' => $template['name'],
                            'language' => ['code' => $template['language'], 'policy' => 'deterministic'],
                            'to_and_components' => [['to' => [$phone], 'components' => $components]],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new Msg91AmbiguousSubmissionException('MSG91 submission did not receive a response.', 0, $exception);
        }

        if (!$response->successful()) {
            throw new Msg91ProviderException('MSG91 rejected the request with HTTP ' . $response->status(), $response->status(), $response->json() ?: []);
        }
        $body = $response->json() ?: [];
        if (data_get($body, 'hasError') === true || strtolower((string) data_get($body, 'status')) === 'error') {
            throw new Msg91ProviderException('MSG91 rejected the request.', $response->status(), $body);
        }
        return ['accepted' => true, 'request_id' => data_get($body, 'request_id') ?? data_get($body, 'data.request_id'), 'payload' => $body];
    }
}

class Msg91ProviderException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 0, public array $payload = []) { parent::__construct($message); }
    public function isRetryable(): bool { return $this->httpStatus === 429 || $this->httpStatus >= 500; }
}

class Msg91AmbiguousSubmissionException extends RuntimeException {}
