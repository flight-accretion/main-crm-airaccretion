<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateAttendanceShiftPolicyTables extends Migration
{
    public function up()
    {
        Schema::create('attendance_shift_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create(
            'attendance_user_shift_assignments',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('shift_policy_id');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->index(
                    [
                        'user_id',
                        'effective_from',
                        'effective_to',
                    ],
                    'attendance_user_shift_range_index'
                );

                $table->index('shift_policy_id');
            }
        );

        DB::table('attendance_shift_policies')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Default Office Time',
            'start_time' => config('kpi.attendance.shift_start', '10:30'),
            'end_time' => null,
            'grace_minutes' => (int) config('kpi.attendance.grace_minutes', 15),
            'is_default' => true,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('attendance_user_shift_assignments');
        Schema::dropIfExists('attendance_shift_policies');
    }
}
