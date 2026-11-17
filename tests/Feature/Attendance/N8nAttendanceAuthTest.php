<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;

class N8nAttendanceAuthTest extends TestCase
{
    public function test_employee_api_rejects_missing_token(): void
    {
        config([
            'services.n8n_attendance.token' => 'local-test-token',
        ]);

        $this->getJson(
            '/api/n8n/employees/active'
        )->assertStatus(401);
    }

    public function test_attendance_api_rejects_bad_token_before_processing_payload(): void
    {
        config([
            'services.n8n_attendance.token' => 'local-test-token',
        ]);

        $this->withToken('wrong-token')
            ->postJson('/api/n8n/attendance', [
                'records' => [
                    [
                        'attendance_date' => '2026-11-15',
                        'employee_name' => 'DEEPAK PURANE',
                        'in_time' => '10:33:00',
                        'out_time' => '19:35:00',
                        'raw_status' => 'P',
                    ],
                ],
            ])
            ->assertStatus(401);
    }
}