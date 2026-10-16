<?php

namespace App\Http\Controllers;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendancePolicyAssignmentService;
use App\Services\Attendance\AttendanceCalendarService;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceSettingsController extends Controller
{
    public function index(?Request $request = null)
    {
        $request = $request ?: request();
        $calendarFilters = $request->validate([
            'year' => 'nullable|integer|between:2000,2100',
            'month' => 'nullable|integer|between:1,12',
            'calendar_user_id' => 'nullable|uuid|exists:users,id',
        ]);
        $policies =
            AttendanceShiftPolicy::query()
                ->withCount(
                    [
                        'activeAssignments as assignments_count',
                    ]
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
                    'assignedBy:id,name',
                ])
                ->orderByDesc(
                    'assigned_at'
                )
                ->orderByDesc(
                    'created_at'
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
                ->where(
                    'is_active',
                    true
                )
                ->get()
                ->keyBy(
                    'user_id'
                );

        $year = (int) ($calendarFilters['year'] ?? now()->year);
        $month = (int) ($calendarFilters['month'] ?? now()->month);
        $selectedUser = $users->firstWhere('id', $calendarFilters['calendar_user_id'] ?? null) ?: $users->first();
        $calendarService = app(AttendanceCalendarService::class);
        $monthPolicy = $calendarService->month($year, $month);
        $calendar = [
            'year' => $year, 'month' => $month, 'selectedUser' => $selectedUser,
            'holidays' => $monthPolicy->dates ?? [], 'holiday_mode' => $monthPolicy->mode ?? 'additional',
            'schedules' => $selectedUser ? $calendarService->schedules($selectedUser) : collect(),
            'summary' => $selectedUser ? $calendarService->summary($selectedUser, $year, $month) : null,
            'swaps' => collect(), 'audit' => collect(),
        ];
        if (\Illuminate\Support\Facades\Schema::hasTable('attendance_week_off_swaps')) {
            $calendar['swaps'] = $selectedUser ? DB::table('attendance_week_off_swaps')
                ->where('user_id', $selectedUser->id)->where(function ($query) use ($year, $month) {
                    $query->whereYear('worked_date', $year)->whereMonth('worked_date', $month)
                        ->orWhere(function ($query) use ($year, $month) {
                            $query->whereYear('off_date', $year)->whereMonth('off_date', $month);
                        });
                })->orderByDesc('worked_date')->limit(100)->get() : collect();
            $calendar['audit'] = DB::table('attendance_calendar_audits as audit')
                ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
                ->leftJoin('users as employee', 'employee.id', '=', 'audit.user_id')
                ->orderByDesc('audit.created_at')->limit(50)
                ->get(['audit.*', 'actor.name as actor_name', 'employee.name as employee_name']);
        }
        $canManageCalendar = in_array(optional(optional(auth()->user())->userType)->user_type,
            [UserType::HR, UserType::SUPER_ADMIN], true);

        return view(
            'admin.pages.attendance.settings',
            [
                'calendar' => $calendar,
                'canManageCalendar' => $canManageCalendar,
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

    public function storeAssignments(
        Request $request,
        AttendancePolicyAssignmentService $assignmentService
    )
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
            ]);

        $policy =
            AttendanceShiftPolicy::query()
                ->where(
                    'id',
                    $validated['shift_policy_id']
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        $users =
            User::query()
                ->whereIn(
                    'id',
                    $validated['user_ids']
                )
                ->get()
                ->keyBy('id');

        $assignmentService->assignMany(
            collect($validated['user_ids'])
                ->map(
                    fn (string $userId) =>
                        $users->get($userId)
                )
                ->filter()
                ->values(),
            $policy,
            $request->user()
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

}
