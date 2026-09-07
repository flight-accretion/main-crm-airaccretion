<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProviderClient
    implements AiProviderClientInterface
{
    public function generate(
        string $model,
        string $apiKey,
        string $instructions,
        string $input
    ): string {
        $response =
            Http::timeout(
                (int) config(
                    'whatcrm.timeout',
                    20
                )
            )
                ->acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->post(
                    (string) config(
                        'whatcrm.openai_responses_url',
                        'https://api.openai.com/v1/responses'
                    ),
                    [
                        'model' =>
                            $model,

                        'instructions' =>
                            $instructions,

                        'input' =>
                            $input,
                    ]
                );

        if (!$response->successful()) {
            throw new RuntimeException(
                'AI provider request failed.'
            );
        }

        $payload =
            $response->json() ?: [];

        $outputText =
            data_get(
                $payload,
                'output_text'
            );

        if (
            is_string($outputText)
            &&
            trim($outputText) !== ''
        ) {
            return trim(
                $outputText
            );
        }

        $parts = [];

        foreach (
            (array) data_get(
                $payload,
                'output',
                []
            )
            as $item
        ) {
            foreach (
                (array) data_get(
                    $item,
                    'content',
                    []
                )
                as $content
            ) {
                $text =
                    data_get(
                        $content,
                        'text'
                    );

                if (
                    is_string($text)
                    &&
                    trim($text) !== ''
                ) {
                    $parts[] =
                        trim($text);
                }
            }
        }

        $text =
            trim(
                implode(
                    PHP_EOL,
                    $parts
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