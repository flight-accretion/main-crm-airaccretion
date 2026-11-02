<?php

namespace Tests\Unit;

use App\Services\WhatsApp\Msg91\Msg91TemplateSyncService;
use Tests\TestCase;

class Msg91TemplateSyncServiceTest extends TestCase
{
    public function test_it_assigns_standard_crm_values_to_template_variables_automatically(): void
    {
        $schema = app(Msg91TemplateSyncService::class)
            ->schemaForBody('Hello {{1}}, your email is {{2}} and your service is {{3}}.');

        $this->assertSame('customer_name', $schema['body_1']['field']);
        $this->assertSame('customer_email', $schema['body_2']['field']);
        $this->assertSame('product_name', $schema['body_3']['field']);
    }

    public function test_it_uses_customer_name_for_variables_beyond_the_standard_fields(): void
    {
        $schema = app(Msg91TemplateSyncService::class)->schemaForBody('{{7}}');

        $this->assertSame('customer_name', $schema['body_7']['field']);
    }
}
