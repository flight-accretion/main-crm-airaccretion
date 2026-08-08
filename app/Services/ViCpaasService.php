<?php

namespace App\Services;

use App\Models\IvrCallType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ViCpaasService
{
    public function getToken(): string
    {
        $cacheKey = 'vi_cpaas_pull_report_token';
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.vi_cpaas.timeout', 30))
            ->post(config('services.vi_cpaas.auth_url'), [
                'username' => config('services.vi_cpaas.username'),
                'password' => config('services.vi_cpaas.password'),
                'dni' => config('services.vi_cpaas.dni'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('VI CPaaS authentication failed with HTTP ' . $response->status() . ': ' . $response->body());
        }

        $token = (string) $response->json('idToken', '');
        $expiresIn = (int) $response->json('expiresIn', 86400);
        if ($token === '') {
            throw new RuntimeException('VI CPaaS authentication response does not contain idToken.');
        }

        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 300)));
        return $token;
    }

    public function pullReport(IvrCallType $callType, string $fromDate, string $toDate): array
    {
        $payload = [
            'dni' => config('services.vi_cpaas.dni'),
            'fromdate' => $fromDate,
            'todate' => $toDate,
            'calltype' => (string) $callType->code,
        ];

        $campaignId = trim((string) config('services.vi_cpaas.campaign_id', ''));
        if ($campaignId !== '') {
            $payload['campaignid'] = $campaignId;
        }

        $response = $this->sendPullRequest($payload, $this->getToken());

        if ($response->status() === 401) {
            Cache::forget('vi_cpaas_pull_report_token');
            $response = $this->sendPullRequest($payload, $this->getToken());
        }

        if ($response->failed()) {
            throw new RuntimeException('VI CPaaS pull report failed with HTTP ' . $response->status() . ': ' . $response->body());
        }

        $json = $response->json();
        return $this->extractRecords($json);
    }

    private function sendPullRequest(array $payload, string $token)
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('services.vi_cpaas.timeout', 60))
            ->retry(2, 1000)
            ->post(config('services.vi_cpaas.report_url'), $payload);
    }

    private function extractRecords($json): array
    {
        if (!is_array($json)) {
            return [];
        }

        if ($this->isList($json)) {
            return $json;
        }

        foreach (['data', 'result', 'records', 'report', 'callDetails', 'calldetails'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $records = $this->findRecordList($json[$key], 0);
                if ($records !== null) {
                    return $records;
                }
            }
        }

        return $this->findRecordList($json, 0) ?? [];
    }

    private function findRecordList(array $value, int $depth): ?array
    {
        if ($depth > 3) {
            return null;
        }

        if ($this->isList($value)) {
            if ($value === [] || (isset($value[0]) && is_array($value[0]))) {
                return $value;
            }
            return null;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $records = $this->findRecordList($child, $depth + 1);
                if ($records !== null) {
                    return $records;
                }
            }
        }

        return null;
    }

    private function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}
