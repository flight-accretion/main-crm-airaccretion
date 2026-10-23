<?php
namespace Tests\Feature;

use Tests\TestCase;

class RideStatusPresentationTest extends TestCase
{
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
}
