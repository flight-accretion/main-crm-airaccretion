<?php

namespace Tests\Feature\GoogleChat;

use App\Models\GoogleChatSubscription;
use App\Services\GoogleChat\{GoogleChatClient, GoogleChatSubscriptionService};
use Illuminate\Support\Facades\Http;

class GoogleChatSubscriptionTest extends GoogleChatTestCase
{
    public function test_subscription_is_scoped_and_reused_per_space(): void
    {
        $this->harden();
        config(['services.google_chat.allowed_spaces' => ['spaces/one', 'spaces/two'],
            'services.google_chat.pubsub_topic' => 'projects/test/topics/chat']);
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('accessToken')->andReturn('test');
        Http::fake(function ($request) {
            $target = $request['targetResource'];
            return Http::response(['done' => true, 'response' => [
                'name' => 'subscriptions/'.basename($target), 'targetResource' => $target,
                'expireTime' => now()->addDays(7)->toIso8601String(),
            ]]);
        });
        $service = app(GoogleChatSubscriptionService::class);
        $one = $service->ensureForSpace('spaces/one');
        $two = $service->ensureForSpace('spaces/two');
        $this->assertNotSame($one->id, $two->id);
        $this->assertSame($one->id, $service->ensureForSpace('spaces/one')->id);
        $this->assertSame(2, GoogleChatSubscription::count());
        Http::assertSentCount(2);
    }
}
