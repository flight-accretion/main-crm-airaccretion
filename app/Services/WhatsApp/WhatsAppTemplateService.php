<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppTemplateService
{
    public function sendTemplate(
        string $sendTo,
        string $templateName,
        array $variables,
        ?string $mediaUri = null
    ): array {
        $url = trim((string) config('services.operations_ride_alert.api_url'));
        $apiKey = trim((string) config('services.operations_ride_alert.api_key'));
        $token = trim((string) config('services.operations_ride_alert.token'));

        if ($apiKey === '' || $token === '') {
            throw new RuntimeException('Operations WhatsApp credentials are missing.');
        }

        $sendTo = $this->normalizeNumber($sendTo);

        if ($sendTo === '') {
            throw new RuntimeException('Recipient WhatsApp number is invalid.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->post($url, [
                'sendTo' => $sendTo,
                'templetName' => $templateName,
                'exampleArr' => array_values(array_map(
                    fn ($value) => (string) $value,
                    $variables
                )),
                'token' => $token,
                'mediaUri' => $mediaUri ?: '',
            ]);

        if (!$response->successful()) {
            Log::warning('WhatsApp template request failed', [
                'sender' => 'operations',
                'template' => $templateName,
                'recipient' => $sendTo,
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException(
                'WhatsApp template API returned HTTP ' . $response->status()
            );
        }

        $json = $response->json();

        return [
            'success' => true,
            'provider_message_id' => data_get($json, 'message_id')
                ?? data_get($json, 'data.message_id')
                ?? data_get($json, 'id'),
            'response' => $json,
        ];
    }

    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        if (!$digits) {
            return '';
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        if (strlen($digits) < 11 || strlen($digits) > 15) {
            return '';
        }

        return '+' . $digits;
    }
}
