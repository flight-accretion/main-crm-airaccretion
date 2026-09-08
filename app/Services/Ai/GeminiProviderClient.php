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
        $response =
            $this->postRequest(
                $this->url($model),
                $apiKey,
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
                ],
                (int) config(
                    'whatcrm.timeout',
                    20
                )
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'AI provider request failed.'
            );
        }

        return $this->extractText(
            $response->json() ?: []
        );
    }

    public function generateStructured(
        string $model,
        string $apiKey,
        string $instructions,
        string $input,
        array $responseSchema
    ): array {
        $body = [
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

            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 700,
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $response =
            $this->postRequest(
                $this->url($model),
                $apiKey,
                $body,
                60
            );

        if (
            !$response->successful()
            && $this->shouldRetryWithoutThinking(
                $response
            )
        ) {
            unset(
                $body[
                    'generationConfig'
                ][
                    'thinkingConfig'
                ]
            );

            $response =
                $this->postRequest(
                    $this->url($model),
                    $apiKey,
                    $body,
                    60
                );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'AI provider request failed.'
            );
        }

        $payload =
            $response->json() ?: [];

        return [
            'text' =>
                $this->extractText(
                    $payload
                ),

            'usage' => [
                'input_tokens' =>
                    (int) data_get(
                        $payload,
                        'usageMetadata.promptTokenCount',
                        0
                    ),

                'cached_input_tokens' =>
                    (int) data_get(
                        $payload,
                        'usageMetadata.cachedContentTokenCount',
                        0
                    ),

                'output_tokens' =>
                    (int) data_get(
                        $payload,
                        'usageMetadata.candidatesTokenCount',
                        0
                    ),

                'total_tokens' =>
                    (int) data_get(
                        $payload,
                        'usageMetadata.totalTokenCount',
                        0
                    ),
            ],
        ];
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

    private function postRequest(
        string $url,
        string $apiKey,
        array $body,
        int $timeout
    ) {
        return Http::timeout(
            $timeout
        )
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key' =>
                    $apiKey,
            ])
            ->post(
                $url,
                $body
            );
    }

    private function url(
        string $model
    ): string {
        return 'https://generativelanguage.googleapis.com/'
            . 'v1beta/models/'
            . rawurlencode(
                trim($model)
            )
            . ':generateContent';
    }

    private function extractText(
        array $payload
    ): string {
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
                && trim($text) !== ''
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

    private function shouldRetryWithoutThinking(
        $response
    ): bool {
        if ((int) $response->status() !== 400) {
            return false;
        }

        $body =
            strtolower(
                $response->body()
            );

        return str_contains(
            $body,
            'thinkingconfig'
        )
            || str_contains(
                $body,
                'thinkingbudget'
            );
    }
}
