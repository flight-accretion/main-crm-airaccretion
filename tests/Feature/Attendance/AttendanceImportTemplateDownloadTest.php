<?php

namespace Tests\Feature\Attendance;

use App\Models\UserType;

class AttendanceImportTemplateDownloadTest extends AttendanceFeatureTestCase
{
    public function test_hr_can_download_attendance_import_csv_template(): void
    {
        $hr = $this->createUserWithRole(
            'Attendance HR',
            UserType::HR
        );

        $response = $this
            ->actingAs($hr)
            ->get(route('admin.attendance.import.sample', [
                'format' => 'csv',
            ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'attendance_import_sample_',
            (string) $response->headers->get('Content-Disposition')
        );

        $content = $response->streamedContent();

        $this->assertStringContainsString('Paycode:-', $content);
        $this->assertStringContainsString('Date,Day,In,Out,Status', $content);
        $this->assertStringContainsString('2026-09-01,Tuesday,10:30,19:30,P', $content);
    }

    public function test_hr_can_download_attendance_import_excel_template(): void
    {
        $hr = $this->createUserWithRole(
            'Attendance HR',
            UserType::HR
        );

        $response = $this
            ->actingAs($hr)
            ->get(route('admin.attendance.import.sample'));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertStringStartsWith(
            'PK',
            $response->streamedContent()
        );
    }
}
