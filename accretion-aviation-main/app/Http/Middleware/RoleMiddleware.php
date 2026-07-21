<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\UserType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roleGroups): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $roles = [];

        foreach ($roleGroups as $group) {
            if (defined('App\Models\UserType::' . $group)) {
                $roles = array_merge($roles, (array) constant('App\Models\UserType::' . $group));
            } else {
                $roles[] = $group; // fallback if you pass direct role string
            }
        }

        if (!in_array($user->userType->user_type, $roles, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
