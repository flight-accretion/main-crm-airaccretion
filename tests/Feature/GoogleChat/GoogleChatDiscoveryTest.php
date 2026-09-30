<?php

namespace Tests\Feature\GoogleChat;

use App\Services\GoogleChat\GoogleChatClient;
use Illuminate\Support\Facades\Http;

class GoogleChatDiscoveryTest extends GoogleChatTestCase
{
    public function test_space_discovery_reads_all_pages(): void
    {
        Http::fakeSequence()
            ->push(['spaces' => [['name' => 'spaces/one']], 'nextPageToken' => 'next'])
            ->push(['spaces' => [['name' => 'spaces/two']]]);
        $client = \Mockery::mock(GoogleChatClient::class)->makePartial();
        $client->shouldReceive('accessToken')->andReturn('test-token');
        $this->assertSame(['spaces/one', 'spaces/two'], array_column($client->listSpaces(), 'name'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'pageToken=next'));
    }

    public function test_space_command_uses_shared_client(): void
    {
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('listSpaces')->once()->andReturn([
            ['name' => 'spaces/one', 'displayName' => 'Operations'],
        ]);
        $this->artisan('google-chat:list-spaces')->assertExitCode(0);
    }

    public function test_member_lookup_fails_with_actionable_reauthorization_message(): void
    {
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('listSpaceMembers')->with('spaces/one')->once()
            ->andThrow(new \RuntimeException('private upstream error'));
        $this->artisan('google-chat:list-members', ['space' => 'spaces/one'])
            ->expectsOutput('For insufficient scope, re-authorize at /admin/google-chat/oauth with chat.memberships.readonly.')
            ->assertExitCode(1);
    }
}
