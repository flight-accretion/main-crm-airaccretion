<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySkyrackToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.skyrack.token');

        if ($configuredToken === '') {
            report(new \RuntimeException(
                'SKYRACK_LEADS_API_TOKEN is not configured.'
            ));

            return response()->json([
                'success' => false,
                'message' => 'API authentication is not configured.',
            ], 500);
        }

        $providedToken = $request->bearerToken();

        if (
            !$providedToken ||
            !hash_equals($configuredToken, $providedToken)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}