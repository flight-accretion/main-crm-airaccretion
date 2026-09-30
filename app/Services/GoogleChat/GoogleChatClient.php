<?php

namespace App\Services\GoogleChat;

use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleChatClient
{
    public function enabled(): bool
    {
        return (bool)
            config(
                'services.google_chat.enabled',
                false
            );
    }


    public function spaceName(): string
    {
        $space =
            trim(
                (string)
                config(
                    'services.google_chat.space_name'
                )
            );


        if (
            $space === ''
        ) {

            throw new RuntimeException(
                'GOOGLE_CHAT_SPACE_NAME is missing.'
            );
        }


        if (
            !str_starts_with(
                $space,
                'spaces/'
            )
        ) {

            throw new RuntimeException(
                'GOOGLE_CHAT_SPACE_NAME must start with spaces/.'
            );
        }


        return $space;
    }


    public function accessToken(): string
    {
        return Cache::remember(

            'google-chat-user-access-token',

            now()->addMinutes(50),

            function () {

                $clientId =
                    trim(
                        (string)
                        config(
                            'services.google_chat.client_id'
                        )
                    );


                $secret =
                    trim(
                        (string)
                        config(
                            'services.google_chat.client_secret'
                        )
                    );


                $refreshToken = app(GoogleChatCredentials::class)->refreshToken();


                if (
                    $clientId === ''

                    ||

                    $secret === ''

                    ||

                    $refreshToken === ''
                ) {

                    throw new RuntimeException(
                        'Google Chat OAuth credentials are incomplete.'
                    );
                }


                $client =
                    new Client();


                $client->setClientId(
                    $clientId
                );


                $client->setClientSecret(
                    $secret
                );


                $token =
                    $client
                        ->fetchAccessTokenWithRefreshToken(
                            $refreshToken
                        );


                if (
                    !empty(
                        $token['error']
                    )
                ) {

                    throw new RuntimeException(

                        'Google OAuth refresh failed: '

                        .

                        (
                            $token[
                                'error_description'
                            ]

                            ??

                            $token['error']
                        )
                    );
                }


                $accessToken =
                    $token[
                        'access_token'
                    ]
                    ??
                    null;


                if (
                    !$accessToken
                ) {

                    throw new RuntimeException(
                        'Google access token missing.'
                    );
                }


                return $accessToken;
            }
        );
    }


    public function createMessage(
        string $text,
        string $threadKey,
        string $crmMessageId,
        ?string $spaceName = null,
        ?string $threadName = null
    ): array {

        $space =
            $spaceName ?: $this->spaceName();

        $this->validateSpace($space);
        if ($threadName && !str_starts_with($threadName, $space.'/threads/')) {
            throw new RuntimeException('Google thread does not belong to the selected Space.');
        }


        /*
         * Deterministic Google message identifier.
         *
         * This is also used for loop protection.
         */

        $messageId =
            'client-'
            .
            strtolower(
                $crmMessageId
            );


        $query =
            http_build_query([

                'messageReplyOption' =>
                    $threadName ? 'REPLY_MESSAGE_OR_FAIL' : 'REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD',

                'requestId' =>
                    $crmMessageId,

                'messageId' =>
                    $messageId,
            ]);


        $response =
            Http::timeout(20)

                ->withToken(
                    $this->accessToken()
                )

                ->acceptJson()

                ->post(

                    "https://chat.googleapis.com/v1/{$space}/messages?{$query}",

                    [

                        'text' =>
                            $text,

                        'thread' => $threadName ? ['name' => $threadName] : ['threadKey' => $threadKey],
                    ]
                );


        if ($response->status() === 409) {
            return $this->getMessage($space.'/messages/'.$messageId);
        }

        if (
            !$response->successful()
        ) {

            throw new RuntimeException(

                'Google Chat create failed. HTTP '

                .

                $response->status()

                .

                ': '

                .

                $response->body()
            );
        }


        return $response->json();
    }


    public function updateMessage(
        string $messageName,
        string $text
    ): array {

        $response =
            Http::timeout(20)

                ->retry(
                    2,
                    500
                )

                ->withToken(
                    $this->accessToken()
                )

                ->acceptJson()

                ->patch(

                    'https://chat.googleapis.com/v1/'
                    .
                    $messageName
                    .
                    '?updateMask=text',

                    [
                        'text' =>
                            $text,
                    ]
                );


        if (
            !$response->successful()
        ) {

            throw new RuntimeException(
                'Google Chat message update failed. '
                .
                $response->body()
            );
        }


        return $response->json();
    }


    public function deleteMessage(
        string $messageName
    ): void {

        $response =
            Http::timeout(20)

                ->withToken(
                    $this->accessToken()
                )

                ->delete(
                    'https://chat.googleapis.com/v1/'
                    .
                    $messageName
                );


        if (
            !$response->successful()

            &&

            $response->status()
            !==
            404
        ) {

            throw new RuntimeException(
                'Google Chat message delete failed. '
                .
                $response->body()
            );
        }
    }


    public function getMessage(
        string $messageName
    ): array {

        $response =
            Http::timeout(20)

                ->retry(
                    2,
                    500
                )

                ->withToken(
                    $this->accessToken()
                )

                ->acceptJson()

                ->get(
                    'https://chat.googleapis.com/v1/'
                    .
                    $messageName
                );


        if (
            !$response->successful()
        ) {

            $response->throw();
        }


        return $response->json();
    }


    public function listSpaces(): array
    {
        $spaces = [];
        $page = null;
        do {
            $response = Http::timeout(20)->withToken($this->accessToken())->acceptJson()
                ->get('https://chat.googleapis.com/v1/spaces', [
                    'filter' => 'space_type = "SPACE"', 'pageSize' => 100, 'pageToken' => $page,
                ]);
            $response->throw();
            $spaces = array_merge($spaces, $response->json('spaces', []));
            $page = $response->json('nextPageToken');
        } while ($page);
        return $spaces;
    }

    public function validateSpace(string $space): void
    {
        if (!preg_match('~^spaces/[A-Za-z0-9_-]+$~D', $space)) {
            throw new RuntimeException('Invalid Google Space resource name.');
        }
    }

    public function listSpaceMembers(string $spaceName): array
    {
        $this->validateSpace($spaceName);
        $members = [];
        $page = null;
        do {
            $response = Http::timeout(20)->withToken($this->accessToken())->acceptJson()
                ->get('https://chat.googleapis.com/v1/'.$spaceName.'/members',
                    ['pageSize' => 100, 'pageToken' => $page]);
            $response->throw();
            $members = array_merge($members, $response->json('memberships', []));
            $page = $response->json('nextPageToken');
        } while ($page);
        return $members;
    }

    public function listThreadMessages(string $spaceName, string $threadName, ?string $page = null): array
    {
        $this->validateSpace($spaceName);
        if (!preg_match('~^'.preg_quote($spaceName, '~').'/threads/[A-Za-z0-9_-]+$~D', $threadName)) {
            throw new RuntimeException('Invalid Google thread resource name.');
        }
        return Http::timeout(20)->withToken($this->accessToken())->acceptJson()
            ->get('https://chat.googleapis.com/v1/'.$spaceName.'/messages', [
                'filter' => 'thread.name = "'.$threadName.'"', 'pageSize' => 100, 'pageToken' => $page,
                'showDeleted' => true,
            ])->throw()->json();
    }
}
