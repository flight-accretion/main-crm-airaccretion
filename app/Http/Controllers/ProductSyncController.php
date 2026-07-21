<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductSyncController extends Controller
{
    /**
     * Display the Product Sync page
     */
    public function index()
    {
        // Show only active products that are not yet synced (sync_at = 0).
        $products = Product::where('status', 1)->where('sync_at', 0)->get();

        return view('admin.pages.products.product-sync', compact('products'));
    }

    /**
     * Sync all products to Airpoints
     */
    public function syncProducts(Request $request)
    {
        try {
            // Config entry: config('services.airpoints.base_url')
            $airpointsUrl = config('services.airpoints.base_url');

            if (!$airpointsUrl) {
                Log::error('AIRPOINTS_API_URL not configured. Set AIRPOINTS_API_URL in .env or config/services.php');
                return response()->json([
                    'success' => false,
                    'message' => 'Airpoints API URL not configured. Please set AIRPOINTS_API_URL in .env or add services.airpoints.base_url in config/services.php'
                ], 500);
            }

            // Only sync active products that have not been synced yet (sync_at = 0).
            $products = Product::where('status', 1)->where('sync_at', 0)->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active (unsynced) products found to sync'
                ], 404);
            }

            // normalize and prepare sync URL
            $results = [];
            $created = 0;
            $existing = 0;
            $failed = 0;
            $configured = trim($airpointsUrl);
            $base = rtrim($configured, '/');
            if (preg_match('#/api/products/sync$#i', $base) || preg_match('#/products/sync$#i', $base)) {
                $syncUrl = $base; // already contains the path
            } else {
                $syncUrl = $base . '/products/sync';
            }

            // (silent) use configured URL; do not emit noisy debug logs in production

            foreach ($products as $product) {
                $url = $syncUrl;
                $payload = ['name' => $product->product];

                try {
                    // Keep outgoing request non-verbosely handled; errors will be logged below

                    // Call Airpoints API to sync product
                    $response = Http::timeout(10)->post($url, $payload);

                    $statusCode = $response->status();

                    if ($response->successful()) {
                        try {
                            $data = $response->json();
                        } catch (\Exception $jsonErr) {
                            Log::error('Failed to parse JSON from Airpoints', [
                                'product_uuid' => $product->id,
                                'url' => $url,
                                'status' => $statusCode,
                                'body' => $response->body(),
                                'json_error' => $jsonErr->getMessage()
                            ]);
                            
                            $results[] = [
                                'product_uuid' => $product->id,
                                'product_name' => $product->product,
                                'status' => 'error',
                                'message' => 'Invalid JSON response from Airpoints: ' . $jsonErr->getMessage(),
                                'request_url' => $url,
                                'request_payload' => $payload,
                                'success' => false
                            ];
                            $failed++;
                            continue;
                        }

                        // Successful response; don't log full payloads to avoid noise in production

                        $results[] = [
                            'product_uuid' => $product->id,
                            'product_name' => $product->product,
                            'status' => $data['status'] ?? 'unknown',
                            'airpoints_id' => $data['id'] ?? null,
                            'airpoints_name' => $data['name'] ?? null,
                            'success' => true,
                            'raw_response' => $data
                        ];

                        // Mark as synced (sync_at = 1) if products table has the column.
                        try {
                            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'sync_at')) {
                                $product->sync_at = 1;
                                $product->save();
                            }
                        } catch (\Exception $e) {
                            // Log and continue; don't fail the whole sync if DB write fails
                            Log::warning('Failed to persist sync_at for product', [
                                'product_uuid' => $product->id,
                                'error' => $e->getMessage()
                            ]);
                        }

                        if (isset($data['status'])) {
                            if ($data['status'] === 'created') {
                                $created++;
                            } elseif ($data['status'] === 'existing') {
                                $existing++;
                            }
                        }
                    } else {
                        $body = $response->body();

                        Log::warning('Product sync response (non-success)', [
                            'product_uuid' => $product->id,
                            'url' => $url,
                            'status' => $statusCode
                        ]);

                        $results[] = [
                            'product_uuid' => $product->id,
                            'product_name' => $product->product,
                            'status' => 'error',
                            'message' => 'API returned status: ' . $statusCode,
                            'status_code' => $statusCode,
                            'response_body' => $body,
                            'request_url' => $url,
                            'request_payload' => $payload,
                            'success' => false
                        ];
                        $failed++;
                    }
                } catch (\Exception $e) {
                    Log::error('Product sync exception for product', [
                        'product_uuid' => $product->id,
                        'product_name' => $product->product,
                        'exception' => $e->getMessage()
                    ]);

                    $results[] = [
                        'product_uuid' => $product->id,
                        'product_name' => $product->product,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'request_url' => $url,
                        'request_payload' => $payload,
                        'success' => false
                    ];
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product sync completed',
                'summary' => [
                    'total' => $products->count(),
                    'created' => $created,
                    'existing' => $existing,
                    'failed' => $failed
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Product sync error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error during sync: ' . $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ], 500);
        }
    }
}
