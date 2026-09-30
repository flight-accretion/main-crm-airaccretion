<?php

namespace App\Http\Controllers;

use Google\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleChatOAuthController extends Controller
{
    public function redirect(
        Request $request
    ) {

        abort_unless(

            $request->user()

            &&

            $request
                ->user()
                ->isSuperAdmin(),

            403
        );


        $state =
            Str::random(40);


        $request
            ->session()
            ->put(
                'google_chat_oauth_state',
                $state
            );


        $client =
            $this->client();


        $client->setState(
            $state
        );


        return redirect()->away(
            $client->createAuthUrl()
        );
    }


    public function callback(
        Request $request
    ) {

        abort_unless(

            $request->user()

            &&

            $request
                ->user()
                ->isSuperAdmin(),

            403
        );


        $expectedState =
            (string)
            $request
                ->session()
                ->pull(
                    'google_chat_oauth_state',
                    ''
                );


        $returnedState =
            (string)
            $request->query(
                'state',
                ''
            );


        abort_unless(

            $expectedState !== ''

            &&

            hash_equals(
                $expectedState,
                $returnedState
            ),

            419,

            'Google OAuth state validation failed.'
        );


        abort_unless(

            $request->filled(
                'code'
            ),

            400,

            'Google authorization code is missing.'
        );


        $client =
            $this->client();


   $code =
    (string)
    $request->query(
        'code',
        ''
    );


$token =
    $client->fetchAccessTokenWithAuthCode(
        $code
    );


        if (
            !empty(
                $token['error']
            )
        ) {

            abort(

                500,

                $token[
                    'error_description'
                ]

                ??

                $token['error']
            );
        }


        $refreshToken =
            $token[
                'refresh_token'
            ]
            ??
            null;


        if (
            !$refreshToken
        ) {

            return response(
                'Google did not return a refresh token. Revoke the previous OAuth consent and authorize again.',
                500
            );
        }


        app(\App\Services\GoogleChat\GoogleChatCredentials::class)->save($refreshToken);

        return response(

            'Google Chat authorization saved securely. You may close this page.',

            200,

            [
                'Cache-Control' => 'no-store',
                'Content-Type' =>
                    'text/plain; charset=UTF-8'
            ]
        );
    }


    private function client(): Client
    {
        $client =
            new Client();


        $client->setClientId(
            config(
                'services.google_chat.client_id'
            )
        );


        $client->setClientSecret(
            config(
                'services.google_chat.client_secret'
            )
        );


        $client->setRedirectUri(
            config(
                'services.google_chat.redirect_uri'
            )
        );


        $client->setScopes([

            'https://www.googleapis.com/auth/chat.messages',

            'https://www.googleapis.com/auth/chat.spaces.readonly',
            'https://www.googleapis.com/auth/chat.memberships.readonly',
        ]);


        $client->setAccessType(
            'offline'
        );


        $client->setPrompt(
            'consent select_account'
        );


        return $client;
    }
}
