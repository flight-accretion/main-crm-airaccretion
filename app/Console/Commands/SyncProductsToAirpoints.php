<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncProductsToAirpoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'airpoints:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync active unsynced products to Airpoints (run every 15 days)';

    public function handle()
    {
        // Structured run logging
        Log::info('airpoints.product_sync.started', ['timestamp' => now()->toDateTimeString()]);
        $this->info('Starting product sync to Airpoints...');

        try {
            $airpointsUrl = config('services.airpoints.base_url');

            if (!$airpointsUrl) {
                $this->error('AIRPOINTS_API_URL not configured. Set AIRPOINTS_API_URL in .env or config/services.php');
                Log::error('airpoints.product_sync.failed', ['reason' => 'AIRPOINTS_API_URL not configured']);
                return 1;
            }

            $products = Product::where('status', 1)->where('sync_at', 0)->get();

            Log::info('airpoints.product_sync.found_products', ['count' => $products->count()]);

            if ($products->isEmpty()) {
                $this->info('No active unsynced products found.');
                Log::info('airpoints.product_sync.nothing_to_sync');
                return 0;
            }

            $configured = trim($airpointsUrl);
            $base = rtrim($configured, '/');
            if (preg_match('#/api/products/sync$#i', $base) || preg_match('#/products/sync$#i', $base)) {
                $syncUrl = $base;
            } else {
                $syncUrl = $base . '/products/sync';
            }

            $created = 0;
            $existing = 0;
            $failed = 0;

            foreach ($products as $product) {
                $url = $syncUrl;
                $payload = ['name' => $product->product];

                try {
                    $this->info("Syncing product: {$product->product}");

                    // Use a slightly higher timeout, a connect timeout and retries to
                    // mitigate transient DNS/connectivity issues (cURL error 28).
                    $response = Http::withOptions(['connect_timeout' => 10])
                        ->timeout(30)
                        ->retry(3, 2000)
                        ->post($url, $payload);

                    if ($response->successful()) {
                        $data = null;
                        try {
                            $data = $response->json();
                        } catch (\Exception $e) {
                            Log::error('airpoints.product_sync.product_invalid_json', ['product_id' => $product->id, 'error' => $e->getMessage()]);
                            $this->error('Invalid JSON response for product: ' . $product->id);
                            $failed++;
                            continue;
                        }

                        // Per-product success log
                        Log::info('airpoints.product_sync.product_success', [
                            'product_id' => $product->id,
                            'product_name' => $product->product,
                            'endpoint' => $url,
                            'response_status' => $data['status'] ?? null,
                            'airpoints_id' => $data['id'] ?? null,
                        ]);

                        $this->info('Product sync response: ' . ($data['status'] ?? 'unknown'));

                        try {
                            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'sync_at')) {
                                $product->sync_at = 1;
                                $product->save();
                            }
                        } catch (\Exception $e) {
                            Log::warning('airpoints.product_sync.persist_sync_at_failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
                        }

                        if (isset($data['status'])) {
                            if ($data['status'] === 'created') $created++;
                            if ($data['status'] === 'existing') $existing++;
                        }
                    } else {
                        $this->error('Product sync failed: HTTP ' . $response->status());
                        Log::warning('airpoints.product_sync.product_failed', ['product_id' => $product->id, 'status' => $response->status(), 'body' => $response->body(), 'endpoint' => $url]);
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error('Exception syncing product ' . $product->id . ': ' . $e->getMessage());

                    // Special-case cURL timeout (DNS resolution / connect timeouts)
                    if (stripos($e->getMessage(), 'cURL error 28') !== false || stripos($e->getMessage(), 'timed out') !== false) {
                        Log::error('airpoints.product_sync.product_exception.dns_timeout', ['product_id' => $product->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'endpoint' => $url]);
                    } else {
                        Log::error('airpoints.product_sync.product_exception', ['product_id' => $product->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'endpoint' => $url]);
                    }

                    $failed++;
                }
            }

            $summary = ['total' => $products->count(), 'created' => $created, 'existing' => $existing, 'failed' => $failed];
            $this->info('Product sync completed. Total: ' . $summary['total'] . ', created: ' . $summary['created'] . ', existing: ' . $summary['existing'] . ', failed: ' . $summary['failed']);

            Log::info('airpoints.product_sync.completed', $summary + ['timestamp' => now()->toDateTimeString()]);

            return 0;

        } catch (\Exception $e) {
            Log::error('airpoints.product_sync.failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Product sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
