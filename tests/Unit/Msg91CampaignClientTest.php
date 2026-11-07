<?php

namespace Tests\Unit;

use App\Services\WhatsApp\Msg91\Msg91Client;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Msg91CampaignClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.msg91_campaigns.enabled', true);
        config()->set('services.msg91_campaigns.dry_run', false);
        config()->set('services.msg91_campaigns.auth_key', 'test-auth-key');
        config()->set('services.msg91_campaigns.control_url', 'https://msg91.example.test');
        config()->set('services.msg91_campaigns.template_create_url', 'https://api.msg91.example.test');
    }

    public function test_bulk_template_request_uses_the_server_side_sender_and_documented_payload_shape(): void
    {
        Http::fake(['https://msg91.example.test/api/v5/whatsapp/whatsapp-outbound-message/bulk/' => Http::response(['request_id' => 'msg91-request-1'])]);
        $result = app(Msg91Client::class)->sendTemplate('919876543210', ['name' => 'follow_up', 'language' => 'en'], '919812345678', ['body_1' => ['type' => 'text', 'value' => 'Asha']]);
        $this->assertSame('msg91-request-1', $result['request_id']);
        Http::assertSent(function ($request) {
            return $request->hasHeader('authkey', 'test-auth-key')
                && data_get($request->data(), 'integrated_number') === '919876543210'
                && data_get($request->data(), 'payload.template.to_and_components.0.to.0') === '919812345678'
                && data_get($request->data(), 'payload.template.to_and_components.0.components.body_1.value') === 'Asha';
        });
    }

    public function test_disabled_module_never_calls_msg91(): void
    {
        config()->set('services.msg91_campaigns.enabled', false);
        Http::fake();
        try {
            app(Msg91Client::class)->sendTemplate('919876543210', ['name' => 'test', 'language' => 'en'], '919812345678', []);
            $this->fail('Disabled campaigns must not call MSG91.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('MSG91 campaigns are disabled.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_template_creation_uses_msg91_endpoint_and_never_auto_approves_a_template(): void
    {
        config()->set('services.msg91_campaigns.template_create_enabled', true);
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'data' => ['template_id' => 'provider-template-1'],
            ]),
        ]);

        $result = app(Msg91Client::class)->createTemplate([
            'integrated_number' => '919876543210',
            'template_name' => 'accretion_demo_test',
            'language' => 'en',
            'category' => 'UTILITY',
            'button_url' => false,
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
        ]);

        $this->assertSame('provider-template-1', $result['template_id']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.msg91.example.test/api/v5/whatsapp/client-panel-template/'
                && $request->hasHeader('authkey', 'test-auth-key')
                && str_contains($request->body(), '"template_name":"accretion_demo_test"')
                && str_contains($request->body(), '"integrated_number":"919876543210"');
        });
    }

    public function test_template_deletion_uses_query_parameters_and_treats_missing_template_as_already_deleted(): void
    {
        config()->set('services.msg91_campaigns.template_create_enabled', true);
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        app(Msg91Client::class)->deleteTemplate(
            '919876543210',
            'follow up & reminders'
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://msg91.example.test/api/v5/whatsapp/client-panel-template?integrated_number=919876543210&template_name=follow%20up%20%26%20reminders'
                && $request->hasHeader('authkey', 'test-auth-key');
        });
    }
}
