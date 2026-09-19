<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AttendanceImportParser
{
    public function parse(
    string $path,
    Carbon $fromDate,
    Carbon $toDate
): array {
        $spreadsheet = IOFactory::load($path);

        foreach (
            $spreadsheet->getWorksheetIterator()
            as $worksheet
        ) {
            $rows = $worksheet->toArray(
                null,
                true,
                true,
                false
            );

        $parsed = $this->parseRows(
        $rows,
        $fromDate,
        $toDate
    );

            if (!empty($parsed['employees'])) {
                return $parsed;
            }
        }

        throw ValidationException::withMessages([
            'excel_file' =>
                'No attendance employee blocks were found. '
                . 'Expected rows beginning with Paycode:-.',
        ]);
    }

  private function parseRows(
    array $rows,
    Carbon $fromDate,
    Carbon $toDate
): array {
        $employees = [];

        $currentPaycode = null;
        $currentName = null;

        /*
         * Defaults exactly match your uploaded
         * MonthlyPerformance sheet:
         *
         * A = Date
         * D = Day
         * E = In
         * F = Out
         * G = Status
         */
        $columns = [
            'date' => 0,
            'day' => 3,
            'in' => 4,
            'out' => 5,
            'status' => 6,
        ];

        $outsideRangeRows = 0;

        foreach ($rows as $row) {

            /*
             * Employee block starts:
             *
             * Paycode:- ... 1 CHARUL GARG Department:-
             */
            $header =
                $this->extractEmployeeHeader(
                    $row
                );

            if ($header) {

                $currentPaycode =
                    $header['paycode'];

                $currentName =
                    $header['name'];

                if (
                    !isset(
                        $employees[
                            $currentPaycode
                        ]
                    )
                ) {

                    $employees[
                        $currentPaycode
                    ] = [
                        'paycode' =>
                            $currentPaycode,

                        'file_name' =>
                            $currentName,

                        'records' =>
                            [],
                    ];
                }

                continue;
            }

            /*
             * Detect:
             *
             * Date | Day | In | Out | Status
             */
            $detectedColumns =
                $this->detectColumns(
                    $row
                );

            if ($detectedColumns) {

                $columns =
                    $detectedColumns;

                continue;
            }

            if (!$currentPaycode) {
                continue;
            }

            $dateValue =
                $row[
                    $columns['date']
                ]
                ?? null;

            $date =
                $this->parseDate(
                    $dateValue
                );

            if (!$date) {
                continue;
            }

            /*
            * Only attendance inside the HR-selected
            * From Date -> To Date range is imported.
            *
            * Example:
            * Selected: 01 Sep -> 18 Sep
            *
            * 31 Aug -> ignored
            * 01 Sep -> accepted
            * 18 Sep -> accepted
            * 19 Sep -> ignored
            */
            if (
                $date->lt(
                    $fromDate->copy()->startOfDay()
                )
                ||
                $date->gt(
                    $toDate->copy()->endOfDay()
                )
            ) {
                $outsideRangeRows++;

                continue;
            }

            $rawIn =
                $this->cleanScalar(
                    $row[
                        $columns['in']
                    ]
                    ?? null
                );

            $rawOut =
                $this->cleanScalar(
                    $row[
                        $columns['out']
                    ]
                    ?? null
                );

            $rawStatus =
                strtoupper(
                    trim(
                        $this->cleanScalar(
                            $row[
                                $columns[
                                    'status'
                                ]
                            ]
                            ?? null
                        )
                        ?? ''
                    )
                );

            $day =
                trim(
                    (string) (
                        $row[
                            $columns['day']
                        ]
                        ?? ''
                    )
                );

            $employees[
                $currentPaycode
            ]['records'][] = [

                'attendance_date' =>
                    $date->toDateString(),

                'day_name' =>
                    $day !== ''
                        ? $day
                        : $date->format('l'),

                'in_time' =>
                    $this->normalizeTime(
                        $row[
                            $columns['in']
                        ]
                        ?? null
                    ),

                'out_time' =>
                    $this->normalizeTime(
                        $row[
                            $columns['out']
                        ]
                        ?? null
                    ),

                'raw_in' =>
                    $rawIn,

                'raw_out' =>
                    $rawOut,

                'raw_status' =>
                    $rawStatus !== ''
                        ? $rawStatus
                        : null,
            ];
        }

        $employees =
            array_values(
                array_filter(
                    $employees,
                    fn (array $employee) =>
                        !empty(
                            $employee[
                                'records'
                            ]
                        )
                )
            );

        if (empty($employees)) {

            return [
                'employees' => [],
                'rows' => [],
                'period_from' => null,
                'period_to' => null,
               'outside_range_rows' =>
    $outsideRangeRows,
            ];
        }

        $flatRows = [];

        foreach (
            $employees as &$employee
        ) {

            usort(
                $employee['records'],
                function ($a, $b) {
                    return strcmp(
                        $a[
                            'attendance_date'
                        ],
                        $b[
                            'attendance_date'
                        ]
                    );
                }
            );

            $dates =
                array_column(
                    $employee['records'],
                    'attendance_date'
                );

            $employee[
                'record_count'
            ] =
                count(
                    $employee[
                        'records'
                    ]
                );

            $employee[
                'period_from'
            ] =
                min($dates);

            $employee[
                'period_to'
            ] =
                max($dates);

            foreach (
                $employee['records']
                as $record
            ) {

                $flatRows[] =
                    array_merge(
                        [
                            'paycode' =>
                                $employee[
                                    'paycode'
                                ],

                            'file_name' =>
                                $employee[
                                    'file_name'
                                ],
                        ],
                        $record
                    );
            }
        }

        unset($employee);

        usort(
            $flatRows,
            function ($a, $b) {

                $dateCompare =
                    strcmp(
                        $a[
                            'attendance_date'
                        ],
                        $b[
                            'attendance_date'
                        ]
                    );

                if (
                    $dateCompare !== 0
                ) {
                    return $dateCompare;
                }

                return strcmp(
                    (string) $a[
                        'paycode'
                    ],
                    (string) $b[
                        'paycode'
                    ]
                );
            }
        );

        $allDates =
            array_column(
                $flatRows,
                'attendance_date'
            );

        return [
            'employees' =>
                $employees,

            'rows' =>
                $flatRows,

            'period_from' =>
                min($allDates),

            'period_to' =>
                max($allDates),

           'outside_range_rows' =>
    $outsideRangeRows,
        ];
    }

    private function extractEmployeeHeader(
        array $row
    ): ?array {
        $first =
            strtolower(
                trim(
                    (string) (
                        $row[0]
                        ?? ''
                    )
                )
            );

        if (
            $first === ''
            ||
            strpos(
                $first,
                'paycode'
            ) === false
        ) {
            return null;
        }

        /*
         * Uploaded workbook:
         *
         * D = paycode
         * E = employee name
         */
        $paycode =
            trim(
                (string) (
                    $row[3]
                    ?? ''
                )
            );

        $name =
            trim(
                (string) (
                    $row[4]
                    ?? ''
                )
            );

        /*
         * Fallback in case attendance
         * software shifts columns.
         */
        if ($paycode === '') {

            foreach (
                array_slice(
                    $row,
                    1
                )
                as $value
            ) {

                $value =
                    trim(
                        (string) $value
                    );

                if (
                    $value !== ''
                    &&
                    stripos(
                        $value,
                        'department'
                    ) === false
                ) {

                    $paycode =
                        $value;

                    break;
                }
            }
        }

        if ($name === '') {

            $paycodeFound =
                false;

            foreach (
                array_slice(
                    $row,
                    1
                )
                as $value
            ) {

                $value =
                    trim(
                        (string) $value
                    );

                if (
                    $value === ''
                    ||
                    stripos(
                        $value,
                        'department'
                    ) !== false
                ) {
                    continue;
                }

                if (
                    !$paycodeFound
                    &&
                    $value ===
                        $paycode
                ) {

                    $paycodeFound =
                        true;

                    continue;
                }

                if ($paycodeFound) {

                    $name =
                        $value;

                    break;
                }
            }
        }

        if ($paycode === '') {
            return null;
        }

        return [
            'paycode' =>
                $paycode,

            'name' =>
                $name !== ''
                    ? $name
                    : 'Unknown Employee',
        ];
    }

    private function detectColumns(
        array $row
    ): ?array {
        $normalized = [];

        foreach (
            $row as $index => $value
        ) {

            $normalized[
                $index
            ] =
                strtolower(
                    trim(
                        (string) $value
                    )
                );
        }

        $dateIndex =
            array_search(
                'date',
                $normalized,
                true
            );

        $dayIndex =
            array_search(
                'day',
                $normalized,
                true
            );

        $inIndex =
            array_search(
                'in',
                $normalized,
                true
            );

        $outIndex =
            array_search(
                'out',
                $normalized,
                true
            );

        $statusIndex =
            array_search(
                'status',
                $normalized,
                true
            );

        if (
            $dateIndex === false
            ||
            $statusIndex === false
        ) {
            return null;
        }

        return [
            'date' =>
                (int) $dateIndex,

            'day' =>
                $dayIndex !== false
                    ? (int) $dayIndex
                    : 3,

            'in' =>
                $inIndex !== false
                    ? (int) $inIndex
                    : 4,

            'out' =>
                $outIndex !== false
                    ? (int) $outIndex
                    : 5,

            'status' =>
                (int) $statusIndex,
        ];
    }

    private function parseDate(
        $value
    ): ?Carbon {
        if (
            $value instanceof
            DateTimeInterface
        ) {

            return Carbon::instance(
                $value
            )->startOfDay();
        }

        if (is_numeric($value)) {

            try {

                return Carbon::instance(
                    ExcelDate::
                        excelToDateTimeObject(
                            (float) $value
                        )
                )->startOfDay();

            } catch (\Throwable $e) {

                return null;
            }
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        foreach (
            [
                'd/m/Y',
                'd-m-Y',
                'Y-m-d',
                'm/d/Y',
            ]
            as $format
        ) {

            try {

                $date =
                    Carbon::
                        createFromFormat(
                            $format,
                            $value
                        );

                if (
                    $date !== false
                ) {
                    return $date
                        ->startOfDay();
                }

            } catch (\Throwable $e) {
                // try next
            }
        }

        return null;
    }

    private function normalizeTime(
        $value
    ): ?string {
        if (
            $value instanceof
            DateTimeInterface
        ) {

            return $value->format(
                'H:i:s'
            );
        }

        if (is_numeric($value)) {

            $numeric =
                (float) $value;

            /*
             * Proper Excel time is a
             * fraction of one day.
             *
             * The uploaded file contains
             * placeholder "3" on absent/
             * weekly-off rows. We correctly
             * treat that as no clock time.
             */
            if (
                $numeric > 0
                &&
                $numeric < 1
            ) {

                try {

                    return ExcelDate::
                        excelToDateTimeObject(
                            $numeric
                        )
                        ->format(
                            'H:i:s'
                        );

                } catch (\Throwable $e) {

                    return null;
                }
            }

            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if (
            $value === ''
            ||
            preg_match(
                '/^\d{1,2}$/',
                $value
            )
        ) {
            return null;
        }

        foreach (
            [
                'H:i:s',
                'H:i',
                'h:i A',
                'h:i a',
            ]
            as $format
        ) {

            try {

                $time =
                    Carbon::
                        createFromFormat(
                            $format,
                            $value
                        );

                if (
                    $time !== false
                ) {

                    return $time
                        ->format(
                            'H:i:s'
                        );
                }

            } catch (\Throwable $e) {
                // try next
            }
        }

        return null;
    }

    private function cleanScalar(
        $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            $value instanceof
            DateTimeInterface
        ) {
            return $value->format(
                'H:i:s'
            );
        }

        $value =
            trim(
                (string) $value
            );

        return $value !== ''
            ? $value
            : null;
    }
}