<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Services\Attendance\AttendanceShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceShiftResolverTest extends AttendanceFeatureTestCase
{
    public function test_resolves_matching_user_assignment_for_date(): void
    {
        $user = $this->createUserWithRole('Assigned Employee');

        AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $early = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $early->id,
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame($early->id, $resolved->id);
        $this->assertSame('Early Shift', $resolved->name);
        $this->assertSame(5, $resolved->grace_minutes);
    }

    public function test_falls_back_to_active_default_policy(): void
    {
        $user = $this->createUserWithRole('Default Employee');

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame($default->id, $resolved->id);
    }

    public function test_falls_back_to_legacy_config_when_no_default_policy_exists(): void
    {
        config()->set('kpi.attendance.shift_start', '11:00');
        config()->set('kpi.attendance.grace_minutes', 20);

        $user = $this->createUserWithRole('Legacy Fallback Employee');

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame('Legacy Config Office Time', $resolved->name);
        $this->assertSame('11:00', substr((string) $resolved->start_time, 0, 5));
        $this->assertSame(20, $resolved->grace_minutes);
        $this->assertFalse($resolved->exists);
    }

    public function test_bulk_resolution_uses_date_specific_assignments(): void
    {
        $user = $this->createUserWithRole('Changing Shift Employee');

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $late = AttendanceShiftPolicy::create([
            'name' => 'Late Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 10,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $late->id,
            'effective_from' => '2026-09-10',
            'effective_to' => null,
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDates(
            $user,
            collect([
                Carbon::parse('2026-09-09'),
                Carbon::parse('2026-09-10'),
            ])
        );

        $this->assertInstanceOf(Collection::class, $resolved);
        $this->assertSame($default->id, $resolved->get('2026-09-09')->id);
        $this->assertSame($late->id, $resolved->get('2026-09-10')->id);
    }

    public function test_first_current_assignment_is_base_policy_for_prior_unprocessed_dates(): void
    {
        $user = $this->createUserWithRole('Base Policy Employee');

        AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $early = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $early->id,
            'effective_from' => '2026-09-19',
            'effective_to' => null,
            'is_active' => true,
            'assigned_at' => '2026-09-19 10:00:00',
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-01')
        );

        $this->assertSame($early->id, $resolved->id);
        $this->assertSame('Early Shift', $resolved->name);
    }
}
