<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWebsiteCatalogWebhook
{
    public function handle(
        Request $request,
        Closure $next
    ) {
        /*
        |--------------------------------------------------------------------------
        | Shared Secret
        |--------------------------------------------------------------------------
        */

        $secret =
            trim(
                (string) config(
                    'services.website_catalog.webhook_secret'
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Signed Headers
        |--------------------------------------------------------------------------
        */

        $timestamp =
            trim(
                (string) $request->header(
                    'X-Accretion-Timestamp',
                    ''
                )
            );


        $providedSignature =
            trim(
                (string) $request->header(
                    'X-Accretion-Signature',
                    ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if (
            $secret === ''
            ||
            $timestamp === ''
            ||
            $providedSignature === ''
            ||
            !ctype_digit($timestamp)
        ) {
            return $this->unauthorized(
                $request
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Replay Protection
        |--------------------------------------------------------------------------
        |
        | Requests older/newer than 5 minutes are rejected.
        |
        */

        if (
            abs(
                time()
                - (int) $timestamp
            ) > 300
        ) {
            return $this->unauthorized(
                $request
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Generate Expected Signature
        |--------------------------------------------------------------------------
        |
        | MUST match Website:
        |
        | HMAC-SHA256(
        |     timestamp + "." + raw_json_body,
        |     secret
        | )
        |
        */

        $expectedSignature =
            hash_hmac(
                'sha256',

                $timestamp
                . '.'
                . $request->getContent(),

                $secret
            );


        /*
        |--------------------------------------------------------------------------
        | Constant-time Comparison
        |--------------------------------------------------------------------------
        */

        if (
            !hash_equals(
                $expectedSignature,
                $providedSignature
            )
        ) {
            return $this->unauthorized(
                $request
            );
        }


        return $next(
            $request
        );
    }


    private function unauthorized(
        Request $request
    ) {
        Log::warning(
            'Unauthorized Website catalog webhook request.',
            [
                'ip' =>
                    $request->ip(),

                'path' =>
                    $request->path(),
            ]
        );


        return response()->json(
            [
                'success' =>
                    false,

                'message' =>
                    'Unauthorized',
            ],
            401
        );
    }
}