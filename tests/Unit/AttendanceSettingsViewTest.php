<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceSettingsViewTest extends TestCase
{
    public function test_attendance_settings_has_searchable_employee_checkbox_assignment_ui(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/attendance/settings.blade.php')
        );

        $this->assertStringContainsString('data-attendance-user-search', $source);
        $this->assertStringContainsString('data-attendance-user-option', $source);
        $this->assertStringContainsString('name="user_ids[]"', $source);
        $this->assertStringContainsString('name="shift_policy_id"', $source);
        $this->assertStringContainsString('name="effective_from"', $source);
        $this->assertStringContainsString('name="effective_to"', $source);
        $this->assertStringContainsString('Grace Minutes', $source);
        $this->assertStringContainsString('Office Time Policies', $source);
    }
}
