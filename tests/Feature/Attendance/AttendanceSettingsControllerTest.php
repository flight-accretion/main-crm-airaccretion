<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\UserType;

class AttendanceSettingsControllerTest extends AttendanceFeatureTestCase
{
    public function test_store_policy_makes_new_policy_default_and_clears_previous_default(): void
    {
        $admin = $this->createUserWithRole(
            'Attendance Admin',
            UserType::SUPER_ADMIN
        );

        $oldDefault = AttendanceShiftPolicy::create([
            'name' => 'Old Default',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.attendance.settings.policies.store'), [
                'name' => 'New Default',
                'start_time' => '09:30',
                'end_time' => '18:30',
                'grace_minutes' => 10,
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.attendance.settings.index'));

        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertDatabaseHas('attendance_shift_policies', [
            'name' => 'New Default',
            'start_time' => '09:30',
            'grace_minutes' => 10,
            'is_default' => true,
        ]);
    }

    public function test_update_policy_changes_office_time_and_clears_previous_default(): void
    {
        $admin = $this->createUserWithRole(
            'Attendance Admin',
            UserType::SUPER_ADMIN
        );

        $oldDefault = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Late Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.attendance.settings.policies.update', $policy), [
                'name' => 'Late Shift Updated',
                'start_time' => '12:30',
                'end_time' => '21:30',
                'grace_minutes' => 20,
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.attendance.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertDatabaseHas('attendance_shift_policies', [
            'id' => $policy->id,
            'name' => 'Late Shift Updated',
            'start_time' => '12:30',
            'end_time' => '21:30',
            'grace_minutes' => 20,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_bulk_assignment_replaces_current_policy_without_manual_dates(): void
    {
        $admin = $this->createUserWithRole(
            'Attendance Admin',
            UserType::SUPER_ADMIN
        );

        $employee = $this->createUserWithRole(
            'Sales Employee',
            UserType::SALES_EXECUTIVE
        );

        $oldPolicy = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $newPolicy = AttendanceShiftPolicy::create([
            'name' => 'Early Office Time',
            'start_time' => '09:30',
            'end_time' => '18:30',
            'grace_minutes' => 10,
            'is_default' => false,
            'is_active' => true,
        ]);

        $oldAssignment = AttendanceUserShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_policy_id' => $oldPolicy->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'is_active' => true,
            'assigned_at' => '2026-09-01 09:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->from(route('admin.attendance.settings.index'))
            ->post(route('admin.attendance.settings.assignments.store'), [
                'user_ids' => [$employee->id],
                'shift_policy_id' => $newPolicy->id,
            ])
            ->assertRedirect(route('admin.attendance.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($oldAssignment->fresh()->is_active);
        $this->assertNotNull($oldAssignment->fresh()->unassigned_at);

        $activeAssignments =
            AttendanceUserShiftAssignment::query()
                ->where('user_id', $employee->id)
                ->where('is_active', true)
                ->get();

        $this->assertCount(1, $activeAssignments);
        $this->assertSame($newPolicy->id, $activeAssignments->first()->shift_policy_id);
        $this->assertSame($admin->id, $activeAssignments->first()->assigned_by);
        $this->assertNotNull($activeAssignments->first()->assigned_at);
    }

    public function test_bulk_assignment_rejects_inactive_policy(): void
    {
        $admin = $this->createUserWithRole(
            'Attendance Admin',
            UserType::SUPER_ADMIN
        );

        $employee = $this->createUserWithRole(
            'Sales Employee',
            UserType::SALES_EXECUTIVE
        );

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Inactive Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 15,
            'is_default' => false,
            'is_active' => false,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.attendance.settings.assignments.store'), [
                'user_ids' => [$employee->id],
                'shift_policy_id' => $policy->id,
            ])
            ->assertSessionHasErrors('shift_policy_id');
    }
}
