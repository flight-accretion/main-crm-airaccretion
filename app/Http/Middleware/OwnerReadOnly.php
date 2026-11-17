<?php

namespace App\Http\Middleware;

use App\Models\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Owner role is read-only oversight. Any mutating request is rejected,
 * regardless of which role groups the route grants. GET/HEAD/OPTIONS pass.
 * Exceptions: logout, self password change, field-scoped Product/Service editing,
 * and the dedicated field-scoped lead status action.
 */
class OwnerReadOnly
{
    private const ALLOWED_MUTATING_PATHS = [
        'logout',
        'change-password',
        'user/password/update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || optional($user->userType)->user_type !== UserType::OWNER) {
            return $next($request);
        }

        // updateLead has an Owner-only branch that persists only Product/Service IDs.
        if ($request->isMethod('POST') && $request->routeIs('admin.leads.status.update')) {
            return $next($request);
        }

        if ($request->isMethod('PUT')
            && $request->routeIs('admin.leads.update')
            && $request->input('workspace_section') === 'services-details-editor') {
            return $next($request);
        }

        foreach (self::ALLOWED_MUTATING_PATHS as $path) {
            if ($request->is($path) || $request->routeIs(str_replace('/', '.', $path))) {
                return $next($request);
            }
        }

        abort(403, 'The owner role is read-only.');
    }
}
