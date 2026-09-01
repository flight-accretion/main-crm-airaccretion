<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Tests\TestCase;

class SalesDashboardPresenceViewTest extends TestCase
{
    public function test_presence_partial_shows_all_sales_people_for_super_admin(): void
    {
        $html = view('admin.pages.dashboards.partials.sales-presence', [
            'salesPresenceRows' => new Collection([
                [
                    'name' => 'Akshita Borkar',
                    'role' => 'Sales Executive',
                    'status_label' => 'Yes',
                    'is_present_today' => true,
                    'last_response_label' => '31 Aug 2026, 10:45 AM',
                ],
                [
                    'name' => 'Pallavi Singh',
                    'role' => 'Sales Manager',
                    'status_label' => 'No',
                    'is_present_today' => false,
                    'last_response_label' => '30 Aug 2026, 06:00 PM',
                ],
            ]),
            'canViewAllSalesPresence' => true,
        ])->render();

        $this->assertStringContainsString('Today', $html);
        $this->assertStringContainsString('Present Today', $html);
        $this->assertStringContainsString('Akshita Borkar', $html);
        $this->assertStringContainsString('Pallavi Singh', $html);
        $this->assertStringContainsString('Yes', $html);
        $this->assertStringContainsString('No', $html);
    }

    public function test_presence_partial_shows_own_status_for_regular_user(): void
    {
        $html = view('admin.pages.dashboards.partials.sales-presence', [
            'salesPresenceRows' => new Collection([
                [
                    'name' => 'Samarpit Sharma',
                    'role' => 'Sales Executive',
                    'status_label' => 'No',
                    'is_present_today' => false,
                    'last_response_label' => 'Not confirmed today',
                ],
            ]),
            'canViewAllSalesPresence' => false,
        ])->render();

        $this->assertStringContainsString('Your Availability Today', $html);
        $this->assertStringContainsString('Samarpit Sharma', $html);
        $this->assertStringContainsString('No', $html);
    }
}
