<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadProductRoutingService
{
    private const CHARTER_KEYWORD_GROUPS = [
        'ambulance' => [
            'air ambulance',
            'ambulance',
        ],
        'charter' => [
            'charter',
        ],
        'dham' => [
            'char dham',
            'chardham',
            'dham',
        ],
        'shirdi' => [
            'shirdi',
            'shridi',
        ],
        'vaishnodevi' => [
            'vaishno devi',
            'vaishnodevi',
            'vaishno',
            'beshnodevi',
            'beshno devi',
            'baishnodevi',
        ],
        'flower_shower' => [
            'flower shower',
            'flowershower',
        ],
    ];

    public function resolveProduct(?string $serviceName): ?Product
    {
        $needle = $this->normalize($serviceName);

        if (
            $needle === ''
            || in_array(
                $needle,
                [
                    'n a',
                    'na',
                    'not available',
                ],
                true
            )
        ) {
            return null;
        }

        $products = $this->activeProducts();

        $exactProduct = $products->first(function (Product $product) use ($needle) {
            return $this->normalize($product->product) === $needle;
        });

        if ($exactProduct) {
            return $exactProduct;
        }

        $phraseProduct = $products->first(function (Product $product) use ($needle) {
            $productName = $this->normalize($product->product);

            if ($productName === '') {
                return false;
            }

            return Str::contains($needle, $productName)
                || (
                    mb_strlen($needle) >= 4
                    && Str::contains($productName, $needle)
                );
        });

        if ($phraseProduct) {
            return $phraseProduct;
        }

        $needleKeywords = $this->matchedCharterKeywords($needle);

        if (empty($needleKeywords)) {
            return null;
        }

        return $products->first(function (Product $product) use ($needleKeywords) {
            $productKeywords = $this->matchedCharterKeywords(
                $product->product
            );

            return !empty(
                array_intersect(
                    $needleKeywords,
                    $productKeywords
                )
            );
        });
    }

    public function isCharterProduct(
        ?Product $product,
        ?string $sourceText = null
    ): bool {
        if ($product) {
            if (
                (bool) $product->getAttribute('is_private')
                || (bool) $product->getAttribute('is_airambulance')
            ) {
                return true;
            }

            if ($this->hasCharterKeyword($product->product)) {
                return true;
            }
        }

        return $this->hasCharterKeyword($sourceText);
    }

    public function charterProductIds(): Collection
    {
        return $this->activeProducts()
            ->filter(function (Product $product) {
                return $this->isCharterProduct($product);
            })
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();
    }

    public function normalize(?string $value): string
    {
        $value = Str::lower(
            trim(
                (string) $value
            )
        );

        if ($value === '') {
            return '';
        }

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?: '';

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value
            ) ?: ''
        );
    }

    private function activeProducts(): Collection
    {
        return Product::query()
            ->where('status', 1)
            ->orderBy('product')
            ->get();
    }

    private function hasCharterKeyword(?string $value): bool
    {
        return !empty(
            $this->matchedCharterKeywords($value)
        );
    }

    private function matchedCharterKeywords(?string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        $compact = str_replace(
            ' ',
            '',
            $normalized
        );

        $matches = [];

        foreach (self::CHARTER_KEYWORD_GROUPS as $group => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = $this->normalize($keyword);
                $keywordCompact = str_replace(
                    ' ',
                    '',
                    $keyword
                );

                if (
                    $keyword !== ''
                    && (
                        Str::contains($normalized, $keyword)
                        || Str::contains($compact, $keywordCompact)
                    )
                ) {
                    $matches[] = $group;
                    break;
                }
            }
        }

        return array_values(
            array_unique($matches)
        );
    }
}
