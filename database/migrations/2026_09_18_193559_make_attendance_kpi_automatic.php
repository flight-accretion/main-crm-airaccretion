<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeAttendanceKpiAutomatic extends Migration
{
    public function up()
    {
        DB::table('kpi_metrics')
            ->where('code', 'attendance')
            ->update([
                'measurement_type' => 'automatic',
                'source_key' => 'sales_attendance',
                'target_value' => 95,
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        DB::table('kpi_metrics')
            ->where('code', 'attendance')
            ->update([
                'measurement_type' => 'manual_numeric',
                'source_key' => null,
                'target_value' => 95,
                'updated_at' => now(),
            ]);
    }
}