<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppOpenAiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOpenAiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_old_n8n_prompt_placeholders_are_replaced_with_crm_runtime_data(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 24, 15, 45, 0)
        );

        config()->set(
            'whatcrm.openai_responses_url',
            'https://api.openai.test/v1/responses'
        );

        $setting = new WhatsAppAiAgentSetting([
            'model' => 'gpt-4o-mini',
            'prompt' => implode(PHP_EOL, [
                'MEMORY FIRST: Before every reply, check the conversation history and the table for {{ $(\'Webhook\').item.json.body.number }}.',
                'Today\'s date is {{ $now.format(\'dd MMMM yyyy\') }} if asked for today, tommorow or anny other timing.',
                'Update the row for {{ $(\'Webhook\').item.json.body.number }} once you collected name | number | service | date | occassion | guests',
            ]),
        ]);
        $setting->setApiKey('openai-key');

        $conversation = new WhatsAppConversation();
        $conversation->setRelation(
            'contact',
            new WhatsAppContact([
                'name' => 'Runtime Customer',
                'normalized_phone' => '919876543210',
                'raw_phone' => '+91 98765 43210',
            ])
        );

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response(
                [
                    'output_text' => json_encode([
                        'reply' => 'Which city are you looking for yacht booking in?',
                        'product' => 'N/A',
                    ]),
                ],
                200
            ),
        ]);

        app(WhatsAppOpenAiClient::class)->generateReply(
            $setting,
            $conversation,
            collect([
                new WhatsAppMessage([
                    'direction' => 'incoming',
                    'message_type' => 'text',
                    'body' => 'Need yacht booking',
                    'message_at' => now(),
                ]),
            ]),
            collect([
                new Product([
                    'product' => 'Yacht in Goa',
                ]),
                new Product([
                    'product' => 'Helicopter Ride in Mumbai',
                ]),
            ])
        );

        Http::assertSent(function ($request) {
            $instructions = (string) data_get(
                $request->data(),
                'instructions'
            );

            return str_contains($instructions, '919876543210')
                && str_contains(
                    $instructions,
                    'Current CRM date/time: 24 August 2026 03:45 PM'
                )
                && str_contains($instructions, 'Yacht in Goa')
                && str_contains($instructions, 'Helicopter Ride in Mumbai')
                && !str_contains($instructions, 'Webhook')
                && !str_contains($instructions, '$now.format');
        });
    }
}
