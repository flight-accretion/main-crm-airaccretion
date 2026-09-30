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
        return $disk->exists(self::PATH)
            ? Crypt::decryptString($disk->get(self::PATH))
            : trim((string) config('services.google_chat.refresh_token'));
    }
}
