<?php

namespace App\Http\Controllers;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceSettingsController extends Controller
{
    public function index()
    {
        $policies =
            AttendanceShiftPolicy::query()
                ->withCount(
                    'assignments'
                )
                ->orderByDesc(
                    'is_default'
                )
                ->orderBy(
                    'name'
                )
                ->get();

        $users =
            User::query()
                ->with(
                    'userType:id,user_type'
                )
                ->where(
                    'status',
                    1
                )
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'name',
                    'user_type_id',
                ]);

        $assignments =
            AttendanceUserShiftAssignment::query()
                ->with([
                    'user:id,name',
                    'shiftPolicy:id,name,start_time,end_time,grace_minutes',
                ])
                ->orderByDesc(
                    'effective_from'
                )
                ->limit(
                    100
                )
                ->get();

        $currentAssignments =
            AttendanceUserShiftAssignment::query()
                ->with(
                    'shiftPolicy:id,name,start_time,end_time,grace_minutes'
                )
                ->whereDate(
                    'effective_from',
                    '<=',
                    now()->toDateString()
                )
                ->where(
                    function ($query) {
                        $query
                            ->whereNull(
                                'effective_to'
                            )
                            ->orWhereDate(
                                'effective_to',
                                '>=',
                                now()->toDateString()
                            );
                    }
                )
                ->get()
                ->keyBy(
                    'user_id'
                );

        return view(
            'admin.pages.attendance.settings',
            [
                'policies' =>
                    $policies,

                'users' =>
                    $users,

                'assignments' =>
                    $assignments,

                'currentAssignments' =>
                    $currentAssignments,
            ]
        );
    }

    public function storePolicy(Request $request)
    {
        $validated =
            $this->validatePolicy(
                $request
            );

        DB::transaction(
            function () use (
                $validated,
                $request
            ) {
                if (
                    $validated[
                        'is_default'
                    ]
                ) {
                    AttendanceShiftPolicy::query()
                        ->update([
                            'is_default' =>
                                false,
                        ]);
                }

                AttendanceShiftPolicy::create(
                    array_merge(
                        $validated,
                        [
                            'created_by' =>
                                optional(
                                    $request->user()
                                )->id,

                            'updated_by' =>
                                optional(
                                    $request->user()
                                )->id,
                        ]
                    )
                );
            }
        );

        return redirect()
            ->route(
                'admin.attendance.settings.index'
            )
            ->with(
                'success',
                'Office time policy created successfully.'
            );
    }

    public function updatePolicy(
        Request $request,
        AttendanceShiftPolicy $policy
    ) {
        $validated =
            $this->validatePolicy(
                $request
            );

        DB::transaction(
            function () use (
                $policy,
                $validated,
                $request
            ) {
                if (
                    $validated[
                        'is_default'
                    ]
                ) {
                    AttendanceShiftPolicy::query()
                        ->where(
                            'id',
                            '!=',
                            $policy->id
                        )
                        ->update([
                            'is_default' =>
                                false,
                        ]);
                }

                $policy->update(
                    array_merge(
                        $validated,
                        [
                            'updated_by' =>
                                optional(
                                    $request->user()
                                )->id,
                        ]
                    )
                );
            }
        );

        return redirect()
            ->route(
                'admin.attendance.settings.index'
            )
            ->with(
                'success',
                'Office time policy updated successfully.'
            );
    }

    public function storeAssignments(Request $request)
    {
        $validated =
            $request->validate([
                'user_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'user_ids.*' => [
                    'required',
                    'uuid',
                    'exists:users,id',
                ],

                'shift_policy_id' => [
                    'required',
                    'uuid',
                    Rule::exists(
                        'attendance_shift_policies',
                        'id'
                    )
                        ->where(
                            'is_active',
                            true
                        ),
                ],

                'effective_from' => [
                    'required',
                    'date',
                ],

                'effective_to' => [
                    'nullable',
                    'date',
                    'after_or_equal:effective_from',
                ],
            ]);

        $from =
            $validated[
                'effective_from'
            ];

        $to =
            $validated[
                'effective_to'
            ]
            ?? null;

        foreach (
            $validated[
                'user_ids'
            ]
            as $userId
        ) {
            if (
                $this->hasOverlappingAssignment(
                    $userId,
                    $from,
                    $to
                )
            ) {
                throw ValidationException::withMessages([
                    'assignments' =>
                        'One or more selected employees already has an office time assignment in this date range.',
                ]);
            }
        }

        DB::transaction(
            function () use (
                $validated,
                $request,
                $from,
                $to
            ) {
                foreach (
                    $validated[
                        'user_ids'
                    ]
                    as $userId
                ) {
                    AttendanceUserShiftAssignment::create([
                        'user_id' =>
                            $userId,

                        'shift_policy_id' =>
                            $validated[
                                'shift_policy_id'
                            ],

                        'effective_from' =>
                            $from,

                        'effective_to' =>
                            $to,

                        'created_by' =>
                            optional(
                                $request->user()
                            )->id,

                        'updated_by' =>
                            optional(
                                $request->user()
                            )->id,
                    ]);
                }
            }
        );

        return redirect()
            ->route(
                'admin.attendance.settings.index'
            )
            ->with(
                'success',
                'Office time assigned successfully.'
            );
    }

    private function validatePolicy(Request $request): array
    {
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'start_time' => [
                    'required',
                    'date_format:H:i',
                ],

                'end_time' => [
                    'nullable',
                    'date_format:H:i',
                ],

                'grace_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:240',
                ],

                'is_default' => [
                    'nullable',
                    'boolean',
                ],

                'is_active' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $validated[
            'is_default'
        ] =
            $request->boolean(
                'is_default'
            );

        $validated[
            'is_active'
        ] =
            $request->boolean(
                'is_active'
            );

        return $validated;
    }

    private function hasOverlappingAssignment(
        string $userId,
        string $from,
        ?string $to
    ): bool {
        $rangeEnd =
            $to
            ?: '9999-12-31';

        return AttendanceUserShiftAssignment::query()
            ->where(
                'user_id',
                $userId
            )
            ->whereDate(
                'effective_from',
                '<=',
                $rangeEnd
            )
            ->where(
                function ($query) use ($from) {
                    $query
                        ->whereNull(
                            'effective_to'
                        )
                        ->orWhereDate(
                            'effective_to',
                            '>=',
                            $from
                        );
                }
            )
            ->exists();
    }
}
