<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWebsiteLeadWebhook
{
    public function handle(
        Request $request,
        Closure $next
    ) {
        $providedToken =
            (string) $request->header(
                'X-Website-Webhook-Token',
                ''
            );

        $expectedToken =
            (string) config(
                'services.website_lead_webhook.token'
            );

        if (
            $expectedToken === ''
            || $providedToken === ''
            || !hash_equals(
                $expectedToken,
                $providedToken
            )
        ) {
            Log::warning(
                'Unauthorized website lead webhook request.',
                [
                    'ip' => $request->ip(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unauthorized',
                ],
                401
            );
        }

        return $next($request);
    }
}
