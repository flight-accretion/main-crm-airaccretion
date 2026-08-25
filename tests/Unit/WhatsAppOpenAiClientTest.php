<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\Product;
use App\Models\User;
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

    public function test_named_crm_runtime_placeholders_are_replaced_with_ist_date_time(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 25, 13, 37, 0, 'Asia/Kolkata')
        );

        config()->set(
            'whatcrm.openai_responses_url',
            'https://api.openai.test/v1/responses'
        );

        $setting = new WhatsAppAiAgentSetting([
            'model' => 'gpt-4o-mini',
            'prompt' => implode(PHP_EOL, [
                'Reference timestamp: {{CRM_CURRENT_DATETIME_IST}}.',
                'Reference date: {{CRM_CURRENT_DATE_IST}}.',
                'Reference time: {{CRM_CURRENT_TIME_IST}}.',
                'Customer number: {{CRM_CUSTOMER_NUMBER}}.',
            ]),
        ]);
        $setting->setApiKey('openai-key');

        $conversation = new WhatsAppConversation();
        $conversation->setRelation(
            'contact',
            new WhatsAppContact([
                'name' => 'IST Customer',
                'normalized_phone' => '918765432100',
                'raw_phone' => '+91 87654 32100',
            ])
        );

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response(
                [
                    'output_text' => json_encode([
                        'reply' => 'May I confirm the exact date?',
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
                    'body' => 'Tomorrow',
                    'message_at' => now(),
                ]),
            ]),
            collect()
        );

        Http::assertSent(function ($request) {
            $instructions = (string) data_get(
                $request->data(),
                'instructions'
            );

            return str_contains(
                $instructions,
                'Reference timestamp: 25-Aug-2026 01:37 PM IST.'
            )
                && str_contains(
                    $instructions,
                    'Reference date: 25-Aug-2026.'
                )
                && str_contains(
                    $instructions,
                    'Reference time: 01:37 PM IST.'
                )
                && str_contains(
                    $instructions,
                    'Customer number: 918765432100.'
                )
                && !str_contains($instructions, '{{CRM_CURRENT_DATETIME_IST}}')
                && !str_contains($instructions, '{{CRM_CURRENT_DATE_IST}}')
                && !str_contains($instructions, '{{CRM_CURRENT_TIME_IST}}')
                && !str_contains($instructions, '{{CRM_CUSTOMER_NUMBER}}');
        });
    }

    public function test_full_crm_runtime_placeholders_are_replaced_from_crm_models_and_pricing_sheet(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 25, 13, 37, 0, 'Asia/Kolkata')
        );

        config()->set(
            'whatcrm.openai_responses_url',
            'https://api.openai.test/v1/responses'
        );
        config()->set(
            'whatcrm.pricing_sheet_csv_url',
            'https://sheets.test/pricing.csv'
        );
        config()->set('whatcrm.pricing_sheet_cache_ttl', 0);

        $setting = new WhatsAppAiAgentSetting([
            'model' => 'gpt-4o-mini',
            'prompt' => implode(PHP_EOL, [
                'Customer: {{CRM_CUSTOMER_NAME}} / {{CRM_CUSTOMER_NUMBER}}',
                'Status: {{CRM_LEAD_STATUS}}',
                'Previous service: {{CRM_PREVIOUS_SERVICE}}',
                'Last booking: {{CRM_LAST_BOOKING_DATE}}',
                'State: {{CRM_LEAD_STATE}}',
                'Missing: {{CRM_MISSING_FIELDS}}',
                'Notes: {{CRM_NOTES}}',
                'Agent: {{CRM_ASSIGNED_AGENT_NAME}} / {{CRM_ASSIGNED_AGENT_NUMBER}}',
                'Products: {{CRM_ACTIVE_PRODUCTS}}',
                'Service data: {{CRM_SERVICE_DATA}}',
                'Locations: {{CRM_SERVICE_LOCATIONS}}',
                'Pricing: {{CRM_PRICING_DATA}}',
                'Availability: {{CRM_AVAILABILITY_DATA}}',
                'Product link: {{CRM_PRODUCT_LINK}}',
                'Selling facts: {{CRM_APPROVED_SELLING_FACTS}}',
                'History: {{CRM_CONVERSATION_HISTORY}}',
                'Current message: {{CRM_CURRENT_CUSTOMER_MESSAGE}}',
            ]),
        ]);
        $setting->setApiKey('openai-key');

        $product = new Product([
            'id' => 'product-yacht',
            'product' => 'Yacht Rental',
            'status' => 1,
        ]);

        $lead = new Lead([
            'product_ids' => ['product-yacht'],
            'number_of_passengers' => 4,
            'description' => 'Lead wants a premium Goa yacht experience.',
            'occasion' => 'Anniversary',
            'created_at' => now()->subDay(),
        ]);
        $lead->setRelation(
            'client',
            new Client([
                'name' => 'Rahul Sharma',
                'contact_number' => '+91 98765 43210',
            ])
        );
        $lead->setRelation(
            'representative',
            new User([
                'name' => 'Samarpit Sharma',
                'contact_number' => '9109152175',
            ])
        );
        $lead->setRelation(
            'leadFollowups',
            collect([
                new LeadFollowup([
                    'status' => 1,
                    'followup_note' => 'Customer interested in Goa yacht.',
                    'next_followup_date' => now()->addDay(),
                    'created_at' => now()->subHour(),
                ]),
            ])
        );
        $lead->setRelation(
            'rideSegments',
            collect([
                new LeadRide([
                    'from_place' => 'Goa',
                    'to_place' => null,
                    'from_date' => Carbon::create(
                        2026,
                        8,
                        30,
                        16,
                        0,
                        0,
                        'Asia/Kolkata'
                    ),
                ]),
            ])
        );

        $conversation = new WhatsAppConversation([
            'last_message' => 'Need yacht in Goa on 30 Aug',
        ]);
        $conversation->setRelation(
            'contact',
            new WhatsAppContact([
                'name' => 'Rahul Sharma',
                'normalized_phone' => '919876543210',
                'raw_phone' => '+91 98765 43210',
            ])
        );
        $conversation->setRelation('lead', $lead);
        $conversation->setRelation(
            'assignedUser',
            new User([
                'name' => 'Assigned Chat Agent',
                'contact_number' => '9999999999',
            ])
        );

        Http::fake([
            'https://sheets.test/pricing.csv' => Http::response(
                implode("\n", [
                    'Product,City,Duration,Price,Currency,Notes',
                    'Yacht Rental,Goa,2 hours,35000,INR,Per ride',
                ]),
                200
            ),
            'https://api.openai.test/v1/responses' => Http::response(
                [
                    'output_text' => json_encode([
                        'reply' => 'What time would you prefer for the yacht?',
                        'product' => 'Yacht Rental',
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
                    'body' => 'Need yacht in Goa on 30 Aug',
                    'message_at' => now(),
                ]),
            ]),
            collect([$product]),
            collect([
                new WhatsAppMessage([
                    'direction' => 'incoming',
                    'message_type' => 'text',
                    'body' => 'Previous context says anniversary',
                    'message_at' => now()->subMinutes(5),
                ]),
            ])
        );

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.openai.test/v1/responses') {
                return false;
            }

            $instructions = (string) data_get(
                $request->data(),
                'instructions'
            );

            return str_contains($instructions, 'Customer: Rahul Sharma / 919876543210')
                && str_contains($instructions, 'Status: Active')
                && str_contains($instructions, 'Previous service: Yacht Rental')
                && str_contains($instructions, 'Last booking: 30-Aug-2026')
                && str_contains($instructions, '"guests":4')
                && str_contains($instructions, 'Missing: none')
                && str_contains($instructions, 'Customer interested in Goa yacht.')
                && str_contains($instructions, 'Agent: Assigned Chat Agent / 9999999999')
                && str_contains($instructions, 'Products: Yacht Rental')
                && str_contains($instructions, 'Yacht Rental | id: product-yacht')
                && str_contains($instructions, 'Goa')
                && str_contains($instructions, 'Yacht Rental | Goa | 2 hours | INR 35000 | Per ride')
                && str_contains($instructions, 'Availability: Not provided by CRM')
                && str_contains($instructions, 'Product link: Not provided by CRM')
                && str_contains($instructions, 'Selling facts: Not provided by CRM')
                && str_contains($instructions, 'Previous context says anniversary')
                && str_contains($instructions, 'Current message: Need yacht in Goa on 30 Aug')
                && !str_contains($instructions, '{{CRM_');
        });
    }
}
