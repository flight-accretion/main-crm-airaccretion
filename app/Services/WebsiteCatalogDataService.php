<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebsiteCatalogDataService
{
    public function forProducts(
        Collection $products,
        array $state = []
    ): array {
        $serviceTypeIds = $this->serviceTypeIds($products, $state);

        if (empty($serviceTypeIds)) {
            return [
                'status' => 'not_mapped',
                'catalog' => [],
                'similar_services' => [],
            ];
        }

        $url = $this->catalogUrl();
        $secret = trim(
            (string) config('services.website_catalog.notes_secret')
        );

        if ($url === '' || $secret === '') {
            return [
                'status' => 'unavailable',
                'catalog' => [],
                'similar_services' => [],
                'message' => 'Website catalog configuration is missing.',
            ];
        }

        $payload = [
            'service_type_ids' => $serviceTypeIds,
            'query' => $this->firstText([
                $state['product_name'] ?? null,
                $state['service'] ?? null,
                $state['service_family'] ?? null,
                $state['current_customer_message'] ?? null,
            ]),
            'city' => $this->firstText([
                $state['city'] ?? null,
                $state['origin'] ?? null,
                $state['destination'] ?? null,
            ]),
            'limit_per_type' => 4,
            'similar_limit' => 4,
        ];

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $timestamp = (string) time();
        $signature = hash_hmac(
            'sha256',
            $timestamp . '.' . $body,
            $secret
        );

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'X-Accretion-Timestamp' => $timestamp,
                    'X-Accretion-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (!$response->successful()) {
                Log::warning(
                    'Website catalog data API returned an error.',
                    [
                        'service_type_ids' => $serviceTypeIds,
                        'http_status' => $response->status(),
                    ]
                );

                return [
                    'status' => 'unavailable',
                    'catalog' => [],
                    'similar_services' => [],
                ];
            }

            $payload = $response->json();

            if (!is_array($payload) || !($payload['success'] ?? false)) {
                return [
                    'status' => 'unavailable',
                    'catalog' => [],
                    'similar_services' => [],
                ];
            }

            return [
                'status' => 'ok',
                'catalog' => $this->normalizeCatalog(
                    $payload['catalog'] ?? []
                ),
                'similar_services' => $this->normalizeServices(
                    $payload['similar_services'] ?? []
                ),
            ];
        } catch (Throwable $exception) {
            Log::warning(
                'Website catalog data is temporarily unavailable.',
                [
                    'service_type_ids' => $serviceTypeIds,
                    'error' => $exception->getMessage(),
                ]
            );

            return [
                'status' => 'unavailable',
                'catalog' => [],
                'similar_services' => [],
            ];
        }
    }

    private function serviceTypeIds(
        Collection $products,
        array $state
    ): array {
        $productId = trim((string) ($state['product_id'] ?? ''));
        $productName = $this->normalize($state['product_name'] ?? null);
        $messageText = $this->normalize(
            $state['current_customer_message'] ?? null
        );

        $selectedProducts = $products
            ->filter(function (Product $product) use (
                $productId,
                $productName,
                $messageText
            ) {
                if ($productId !== ''
                    && (string) $product->id === $productId
                ) {
                    return true;
                }

                if ($productName !== '') {
                    return str_contains(
                        $this->normalize($product->product),
                        $productName
                    );
                }

                if ($messageText !== '') {
                    return $this->productMatchesMessage(
                        $product->product,
                        $messageText
                    );
                }

                return false;
            });

        return $selectedProducts
            ->pluck('website_service_type_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function productMatchesMessage(
        ?string $productName,
        string $messageText
    ): bool {
        $productText = $this->normalize($productName);

        if ($productText === '' || $messageText === '') {
            return false;
        }

        if (
            str_contains($messageText, $productText)
            || str_contains($productText, $messageText)
        ) {
            return true;
        }

        $ignored = [
            'and',
            'for',
            'from',
            'in',
            'of',
            'service',
            'services',
            'the',
            'to',
        ];
        $words = collect(explode(' ', $productText))
            ->filter(fn ($word) =>
                mb_strlen($word) >= 3
                && !in_array($word, $ignored, true)
            )
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return false;
        }

        $matches = $words
            ->filter(function ($word) use ($messageText) {
                $singular = str_ends_with($word, 's')
                    ? mb_substr($word, 0, -1)
                    : $word;

                return str_contains($messageText, $word)
                    || (
                        mb_strlen($singular) >= 3
                        && str_contains($messageText, $singular)
                    );
            })
            ->count();

        return $matches >= min(2, $words->count());
    }

    private function catalogUrl(): string
    {
        $url = trim(
            (string) config('services.website_catalog.catalog_url')
        );

        if ($url !== '') {
            return $url;
        }

        $notesUrl = trim(
            (string) config('services.website_catalog.notes_url')
        );

        if ($notesUrl === '') {
            return '';
        }

        return preg_replace(
            '/catalog-ai-notes\.php(?:\?.*)?$/',
            'catalog-products.php',
            $notesUrl
        ) ?: '';
    }

    private function normalizeCatalog(array $catalog): array
    {
        return collect($catalog)
            ->filter(fn ($entry) => is_array($entry))
            ->map(function (array $entry) {
                return [
                    'website_service_type_id' =>
                        isset($entry['website_service_type_id'])
                            ? (int) $entry['website_service_type_id']
                            : null,
                    'service_type_name' =>
                        $this->cleanText($entry['service_type_name'] ?? null),
                    'service_type_slug' =>
                        $this->cleanText($entry['service_type_slug'] ?? null),
                    'location' =>
                        is_array($entry['location'] ?? null)
                            ? $this->normalizeLocation($entry['location'])
                            : null,
                    'services' =>
                        $this->normalizeServices($entry['services'] ?? []),
                    'similar_services' =>
                        $this->normalizeServices(
                            $entry['similar_services'] ?? []
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeLocation(array $location): array
    {
        return [
            'location_id' => isset($location['location_id'])
                ? (int) $location['location_id']
                : null,
            'name' => $this->cleanText($location['name'] ?? null),
            'slug' => $this->cleanText($location['slug'] ?? null),
            'city' => $this->cleanText($location['city'] ?? null),
            'url' => $this->cleanText($location['url'] ?? null),
            'ai_note' => $this->cleanText($location['ai_note'] ?? null),
        ];
    }

    private function normalizeServices($services): array
    {
        return collect(is_array($services) ? $services : [])
            ->filter(fn ($service) => is_array($service))
            ->map(function (array $service) {
                return [
                    'website_service_product_id' =>
                        isset($service['website_service_product_id'])
                            ? (int) $service['website_service_product_id']
                            : null,
                    'name' => $this->cleanText($service['name'] ?? null),
                    'slug' => $this->cleanText($service['slug'] ?? null),
                    'url' => $this->cleanText($service['url'] ?? null),
                    'filtered_url' =>
                        $this->cleanText($service['filtered_url'] ?? null),
                    'price' => $this->cleanText($service['price'] ?? null),
                    'price_type' =>
                        $this->cleanText($service['price_type'] ?? null),
                    'duration' =>
                        $this->cleanText($service['duration'] ?? null),
                    'capacity' =>
                        $this->cleanText($service['capacity'] ?? null),
                    'city' => $this->cleanText($service['city'] ?? null),
                    'meeting_point' =>
                        $this->cleanText($service['meeting_point'] ?? null),
                    'highlights' =>
                        $this->cleanList($service['highlights'] ?? []),
                    'add_ons' =>
                        $this->normalizeAddOns($service['add_ons'] ?? []),
                    'ai_note' =>
                        $this->cleanText($service['ai_note'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeAddOns($addOns): array
    {
        return collect(is_array($addOns) ? $addOns : [])
            ->filter(fn ($addOn) => is_array($addOn))
            ->map(fn (array $addOn) => [
                'name' => $this->cleanText($addOn['name'] ?? null),
                'price' => $this->cleanText($addOn['price'] ?? null),
            ])
            ->filter(fn ($addOn) => $addOn['name'] !== null)
            ->values()
            ->all();
    }

    private function cleanList($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => $this->cleanText($item))
            ->filter()
            ->values()
            ->all();
    }

    private function firstText(array $values): ?string
    {
        foreach ($values as $value) {
            $clean = $this->cleanText($value);

            if ($clean !== null) {
                return $clean;
            }
        }

        return null;
    }

    private function cleanText($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?: null;
    }

    private function normalize($value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        return trim(
            preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '') ?: ''
        );
    }
}
