<?php

namespace App\Services\GoogleChat;

use Illuminate\Support\Facades\{Cache, Crypt, Storage};

class GoogleChatCredentials
{
    public const PATH = 'private/google-chat/refresh-token.enc';

    public function save(string $token): void
    {
        if (!Storage::disk('local')->put(self::PATH, Crypt::encryptString($token), 'private')) {
            throw new \RuntimeException('Unable to persist Google Chat credentials.');
        }
        Cache::forget('google-chat-user-access-token');
    }

    public function refreshToken(): string
    {
        $disk = Storage::disk('local');
        $legacyToken = trim((string) config('services.google_chat.refresh_token'));

        if (!$disk->exists(self::PATH)) {
            return $legacyToken;
        }

        try {
            return Crypt::decryptString($disk->get(self::PATH));
        } catch (\Throwable $exception) {
            // A legacy environment token remains the safe fallback when an old
            // on-disk credential can no longer be decrypted (for example after
            // an application-key rotation).
            return $legacyToken;
        }
    }
}
