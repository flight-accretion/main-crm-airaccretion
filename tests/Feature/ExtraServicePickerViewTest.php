<?php

namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ExtraServicePickerViewTest extends TestCase
{
    public function test_long_labels_wrap_and_selected_ids_render_as_checkbox_values(): void
    {
        $label = str_repeat('LongServiceName', 12);
        $html = view('admin.pages.services.partials.extra-service-picker', [
            'extraServices' => [
                (object) ['id' => 'selected-id', 'extra_service' => $label, 'extra_service_amount' => 150],
                (object) ['id' => 'available-id', 'extra_service' => 'Available', 'extra_service_amount' => 200],
            ],
            'selectedExtraServiceIds' => ['selected-id'],
            'errors' => new ViewErrorBag(),
        ])->render();

        $document = new \DOMDocument();
        $document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $selected = $xpath->query('//label/input[@name="extra_service_ids[]" and @value="selected-id" and @checked]')->item(0);
        $this->assertNotNull($selected);
        $this->assertStringContainsString('shrink-0', $selected->getAttribute('class'));
        $this->assertStringContainsString($label, $selected->parentNode->textContent);
        $this->assertSame(1, $xpath->query('..//span[contains(@style,"overflow-wrap: anywhere")]', $selected)->length);
        $this->assertSame(1, $xpath->query('//label/input[@name="extra_service_ids[]" and @value="available-id" and not(@checked)]')->length);
    }
}
