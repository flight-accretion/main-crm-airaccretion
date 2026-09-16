<?php

namespace Tests\Unit;

use Tests\TestCase;

class WebsiteCatalogConfigTest extends TestCase
{
    public function test_website_catalog_config_is_available_at_the_runtime_path(): void
    {
        $services = require base_path('config/services.php');

        $this->assertIsArray($services['website_catalog'] ?? null);
        $this->assertArrayHasKey('webhook_secret', $services['website_catalog']);
        $this->assertArrayHasKey('notes_url', $services['website_catalog']);
        $this->assertArrayHasKey('notes_secret', $services['website_catalog']);
        $this->assertArrayNotHasKey('website_catalog', $services['email_leads']);
    }
}
