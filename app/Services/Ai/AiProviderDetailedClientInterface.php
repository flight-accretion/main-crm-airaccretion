<?php

namespace App\Services\Ai;

interface AiProviderDetailedClientInterface
{
    public function generateDetailed(
        string $model,
        string $apiKey,
        string $instructions,
        string $input,
        array $options = []
    ): array;
}
