<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WhatsAppAiPricingSheetService
{
    private const NOT_PROVIDED = 'Not provided by CRM';

    public function pricingData(): string
    {
        $url = $this->csvUrl();

        if (!$url) {
            return self::NOT_PROVIDED;
        }

        $ttl = (int) config(
            'whatcrm.pricing_sheet_cache_ttl',
            86400
        );

        if ($ttl <= 0) {
            return $this->fetchPricingData($url) ?: self::NOT_PROVIDED;
        }

        $cacheKey = 'whatcrm:pricing-sheet:' . sha1($url);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $pricingData = $this->fetchPricingData($url);

        if ($pricingData) {
            Cache::put(
                $cacheKey,
                $pricingData,
                now()->addSeconds($ttl)
            );

            return $pricingData;
        }

        return self::NOT_PROVIDED;
    }

    private function csvUrl(): ?string
    {
        $directUrl = trim(
            (string) config('whatcrm.pricing_sheet_csv_url')
        );

        if ($directUrl !== '') {
            return $directUrl;
        }

        $sheetId = trim(
            (string) config('whatcrm.pricing_sheet_id')
        );

        if ($sheetId === '') {
            return null;
        }

        $gid = trim(
            (string) config('whatcrm.pricing_sheet_gid', '0')
        );

        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
            rawurlencode($sheetId),
            rawurlencode($gid === '' ? '0' : $gid)
        );
    }

    private function fetchPricingData(string $url): ?string
    {
        try {
            $response = Http::timeout(
                (int) config('whatcrm.timeout', 10)
            )->get($url);

            if (!$response->successful()) {
                return null;
            }

            return $this->formatCsv(
                (string) $response->body()
            );
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function formatCsv(string $csv): ?string
    {
        $rows = preg_split(
            '/\r\n|\r|\n/',
            trim($csv)
        );

        if (!$rows || count($rows) < 2) {
            return null;
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader($header),
            str_getcsv(array_shift($rows))
        );

        if (count(array_filter($headers)) < 2) {
            return null;
        }

        $maxRows = max(
            1,
            (int) config('whatcrm.pricing_sheet_max_rows', 100)
        );

        $formatted = [];

        foreach ($rows as $row) {
            if (count($formatted) >= $maxRows) {
                break;
            }

            $values = str_getcsv($row);
            $record = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = trim(
                    (string) ($values[$index] ?? '')
                );
            }

            if (implode('', $record) === '') {
                continue;
            }

            $formatted[] = $this->formatRecord($record);
        }

        if (empty($formatted)) {
            return null;
        }

        return implode(PHP_EOL, $formatted);
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';

        return trim($header, '_');
    }

    private function formatRecord(array $record): string
    {
        $product = $this->firstValue(
            $record,
            ['product', 'service', 'product_name', 'service_name']
        );
        $city = $this->firstValue(
            $record,
            ['city', 'route', 'location', 'city_route']
        );
        $duration = $this->firstValue(
            $record,
            ['duration', 'time', 'package_duration']
        );
        $price = $this->firstValue(
            $record,
            ['price', 'amount', 'rate', 'customer_price']
        );
        $currency = $this->firstValue(
            $record,
            ['currency']
        );
        $notes = $this->firstValue(
            $record,
            ['notes', 'note', 'remarks', 'description']
        );

        $parts = array_filter([
            $product,
            $city,
            $duration,
            trim(($currency ? $currency . ' ' : '') . $price),
            $notes,
        ], fn ($value) => trim((string) $value) !== '');

        if (!empty($parts)) {
            return implode(' | ', $parts);
        }

        return collect($record)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value, $key) => $key . ': ' . $value)
            ->implode(' | ');
    }

    private function firstValue(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($record[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
