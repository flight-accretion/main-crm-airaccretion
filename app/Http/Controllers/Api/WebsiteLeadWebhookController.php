<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailLeadService;
use App\Services\LeadProductRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteLeadWebhookController extends Controller
{
    public function store(
        Request $request,
        EmailLeadService $leadService,
        LeadProductRoutingService $productRouter
    ) {
        $request->merge([
            'service' =>
                $request->input(
                    'service',
                    $request->input(
                        'services',
                        $request->input('service_name')
                    )
                ),
        ]);

        $validated =
            $request->validate([
                'service_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'service' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'mobile' => [
                    'required',
                    'string',
                    'max:30',
                    function (
                        $attribute,
                        $value,
                        $fail
                    ) {
                        $digits =
                            preg_replace(
                                '/\D+/',
                                '',
                                (string) $value
                            );

                        if (strlen($digits) < 10) {
                            $fail(
                                'A valid mobile number is required.'
                            );
                        }
                    },
                ],

                'departure_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'departure_time' => [
                    'required',
                    'date_format:H:i',
                ],

                'guest' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

        $serviceName =
            trim($validated['service']);

        $fingerprintData = [
            'service_id' =>
                (int) ($validated['service_id'] ?? 0),

            'service' =>
                Str::lower($serviceName),

            'name' =>
                Str::lower(
                    trim($validated['name'])
                ),

            'mobile' =>
                $this->normalizeMobile(
                    $validated['mobile']
                ),

            'departure_date' =>
                $validated['departure_date'],

            'departure_time' =>
                $validated['departure_time'],

            'guest' =>
                (int) $validated['guest'],
        ];

        $fingerprint =
            hash(
                'sha256',
                json_encode(
                    $fingerprintData,
                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES
                )
            );

        $cacheKey =
            'website-lead-webhook:'
            . $fingerprint;

        if (
            !Cache::add(
                $cacheKey,
                true,
                now()->addSeconds(60)
            )
        ) {
            return response()->json([
                'success' => true,
                'status' =>
                    'duplicate_submission',
                'message' =>
                    'Duplicate webhook delivery ignored.',
            ]);
        }

        try {
            $bodyLines = [
                'Name: '
                . trim($validated['name']),

                'Mobile: '
                . $validated['mobile'],

                'Services: '
                . $serviceName,

                'DepartureDate: '
                . $validated['departure_date'],

                'DepartureTime: '
                . $validated['departure_time'],

                'Guest: '
                . (int) $validated['guest'],
            ];

            if (!empty($validated['service_id'])) {
                $bodyLines[] =
                    'WebsiteServiceId: '
                    . (int) $validated['service_id'];
            }

            $body =
                implode(
                    PHP_EOL,
                    $bodyLines
                );

            $result =
                $leadService->process([
                    'message_id' =>
                        'website:' . (string) Str::uuid(),

                    'uid' =>
                        null,

                    'sender_email' =>
                        'website-form@accretionaviation.com',

                    'recipient_email' =>
                        config(
                            'services.email_leads.recipient',
                            'leads@accretionaviation.com'
                        ),

                    'subject' =>
                        'Website Form Lead - '
                        . $serviceName,

                    'body' =>
                        $body,

                    'received_at' =>
                        now(),

                    'source_type' =>
                        'website_form',
                ]);

            if (($result['status'] ?? null) === 'error') {
                Cache::forget($cacheKey);

                return response()->json(
                    [
                        'success' => false,
                        'status' =>
                            $result['status'],
                        'reason' =>
                            $result['reason']
                            ?? 'processing_error',
                    ],
                    422
                );
            }

            $responseProductId =
                $result['product_id']
                ?? optional(
                    $productRouter->resolveProduct(
                        $serviceName
                    )
                )->id;

            return response()->json([
                'success' => true,
                'status' =>
                    $result['status']
                    ?? 'processed',
                'lead_id' =>
                    $result['lead_id']
                    ?? null,
                'product_id' =>
                    $responseProductId,
                'agent_user_id' =>
                    $result['agent_user_id']
                    ?? null,
            ]);
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);

            Log::error(
                'Website lead webhook processing failed.',
                [
                    'service_id' =>
                        $validated['service_id'] ?? null,
                    'service' =>
                        $serviceName,
                    'mobile' =>
                        $validated['mobile'],
                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Unable to process website lead.',
                ],
                500
            );
        }
    }

    private function normalizeMobile(
        string $mobile
    ): string {
        $digits =
            preg_replace(
                '/\D+/',
                '',
                $mobile
            );

        if (strlen($digits) > 10) {
            $digits =
                substr($digits, -10);
        }

        return $digits;
    }
}
