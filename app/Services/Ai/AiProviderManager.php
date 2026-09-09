<?php

namespace App\Services\Ai;

use App\Models\AiModelProfile;
use RuntimeException;

class AiProviderManager
{
    public function generate(
        AiModelProfile $profile,
        string $instructions,
        string $input
    ): string {
        if (!$profile->enabled) {
            throw new RuntimeException(
                'Selected AI Model Profile is inactive.'
            );
        }

        $apiKey =
            $profile->apiKey();

        if (!$apiKey) {
            throw new RuntimeException(
                'Selected AI Model Profile has no API key.'
            );
        }

        return $this
            ->client(
                $profile->provider
            )
            ->generate(
                $profile->model,
                $apiKey,
                $instructions,
                $input
            );
    }

    public function generateStructured(
        AiModelProfile $profile,
        string $instructions,
        string $input,
        array $responseSchema
    ): array {
        return $this->generateDetailed(
            $profile,
            $instructions,
            $input,
            [
                'response_schema' =>
                    $responseSchema,
            ]
        );
    }

    public function generateDetailed(
        AiModelProfile $profile,
        string $instructions,
        string $input,
        array $options = []
    ): array {
        if (!$profile->enabled) {
            throw new RuntimeException(
                'Selected AI Model Profile is inactive.'
            );
        }

        $apiKey =
            $profile->apiKey();

        if (!$apiKey) {
            throw new RuntimeException(
                'Selected AI Model Profile has no API key.'
            );
        }

        $client =
            $this->client(
                $profile->provider
            );

        if (
            !$client instanceof
                AiProviderDetailedClientInterface
        ) {
            throw new RuntimeException(
                'Selected AI provider does not support structured lead scoring.'
            );
        }

        return $client
            ->generateDetailed(
                $profile->model,
                $apiKey,
                $instructions,
                $input,
                $options
            );
    }

    public function test(
        string $provider,
        string $model,
        string $apiKey
    ): void {
        if (
            trim($apiKey) === ''
        ) {
            throw new RuntimeException(
                'API key is required.'
            );
        }

        if (
            trim($model) === ''
        ) {
            throw new RuntimeException(
                'Model is required.'
            );
        }

        $this
            ->client($provider)
            ->testConnection(
                $model,
                $apiKey
            );
    }

    public function client(
        string $provider
    ): AiProviderClientInterface {
        switch (
            strtolower(
                trim($provider)
            )
        ) {
            case 'openai':
                return app(
                    OpenAiProviderClient::class
                );

            case 'gemini':
                return app(
                    GeminiProviderClient::class
                );

            default:
                throw new RuntimeException(
                    'Unsupported AI provider.'
                );
        }
    }
}
