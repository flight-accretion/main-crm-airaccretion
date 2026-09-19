<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\AttendanceUserMapping;
use App\Models\User;
use App\Services\Attendance\AttendanceImportParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceImportController extends Controller
{
    public function index()
    {
        $imports =
            AttendanceImport::query()
                ->with(
                    'uploadedBy:id,name'
                )
                ->latest()
                ->limit(25)
                ->get();

        return view(
            'admin.pages.attendance.import',
            [
                'imports' =>
                    $imports,
            ]
        );
    }

    public function preview(
        Request $request,
        AttendanceImportParser $parser
    ) {
        $validated =
            $request->validate(
                [
                   'from_date' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'to_date' => [
                    'required',
                    'date',
                    'after_or_equal:from_date',
                    'before_or_equal:today',
                ],

                    'excel_file' =>
                        'required|file|mimes:xlsx,xls,csv|max:10240',
                ],
                [
                    'from_date.required' =>
                        'Please select the From Date.',

                    'from_date.before_or_equal' =>
                        'From Date cannot be in the future.',

                    'to_date.required' =>
                        'Please select the To Date.',

                    'to_date.after_or_equal' =>
                        'To Date cannot be before From Date.',

                    'to_date.before_or_equal' =>
                        'To Date cannot be in the future.',

                    'excel_file.required' =>
                        'Please select an attendance file.',

                    'excel_file.mimes' =>
                        'Attendance file must be XLSX, XLS, or CSV.',

                    'excel_file.max' =>
                        'Attendance file cannot exceed 10MB.',
                ]
            );

       $fromDate =
    Carbon::parse(
        $validated['from_date']
    )->startOfDay();

$toDate =
    Carbon::parse(
        $validated['to_date']
    )->endOfDay();

        $file =
            $request->file(
                'excel_file'
            );

        $extension =
            strtolower(
                (string)
                $file
                    ->getClientOriginalExtension()
            );

        $storedPath =
            $file->storeAs(
                'attendance-imports/'
. $fromDate->format('Y/m'),

                (string)
                Str::uuid()
                . '.'
                . $extension,

                'local'
            );

        try {

           $parsed =
    $parser->parse(
        Storage::disk('local')->path(
            $storedPath
        ),
        $fromDate,
        $toDate
    );

            $import =
                AttendanceImport::create([
                   'from_date' =>
                $fromDate->toDateString(),

            'to_date' =>
                $toDate->toDateString(),

                    'period_from' =>
                        $parsed[
                            'period_from'
                        ],

                    'period_to' =>
                        $parsed[
                            'period_to'
                        ],

                    'original_filename' =>
                        $file
                            ->getClientOriginalName(),

                    'stored_path' =>
                        $storedPath,

                    'file_type' =>
                        $extension,

                    'status' =>
                        'previewed',

                    'total_employees' =>
                        count(
                            $parsed[
                                'employees'
                            ]
                        ),

                    'total_records' =>
                        count(
                            $parsed[
                                'rows'
                            ]
                        ),

                    'uploaded_by' =>
                        $request
                            ->user()
                            ->id,
                ]);

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

            $employees =
                $this
                    ->buildEmployeePreview(
                        $parsed[
                            'employees'
                        ],
                        $users
                    );

            return response()
                ->json([
                    'ok' =>
                        true,

                    'import_id' =>
                        $import->id,

                    'from_date' =>
                    $fromDate->toDateString(),

                'to_date' =>
                    $toDate->toDateString(),

                    'period_from' =>
                        $parsed[
                            'period_from'
                        ],

                    'period_to' =>
                        $parsed[
                            'period_to'
                        ],

                    'outside_range_rows' =>
                    $parsed['outside_range_rows'],

                    'total_employees' =>
                        count(
                            $employees
                        ),

                    'total_records' =>
                        count(
                            $parsed[
                                'rows'
                            ]
                        ),

                    'employees' =>
                        $employees,

                    'rows' =>
                        $parsed[
                            'rows'
                        ],

                    'users' =>
                        $users
                            ->map(
                                function (
                                    User $user
                                ) {

                                    return [
                                        'id' =>
                                            $user
                                                ->id,

                                        'name' =>
                                            $user
                                                ->name,

                                        'role' =>
                                            $user
                                                ->userType
                                                ->user_type
                                            ?? '',
                                    ];
                                }
                            )
                            ->values(),
                ]);

        } catch (\Throwable $e) {

            Storage::disk(
                'local'
            )->delete(
                $storedPath
            );

            if (
                $e instanceof
                ValidationException
            ) {
                throw $e;
            }

            report($e);

            throw ValidationException::
                withMessages([
                    'excel_file' =>
                        'Unable to read attendance file: '
                        . $e
                            ->getMessage(),
                ]);
        }
    }

    public function confirm(
        Request $request,
        AttendanceImportParser $parser
    ) {
        $validated =
            $request->validate([
                'import_id' =>
                    'required|uuid|exists:attendance_imports,id',

                'mappings' =>
                    'required|array',

                'mappings.*' =>
                    'required|uuid|exists:users,id',
            ]);

        $import =
            AttendanceImport::query()
                ->where(
                    'id',
                    $validated[
                        'import_id'
                    ]
                )
                ->firstOrFail();

        if (
            $import->status
            !== 'previewed'
        ) {

            return redirect()
                ->route(
                    'admin.attendance.import.index'
                )
                ->with(
                    'error',
                    'This attendance import has already been confirmed or is no longer available.'
                );
        }

        if (
            !Storage::disk(
                'local'
            )->exists(
                $import
                    ->stored_path
            )
        ) {

            return redirect()
                ->route(
                    'admin.attendance.import.index'
                )
                ->with(
                    'error',
                    'The uploaded attendance file is no longer available. Please upload it again.'
                );
        }

       $parsed =
    $parser->parse(
        Storage::disk('local')->path(
            $import->stored_path
        ),

        Carbon::parse(
            $import->from_date
        )->startOfDay(),

        Carbon::parse(
            $import->to_date
        )->endOfDay()
    );

        $resolvedMappings =
            $this->resolveMappings(
                $parsed[
                    'employees'
                ],

                (array)
                $validated[
                    'mappings'
                ]
            );

        /*
         * Do not allow two Paycodes
         * to map to the same employee
         * in one import.
         */
        $uniqueUserIds =
            array_values(
                array_unique(
                    array_values(
                        $resolvedMappings
                    )
                )
            );

        if (
            count(
                $uniqueUserIds
            )
            !==
            count(
                $resolvedMappings
            )
        ) {

            return back()
                ->withInput()
                ->with(
                    'error',
                    'One CRM employee cannot be mapped to two different paycodes in the same attendance import.'
                );
        }

        $validUsers =
            User::query()
                ->where(
                    'status',
                    1
                )
                ->whereIn(
                    'id',
                    $uniqueUserIds
                )
                ->pluck(
                    'id'
                )
                ->all();

        if (
            count(
                $validUsers
            )
            !==
            count(
                $uniqueUserIds
            )
        ) {

            return back()
                ->withInput()
                ->with(
                    'error',
                    'One or more selected CRM employees are inactive or invalid.'
                );
        }

        $created = 0;
        $updated = 0;

        DB::transaction(
            function () use (
                $request,
                $import,
                $parsed,
                $resolvedMappings,
                &$created,
                &$updated
            ) {

                foreach (
                    $parsed[
                        'employees'
                    ]
                    as $employee
                ) {

                    $paycode =
                        (string)
                        $employee[
                            'paycode'
                        ];

                    $userId =
                        $resolvedMappings[
                            $paycode
                        ];

                    /*
                     * Remember Paycode mapping
                     * for next upload.
                     */
                    AttendanceUserMapping::
                        updateOrCreate(
                            [
                                'paycode' =>
                                    $paycode,
                            ],
                            [
                                'user_id' =>
                                    $userId,

                                'employee_name' =>
                                    $employee[
                                        'file_name'
                                    ],

                                'updated_by' =>
                                    $request
                                        ->user()
                                        ->id,
                            ]
                        );

                    foreach (
                        $employee[
                            'records'
                        ]
                        as $record
                    ) {

                        /*
                         * Same employee + same date:
                         * update existing row.
                         *
                         * This is how daily,
                         * weekly and monthly
                         * imports consolidate.
                         */
                        $existing =
                            AttendanceRecord::
                                query()
                                ->where(
                                    'user_id',
                                    $userId
                                )
                                ->where(
                                    'attendance_date',
                                    $record[
                                        'attendance_date'
                                    ]
                                )
                                ->exists();

                        AttendanceRecord::
                            updateOrCreate(
                                [
                                    'user_id' =>
                                        $userId,

                                    'attendance_date' =>
                                        $record[
                                            'attendance_date'
                                        ],
                                ],
                                [
                                    'paycode' =>
                                        $paycode,

                                    'day_name' =>
                                        $record[
                                            'day_name'
                                        ],

                                    'in_time' =>
                                        $record[
                                            'in_time'
                                        ],

                                    'out_time' =>
                                        $record[
                                            'out_time'
                                        ],

                                    'raw_in' =>
                                        $record[
                                            'raw_in'
                                        ],

                                    'raw_out' =>
                                        $record[
                                            'raw_out'
                                        ],

                                    'raw_status' =>
                                        $record[
                                            'raw_status'
                                        ],

                                    'source_import_id' =>
                                        $import
                                            ->id,
                                ]
                            );

                        if ($existing) {
                            $updated++;
                        } else {
                            $created++;
                        }
                    }
                }

                $import->update([
                    'period_from' =>
                        $parsed[
                            'period_from'
                        ],

                    'period_to' =>
                        $parsed[
                            'period_to'
                        ],

                    'status' =>
                        'completed',

                    'total_employees' =>
                        count(
                            $parsed[
                                'employees'
                            ]
                        ),

                    'total_records' =>
                        count(
                            $parsed[
                                'rows'
                            ]
                        ),

                    'created_records' =>
                        $created,

                    'updated_records' =>
                        $updated,

                    'confirmed_at' =>
                        now(),
                ]);
            }
        );

        return redirect()
            ->route(
                'admin.attendance.import.index'
            )
            ->with(
                'success',
                "Attendance imported successfully. {$created} new daily records added and {$updated} existing daily records updated."
            );
    }

    private function buildEmployeePreview(
        array $employees,
        Collection $users
    ): array {
        $paycodes =
            collect(
                $employees
            )
                ->pluck(
                    'paycode'
                )
                ->map(
                    fn ($value) =>
                        (string)
                        $value
                )
                ->all();

        $storedMappings =
            AttendanceUserMapping::
                query()
                ->whereIn(
                    'paycode',
                    $paycodes
                )
                ->get()
                ->keyBy(
                    fn ($mapping) =>
                        (string)
                        $mapping
                            ->paycode
                );

        /*
         * Exact employee name is only
         * a suggestion.
         *
         * Saved Paycode mapping always
         * takes priority.
         */
        $usersByNormalizedName =
            $users->groupBy(
                fn (User $user) =>
                    $this
                        ->normalizeName(
                            $user
                                ->name
                        )
            );

        return collect(
            $employees
        )
            ->map(
                function (
                    array $employee
                ) use (
                    $storedMappings,
                    $users,
                    $usersByNormalizedName
                ) {

                    $paycode =
                        (string)
                        $employee[
                            'paycode'
                        ];

                    $selectedUserId =
                        null;

                    $matchType =
                        'unmatched';

                    $saved =
                        $storedMappings
                            ->get(
                                $paycode
                            );

                    if (
                        $saved
                        &&
                        $users
                            ->contains(
                                'id',
                                $saved
                                    ->user_id
                            )
                    ) {

                        $selectedUserId =
                            $saved
                                ->user_id;

                        $matchType =
                            'saved_paycode';

                    } else {

                        $matches =
                            $usersByNormalizedName
                                ->get(
                                    $this
                                        ->normalizeName(
                                            $employee[
                                                'file_name'
                                            ]
                                        ),
                                    collect()
                                );

                        if (
                            $matches
                                ->count()
                            === 1
                        ) {

                            $selectedUserId =
                                $matches
                                    ->first()
                                    ->id;

                            $matchType =
                                'exact_name';
                        }
                    }

                    return array_merge(
                        $employee,
                        [
                            'user_id' =>
                                $selectedUserId,

                            'match_type' =>
                                $matchType,
                        ]
                    );
                }
            )
            ->values()
            ->all();
    }

    private function resolveMappings(
        array $employees,
        array $postedMappings
    ): array {
        $resolved = [];
        $errors = [];

        foreach (
            $employees
            as $employee
        ) {

            $paycode =
                (string)
                $employee[
                    'paycode'
                ];

            $userId =
                $postedMappings[
                    $paycode
                ]
                ?? null;

            /*
             * Existing Paycode mapping
             * can be used as fallback.
             */
            if (!$userId) {

                $saved =
                    AttendanceUserMapping::
                        query()
                        ->where(
                            'paycode',
                            $paycode
                        )
                        ->value(
                            'user_id'
                        );

                $userId =
                    $saved ?: null;
            }

            if (!$userId) {

                $errors[
                    "mappings.{$paycode}"
                ] =
                    "Please map paycode {$paycode} ({$employee['file_name']}) to a CRM employee.";

                continue;
            }

            $resolved[
                $paycode
            ] =
                (string)
                $userId;
        }

        if (
            !empty(
                $errors
            )
        ) {

            throw ValidationException::
                withMessages(
                    $errors
                );
        }

        return $resolved;
    }

    private function normalizeName(
        ?string $name
    ): string {
        $name =
            Str::ascii(
                (string)
                $name
            );

        $name =
            strtoupper(
                $name
            );

        $name =
            preg_replace(
                '/[^A-Z0-9]+/',
                ' ',
                $name
            )
            ?: '';

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $name
            )
            ?: ''
        );
    }
}
