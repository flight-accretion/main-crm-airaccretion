<?php

namespace Tests\Unit;

use App\Services\WhatsApp\VendorRefundWhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorRefundWhatsAppServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.operations_ride_alert.api_url', 'https://whatsapp.example.test/send_templet');
        config()->set('services.operations_ride_alert.api_key', 'operations-api-key');
        config()->set('services.operations_ride_alert.token', 'operations-ivr-token');
        config()->set('services.whatscrm.vendor_refund_template', 'vendor_refund_pdf');
        config()->set('services.whatscrm.vendor_refund_image_template', 'vendor_refund_image');
    }

    public function test_vendor_refund_template_uses_operations_whatsapp_account(): void
    {
        Http::fake([
            'https://whatsapp.example.test/send_templet' => Http::response([
                'message_id' => 'vendor-refund-message-1',
            ]),
        ]);

        $result = app(VendorRefundWhatsAppService::class)->send(
            '+91 9876543210',
            ['Vendor', 'Customer', 'Service', '2026-10-29', '1000.00'],
            'https://crm.example.test/storage/refund.pdf',
            'refund.pdf'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('vendor-refund-message-1', $result['provider_message_id']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://whatsapp.example.test/send_templet'
                && $request->hasHeader('Authorization', 'Bearer operations-api-key')
                && $request->data() === [
                    'sendTo' => '+919876543210',
                    'templetName' => 'vendor_refund_pdf',
                    'exampleArr' => ['Vendor', 'Customer', 'Service', '2026-10-29', '1000.00'],
                    'token' => 'operations-ivr-token',
                    'mediaUri' => 'https://crm.example.test/storage/refund.pdf',
                    'enableLog' => true,
                ];
        });
    }

    public function test_vendor_refund_image_uses_the_configured_image_template(): void
    {
        Http::fake([
            'https://whatsapp.example.test/send_templet' => Http::response(['success' => true]),
        ]);

        app(VendorRefundWhatsAppService::class)->send(
            '9876543210',
            ['Vendor'],
            'https://crm.example.test/storage/refund.jpg',
            'refund.jpg'
        );

        Http::assertSent(function ($request) {
            return $request->data()['templetName'] === 'vendor_refund_image';
        });
    }
}
