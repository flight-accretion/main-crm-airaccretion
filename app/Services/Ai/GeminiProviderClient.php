<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProviderClient
    implements AiProviderClientInterface
{
    public function generate(
        string $model,
        string $apiKey,
        string $instructions,
        string $input
    ): string {
        $model =
            trim($model);

        $url =
            'https://generativelanguage.googleapis.com/'
            . 'v1beta/models/'
            . rawurlencode($model)
            . ':generateContent';

        $response =
            Http::timeout(
                (int) config(
                    'whatcrm.timeout',
                    20
                )
            )
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'x-goog-api-key' =>
                        $apiKey,
                ])
                ->post(
                    $url,
                    [
                        'system_instruction' => [
                            'parts' => [
                                [
                                    'text' =>
                                        $instructions,
                                ],
                            ],
                        ],

                        'contents' => [
                            [
                                'role' =>
                                    'user',

                                'parts' => [
                                    [
                                        'text' =>
                                            $input,
                                    ],
                                ],
                            ],
                        ],
                    ]
                );

        if (!$response->successful()) {
            throw new RuntimeException(
                'AI provider request failed.'
            );
        }

        $payload =
            $response->json() ?: [];

        $parts =
            data_get(
                $payload,
                'candidates.0.content.parts',
                []
            );

        $texts = [];

        foreach (
            (array) $parts
            as $part
        ) {
            $text =
                $part['text']
                ?? null;

            if (
                is_string($text)
                &&
                trim($text) !== ''
            ) {
                $texts[] =
                    trim($text);
            }
        }

        $text =
            trim(
                implode(
                    PHP_EOL,
                    $texts
                )
            );

        if ($text === '') {
            throw new RuntimeException(
                'AI provider returned an empty response.'
            );
        }

        return $text;
    }

    public function testConnection(
        string $model,
        string $apiKey
    ): void {
        $this->generate(
            $model,
            $apiKey,
            'Reply exactly with OK.',
            'Connection test.'
        );
    }
}