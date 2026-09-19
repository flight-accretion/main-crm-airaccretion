<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\UserType;

class AttendanceShiftPolicyModelTest extends AttendanceFeatureTestCase
{
    public function test_shift_policy_generates_uuid_and_casts_flags(): void
    {
        $policy = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($policy->id);
        $this->assertTrue($policy->is_default);
        $this->assertTrue($policy->is_active);
        $this->assertSame(15, $policy->grace_minutes);
    }

    public function test_assignment_links_user_to_shift_policy(): void
    {
        $user = $this->createUserWithRole('HR User', UserType::HR);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        $assignment = AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $policy->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
        ]);

        $this->assertSame($user->id, $assignment->user->id);
        $this->assertSame($policy->id, $assignment->shiftPolicy->id);
        $this->assertSame(
            '2026-09-01',
            $assignment->effective_from->toDateString()
        );
    }
}
