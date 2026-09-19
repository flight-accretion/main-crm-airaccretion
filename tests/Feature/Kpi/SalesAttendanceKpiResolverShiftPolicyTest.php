<?php

namespace Tests\Feature\Kpi;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use App\Models\UserType;
use App\Services\Kpi\SalesAttendanceKpiResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesAttendanceKpiResolverShiftPolicyTest extends KpiFeatureTestCase
{
    public function test_attendance_kpi_uses_employee_specific_shift_policy(): void
    {
        $user = $this->createUserWithRole(
            'Shifted Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $metric = $this->attendanceMetric();

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
            'effective_to' => null,
        ]);

        $this->insertAttendanceRecord(
            $user->id,
            'E001',
            '2026-09-01',
            'Tuesday',
            '09:04:00',
            '18:00:00'
        );

        $this->insertAttendanceRecord(
            $user->id,
            'E001',
            '2026-09-02',
            'Wednesday',
            '09:06:00',
            '18:00:00'
        );

        $resolved = app(SalesAttendanceKpiResolver::class)->resolve(
            $user,
            $metric,
            Carbon::parse('2026-09-02 23:59:59'),
            22
        );

        $this->assertSame(2, $resolved['evidence']['eligible_scheduled_days']);
        $this->assertSame(1, $resolved['evidence']['punctual_days']);
        $this->assertSame(1, $resolved['evidence']['late_days']);
        $this->assertSame(50.0, $resolved['evidence']['punctuality_percent']);
        $this->assertSame('09:00', $resolved['evidence']['shift_start']);
        $this->assertSame(5, $resolved['evidence']['grace_minutes']);
        $this->assertSame('09:05', $resolved['evidence']['punctual_cutoff']);
        $this->assertArrayHasKey(
            'Early Shift',
            $resolved['evidence']['shift_policy_breakdown']
        );
        $this->assertArrayNotHasKey(
            'Default Office Time',
            $resolved['evidence']['shift_policy_breakdown']
        );
    }

    public function test_attendance_kpi_uses_different_policy_after_effective_date_change(): void
    {
        $user = $this->createUserWithRole(
            'Changing Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $metric = $this->attendanceMetric();

        AttendanceShiftPolicy::create([
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
            'grace_minutes' => 0,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $late->id,
            'effective_from' => '2026-09-02',
            'effective_to' => null,
        ]);

        foreach (
            [
                '2026-09-01' => '10:40:00',
                '2026-09-02' => '12:01:00',
            ]
            as $date => $inTime
        ) {
            $this->insertAttendanceRecord(
                $user->id,
                'E002',
                $date,
                Carbon::parse($date)->format('l'),
                $inTime,
                '19:00:00'
            );
        }

        $resolved = app(SalesAttendanceKpiResolver::class)->resolve(
            $user,
            $metric,
            Carbon::parse('2026-09-02 23:59:59'),
            22
        );

        $this->assertSame(1, $resolved['evidence']['punctual_days']);
        $this->assertSame(1, $resolved['evidence']['late_days']);
        $this->assertSame(
            1,
            $resolved['evidence']['shift_policy_breakdown']['Default Office Time']['eligible_days']
        );
        $this->assertSame(
            1,
            $resolved['evidence']['shift_policy_breakdown']['Late Shift']['eligible_days']
        );
    }

    private function attendanceMetric(): KpiMetric
    {
        $template = KpiTemplate::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Retail Sales KPI',
            'department' => 'sales',
            'working_days_per_month' => 22,
            'active' => true,
        ]);

        return KpiMetric::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'template_id' => $template->id,
            'code' => 'attendance',
            'name' => 'Attendance',
            'measurement_type' => 'automatic',
            'source_key' => 'sales_attendance',
            'target_value' => 95,
            'direction' => 'higher_better',
            'active' => true,
        ]);
    }

    private function insertAttendanceRecord(
        string $userId,
        string $paycode,
        string $date,
        string $day,
        string $inTime,
        string $outTime
    ): void {
        DB::table('attendance_records')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'paycode' => $paycode,
            'attendance_date' => $date,
            'day_name' => $day,
            'in_time' => $inTime,
            'out_time' => $outTime,
            'raw_in' => $inTime,
            'raw_out' => $outTime,
            'raw_status' => 'P',
            'source_import_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
