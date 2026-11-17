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

    public function test_template_deletion_uses_query_parameters_but_generic_404_does_not_prove_absence(): void
    {
        config()->set('services.msg91_campaigns.template_create_enabled', true);
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        try {
            app(Msg91Client::class)->deleteTemplate('919876543210', 'follow up & reminders');
            $this->fail('Generic 404 must not imply successful deletion.');
        } catch (\App\Services\WhatsApp\Msg91\Msg91ProviderException $exception) {
            $this->assertSame(404, $exception->httpStatus);
        }

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://msg91.example.test/api/v5/whatsapp/client-panel-template/?integrated_number=919876543210&template_name=follow+up+%26+reminders'
                && $request->hasHeader('authkey', 'test-auth-key');
        });
    }
    public function test_template_deletion_preserves_provider_validation_details_on_rejection(): void
    {
        config()->set('services.msg91_campaigns.template_create_enabled', true);
        Http::fake(['*' => Http::response(['message' => 'Template cannot be deleted while pending approval.'], 422)]);

        try {
            app(Msg91Client::class)->deleteTemplate('919876543210', 'follow_up');
            $this->fail('A provider rejection must not be treated as a successful deletion.');
        } catch (\App\Services\WhatsApp\Msg91\Msg91ProviderException $exception) {
            $this->assertSame(422, $exception->httpStatus);
            $this->assertSame('Template cannot be deleted while pending approval.', data_get($exception->payload, 'message'));
        }
    }
    public function test_provider_sync_database_400_is_not_proof_of_actual_template_absence(): void
    {
        config()->set('services.msg91_campaigns.template_create_enabled', true);
        Http::fake(['*' => Http::response([
            'status' => 'fail',
            'hasError' => true,
            'errors' => "Template doesn't exist in our database, please use 'Sync template' option before trying again",
        ], 400)]);

        try {
            app(Msg91Client::class)->deleteTemplate('12025550101', 'qa_template');
            $this->fail('A provider sync instruction is not a deletion confirmation.');
        } catch (\App\Services\WhatsApp\Msg91\Msg91ProviderException $exception) {
            $this->assertSame(400, $exception->httpStatus);
        }

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_dry_run_cannot_be_reported_as_completed_provider_deletion(): void
    {
        config(['services.msg91_campaigns.template_create_enabled' => true, 'services.msg91_campaigns.dry_run' => true]);
        Http::fake();
        try {
            app(Msg91Client::class)->deleteTemplate('12025550101', 'qa_template');
            $this->fail('Dry run must not report deletion.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no provider deletion', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_empty_success_response_is_ambiguous_and_errors_are_redacted(): void
    {
        config(['services.msg91_campaigns.template_create_enabled' => true]);
        Http::fake(['*' => Http::response([], 200)]);
        try {
            app(Msg91Client::class)->deleteTemplate('12025550101', 'qa_template');
            $this->fail('An empty response does not confirm deletion.');
        } catch (\App\Services\WhatsApp\Msg91\Msg91ProviderException $exception) {
            $this->assertSame(200, $exception->httpStatus);
        }
        $safe = \App\Services\WhatsApp\Msg91\Msg91SafeError::message(['message' => 'authkey=test-auth-key Authorization: Bearer private-token'], 'Fallback');
        $this->assertStringNotContainsString('test-auth-key', $safe);
        $this->assertStringNotContainsString('private-token', $safe);
    }
}
