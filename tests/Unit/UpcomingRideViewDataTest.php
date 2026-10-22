<?php

namespace Tests\Unit;

use App\Services\Operations\UpcomingRideViewData;
use Carbon\Carbon;
use Tests\TestCase;

class UpcomingRideViewDataTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_summary_and_calendar_use_the_same_actual_rides_and_payment_evidence(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $rows = collect([
            $this->row('a', '2026-10-21', 0, 1000, false, true),
            $this->row('b', '2026-10-22', 500, 1000, true, false),
            $this->row('c', '2026-10-23', 0, 0, false, false),
        ]);
        $data = app(UpcomingRideViewData::class)->build($rows);
        $this->assertSame(3, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['today']);
        $this->assertSame(1, $data['summary']['tomorrow']);
        $this->assertSame(1, $data['summary']['unconfirmed']);
        $this->assertSame(1, $data['summary']['payment_pending']);
        $this->assertSame(1, $data['summary']['fully_paid']);
        $this->assertSame(33, $data['summary']['paid_percent']);
        $this->assertSame('urgent', $data['rows'][1]['health']);
        $this->assertSame('Amount not recorded', $data['rows'][2]['payment_label']);
        $this->assertSame(['a', 'b', 'c'], array_column($data['events'], 'id'));
        $this->assertSame(['2026-10-21', '2026-10-22', '2026-10-23'], $data['groups']->keys()->all());
    }

    public function test_empty_data_does_not_invent_payments_or_counts(): void
    {
        $data = app(UpcomingRideViewData::class)->build(collect());
        $this->assertSame(0, $data['summary']['total']);
        $this->assertSame(0, $data['summary']['paid_percent']);
        $this->assertSame([], $data['events']);
    }

    public function test_existing_statuses_and_strings_are_not_replaced_by_sample_states(): void
    {
        $row = $this->row('a', '2026-10-21', 200, 1000, false, true);
        $row['status_name'] = 'Reschedule';
        $row['client_name'] = '<img src=x onerror=alert(1)>';
        $data = app(UpcomingRideViewData::class)->build(collect([$row]));
        $this->assertSame('Reschedule', $data['rows'][0]['status_name']);
        $this->assertSame($row['client_name'], $data['events'][0]['title']);
        $this->assertSame('payment', $data['rows'][0]['health']);
    }

    public function test_list_and_calendar_escape_customer_text_and_keep_details(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $actor = new \App\Models\User(); $actor->id = 'upcoming-preview';
        auth()->setUser($actor);
        $rows = collect();
        for ($index = 0; $index < 18; $index++) {
            $day = now()->addDays((int) floor($index / 5))->toDateString();
            $row = $this->row('ride-'.$index, $day, $index % 3 === 0 ? 0 : 500, 1000, $index === 5, $index % 4 !== 0);
            $row['client_name'] = ['Aditi Sharma', 'Rohit Mehta', 'Priya Kulkarni', 'Nikhil Kapoor', 'Swati Kher'][$index % 5];
            if ($index === 17) $row['client_name'] = '<script>window.badCustomerScript=true</script>';
            $row['description'] = 'Customer asked for the updated service details.';
            $rows->push($row);
        }
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $request = \Illuminate\Http\Request::create('/admin/rides/upcoming-ride');
        $request->setLaravelSession(app('session.store')); app()->instance('request', $request);
        $data = ['schedule' => app(UpcomingRideViewData::class)->build($rows), 'salesReps' => collect(), 'products' => collect(),
            'availableStatuses' => [1 => 'Active', 5 => 'Completed', 7 => 'Reschedule'],
            'currentFilters' => ['from_date' => '2026-10-21', 'to_date' => '2026-11-03', 'status' => null, 'product_id' => null, 'is_tba' => null, 'representative_user_id' => null]];
        $html = view('admin.pages.rides.upcoming-rides', $data)->render();
        $this->assertStringNotContainsString('<script>window.badCustomerScript=true</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Customer asked for the updated service details.', $html);
        if ($dir = getenv('CRM_UPCOMING_PREVIEW_DIR')) file_put_contents($dir.'/synthetic.html', $html);
    }

    private function row(string $id, string $day, float $due, float $total, bool $tba, bool $vendor): array
    {
        return ['id' => $id, 'lead_id' => $id, 'client_name' => 'Customer '.$id, 'ride_day' => $day,
            'ride_start' => $day.'T12:00:00', 'ride_time' => $tba ? 'TBA' : '12:00', 'is_tba' => $tba,
            'pending_amount_value' => $due, 'total_amount_value' => $total, 'has_vendor' => $vendor,
            'status_id' => 3, 'status_name' => 'Full Payment Received', 'product_names' => 'Flight',
            'service_names' => 'Mumbai', 'sales_person_name' => 'Staff', 'from_place' => 'Mumbai', 'to_place' => 'Pune',
            'ride_date' => $day, 'contact_number' => '+91 9876543210', 'extra_service_names' => '',
            'number_of_passengers' => 2, 'occasion' => '', 'description' => ''];
    }
}
