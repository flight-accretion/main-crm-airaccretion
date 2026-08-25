<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyWhatCrmWebhook;
use Illuminate\Http\Request;
use Tests\TestCase;

class VerifyWhatCrmWebhookTest extends TestCase
{
    public function test_accepts_raw_token_header_from_whatcrm_flow(): void
    {
        config()->set('whatcrm.token', 'shared-secret');

        $request = Request::create(
            '/api/whatcrm/messages',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_TOKEN' => 'shared-secret',
            ]
        );

        $response = app(VerifyWhatCrmWebhook::class)
            ->handle(
                $request,
                fn () => response()->json([
                    'success' => true,
                ])
            );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_accepts_raw_authorization_header_without_bearer_prefix(): void
    {
        config()->set('whatcrm.token', 'shared-secret');

        $request = Request::create(
            '/api/whatcrm/messages',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'shared-secret',
            ]
        );

        $response = app(VerifyWhatCrmWebhook::class)
            ->handle(
                $request,
                fn () => response()->json([
                    'success' => true,
                ])
            );

        $this->assertSame(200, $response->getStatusCode());
    }
}
