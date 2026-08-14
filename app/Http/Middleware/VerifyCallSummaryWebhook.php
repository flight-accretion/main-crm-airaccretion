<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyCallSummaryWebhook
{
    public function handle(
        Request $request,
        Closure $next
    ) {

        $expectedToken = trim(
            (string)
            config(
                'call_summary.token'
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Allow either standard Bearer or custom header
        |--------------------------------------------------------------------------
        */

        $receivedToken = trim(
            (string) (
                $request->bearerToken()
                ?:
                $request->header(
                    'X-Call-Summary-Token'
                )
            )
        );


        if ($expectedToken === '') {

            Log::error(
                'Call Summary API token is not configured.'
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'API authentication is not configured.',
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

            Log::warning(
                'Unauthorized Call Summary API request.',
                [
                    'ip' =>
                        $request->ip(),

                    'has_bearer' =>
                        !empty(
                            $request->bearerToken()
                        ),

                    'has_custom_header' =>
                        !empty(
                            $request->header(
                                'X-Call-Summary-Token'
                            )
                        ),

                    'received_length' =>
                        strlen(
                            $receivedToken
                        ),
                ]
            );


            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }


        return $next($request);
    }
}