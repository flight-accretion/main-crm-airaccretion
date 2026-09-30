<?php

namespace Tests\Feature\GoogleChat;

use App\Services\GoogleChat\GoogleChatPushVerifier;
use Google\Auth\AccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GoogleChatPushVerifierTest extends GoogleChatTestCase
{
    public function test_missing_verification_configuration_fails_closed(): void
    {
        config(['services.google_chat.pubsub_audience' => '', 'services.google_chat.pubsub_service_account' => '']);
        $this->expectException(HttpException::class);
        app(GoogleChatPushVerifier::class)->verify('untrusted');
    }

    public function test_wrong_email_or_unverified_email_is_rejected(): void
    {
        config(['services.google_chat.pubsub_audience' => 'https://test.test/push',
            'services.google_chat.pubsub_service_account' => 'push@test.iam.gserviceaccount.com']);
        $token = $this->mock(AccessToken::class);
        $token->shouldReceive('verify')->andReturn(['iss' => 'https://accounts.google.com',
            'aud' => 'https://test.test/push', 'email' => 'wrong@example.test', 'email_verified' => true]);
        $this->expectException(HttpException::class);
        app(GoogleChatPushVerifier::class)->verify('signed-token');
    }
}
