<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\LeadRide;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Airpoints Integration Service
 * 
 * Handles all interactions with the Ace Points (Airpoints) system including:
 * - Customer synchronization
 * - Product synchronization
 * - Point claims (ride completion rewards)
 * 
 * Note: Airpoints uses regular integer IDs while Accretion Aviation uses UUIDs
 */
class AirpointsIntegrationService
{
    /**
     * Base URL for Airpoints API
     */
    protected $baseUrl;

    /**
     * Timeout for API requests (in seconds)
     */
    protected $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.airpoints.base_url', 'https://airpoints.airaccretion.com'), '/');
    }

    /**
     * Sync or get customer from Airpoints based on phone number
     * 
     * @param Client $client The client from Accretion Aviation
     * @return array Response containing 'success', 'customer_id', 'status', and 'message'
     */
    public function syncCustomer(Client $client)
    {
        try {
            // Extract phone number without country code for matching
            $phoneWithoutCountryCode = $this->extractPhoneWithoutCountryCode($client->contact_number);

            if (empty($phoneWithoutCountryCode)) {
                Log::warning('Customer sync skipped - no valid phone number', [
                    'client_id' => $client->id,
                    'client_name' => $client->name
                ]);

                return [
                    'success' => false,
                    'message' => 'No valid phone number found',
                    'customer_id' => null
                ];
            }

            // Prepare customer data
            $customerData = [
                'name' => $client->name ?? 'Unknown',
                'email' => $client->email ?? '',
                'phone' => $phoneWithoutCountryCode,
                'country_code' => $this->extractCountryCode($client->contact_number),
                'address' => $client->address ?? '',
                'date_of_birth' => $client->date_of_birth ?? null,
            ];

            $url = $this->baseUrl . '/api/customers/sync';

            Log::info('Syncing customer to Airpoints', [
                'client_uuid' => $client->id,
                'phone' => $phoneWithoutCountryCode,
                'url' => $url
            ]);

            // Call Airpoints API (use connect timeout + retries to mitigate transient DNS/connect issues)
            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000)
                ->post($url, $customerData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Customer synced successfully', [
                    'client_uuid' => $client->id,
                    'airpoints_customer_id' => $data['customer_id'] ?? null,
                    'status' => $data['status'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'customer_id' => $data['customer_id'] ?? null,
                    'status' => $data['status'] ?? 'unknown', // 'created' or 'existing'
                    'message' => $data['message'] ?? 'Customer synced',
                    'data' => $data
                ];
            } else {
                Log::error('Customer sync failed', [
                    'client_uuid' => $client->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'API returned status: ' . $response->status(),
                    'customer_id' => null
                ];
            }
        } catch (Exception $e) {
            Log::error('Customer sync exception', [
                'client_uuid' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'customer_id' => null
            ];
        }
    }

    /**
     * Sync or get product from Airpoints
     * 
     * @param Product $product The product from Accretion Aviation
     * @return array Response containing 'success', 'product_id', 'status', and 'message'
     */
    public function syncProduct(Product $product)
    {
        try {
            $productData = [
                'name' => $product->product ?? 'Unknown Product',
            ];

            $url = $this->baseUrl . '/api/products/sync';

            Log::info('Syncing product to Airpoints', [
                'product_uuid' => $product->id,
                'product_name' => $product->product,
                'url' => $url
            ]);

            // Call Airpoints API (use connect timeout + retries to mitigate transient DNS/connect issues)
            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000)
                ->post($url, $productData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Product synced successfully', [
                    'product_uuid' => $product->id,
                    'airpoints_product_id' => $data['product_id'] ?? null,
                    'status' => $data['status'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'product_id' => $data['product_id'] ?? null,
                    'status' => $data['status'] ?? 'unknown', // 'created' or 'existing'
                    'message' => $data['message'] ?? 'Product synced',
                    'data' => $data
                ];
            } else {
                Log::error('Product sync failed', [
                    'product_uuid' => $product->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'API returned status: ' . $response->status(),
                    'product_id' => null
                ];
            }
        } catch (Exception $e) {
            Log::error('Product sync exception', [
                'product_uuid' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'product_id' => null
            ];
        }
    }

    /**
     * Create a point claim in Airpoints when a ride is completed
     * 
     * @param LeadRide $ride The completed ride
     * @param int $customerId Airpoints customer ID (regular integer ID)
     * @param int $productId Airpoints product ID (regular integer ID)
     * @param float $amount Ride amount
     * @param int $points Points to award
     * @return array Response containing 'success' and 'message'
     */
    public function createPointClaim($ride, $customerId, $productId, $amount, $points)
    {
        try {
            $claimData = [
                'customer_id' => $customerId,
                'product_id' => $productId,
                'amount' => $amount,
                'points' => $points,
                'service_date' => $ride->from_date ? $ride->from_date->format('Y-m-d') : now()->format('Y-m-d'),
                'ride_uuid' => $ride->id, // Store CRM ride UUID for reference and duplicate prevention
                'created_by_crm' => auth()->id() ?? null, // CRM user who completed the ride
            ];

            $url = $this->baseUrl . '/api/point-claims/create';

            Log::info('Creating point claim in Airpoints', [
                'ride_uuid' => $ride->id,
                'customer_id' => $customerId,
                'product_id' => $productId,
                'points' => $points,
                'amount' => $amount
            ]);

            // Call Airpoints API (use connect timeout + retries to mitigate transient DNS/connect issues)
            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000)
                ->post($url, $claimData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Point claim created successfully', [
                    'ride_uuid' => $ride->id,
                    'point_claim_id' => $data['point_claim_id'] ?? null,
                    'status' => $data['status'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'point_claim_id' => $data['point_claim_id'] ?? null,
                    'message' => $data['message'] ?? 'Points claimed successfully',
                    'data' => $data
                ];
            } else {
                $responseBody = $response->body();
                Log::error('Point claim creation failed', [
                    'ride_uuid' => $ride->id,
                    'status_code' => $response->status(),
                    'response' => $responseBody
                ]);

                return [
                    'success' => false,
                    'message' => 'API returned status: ' . $response->status() . ' - ' . $responseBody
                ];
            }
        } catch (Exception $e) {
            Log::error('Point claim creation exception', [
                'ride_uuid' => $ride->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process completed ride - sync customer, product, and create point claim
     * 
     * @param LeadRide $ride The completed ride
     * @param float $totalAmount Total ride amount
     * @return array Response containing 'success', 'message', and sync details
     */
    public function processCompletedRide(LeadRide $ride, $totalAmount)
    {
        try {
            // Load necessary relationships (only proper relationships)
            $ride->load(['enquiry.client']);

            if (!$ride->enquiry) {
                return [
                    'success' => false,
                    'message' => 'No enquiry found for this ride'
                ];
            }

            if (!$ride->enquiry->client) {
                return [
                    'success' => false,
                    'message' => 'No client found for this ride'
                ];
            }

            $client = $ride->enquiry->client;
            
            // Get products (this calls the method, not eager loading)
            $products = $ride->enquiry->products;
            
            // If products is a method that returns a collection, call it
            if (!$products && method_exists($ride->enquiry, 'products')) {
                $products = $ride->enquiry->products();
            }
            
            // Convert to collection if it's an array
            if (is_array($products)) {
                $products = collect($products);
            }

            // If no products, we can't create a point claim
            if (!$products || (is_object($products) && method_exists($products, 'isEmpty') && $products->isEmpty())) {
                return [
                    'success' => false,
                    'message' => 'No products found for this ride'
                ];
            }

            // Use the first product for point claim (you can adjust logic as needed)
            $product = is_object($products) && method_exists($products, 'first') ? $products->first() : (is_array($products) ? reset($products) : null);

            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'No valid product found for this ride'
                ];
            }

            Log::info('Processing completed ride for Airpoints sync', [
                'ride_uuid' => $ride->id,
                'client_uuid' => $client->id,
                'product_uuid' => $product->id ?? 'unknown',
                'product_name' => $product->product ?? 'unknown',
                'total_amount' => $totalAmount
            ]);

            // Step 1: Sync customer
            $customerSync = $this->syncCustomer($client);
            
            if (!$customerSync['success'] || !$customerSync['customer_id']) {
                return [
                    'success' => false,
                    'message' => 'Customer sync failed: ' . ($customerSync['message'] ?? 'Unknown error'),
                    'customer_sync' => $customerSync
                ];
            }

            // Step 2: Sync product
            $productSync = $this->syncProduct($product);
            
            if (!$productSync['success'] || !$productSync['product_id']) {
                return [
                    'success' => false,
                    'message' => 'Product sync failed: ' . ($productSync['message'] ?? 'Unknown error'),
                    'customer_sync' => $customerSync,
                    'product_sync' => $productSync
                ];
            }

            // Step 3: Calculate points (assuming 1 point per 100 rupees, adjust as needed)
            $points = $this->calculatePoints($totalAmount);

            // Step 4: Create point claim
            $pointClaimResult = $this->createPointClaim(
                $ride,
                $customerSync['customer_id'],
                $productSync['product_id'],
                $totalAmount,
                $points
            );

            if (!$pointClaimResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Point claim creation failed: ' . ($pointClaimResult['message'] ?? 'Unknown error'),
                    'customer_sync' => $customerSync,
                    'product_sync' => $productSync,
                    'point_claim' => $pointClaimResult
                ];
            }

            // All successful
            Log::info('Ride successfully processed and synced to Airpoints', [
                'ride_uuid' => $ride->id,
                'customer_id' => $customerSync['customer_id'],
                'product_id' => $productSync['product_id'],
                'points' => $points,
                'point_claim_id' => $pointClaimResult['point_claim_id']
            ]);

            return [
                'success' => true,
                'message' => 'Ride synced successfully to Ace Points',
                'customer_sync' => $customerSync,
                'product_sync' => $productSync,
                'point_claim' => $pointClaimResult,
                'points_awarded' => $points
            ];

        } catch (Exception $e) {
            Log::error('Ride processing exception', [
                'ride_uuid' => $ride->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate points based on amount
     * 
     * @param float $amount
     * @return int
     */
    protected function calculatePoints($amount)
    {
        // Default logic: 1 point per 100 rupees
        // You can adjust this based on business rules
        $pointsPerHundred = 1;
        return (int) round(($amount / 100) * $pointsPerHundred);
    }

    /**
     * Extract phone number without country code
     * 
     * Airpoints stores phone without country code, Accretion has country code
     * 
     * @param string $phone
     * @return string
     */
    protected function extractPhoneWithoutCountryCode($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Common country codes to remove
        $countryCodes = ['91', '1', '44', '61', '971']; // India, USA, UK, Australia, UAE

        foreach ($countryCodes as $code) {
            if (str_starts_with($cleaned, $code)) {
                // Remove country code and return
                return substr($cleaned, strlen($code));
            }
        }

        // If no country code detected, return last 10 digits (common mobile length)
        if (strlen($cleaned) > 10) {
            return substr($cleaned, -10);
        }

        return $cleaned;
    }

    /**
     * Extract country code from phone number
     * 
     * @param string $phone
     * @return int
     */
    protected function extractCountryCode($phone)
    {
        if (empty($phone)) {
            return 91; // Default to India
        }

        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Check for common country codes
        if (str_starts_with($cleaned, '91') && strlen($cleaned) > 10) {
            return 91; // India
        } elseif (str_starts_with($cleaned, '971') && strlen($cleaned) > 9) {
            return 971; // UAE
        } elseif (str_starts_with($cleaned, '44') && strlen($cleaned) > 10) {
            return 44; // UK
        } elseif (str_starts_with($cleaned, '1') && strlen($cleaned) === 11) {
            return 1; // USA/Canada
        }

        return 91; // Default to India
    }

    /**
     * Check if a ride has already been synced to Airpoints
     * 
     * @param string $rideUuid
     * @return array Response with 'synced' boolean and details
     */
    public function checkRideSynced($rideUuid)
    {
        try {
            $url = $this->baseUrl . '/api/point-claims/check-ride';

            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000)
                ->post($url, [
                    'ride_uuid' => $rideUuid
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'synced' => $data['synced'] ?? false,
                    'point_claim_id' => $data['point_claim_id'] ?? null
                ];
            }

            return [
                'success' => false,
                'synced' => false,
                'message' => 'Failed to check sync status'
            ];
        } catch (Exception $e) {
            Log::warning('Failed to check ride sync status', [
                'ride_uuid' => $rideUuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'synced' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user available points from Airpoints system
     * 
     * @param Client $client The client from Accretion Aviation
     * @return array Response containing 'success', 'points', and 'customer_id'
     */
    public function getUserPoints(Client $client)
    {
        try {
            // Extract phone number without country code for matching
            $phoneWithoutCountryCode = $this->extractPhoneWithoutCountryCode($client->contact_number);

            if (empty($phoneWithoutCountryCode)) {
                Log::warning('Get user points skipped - no valid phone number', [
                    'client_id' => $client->id,
                    'client_name' => $client->name
                ]);

                return [
                    'success' => false,
                    'message' => 'No valid phone number found',
                    'points' => 0
                ];
            }

            $url = $this->baseUrl . '/api/customers/get-points';

            Log::info('Fetching user points from Airpoints', [
                'client_uuid' => $client->id,
                'phone' => $phoneWithoutCountryCode,
                'url' => $url
            ]);

            // Call Airpoints API (don't throw on 4xx/5xx responses)
            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000, function ($exception) {
                    // Only retry on connection issues, not on 404 or other client errors
                    return !($exception instanceof \Illuminate\Http\Client\RequestException);
                })
                ->acceptJson()
                ->post($url, [
                    'phone' => $phoneWithoutCountryCode,
                    'email' => $client->email ?? ''
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('User points fetched successfully', [
                    'client_uuid' => $client->id,
                    'airpoints_customer_id' => $data['customer_id'] ?? null,
                    'points' => $data['points'] ?? 0
                ]);

                return [
                    'success' => true,
                    'customer_id' => $data['customer_id'] ?? null,
                    'points' => $data['points'] ?? 0,
                    'message' => $data['message'] ?? 'Points fetched successfully'
                ];
            } else {
                // Try to parse error response for better error messages
                $errorMessage = 'User not found in Airpoints system';
                try {
                    $errorData = $response->json();
                    if (isset($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    }
                } catch (\Exception $e) {
                    // Keep default message if JSON parsing fails
                }

                Log::error('Get user points failed', [
                    'client_uuid' => $client->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'points' => 0
                ];
            }
        } catch (\Exception $e) {
            // Try to extract user-friendly message when Airpoints returns 4xx/5xx JSON
            $friendlyMessage = $e->getMessage();

            // If this is an HTTP client exception, try to read the response body
            if ($e instanceof \Illuminate\Http\Client\RequestException && method_exists($e, 'response')) {
                try {
                    $resp = $e->response;
                    if ($resp && $resp->json()) {
                        $err = $resp->json();
                        $friendlyMessage = $err['message'] ?? $friendlyMessage;
                    }
                } catch (\Throwable $_e) {
                    // ignore parsing errors
                }
            } elseif (strpos($friendlyMessage, 'HTTP request returned status code 404') !== false) {
                // The exception text often contains the JSON body — try to pull it out to show a friendly message
                try {
                    $start = strpos($friendlyMessage, '{');
                    if ($start !== false) {
                        $jsonPart = substr($friendlyMessage, $start);
                        $parsed = json_decode($jsonPart, true);
                        if (is_array($parsed) && isset($parsed['message'])) {
                            $friendlyMessage = $parsed['message'];
                        }
                    }
                } catch (\Throwable $_e) {
                    // ignore
                }
            }

            Log::error('Get user points exception', [
                'client_uuid' => $client->id,
                'error' => $e->getMessage(),
                'friendly' => $friendlyMessage,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $friendlyMessage ?: 'Unable to fetch user points',
                'points' => 0
            ];
        }
    }

    /**
     * Redeem points from Airpoints when payment is made via Acepoint
     * 
     * @param Client $client The client from Accretion Aviation
     * @param Product $product The product from the followup
     * @param int $points Points to redeem
     * @param float $amount Amount value of points (rupees)
     * @param string $serviceDate Date of service
     * @return array Response containing 'success', 'message', and 'redeem_id'
     */
    public function redeemPoints(Client $client, Product $product, $points, $amount, $serviceDate)
    {
        try {
            // Extract phone number without country code for matching
            $phoneWithoutCountryCode = $this->extractPhoneWithoutCountryCode($client->contact_number);

            if (empty($phoneWithoutCountryCode)) {
                Log::warning('Point redemption skipped - no valid phone number', [
                    'client_id' => $client->id,
                    'client_name' => $client->name
                ]);

                return [
                    'success' => false,
                    'message' => 'No valid phone number found'
                ];
            }

            // First, sync/get customer
            $customerSync = $this->syncCustomer($client);
            
            if (!$customerSync['success'] || !$customerSync['customer_id']) {
                return [
                    'success' => false,
                    'message' => 'Customer sync failed: ' . ($customerSync['message'] ?? 'Unknown error')
                ];
            }

            // Sync product to get/create product_id in Acepoints (same approach as createPointClaim)
            $productSync = $this->syncProduct($product);
            
            if (!$productSync['success'] || !$productSync['product_id']) {
                return [
                    'success' => false,
                    'message' => 'Product sync failed: ' . ($productSync['message'] ?? 'Unknown error')
                ];
            }

            $redeemData = [
                'customer_id' => $customerSync['customer_id'],
                'product_id' => $productSync['product_id'],
                'points' => $points,
                'amount' => $amount,
                'service_date' => $serviceDate,
            ];

            $url = $this->baseUrl . '/api/point-redeems/create';

            Log::info('Redeeming points in Airpoints', [
                'client_uuid' => $client->id,
                'customer_id' => $customerSync['customer_id'],
                'points' => $points,
                'amount' => $amount
            ]);

            // Call Airpoints API (don't throw on 4xx/5xx responses)
            $response = Http::withOptions(['connect_timeout' => 10])
                ->timeout($this->timeout)
                ->retry(3, 2000, function ($exception) {
                    // Only retry on connection issues, not on 404 or other client errors
                    return !($exception instanceof \Illuminate\Http\Client\RequestException);
                })
                ->acceptJson()
                ->post($url, $redeemData);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Points redeemed successfully', [
                    'client_uuid' => $client->id,
                    'redeem_id' => $data['redeem_id'] ?? null,
                    'points_redeemed' => $points
                ]);

                return [
                    'success' => true,
                    'redeem_id' => $data['redeem_id'] ?? null,
                    'message' => $data['message'] ?? 'Points redeemed successfully',
                    'data' => $data
                ];
            } else {
                // Try to parse error response for better error messages
                $errorMessage = 'Failed to redeem points';
                try {
                    $errorData = $response->json();
                    if (isset($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    }
                } catch (\Exception $e) {
                    // Keep default message if JSON parsing fails
                }

                $responseBody = $response->body();
                Log::error('Point redemption failed', [
                    'client_uuid' => $client->id,
                    'status_code' => $response->status(),
                    'response' => $responseBody
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage
                ];
            }
        } catch (\Exception $e) {
            // When Airpoints returns an HTTP error, provide the API message as a friendly message when possible
            $friendlyMessage = $e->getMessage();

            if ($e instanceof \Illuminate\Http\Client\RequestException && method_exists($e, 'response')) {
                try {
                    $resp = $e->response;
                    if ($resp && $resp->json()) {
                        $err = $resp->json();
                        $friendlyMessage = $err['message'] ?? $friendlyMessage;
                    }
                } catch (\Throwable $_e) {
                    // ignore
                }
            } elseif (strpos($friendlyMessage, 'HTTP request returned status code 404') !== false) {
                try {
                    $start = strpos($friendlyMessage, '{');
                    if ($start !== false) {
                        $jsonPart = substr($friendlyMessage, $start);
                        $parsed = json_decode($jsonPart, true);
                        if (is_array($parsed) && isset($parsed['message'])) {
                            $friendlyMessage = $parsed['message'];
                        }
                    }
                } catch (\Throwable $_e) {
                    // ignore
                }
            }

            Log::error('Point redemption exception', [
                'client_uuid' => $client->id,
                'error' => $e->getMessage(),
                'friendly' => $friendlyMessage,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $friendlyMessage ?: 'Failed to redeem points'
            ];
        }
    }
}
