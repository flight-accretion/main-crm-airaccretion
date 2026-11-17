<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyN8nAttendanceToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config(
            'services.n8n_attendance.token',
            ''
        );

        $provided = (string) $request->bearerToken();

        if (
            $expected === '' ||
            $provided === '' ||
            !hash_equals($expected, $provided)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}