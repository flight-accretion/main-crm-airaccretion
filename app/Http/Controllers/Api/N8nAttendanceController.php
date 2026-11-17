<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserType;
use App\Services\Attendance\N8nAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class N8nAttendanceController extends Controller
{
    /*
     * GET /api/n8n/employees/active
     */
    public function employees()
    {
        $hasDesignation = Schema::hasColumn(
            'users',
            'designation'
        );

        $hasLocation = Schema::hasColumn(
            'users',
            'location'
        );

        $columns = [
            'id',
            'name',
            'email',
            'contact_number',
            'status',
            'user_type_id',
        ];

        if ($hasDesignation) {
            $columns[] = 'designation';
        }

        if ($hasLocation) {
            $columns[] = 'location';
        }

        $employees = User::query()
            ->with('userType:id,user_type')
            ->where('status', 1)
            ->orderBy('name')
            ->get($columns)
            ->map(function (User $user) use (
                $hasDesignation,
                $hasLocation
            ) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,

                    'designation' => $hasDesignation
                        ? $user->designation
                        : null,

                    'contact_number' => $user->contact_number,
                    'email' => $user->email,

                    'location' => $hasLocation
                        ? $user->location
                        : null,

                    'role' => $user->userType->user_type ?? null,
                    'status' => 'active',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'total' => $employees->count(),
            'employees' => $employees,
        ]);
    }

    /*
     * POST /api/n8n/attendance
     */
    public function store(
        Request $request,
        N8nAttendanceService $service
    ) {
        $actor = User::query()
            ->with('userType')
            ->find(
                config('services.n8n_attendance.actor_user_id')
            );

        if (
            !$actor ||
            (int) $actor->status !== 1 ||
            !in_array(
                $actor->userType->user_type ?? null,
                UserType::ATTENDANCE_IMPORT_ROLES,
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Attendance integration actor is not configured or authorized',
            ], 503);
        }

        $validated = Validator::make(
            $request->all(),
            [
                'records' => [
                    'required',
                    'array',
                    'min:1',
                    'max:500',
                ],

                'records.*' => [
                    'required',
                    'array',
                ],

                'records.*.attendance_date' => [
                    'required',
                    'date_format:Y-m-d',
                    'before_or_equal:today',
                ],

                'records.*.employee_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'records.*.in_time' => [
                    'nullable',
                    'date_format:H:i:s',
                ],

                'records.*.out_time' => [
                    'nullable',
                    'date_format:H:i:s',
                ],

                'records.*.raw_status' => [
                    'required',
                    'string',
                    'max:50',
                ],
            ]
        )->validate();

        return response()->json(
            $service->ingest(
                $validated['records'],
                $actor
            )
        );
    }
}