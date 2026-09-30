<?php

namespace App\Services\GoogleChat;

use Google\Auth\AccessToken;
use Illuminate\Support\Facades\{Cache, Http};
use GuzzleHttp\Psr7\Response;

class GoogleChatPushVerifier
{
    public function verify(string $jwt): array
    {
        $audience = trim((string) config('services.google_chat.pubsub_audience'));
        $email = trim((string) config('services.google_chat.pubsub_service_account'));
        abort_unless($jwt !== '' && $audience !== '' && $email !== '', 403);
        // Cache Google's certificates, never an unverified JWT or its claims.
        $verifier = app()->bound(AccessToken::class) ? app(AccessToken::class) : new AccessToken(function ($request) {
            $uri = (string) $request->getUri();
            $body = Cache::remember('google-chat:certificates:'.sha1($uri), 1800,
                fn () => Http::timeout(10)->get($uri)->throw()->body());
            return new Response(200, ['Content-Type' => 'application/json'], $body);
        });
        try {
            $claims = $verifier->verify($jwt, ['audience' => $audience, 'throwException' => true]);
        } catch (\Throwable $e) {
            abort(403, 'Invalid Pub/Sub identity.');
        }
        abort_unless(is_array($claims)
            && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($claims['aud'] ?? '') === $audience
            && ($claims['email'] ?? '') === $email
            && ($claims['email_verified'] ?? false) === true, 403);
        return $claims;
    }
}
