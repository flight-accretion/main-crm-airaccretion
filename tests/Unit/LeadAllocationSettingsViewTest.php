<?php

namespace Tests\Unit;

use Tests\TestCase;

class LeadAllocationSettingsViewTest extends TestCase
{
    public function test_product_assignment_uses_searchable_checkbox_picker_with_existing_payload_name(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/lead-allocation/settings.blade.php')
        );

        $this->assertStringContainsString('data-lead-product-picker', $source);
        $this->assertStringContainsString('data-lead-product-search', $source);
        $this->assertStringContainsString('data-lead-product-option', $source);
        $this->assertStringContainsString('data-lead-product-summary', $source);
        $this->assertStringContainsString('Search products', $source);
        $this->assertStringContainsString(
            'name="email_product_assignments[{{ $user->id }}][]"',
            $source
        );
        $this->assertStringNotContainsString(
            'email-product-select',
            $source
        );
    }
}
