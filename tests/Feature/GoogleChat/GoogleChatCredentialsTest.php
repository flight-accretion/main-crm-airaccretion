<?php

namespace Tests\Feature\GoogleChat;

use App\Services\GoogleChat\GoogleChatCredentials;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleChatCredentialsTest extends GoogleChatTestCase
{
    public function test_credentials_are_encrypted_with_legacy_env_fallback(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-google-chat-'.Str::uuid();
        config(['filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');
        config(['services.google_chat.refresh_token' => 'legacy-test-token']);
        $store = app(GoogleChatCredentials::class);
        $this->assertSame('legacy-test-token', $store->refreshToken());
        $store->save('new-test-token');
        $this->assertStringNotContainsString('new-test-token', Storage::disk('local')->get($store::PATH));
        $this->assertSame('new-test-token', $store->refreshToken());
    }
}
