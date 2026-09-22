<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceImportViewTest extends TestCase
{
    public function test_attendance_import_view_has_template_download_links(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/attendance/import.blade.php')
        );

        $this->assertStringContainsString(
            "route('admin.attendance.import.sample')",
            $source
        );
        $this->assertStringContainsString(
            "route('admin.attendance.import.sample', ['format' => 'csv'])",
            $source
        );
        $this->assertStringContainsString('Download Excel Format', $source);
        $this->assertStringContainsString('Download CSV Format', $source);
    }
}
