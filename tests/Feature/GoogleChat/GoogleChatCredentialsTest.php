<?php

namespace Tests\Feature\GoogleChat;

use App\Services\GoogleChat\GoogleChatCredentials;
use Illuminate\Support\Facades\Storage;

class GoogleChatCredentialsTest extends GoogleChatTestCase
{
    public function test_credentials_are_encrypted_with_legacy_env_fallback(): void
    {
        Storage::fake('local');
        config(['services.google_chat.refresh_token' => 'legacy-test-token']);
        $store = app(GoogleChatCredentials::class);
        $this->assertSame('legacy-test-token', $store->refreshToken());
        $store->save('new-test-token');
        $this->assertStringNotContainsString('new-test-token', Storage::disk('local')->get($store::PATH));
        $this->assertSame('new-test-token', $store->refreshToken());
    }
}
