<?php
namespace Tests\Feature;

use Tests\TestCase;

class RideStatusPresentationTest extends TestCase
{
    public function test_ride_stylesheet_has_a_deployment_cache_version(): void
    {
        $source = file_get_contents(resource_path('views/admin/account/rides/ride-status.blade.php'));
        $this->assertMatchesRegularExpression('/ride-status\.css.*\?v=/', $source);
    }

    public function test_customer_refund_has_a_reschedule_only_layout_and_explicit_ride_context(): void
    {
        $partial = file_get_contents(resource_path('views/admin/account/rides/partials/customer-refund-two-step.blade.php'));
        $reschedules = file_get_contents(resource_path('views/admin/pages/operations/reschedules/index.blade.php'));
        $rideStatus = file_get_contents(resource_path('views/admin/account/rides/ride-status.blade.php'));
        $script = file_get_contents(public_path('assets/admin/js/crm/customer-refund-two-step.js'));
        $css = file_get_contents(public_path('assets/admin/css/crm/customer-refund-reschedule.css'));

        $this->assertStringContainsString('crm-customer-refund', $partial);
        $this->assertStringContainsString('customer-refund-reschedule.css', $reschedules);
        $this->assertStringContainsString("setAttribute('data-ride-id', selectedRideId)", $rideStatus);
        $this->assertStringContainsString('const explicitRideId = root.dataset.rideId;', $script);
        $this->assertStringContainsString('#ride-details-modal .crm-customer-refund', $css);
        $this->assertStringNotContainsString('.crm-reschedules .crm-customer-refund', $css);
    }
    public function test_removed_export_controls_are_not_left_as_executable_blade_inside_html_comments(): void
    {
        $source = file_get_contents(resource_path('views/admin/account/rides/ride-status.blade.php'));
        $this->assertStringNotContainsString('class="rs-export"', $source);
        $this->assertDoesNotMatchRegularExpression('/<!--\s*@if\(\$exportRoute\)/', $source);
    }

    public function test_timeline_defaults_closed_and_existing_actions_keep_their_targets(): void
    {
        $ridesData=require base_path('tests/Fixtures/ride-status/cards.php');
        $html=view('admin.account.rides.partials.status-cards',['ridesData'=>$ridesData,'tableProductNames'=>[]])->render();
        $dom=new \DOMDocument(); @$dom->loadHTML($html); $xpath=new \DOMXPath($dom);
        $this->assertSame(1,$xpath->query('//details[contains(@class,"rs-timeline")]')->length);
        $this->assertSame(0,$xpath->query('//details[@open]')->length);
        $this->assertSame('Timeline',trim($xpath->query('//details/summary')->item(0)->textContent));
        $this->assertSame($ridesData[0]['id'],$xpath->query('//button[@data-ride-id]')->item(0)->getAttribute('data-ride-id'));
        $this->assertSame('false',$xpath->query('//button[@data-ride-id]')->item(0)->getAttribute('aria-expanded'));
        $this->assertStringContainsString($ridesData[0]['lead_id'],$xpath->query('//div[@class="rs-actions"]/a')->item(0)->getAttribute('href'));
    }

    public function test_cancelled_ride_refund_timeline_displays_recorded_activity_dates(): void
    {
        $ridesData = require base_path('tests/Fixtures/ride-status/cards.php');
        $ridesData[0]['status'] = 2;
        $ridesData[0]['refund_display'] = [
            'vendor' => ['label' => 'Refund Pending', 'tone' => 'warning', 'due' => 100, 'date' => '03 Nov 2026, 14:01'],
            'customer' => ['label' => 'Refunded', 'tone' => 'success', 'due' => 0, 'date' => '03 Nov 2026, 14:02'],
        ];

        $html = view('admin.account.rides.partials.status-cards', [
            'ridesData' => $ridesData,
            'tableProductNames' => [],
        ])->render();
       
        $dom = new \DOMDocument();

        @$dom->loadHTML($html);

        $xpath = new \DOMXPath($dom);

        $refundDates = [];

        foreach (
            $xpath->query(
                '//li[contains(@class, "rs-timeline-refund")]/time'
            ) as $time
        ) {
            $refundDates[] = trim($time->textContent);
        }

        $this->assertContains(
            '03 Nov 2026, 14:01',
            $refundDates
        );

        $this->assertContains(
            '03 Nov 2026, 14:02',
            $refundDates
        );

    }
}
