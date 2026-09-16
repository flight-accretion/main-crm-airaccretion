<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WhatsAppCrossSellService
{
    public function recommendations(array $state): array
    {
        return collect(config('whatsapp_product_recommendations', []))
            ->filter(fn ($rule) => (bool) data_get($rule, 'enabled', true))
            ->filter(fn ($rule) => $this->matches(
                (array) data_get($rule, 'when', []),
                $state
            ))
            ->sortByDesc(fn ($rule) => (int) data_get($rule, 'priority', 0))
            ->map(fn ($rule) => $this->buildRecommendation($rule, $state))
            ->filter()
            ->values()
            ->all();
    }

    private function buildRecommendation(array $rule, array $state): ?array
    {
        $primary = $this->matchingProduct(
            $state['service_family'] ?? null,
            $state['city'] ?? null
        );
        $alternative = $this->matchingProduct(
            data_get($rule, 'recommend.service_family'),
            data_get($rule, 'recommend.city')
        );

        if (!$alternative) {
            return null;
        }

        return [
            'type' => data_get($rule, 'type'),
            'business_reason' => data_get($rule, 'business_reason'),
            'product_id' => $alternative->id,
            'product_name' => $alternative->product,
            'comparison' => $this->valueComparison($primary, $alternative),
        ];
    }

    private function valueComparison(?Product $primary, Product $alternative): array
    {
        $primaryData = $primary ? $this->serviceEconomics($primary) : null;
        $alternativeData = $this->serviceEconomics($alternative);

        if (!$primaryData || !$alternativeData) {
            return [
                'comparison_available' => false,
            ];
        }

        $primaryPerMinute = $primaryData['price'] / $primaryData['duration'];
        $alternativePerMinute = $alternativeData['price'] / $alternativeData['duration'];
        $advantage = $primaryPerMinute > 0
            ? (($primaryPerMinute - $alternativePerMinute) / $primaryPerMinute) * 100
            : 0;

        return [
            'comparison_available' => true,
            'primary_price_per_minute' => round($primaryPerMinute, 2),
            'alternative_price_per_minute' => round($alternativePerMinute, 2),
            'better_value' => $alternativePerMinute < $primaryPerMinute
                ? 'alternative'
                : 'primary',
            'alternative_value_advantage_percent' => round($advantage, 1),
        ];
    }

    private function serviceEconomics(Product $product): ?array
    {
        $service = Service::query()
            ->where('status', 1)
            ->get()
            ->first(fn (Service $service) =>
                in_array(
                    (string) $product->id,
                    array_map('strval', (array) $service->product_ids),
                    true
                )
            );

        if (!$service) {
            return null;
        }

        $duration = (int) (
            $service->duration
            ?? $service->ride_duration
            ?? $service->total_time
            ?? 0
        );
        $price = (float) ($service->service_amount ?? 0);

        if ($duration <= 0 || $price <= 0) {
            return null;
        }

        return [
            'price' => $price,
            'duration' => $duration,
        ];
    }

    private function matchingProduct(?string $family, ?string $city): ?Product
    {
        $terms = [
            'helicopter_joyride' => ['helicopter', 'ride'],
            'plane_joyride' => ['plane', 'ride'],
            'yacht' => ['yacht'],
            'speed_boat' => ['speed boat', 'speedboat'],
        ][$family] ?? [];

        if (empty($terms)) {
            return null;
        }

        return Product::query()
            ->where('status', 1)
            ->orderBy('product')
            ->get()
            ->first(function (Product $product) use ($terms, $city) {
                $name = Str::lower((string) $product->product);

                if ($city && !Str::contains($name, Str::lower($city))) {
                    return false;
                }

                return collect(Arr::wrap($terms))
                    ->every(fn ($term) => Str::contains($name, $term));
            });
    }

    private function matches(array $criteria, array $state): bool
    {
        foreach ($criteria as $key => $value) {
            if (Str::lower((string) ($state[$key] ?? '')) !== Str::lower((string) $value)) {
                return false;
            }
        }

        return true;
    }
}
