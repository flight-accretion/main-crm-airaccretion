<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    /**
     * Send the user to Google's OAuth screen.
     */
    public function redirect()
    {
        /*
        |--------------------------------------------------------------------------
        | Already logged in
        |--------------------------------------------------------------------------
        */

        if (Auth::check()) {
            return redirect(
                $this->destinationFor(
                    Auth::user()
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Google Login
        |--------------------------------------------------------------------------
        |
        | We intentionally request identity information only.
        |
        | Google Drive access is NOT requested by this login flow.
        |
        */

        return Socialite::driver('google')
            ->scopes([
                'openid',
                'profile',
                'email',
            ])
            ->with([
                'prompt' => 'select_account',
            ])
            ->redirect();
    }


    /**
     * Handle Google's OAuth callback.
     */
    public function callback(
        Request $request
    ) {
        try {

            /*
            |--------------------------------------------------------------------------
            | Read authenticated Google account
            |--------------------------------------------------------------------------
            */

            $googleUser =
                Socialite::driver(
                    'google'
                )->user();


            /*
            |--------------------------------------------------------------------------
            | Get email
            |--------------------------------------------------------------------------
            */

            $email =
                strtolower(
                    trim(
                        (string)
                        $googleUser
                            ->getEmail()
                    )
                );

            if ($email === '') {

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' =>
                            'Google did not return an email address.',
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Verify Google email
            |--------------------------------------------------------------------------
            |
            | Google's OpenID response normally contains email_verified.
            | If Google explicitly tells us the email is not verified,
            | do not allow CRM login.
            |
            */

            $googleRawData =
                is_array(
                    $googleUser->user
                    ?? null
                )
                    ? $googleUser->user
                    : [];

            if (
                array_key_exists(
                    'email_verified',
                    $googleRawData
                )
                &&
                !$googleRawData[
                    'email_verified'
                ]
            ) {

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' =>
                            'Your Google email address is not verified.',
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | EXISTING CRM USERS ONLY
            |--------------------------------------------------------------------------
            |
            | VERY IMPORTANT:
            |
            | Google login must NEVER automatically create a CRM user.
            |
            | The employee must already exist in the users table.
            | Their existing CRM role, permissions and assignments remain
            | unchanged.
            |
            */

            $user =
                User::query()
                    ->with(
                        'userType'
                    )
                    ->whereRaw(
                        'LOWER(email) = ?',
                        [
                            $email,
                        ]
                    )
                    ->first();


            if (!$user) {

                Log::warning(
                    'Unauthorized Google CRM login attempt',
                    [
                        'email' =>
                            $email,
                    ]
                );

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' =>
                            'This Google account is not authorized to access the CRM.',
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Active CRM users only
            |--------------------------------------------------------------------------
            */

            if (
                (int) $user->status
                !== 1
            ) {

                Log::warning(
                    'Inactive CRM user attempted Google login',
                    [
                        'user_id' =>
                            $user->id,

                        'email' =>
                            $email,
                    ]
                );

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' =>
                            'Your CRM account is inactive. Please contact the administrator.',
                    ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Login existing CRM user
            |--------------------------------------------------------------------------
            */

            Auth::login(
                $user,
                false
            );

            /*
            |--------------------------------------------------------------------------
            | Prevent session fixation
            |--------------------------------------------------------------------------
            */

            $request
                ->session()
                ->regenerate();


            /*
            |--------------------------------------------------------------------------
            | Existing CRM redirect behaviour
            |--------------------------------------------------------------------------
            |
            | Keep this identical to existing UserController::login().
            |
            */

            $path =
                $this->destinationFor(
                    $user
                );


            Log::info(
                'CRM Google login successful',
                [
                    'user_id' =>
                        $user->id,

                    'email' =>
                        $email,
                ]
            );


            return redirect()
                ->intended(
                    $path
                )
                ->with(
                    'success',
                    'Login successful'
                );

        } catch (\Throwable $exception) {

            Log::error(
                'CRM Google login failed',
                [
                    'error' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Google login could not be completed. Please try again.',
                ]);
        }
    }


    /**
     * Keep Google login landing page identical
     * to the existing password login flow.
     */
    private function destinationFor(
        User $user
    ): string {
        $userType =
            $user
                ->userType
                ?->user_type;

        if (
            in_array(
                $userType,
                UserType::ADMIN_ROLES,
                true
            )
        ) {

            return 'admin/rides/upcoming-ride';

        }

        if (
            in_array(
                $userType,
                UserType::OPERATIONS_ROLES,
                true
            )
        ) {

            return 'admin/rides/upcoming-ride';

        }

        if (
            in_array(
                $userType,
                UserType::SALES_ROLES,
                true
            )
        ) {

            return '/sales-dashboard';

        }

        if (
            in_array(
                $userType,
                UserType::ACCOUNTS_ROLES,
                true
            )
        ) {

            return 'admin/rides/ride-status';

        }

        return 'admin/rides/upcoming-ride';
    }
}