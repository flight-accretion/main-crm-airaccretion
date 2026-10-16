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
        // Office-time assignment remains immediate; effective dates belong only to week-offs.
        preg_match('/<form[^>]*data-assignments-form.*?<\/form>/s', $source, $assignmentForm);
        $this->assertStringNotContainsString('name="effective_from"', $assignmentForm[0]);
        $this->assertStringNotContainsString('name="effective_to"', $assignmentForm[0]);
        $this->assertStringContainsString('Current office time', $source);
        $this->assertStringContainsString('Assign selected', $source);
        $this->assertStringContainsString('Grace (minutes)', $source);
        $this->assertStringContainsString('Office time policies', $source);
        $this->assertStringContainsString("route('admin.attendance.settings.policies.update', \$policy)", $source);
        $this->assertStringContainsString("@method('PUT')", $source);
        $this->assertStringContainsString('Update', $source);
    }

    public function test_calendar_controls_retain_separate_effective_schedule_and_approved_swap_forms(): void
    {
        $source = file_get_contents(resource_path('views/admin/pages/attendance/settings.blade.php'));
        $this->assertStringContainsString('name="effective_from"', $source);
        $this->assertStringContainsString('name="weekdays[]"', $source);
        $this->assertStringContainsString('name="dates[]"', $source);
        $this->assertStringContainsString("route('admin.attendance.calendar.swaps')", $source);
        $this->assertStringContainsString('$canManageCalendar', $source);
    }

    public function test_assignment_history_office_time_shows_hours_not_policy_name(): void
    {
        $source=file_get_contents(resource_path('views/admin/pages/attendance/settings.blade.php'));
        preg_match('/<td data-label="Office time">.*?<\/td>/s',$source,$matches);
        $compiled=app('blade.compiler')->compileString($matches[0]);
        foreach([
            ['10:30:00','19:30:00','10:30 - 19:30'],
            ['09:00:00',null,'09:00 - Not set'],
            [null,null,'-'],
        ] as [$start,$end,$expected]){
            $assignment=(object)['shiftPolicy'=>$start ? (object)['name'=>'Default Office Time','start_time'=>$start,'end_time'=>$end] : null];
            ob_start();try{eval('?>'.$compiled);$html=ob_get_contents();}finally{ob_end_clean();}
            $this->assertStringContainsString($expected,$html);
            $this->assertStringNotContainsString('Default Office Time',$html);
        }
    }
}
