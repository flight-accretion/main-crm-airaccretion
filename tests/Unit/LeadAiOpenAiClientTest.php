<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiModelProfile;
use App\Models\LeadAiScoringSetting;
use App\Services\LeadAiOpenAiClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LeadAiOpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'app.key',
            'base64:' . base64_encode(str_repeat('a', 32))
        );

        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]
        );

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_gemini_structured_response_is_validated_and_temperature_is_derived(): void
    {
        $setting =
            $this->leadScoringSetting();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' =>
                Http::response(
                    [
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        [
                                            'text' => json_encode([
                                                'temperature' => 'cold',
                                                'score' => 70,
                                                'confidence' => 88,
                                                'score_reason' => 'Customer is ready to proceed.',
                                                'score_change_reason' => 'New buying signal appeared.',
                                                'summary' => [
                                                    'Customer confirmed the requirement.',
                                                    'Payment intent is present.',
                                                ],
                                                'next_commitment' => 'request_payment',
                                                'actions' => [
                                                    [
                                                        'channel' => 'call',
                                                        'action' => 'Confirm booking intent',
                                                        'script' => 'Shall I guide you through confirmation?',
                                                    ],
                                                    [
                                                        'channel' => 'whatsapp',
                                                        'action' => 'Send payment next step',
                                                        'script' => 'I can share the confirmation step now.',
                                                    ],
                                                    [
                                                        'channel' => 'call',
                                                        'action' => 'Resolve final concern',
                                                        'script' => 'Is anything else stopping confirmation?',
                                                    ],
                                                ],
                                                'state' => [
                                                    'payment_intent' => true,
                                                    'customer_ghosting' => false,
                                                ],
                                            ]),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'usageMetadata' => [
                            'promptTokenCount' => 101,
                            'cachedContentTokenCount' => 11,
                            'candidatesTokenCount' => 77,
                            'totalTokenCount' => 178,
                        ],
                    ],
                    200
                ),
        ]);

        $result =
            app(LeadAiOpenAiClient::class)
                ->analyse(
                    $setting,
                    [
                        'crm' => [
                            'approved_received_amount' => 0,
                        ],
                        'bootstrap_history' => [],
                    ]
                );

        $this->assertSame('hot', $result['temperature']);
        $this->assertSame(70, $result['score']);
        $this->assertCount(3, $result['actions']);
        $this->assertSame('request_payment', $result['next_commitment']);
        $this->assertSame(11, $result['usage']['cached_input_tokens']);

        Http::assertSent(function ($request) {
            $data =
                $request->data();

            $input =
                (string) data_get(
                    $data,
                    'contents.0.parts.0.text'
                );

            $instructions =
                (string) data_get(
                    $data,
                    'system_instruction.parts.0.text'
                );

            return data_get(
                $data,
                'generationConfig.maxOutputTokens'
            ) === 800
                && data_get(
                    $data,
                    'generationConfig.responseMimeType'
                ) === 'application/json'
                && in_array(
                    'actions',
                    data_get(
                        $data,
                        'generationConfig.responseSchema.required',
                        []
                    ),
                    true
                )
                && !str_contains(
                    $input,
                    PHP_EOL
                )
                && str_contains(
                    $instructions,
                    'do not return a temperature'
                )
                && str_contains(
                    $instructions,
                    'email-source signal as score 90'
                )
                && str_contains(
                    $input,
                    'bootstrap_history'
                );
        });
    }

    public function test_response_must_include_exactly_three_actions(): void
    {
        $setting =
            $this->leadScoringSetting();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' =>
                Http::response(
                    [
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        [
                                            'text' => json_encode([
                                                'score' => 60,
                                                'confidence' => 80,
                                                'score_reason' => 'Customer is still evaluating.',
                                                'score_change_reason' => 'No clear movement.',
                                                'summary' => [
                                                    'Customer is still evaluating.',
                                                ],
                                                'next_commitment' => 'confirm_requirement',
                                                'actions' => [
                                                    [
                                                        'channel' => 'call',
                                                        'action' => 'Confirm requirement',
                                                        'script' => 'May I confirm your requirement?',
                                                    ],
                                                ],
                                                'state' => [],
                                            ]),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'AI lead coaching must contain exactly 3 actions.'
        );

        app(LeadAiOpenAiClient::class)
            ->analyse(
                $setting,
                [
                    'crm' => [],
                    'bootstrap_history' => [],
                ]
            );
    }

    private function leadScoringSetting(): LeadAiScoringSetting
    {
        $profile =
            new AiModelProfile([
                'name' => 'Gemini Lead Scoring',
                'provider' => 'gemini',
                'model' => 'gemini-test',
                'enabled' => true,
            ]);

        $profile->setApiKey('gemini-key');
        $profile->save();

        $agent =
            AiAgent::create([
                'name' => 'Lead Scoring Agent',
                'agent_type' => 'lead_scoring',
                'ai_model_profile_id' => $profile->id,
                'prompt' => 'Follow Accretion Aviation lead scoring rules.',
                'enabled' => true,
            ]);

        return LeadAiScoringSetting::create([
            'id' => (string) Str::uuid(),
            'enabled' => true,
            'auto_analyse' => true,
            'prompt' => 'Follow Accretion Aviation lead scoring rules.',
            'cold_max' => 39,
            'neutral_max' => 69,
            'ai_agent_id' => $agent->id,
            'ai_model_profile_id' => $profile->id,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('ai_model_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('provider');
            $table->string('model');
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('agent_type')->default('generic');
            $table->uuid('ai_model_profile_id')->nullable();
            $table->text('prompt');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_ai_scoring_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_analyse')->default(true);
            $table->string('model')->nullable();
            $table->text('prompt')->nullable();
            $table->unsignedSmallInteger('cold_max')->default(39);
            $table->unsignedSmallInteger('neutral_max')->default(69);
            $table->uuid('ai_model_profile_id')->nullable();
            $table->uuid('ai_agent_id')->nullable();
            $table->timestamps();
        });
    }
}
