<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyLeadApiKey
{
    /**
     * Handle an incoming request.
     * Expect header `X-API-KEY` to match the configured lead API key.
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('X-API-KEY') ?: $request->header('x-api-key');
        $key = (string) config('services.lead_api.key');

        if (empty($key) || empty($header) || !hash_equals($key, $header)) {
            Log::warning('Lead API unauthorized access attempt', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
