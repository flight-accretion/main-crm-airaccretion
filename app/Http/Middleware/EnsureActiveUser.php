<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(
        Request $request,
        Closure $next
    ) {
        $user =
            $request->user();


        if (
            $user
            &&
            (int) $user->status !== 1
        ) {
            Auth::logout();


            $request
                ->session()
                ->invalidate();


            $request
                ->session()
                ->regenerateToken();


            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Your account is inactive.'
                );
        }


        return $next(
            $request
        );
    }
}