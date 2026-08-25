<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWhatCrmWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = trim(
            (string) config('whatcrm.token')
        );

        /*
        |--------------------------------------------------------------------------
        | Accept token from either:
        |
        | 1. Authorization: Bearer TOKEN
        | 2. Authorization: TOKEN
        | 3. X-WhatCRM-Token: TOKEN
        | 4. token: TOKEN
        |
        | X-WhatCRM-Token is useful because some web-server/FPM
        | configurations do not forward Authorization headers.
        |--------------------------------------------------------------------------
        */

        $receivedToken = $this->receivedToken($request);

        if ($expectedToken === '') {

            Log::error(
                'WhatCRM webhook token is not configured.'
            );

            return response()->json([
                'success' => false,
                'message' => 'Webhook authentication is not configured.',
            ], 500);
        }


        if (
            $receivedToken === ''
            ||
            !hash_equals(
                $expectedToken,
                $receivedToken
            )
        ) {

            /*
             * Never log actual tokens.
             */
            Log::warning(
                'Unauthorized WhatCRM webhook request.',
                [
                    'has_bearer_token' =>
                        !empty($request->bearerToken()),

                    'has_authorization_header' =>
                        !empty($request->header('Authorization')),

                    'has_custom_token' =>
                        !empty(
                            $request->header(
                                'X-WhatCRM-Token'
                            )
                        ),

                    'has_token_header' =>
                        !empty($request->header('token')),

                    'received_length' =>
                        strlen($receivedToken),

                    'expected_length' =>
                        strlen($expectedToken),

                    'ip' =>
                        $request->ip(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }


        return $next($request);
    }

    private function receivedToken(Request $request): string
    {
        $authorization = trim(
            (string) $request->header('Authorization')
        );

        $rawAuthorization = '';

        if (
            $authorization !== ''
            && !preg_match('/^Bearer\s+/i', $authorization)
        ) {
            $rawAuthorization = $authorization;
        }

        return trim(
            (string) (
                $request->bearerToken()
                ?: $request->header('X-WhatCRM-Token')
                ?: $request->header('token')
                ?: $rawAuthorization
            )
        );
    }
}
