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

    public function test_stored_start_time_ignores_tba_flag_so_sender_can_untick_it(): void
    {
        $service = new BookingTravelDetailService();

        $ride = new LeadRide([
            'from_date' => '2026-10-04 16:30:00',
            'is_tba' => true,
        ]);

        $this->assertSame('TBA', $service->rideTime($ride));
        $this->assertSame('04:30 PM', $service->storedStartTime($ride));
        $this->assertSame('', $service->storedStartTime(null));
    }

    public function test_time_state_pre_ticks_tba_for_flag_missing_time_and_default_noon(): void
    {
        $service = new BookingTravelDetailService();

        $flagged = $service->timeState(new LeadRide([
            'from_date' => '2026-10-04 16:30:00',
            'to_date' => '2026-10-04 17:30:00',
            'is_tba' => true,
        ]));
        $this->assertTrue($flagged['default_tba']);
        $this->assertSame('04:30 PM', $flagged['voucher_time']);

        $missing = $service->timeState(new LeadRide([
            'from_date' => '2026-10-04 00:00:00',
            'to_date' => '2026-10-04 00:00:00',
        ]));
        $this->assertTrue($missing['default_tba']);
        $this->assertSame('', $missing['voucher_time']);

        // The lead form's date picker fills in 12:00 when only a date is chosen.
        $defaultNoon = $service->timeState(new LeadRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 12:00:00',
        ]));
        $this->assertTrue($defaultNoon['default_tba']);
        $this->assertSame('12:00 PM', $defaultNoon['voucher_time']);
        $this->assertStringContainsString('default 12:00', $defaultNoon['note']);

        // A real noon ride has a different end time.
        $realNoon = $service->timeState(new LeadRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 13:00:00',
        ]));
        $this->assertFalse($realNoon['default_tba']);

        $real = $service->timeState(new LeadRide([
            'from_date' => '2026-10-04 17:00:00',
            'to_date' => '2026-10-04 18:00:00',
        ]));
        $this->assertFalse($real['default_tba']);
        $this->assertSame('05:00 PM', $real['voucher_time']);
    }

    public function test_duration_from_ride_dates(): void
    {
        $service = new BookingTravelDetailService();

        $this->assertSame('1 Hour', $service->durationFromRideDates(new LeadRide([
            'from_date' => '2026-08-04 17:00:00',
            'to_date' => '2026-08-04 18:00:00',
        ])));

        // Several days count the first and the last day.
        $this->assertSame('4 Days', $service->durationFromRideDates(new LeadRide([
            'from_date' => '2026-07-22 12:00:00',
            'to_date' => '2026-07-25 12:00:00',
        ])));

        // Default 12:00 / no time / TBA cannot give a real duration.
        $this->assertSame('', $service->durationFromRideDates(new LeadRide([
            'from_date' => '2026-08-20 12:00:00',
            'to_date' => '2026-08-20 12:00:00',
        ])));
        $this->assertSame('', $service->durationFromRideDates(new LeadRide([
            'from_date' => '2026-09-04 00:00:00',
            'to_date' => '2026-09-04 02:00:00',
        ])));
        $this->assertSame('', $service->durationFromRideDates(new LeadRide([
            'from_date' => '2026-08-04 17:00:00',
            'to_date' => '2026-08-04 18:00:00',
            'is_tba' => true,
        ])));
        $this->assertSame('', $service->durationFromRideDates(null));
    }

    public function test_resolve_duration_order_is_service_then_total_time_then_dates_then_none(): void
    {
        $service = new BookingTravelDetailService();

        $ride = new LeadRide([
            'from_date' => '2026-08-04 17:00:00',
            'to_date' => '2026-08-04 18:30:00',
            'total_time' => '2.00',
        ]);

        $this->assertSame(
            ['value' => '30 Minutes', 'source' => 'service name', 'needs_confirm' => false],
            $service->resolveDuration('30 Minutes', $ride)
        );
        $this->assertSame(
            ['value' => '2 Hours', 'source' => 'voucher total time', 'needs_confirm' => false],
            $service->resolveDuration('', $ride)
        );

        $ride->total_time = null;

        $this->assertSame(
            ['value' => '1 Hour 30 Min', 'source' => 'ride dates', 'needs_confirm' => false],
            $service->resolveDuration('', $ride)
        );
        $this->assertSame(
            ['value' => '', 'source' => 'none', 'needs_confirm' => false],
            $service->resolveDuration('', new LeadRide([
                'from_date' => '2026-08-20 12:00:00',
                'to_date' => '2026-08-20 12:00:00',
            ]))
        );
    }

    public function test_multi_day_ride_dates_need_sender_confirmation(): void
    {
        $service = new BookingTravelDetailService();

        $resolved = $service->resolveDuration('', new LeadRide([
            'from_date' => '2026-07-22 12:00:00',
            'to_date' => '2026-07-25 12:00:00',
        ]));

        $this->assertSame('4 Days', $resolved['value']);
        $this->assertSame('ride dates', $resolved['source']);
        $this->assertTrue($resolved['needs_confirm']);
    }
}
