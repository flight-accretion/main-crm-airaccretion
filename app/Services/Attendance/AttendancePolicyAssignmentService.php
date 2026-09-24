<?php

namespace App\Services\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendancePolicyAssignmentService
{
    public function assign(
        User $user,
        AttendanceShiftPolicy $policy,
        User $assignedBy
    ): AttendanceUserShiftAssignment {
        return DB::transaction(function () use ($user, $policy, $assignedBy) {
            $now = now();

            AttendanceUserShiftAssignment::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'effective_to' => $now->toDateString(),
                    'unassigned_at' => $now,
                    'updated_by' => $assignedBy->id,
                    'updated_at' => $now,
                ]);

            return AttendanceUserShiftAssignment::create([
                'user_id' => $user->id,
                'shift_policy_id' => $policy->id,
                'effective_from' => $now->toDateString(),
                'effective_to' => null,
                'is_active' => true,
                'assigned_at' => $now,
                'unassigned_at' => null,
                'assigned_by' => $assignedBy->id,
                'created_by' => $assignedBy->id,
                'updated_by' => $assignedBy->id,
            ]);
        });
    }

    public function assignMany(
        Collection $users,
        AttendanceShiftPolicy $policy,
        User $assignedBy
    ): Collection {
        return $users
            ->map(
                fn (User $user) =>
                    $this->assign(
                        $user,
                        $policy,
                        $assignedBy
                    )
            )
            ->values();
    }
}
