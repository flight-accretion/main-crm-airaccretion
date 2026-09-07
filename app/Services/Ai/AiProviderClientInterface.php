<?php

namespace App\Services\Ai;

interface AiProviderClientInterface
{
    public function generate(
        string $model,
        string $apiKey,
        string $instructions,
        string $input
    ): string;

    public function testConnection(
        string $model,
        string $apiKey
    ): void;
}