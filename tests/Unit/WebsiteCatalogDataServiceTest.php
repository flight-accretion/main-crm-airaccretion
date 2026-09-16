<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\WebsiteCatalogDataService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteCatalogDataServiceTest extends TestCase
{
    public function test_catalog_request_is_signed_and_normalized_for_mapped_products(): void
    {
        config()->set(
            'services.website_catalog.catalog_url',
            'https://www.accretionaviation.test/api/catalog-products.php'
        );
        config()->set(
            'services.website_catalog.notes_secret',
            'catalog-secret'
        );

        Http::fake([
            'https://www.accretionaviation.test/api/catalog-products.php' =>
                Http::response([
                    'success' => true,
                    'catalog' => [
                        [
                            'website_service_type_id' => 2,
                            'service_type_name' => 'Helicopter Joyride',
                            'location' => [
                                'name' => 'Mumbai',
                                'city' => 'Mumbai',
                                'ai_note' => 'Best seller for Mumbai short rides.',
                            ],
                            'services' => [
                                [
                                    'website_service_product_id' => 21,
                                    'name' => 'Mumbai Helicopter Joyride 30 Minutes',
                                    'url' => 'https://www.accretionaviation.test/mumbai-helicopter-joyride/30-minutes.php',
                                    'filtered_url' => 'https://www.accretionaviation.test/mumbai-helicopter-joyride.php?search=Mumbai%20Helicopter%20Joyride%2030%20Minutes',
                                    'price' => '34550',
                                    'duration' => '30 Minutes',
                                    'city' => 'Mumbai',
                                    'meeting_point' => 'Juhu Helipad',
                                    'highlights' => ['Mumbai skyline'],
                                    'add_ons' => [
                                        ['name' => 'Cake', 'price' => '950'],
                                    ],
                                ],
                            ],
                            'similar_services' => [
                                [
                                    'name' => 'Mumbai Helicopter Joyride 15 Minutes',
                                    'url' => 'https://www.accretionaviation.test/mumbai-helicopter-joyride/15-minutes.php',
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $result = app(WebsiteCatalogDataService::class)
            ->forProducts(collect([
                new Product([
                    'id' => 'product-heli',
                    'product' => 'Helicopter Joyride',
                    'website_service_type_id' => 2,
                ]),
            ]), [
                'city' => 'Mumbai',
                'product_name' => 'Helicopter Joyride',
            ]);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(
            'Mumbai Helicopter Joyride 30 Minutes',
            data_get($result, 'catalog.0.services.0.name')
        );
        $this->assertSame(
            'https://www.accretionaviation.test/mumbai-helicopter-joyride.php?search=Mumbai%20Helicopter%20Joyride%2030%20Minutes',
            data_get($result, 'catalog.0.services.0.filtered_url')
        );
        $this->assertSame(
            'Cake',
            data_get($result, 'catalog.0.services.0.add_ons.0.name')
        );

        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Accretion-Timestamp')[0] ?? '';
            $signature = $request->header('X-Accretion-Signature')[0] ?? '';
            $body = $request->body();
            $payload = json_decode($body, true);

            return $request->url()
                    === 'https://www.accretionaviation.test/api/catalog-products.php'
                && $payload['service_type_ids'] === [2]
                && $payload['city'] === 'Mumbai'
                && $payload['query'] === 'Helicopter Joyride'
                && hash_equals(
                    hash_hmac('sha256', $timestamp . '.' . $body, 'catalog-secret'),
                    $signature
                );
        });
    }

    public function test_missing_catalog_configuration_is_non_blocking(): void
    {
        config()->set('services.website_catalog.catalog_url', null);
        config()->set('services.website_catalog.notes_secret', null);

        $result = app(WebsiteCatalogDataService::class)
            ->forProducts(collect([
                new Product([
                    'id' => 'product-yacht',
                    'product' => 'Yacht',
                    'website_service_type_id' => 1,
                ]),
            ]), [
                'product_name' => 'Yacht',
            ]);

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame([], $result['catalog']);
    }

    public function test_current_message_limits_unselected_catalog_request_to_matching_products(): void
    {
        config()->set(
            'services.website_catalog.catalog_url',
            'https://www.accretionaviation.test/api/catalog-products.php'
        );
        config()->set(
            'services.website_catalog.notes_secret',
            'catalog-secret'
        );

        Http::fake([
            'https://www.accretionaviation.test/api/catalog-products.php' =>
                Http::response([
                    'success' => true,
                    'catalog' => [],
                ], 200),
        ]);

        app(WebsiteCatalogDataService::class)
            ->forProducts(collect([
                new Product([
                    'id' => 'product-yacht',
                    'product' => 'Yachts',
                    'website_service_type_id' => 1,
                ]),
                new Product([
                    'id' => 'product-heli',
                    'product' => 'Helicopter Joyride',
                    'website_service_type_id' => 2,
                ]),
            ]), [
                'current_customer_message' =>
                    'Mumbai helicopter joyride price?',
            ]);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload['service_type_ids'] === [2]
                && $payload['query'] === 'Mumbai helicopter joyride price?';
        });
    }
}
