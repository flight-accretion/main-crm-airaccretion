<?php

namespace Tests\Unit;

use Tests\TestCase;

class LeadServicePreviewViewTest extends TestCase
{
    public function test_service_preview_keeps_full_text_inside_truncation_wrapper_without_hover_title(): void
    {
        $serviceText =
            'Private Helicopter Ride In Mussoorie, '
            . 'Shared Helicopter Ride In Mussoorie';

        $html = view(
            'admin.pages.leads.partials.service-preview',
            [
                'serviceNames' => [
                    'Private Helicopter Ride In Mussoorie',
                    'Shared Helicopter Ride In Mussoorie',
                ],
            ]
        )->render();

        $this->assertStringContainsString(
            '<span class="lead-service-preview">',
            $html
        );
        $this->assertStringContainsString($serviceText, $html);
        $this->assertStringNotContainsString(' title=', $html);
        $this->assertStringNotContainsString('title="', $html);
    }

    public function test_service_preview_shows_na_inside_same_wrapper_when_services_are_missing(): void
    {
        $html = view(
            'admin.pages.leads.partials.service-preview',
            ['serviceNames' => []]
        )->render();

        $this->assertStringContainsString(
            '<span class="lead-service-preview">',
            $html
        );
        $this->assertStringContainsString('N/A', $html);
    }
}
