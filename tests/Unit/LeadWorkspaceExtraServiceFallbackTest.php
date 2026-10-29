<?php

namespace Tests\Unit;

use Tests\TestCase;

class LeadWorkspaceExtraServiceFallbackTest extends TestCase
{
    public function test_selected_extra_service_options_provide_breakdown_fallback_data(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/pages/follow-ups/add-follow-up.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('data-extra-service-name="{{ $extraService->extra_service }}"', $view);
        $this->assertStringContainsString('data-extra-service-amount="{{ $extraService->extra_service_amount }}"', $view);
        $this->assertStringContainsString('selected <option> is the source of truth', $view);
        $this->assertStringContainsString('selectedOption.dataset.extraServiceAmount', $view);
        $this->assertStringContainsString('name: option.dataset.extraServiceName || option.text', $view);
        $this->assertStringContainsString('option.dataset.extraServiceAmount = optionData.amount', $view);
    }
}
