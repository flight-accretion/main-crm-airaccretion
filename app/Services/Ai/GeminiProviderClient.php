<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiProviderClient
    implements AiProviderClientInterface,
    AiProviderDetailedClientInterface
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
        return $this->generateDetailed(
            $model,
            $apiKey,
            $instructions,
            $input,
            [
                'response_schema' =>
                    $responseSchema,
            ]
        );
    }

    public function generateDetailed(
        string $model,
        string $apiKey,
        string $instructions,
        string $input,
        array $options = []
    ): array {
        $responseSchema =
            $options['response_schema']
            ?? $options['responseSchema']
            ?? null;

        if (!is_array($responseSchema)) {
            throw new RuntimeException(
                'Structured response schema is required.'
            );
        }

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
                'maxOutputTokens' => 800,
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        try {
            $response =
                $this->postRequest(
                    $this->url($model),
                    $apiKey,
                    $body,
                    60
                );
        } catch (ConnectionException $e) {
            throw new AiProviderException(
                'AI provider request timed out or could not connect.',
                true,
                null,
                $e
            );
        }

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

            try {
                $response =
                    $this->postRequest(
                        $this->url($model),
                        $apiKey,
                        $body,
                        60
                    );
            } catch (ConnectionException $e) {
                throw new AiProviderException(
                    'AI provider request timed out or could not connect.',
                    true,
                    null,
                    $e
                );
            }
        }

        if (!$response->successful()) {
            throw $this->providerException(
                $response
            );
        }

        $payload =
            $response->json() ?: [];

        try {
            $text =
                $this->extractText(
                    $payload
                );
        } catch (RuntimeException $e) {
            throw new AiProviderException(
                $e->getMessage(),
                false,
                null,
                $e
            );
        }

        return [
            'text' =>
                $text,

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

                'thinking_tokens' =>
                    (int) data_get(
                        $payload,
                        'usageMetadata.thoughtsTokenCount',
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
    ): Response {
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

    private function providerException(
        Response $response
    ): AiProviderException {
        $status =
            (int) $response->status();

        $payload =
            $response->json() ?: [];

        $providerMessage =
            data_get(
                $payload,
                'error.message'
            );

        if (!is_string($providerMessage)) {
            $providerMessage =
                $response->body();
        }

        $providerMessage =
            trim((string) $providerMessage);

        $retryable =
            $status === 408
            || $status === 429
            || $status >= 500;

        $message =
            'AI provider request failed'
            . ($status ? " ({$status})" : '');

        if ($providerMessage !== '') {
            $message .= ': '
                . Str::limit(
                    $providerMessage,
                    500,
                    ''
                );
        }

        return new AiProviderException(
            $message,
            $retryable,
            $status
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
