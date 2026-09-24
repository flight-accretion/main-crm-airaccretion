<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCurrentAssignmentAndPolicySnapshotToAttendance extends Migration
{
    public function up()
    {
        Schema::table(
            'attendance_user_shift_assignments',
            function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_user_shift_assignments', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }

                if (!Schema::hasColumn('attendance_user_shift_assignments', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->index();
                }

                if (!Schema::hasColumn('attendance_user_shift_assignments', 'unassigned_at')) {
                    $table->timestamp('unassigned_at')->nullable();
                }

                if (!Schema::hasColumn('attendance_user_shift_assignments', 'assigned_by')) {
                    $table->uuid('assigned_by')->nullable();
                }
            }
        );

        $now = now();

        DB::table('attendance_user_shift_assignments')
            ->orderBy('created_at')
            ->get()
            ->each(function ($assignment) use ($now) {
                $assignedAt =
                    $assignment->assigned_at
                    ?: (
                        $assignment->effective_from
                            ? Carbon::parse($assignment->effective_from)->startOfDay()
                            : Carbon::parse($assignment->created_at ?: $now)
                    );

                $unassignedAt =
                    $assignment->unassigned_at
                    ?: (
                        $assignment->effective_to
                            ? Carbon::parse($assignment->effective_to)->endOfDay()
                            : null
                    );

                DB::table('attendance_user_shift_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'is_active' =>
                            $unassignedAt
                                ? $unassignedAt->gte($now)
                                : true,
                        'assigned_at' =>
                            $assignedAt,
                        'unassigned_at' =>
                            $unassignedAt,
                        'assigned_by' =>
                            $assignment->assigned_by
                            ?: $assignment->created_by,
                    ]);
            });

        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'resolved_shift_policy_id')) {
                $table->uuid('resolved_shift_policy_id')->nullable();
            }

            if (!Schema::hasColumn('attendance_records', 'resolved_shift_policy_name')) {
                $table->string('resolved_shift_policy_name', 150)->nullable();
            }

            if (!Schema::hasColumn('attendance_records', 'resolved_shift_start_time')) {
                $table->time('resolved_shift_start_time')->nullable();
            }

            if (!Schema::hasColumn('attendance_records', 'resolved_shift_end_time')) {
                $table->time('resolved_shift_end_time')->nullable();
            }

            if (!Schema::hasColumn('attendance_records', 'resolved_shift_grace_minutes')) {
                $table->unsignedSmallInteger('resolved_shift_grace_minutes')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (
                [
                    'resolved_shift_policy_id',
                    'resolved_shift_policy_name',
                    'resolved_shift_start_time',
                    'resolved_shift_end_time',
                    'resolved_shift_grace_minutes',
                ]
                as $column
            ) {
                if (Schema::hasColumn('attendance_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table(
            'attendance_user_shift_assignments',
            function (Blueprint $table) {
                foreach (
                    [
                        'is_active',
                        'assigned_at',
                        'unassigned_at',
                        'assigned_by',
                    ]
                    as $column
                ) {
                    if (Schema::hasColumn('attendance_user_shift_assignments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
}
