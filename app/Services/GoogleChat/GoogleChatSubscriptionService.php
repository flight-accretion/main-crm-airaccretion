<?php

namespace App\Services\GoogleChat;

use App\Models\GoogleChatSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleChatSubscriptionService
{
    public function __construct(
        private GoogleChatClient $client
    ) {
    }


    public function ensure(): GoogleChatSubscription
    {
        return $this->ensureForSpace($this->client->spaceName());
    }

    public function ensureForSpace(string $spaceName): GoogleChatSubscription
    {
        if (!in_array($spaceName, config('services.google_chat.allowed_spaces', []), true)) {
            throw new RuntimeException('Google Space is not approved.');
        }
        return \Illuminate\Support\Facades\Cache::lock('google-chat:subscription:'.sha1($spaceName), 180)
            ->block(5, fn () => $this->ensureRecord($spaceName));
    }

    private function ensureRecord(string $spaceName): GoogleChatSubscription
    {
        $record =
            GoogleChatSubscription::query()
                ->where('target_resource', '//chat.googleapis.com/'.$spaceName)
                ->first();


        if (
            !$record
        ) {

            $record =
                GoogleChatSubscription::create([
                    'target_resource' => '//chat.googleapis.com/'.$spaceName,

                    'status' =>
                        'pending',
                ]);
        }


        /*
         * Don't renew unnecessarily.
         */

        if (
            $record->google_name

            &&

            $record->expire_time

            &&

            $record
                ->expire_time
                ->gt(
                    now()->addDays(2)
                )
        ) {

            return $record;
        }


        try {

            if (
                $record->google_name
            ) {

                try {

                    return
                        $this->renew(
                            $record
                        );


                } catch (\Throwable $e) {

                    /*
                     * Google automatically removes
                     * expired subscriptions.
                     */

                    if (
                        str_contains(
                            $e->getMessage(),
                            'HTTP 404'
                        )
                    ) {

                        $record->update([

                            'google_name' =>
                                null,

                            'expire_time' =>
                                null,
                        ]);

                    } else {

                        throw $e;
                    }
                }
            }


            return
                $this->create(
                    $record
                );


        } catch (\Throwable $e) {

            $record->update([

                'status' =>
                    'error',

                'last_error' =>
                    $e->getMessage(),
            ]);


            throw $e;
        }
    }


    private function create(
        GoogleChatSubscription $record
    ): GoogleChatSubscription {

        $topic =
            trim(
                (string)
                config(
                    'services.google_chat.pubsub_topic'
                )
            );


        if (
            $topic === ''
        ) {

            throw new RuntimeException(
                'GOOGLE_CHAT_PUBSUB_TOPIC is missing.'
            );
        }


        $response =
            Http::timeout(30)

                ->withToken(
                    $this->client
                        ->accessToken()
                )

                ->acceptJson()

                ->post(

                    'https://workspaceevents.googleapis.com/v1/subscriptions',

                    [

                        'targetResource' =>
                            $record->target_resource,

                        'eventTypes' => [

                            'google.workspace.chat.message.v1.created',

                            'google.workspace.chat.message.v1.updated',

                            'google.workspace.chat.message.v1.deleted',
                        ],

                        'notificationEndpoint' => [

                            'pubsubTopic' =>
                                $topic,
                        ],

                        /*
                         * No full resource:
                         * subscription can live up to 7 days.
                         */

                        'payloadOptions' => [

                            'includeResource' =>
                                false,
                        ],

                        /*
                         * 0 = maximum possible lifetime.
                         */

                        'ttl' =>
                            '0s',
                    ]
                );


        if (
            !$response->successful()
        ) {

            throw new RuntimeException(

                'Workspace Events create failed. HTTP '

                .

                $response->status()

                .

                ': '

                .

                $response->body()
            );
        }


        return
            $this->persist(

                $record,

                $this->waitForOperation(
                    $response->json()
                )
            );
    }


    private function renew(
        GoogleChatSubscription $record
    ): GoogleChatSubscription {

        $response =
            Http::timeout(30)

                ->withToken(
                    $this->client
                        ->accessToken()
                )

                ->acceptJson()

                ->patch(

                    'https://workspaceevents.googleapis.com/v1/'
                    .
                    $record->google_name
                    .
                    '?updateMask=ttl',

                    [

                        'name' =>
                            $record
                                ->google_name,

                        'ttl' =>
                            '0s',
                    ]
                );


        if (
            !$response->successful()
        ) {

            throw new RuntimeException(

                'Workspace Events renewal failed. HTTP '

                .

                $response->status()

                .

                ': '

                .

                $response->body()
            );
        }


        return
            $this->persist(

                $record,

                $this->waitForOperation(
                    $response->json()
                )
            );
    }


    private function waitForOperation(
        array $operation
    ): array {

        for (
            $attempt = 0;

            $attempt < 20;

            $attempt++
        ) {

            if (
                !empty(
                    $operation['done']
                )
            ) {

                if (
                    !empty(
                        $operation['error']
                    )
                ) {

                    throw new RuntimeException(
                        json_encode(
                            $operation['error']
                        )
                    );
                }


                return
                    $operation['response']
                    ??
                    throw new RuntimeException(
                        'No subscription returned by Google.'
                    );
            }


            $name =
                $operation['name']
                ??
                null;


            if (
                !$name
            ) {

                throw new RuntimeException(
                    'Workspace operation name missing.'
                );
            }


            sleep(1);


            $response =
                Http::timeout(20)

                    ->withToken(
                        $this->client
                            ->accessToken()
                    )

                    ->acceptJson()

                    ->get(
                        'https://workspaceevents.googleapis.com/v1/'
                        .
                        $name
                    );


            if (
                !$response->successful()
            ) {

                throw new RuntimeException(
                    'Workspace operation lookup failed.'
                );
            }


            $operation =
                $response->json();
        }


        throw new RuntimeException(
            'Workspace Events operation timed out.'
        );
    }


    private function persist(
        GoogleChatSubscription $record,
        array $subscription
    ): GoogleChatSubscription {

        $record->update([

            'google_name' =>
                $subscription['name']
                ??
                $record->google_name,

            'target_resource' =>
                $subscription[
                    'targetResource'
                ]
                ??
                $record
                    ->target_resource,

            'expire_time' =>
                !empty(
                    $subscription[
                        'expireTime'
                    ]
                )
                    ? GoogleChatTime::parse(
                        $subscription[
                            'expireTime'
                        ]
                    )
                    : null,

            'last_renewed_at' =>
                now(),

            'status' =>
                'active',

            'last_error' =>
                null,
        ]);


        return
            $record->fresh();
    }
}
