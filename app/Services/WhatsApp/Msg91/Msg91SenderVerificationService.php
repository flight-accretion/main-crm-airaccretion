<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Msg91WhatsAppSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Msg91SenderVerificationService
{
    /** Verify all configured senders using one read-only provider request. */
    public function syncAll(): array
    {
        $senders = Msg91WhatsAppSender::query()
            ->whereNotNull('integrated_number')
            ->get();

        // Failing to fetch the actual provider status must never create
        // a verified sender. A previous verification is now marked stale.
        try {
            $providerNumbers = $this->fetchProviderNumbers();
        } catch (\Throwable $e) {
            foreach ($senders as $sender) {
                $this->record($sender, 'unavailable', false);
            }
            throw $e;
        }

        $seen = 0;
        $verified = 0;
        foreach ($senders as $sender) {
            $number = $this->digits($sender->integrated_number);
            $state = $providerNumbers[$number] ?? null;
            $state = $state === null ? 'not_found' : $state;
            $connected = in_array(
                $state,
                config('services.msg91_campaigns.connected_states', ['connected']),
                true
            );
            $this->record($sender, $state, $connected);
            $seen++;
            if ($connected) {
                $verified++;
            }
        }

        return ['checked' => $seen, 'verified' => $verified];
    }

    private function record(Msg91WhatsAppSender $sender, string $state, bool $connected): void
    {
        $sender->verified_at = $connected ? now() : null;
        $sender->verified_by = null; // system/provider verification, not a human approval
        $sender->verification_meta = [
            'source' => 'msg91-number-status-api',
            'provider_status' => $state,
            'checked_at' => now()->toIso8601String(),
        ];
        // Never change `status`: Admin/Super Admin own CRM Active/Inactive.
        $sender->save();
    }

    /** @return array<string,string> normalized number => normalized provider status */
    private function fetchProviderNumbers(): array
    {
        $url = trim((string) config('services.msg91_campaigns.numbers_url', ''));
        $key = trim((string) config('services.msg91_campaigns.auth_key', ''));
        if ($url === '' || $key === '') {
            throw new RuntimeException('MSG91 number-status URL or auth key is not configured.');
        }

        // Restrict credentials to an official MSG91 HTTPS host.
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (
            ($parts['scheme'] ?? '') !== 'https'
            || !($host === 'msg91.com' || substr($host, -10) === '.msg91.com')
            || isset($parts['user']) || isset($parts['pass'])
        ) {
            throw new RuntimeException('MSG91 number-status URL must use an official HTTPS MSG91 host.');
        }

        // Do not follow redirects: never forward authkey to another host.
        $response = Http::acceptJson()
            ->withHeaders(['authkey' => $key])
            ->withOptions(['allow_redirects' => false, 'connect_timeout' => 10])
            ->timeout(30)
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('MSG91 number-status request failed (HTTP ' . $response->status() . ').');
        }
        $json = $response->json();
        if (!is_array($json) || data_get($json, 'hasError') === true) {
            throw new RuntimeException('MSG91 number-status response is invalid or rejected.');
        }

        $path = (string) config('services.msg91_campaigns.numbers_list_path', 'data');
        $rows = $path === '' ? $json : data_get($json, $path);
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException('MSG91 number list format is not recognized; check configured list path.');
        }

        $numberField = (string) config('services.msg91_campaigns.numbers_number_field');
        $statusField = (string) config('services.msg91_campaigns.numbers_status_field');
        $numbers = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('MSG91 number row is not an object.');
            }
           $rawNumber = data_get($row, $numberField);
            $rawStatus = data_get($row, $statusField);

            if (!is_scalar($rawNumber) || trim((string) $rawNumber) === '') {
                throw new RuntimeException(
                    'MSG91 returned an invalid integrated number.'
                );
            }

            // The whatsapp-activation API lists activated numbers.
            // It does not return an individual connection-status field.
            if ($rawStatus === null) {
                $rawStatus = 'activated';
            }

            if (!is_string($rawStatus) || trim($rawStatus) === '') {
                throw new RuntimeException(
                    'MSG91 returned an invalid sender status.'
                );
            }
            $number = $this->digits((string) $rawNumber);
            if ($number === '') {
                throw new RuntimeException('MSG91 returned a blank integrated number.');
            }
            $state = strtolower(trim($rawStatus));
            if (isset($numbers[$number])) {
                throw new RuntimeException('MSG91 returned duplicate sender numbers.');
            }
            $numbers[$number] = $state;
        }
        return $numbers;
    }

    private function digits(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?? '';
    }
}