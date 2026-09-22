<?php

namespace Tests\Unit;

use App\Models\LeadRide;
use App\Services\BookingTravelDetailService;
use Tests\TestCase;

class BookingTravelDetailServiceTest extends TestCase
{
    public function test_ride_time_uses_tba_flag_before_any_stored_time(): void
    {
        $service = new BookingTravelDetailService();

        $ride = new LeadRide([
            'from_date' => '2026-10-04 16:30:00',
            'is_tba' => true,
        ]);

        $this->assertSame('TBA', $service->rideTime($ride));
    }

    public function test_ride_time_formats_real_start_time_and_hides_midnight_default(): void
    {
        $service = new BookingTravelDetailService();

        $this->assertSame(
            '04:30 PM',
            $service->rideTime(new LeadRide([
                'from_date' => '2026-10-04 16:30:00',
                'is_tba' => false,
            ]))
        );

        $this->assertSame(
            '',
            $service->rideTime(new LeadRide([
                'from_date' => '2026-10-04 00:00:00',
                'is_tba' => false,
            ]))
        );
    }

    public function test_duration_prefers_existing_service_duration_before_total_time_fallback(): void
    {
        $service = new BookingTravelDetailService();

        $ride = new LeadRide([
            'total_time' => '2.50',
        ]);

        $this->assertSame('30 Minutes', $service->duration('30 Minutes', $ride));
        $this->assertSame('2 Hours 30 Min', $service->duration('', $ride));
        $this->assertSame('', $service->duration('', new LeadRide(['total_time' => '0.00'])));
    }
}
