<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\Ai\GeminiProviderClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderClientLeadScoringTest extends TestCase
{
    public function test_generate_detailed_sends_structured_config_and_returns_thinking_token_usage(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(
                [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '{"score":90}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 101,
                        'cachedContentTokenCount' => 11,
                        'candidatesTokenCount' => 77,
                        'thoughtsTokenCount' => 9,
                        'totalTokenCount' => 198,
                    ],
                ],
                200
            ),
        ]);

        $result = app(GeminiProviderClient::class)
            ->generateDetailed(
                'gemini-2.5-flash-lite',
                'gemini-key',
                'Follow the lead scoring contract.',
                '{"crm":{"lead_source":"email"}}',
                [
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'score' => [
                                'type' => 'INTEGER',
                            ],
                        ],
                    ],
                ]
            );

        $this->assertSame('{"score":90}', $result['text']);
        $this->assertSame(9, $result['usage']['thinking_tokens']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return data_get($data, 'generationConfig.temperature') === 0.2
                && data_get($data, 'generationConfig.maxOutputTokens') === 800
                && data_get($data, 'generationConfig.responseMimeType') === 'application/json'
                && data_get($data, 'generationConfig.responseSchema.type') === 'OBJECT'
                && data_get($data, 'generationConfig.thinkingConfig.thinkingBudget') === 0;
        });
    }

    public function test_generate_detailed_marks_rate_limit_errors_retryable(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(
                [
                    'error' => [
                        'message' => 'Quota exceeded.',
                    ],
                ],
                429
            ),
        ]);

        try {
            app(GeminiProviderClient::class)
                ->generateDetailed(
                    'gemini-2.5-flash-lite',
                    'gemini-key',
                    'Follow the lead scoring contract.',
                    '{}',
                    [
                        'response_schema' => [
                            'type' => 'OBJECT',
                        ],
                    ]
                );

            $this->fail('Expected provider exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(429, $exception->statusCode);
        }
    }

    public function test_generate_detailed_marks_auth_errors_permanent(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(
                [
                    'error' => [
                        'message' => 'Invalid API key.',
                    ],
                ],
                401
            ),
        ]);

        try {
            app(GeminiProviderClient::class)
                ->generateDetailed(
                    'gemini-2.5-flash-lite',
                    'bad-key',
                    'Follow the lead scoring contract.',
                    '{}',
                    [
                        'response_schema' => [
                            'type' => 'OBJECT',
                        ],
                    ]
                );

            $this->fail('Expected provider exception.');
        } catch (AiProviderException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame(401, $exception->statusCode);
        }
    }
}
