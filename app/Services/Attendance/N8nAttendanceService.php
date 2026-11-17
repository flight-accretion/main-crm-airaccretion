<?php

namespace App\Services\Attendance;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class N8nAttendanceService
{
    private function normalizeName(string $name): string
    {
        $name = strtoupper(Str::ascii($name));

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                preg_replace('/[^A-Z0-9]+/', ' ', $name) ?? ''
            ) ?? ''
        );
    }

    private function snapshot($policy): array
    {
        return [
            'resolved_shift_policy_id' =>
                $policy->exists ? $policy->id : null,

            'resolved_shift_policy_name' =>
                $policy->name,

            'resolved_shift_start_time' =>
                $policy->start_time,

            'resolved_shift_end_time' =>
                $policy->end_time,

            'resolved_shift_grace_minutes' =>
                $policy->grace_minutes,
        ];
    }

    public function ingest(array $records, User $actor): array
    {
        /*
         * Fetch active CRM users.
         */
        $users = User::query()
            ->with('userType')
            ->where('status', 1)
            ->get([
                'id',
                'name',
                'user_type_id',
            ]);

        /*
         * Group by normalized employee name.
         */
        $byName = $users->groupBy(
            fn (User $user) =>
                $this->normalizeName($user->name)
        );

        $stats = [
            'received' => count($records),
            'inserted' => 0,
            'updated' => 0,
            'duplicate' => 0,
            'skipped' => 0,
            'blocked' => 0,
        ];

        $details = [];
        $prepared = [];
        $seen = [];

        /*
         * Match every attendance row.
         */
        foreach ($records as $index => $record) {

            $name = trim($record['employee_name']);
            $date = $record['attendance_date'];

            $matches = $byName->get(
                $this->normalizeName($name),
                collect()
            );

            $reason = null;

            if ($matches->count() === 0) {
                $reason =
                    'No active CRM employee with exact normalized name';
            } elseif ($matches->count() !== 1) {
                $reason =
                    'Ambiguous active CRM employee name';
            }

            if ($reason !== null) {
                $stats['skipped']++;

                $details[] = [
                    'index' => $index,
                    'name' => $name,
                    'date' => $date,
                    'result' => 'skipped',
                    'reason' => $reason,
                ];

                continue;
            }

            $user = $matches->first();

            /*
             * Preserve incoming attendance code.
             * P, A, WO, POW, MIS etc.
             */
            $status = strtoupper(
                trim($record['raw_status'])
            );

            if ($status === '') {
                $stats['skipped']++;

                $details[] = [
                    'index' => $index,
                    'name' => $name,
                    'date' => $date,
                    'result' => 'skipped',
                    'reason' => 'Empty status',
                ];

                continue;
            }

            /*
             * Avoid repeated employee/date
             * within same request.
             */
            $key = $user->id . '|' . $date;

            if (isset($seen[$key])) {
                $stats['skipped']++;

                $details[] = [
                    'index' => $index,
                    'name' => $name,
                    'date' => $date,
                    'result' => 'skipped',
                    'reason' =>
                        'Repeated employee/date in request',
                ];

                continue;
            }

            $seen[$key] = true;

            $values = [
                'in_time' => $record['in_time'] ?? null,
                'out_time' => $record['out_time'] ?? null,

                'raw_in' => isset($record['in_time'])
                    ? substr($record['in_time'], 0, 5)
                    : null,

                'raw_out' => isset($record['out_time'])
                    ? substr($record['out_time'], 0, 5)
                    : null,

                'raw_status' => $status,
            ];

            $prepared[] = compact(
                'index',
                'name',
                'date',
                'user',
                'values'
            );
        }

        /*
         * No matched employees.
         */
        if (!$prepared) {
            return [
                'success' => true,
                'import_id' => null,
            ] + $stats + [
                'results' => $details,
            ];
        }

        /*
         * Create source import for audit/history.
         */
        $dates = array_column($prepared, 'date');

        $from = min($dates);
        $to = max($dates);

        $importId = (string) Str::uuid();

        $path = 'attendance-imports/n8n/'
            . $importId
            . '.json';

        $payload = json_encode(
            ['records' => $records],
            JSON_THROW_ON_ERROR
        );

        Storage::disk('local')->put(
            $path,
            $payload
        );

        try {
            $import = AttendanceImport::create([
                'id' => $importId,

                'from_date' => $from,
                'to_date' => $to,

                'period_from' => $from,
                'period_to' => $to,

                'original_filename' =>
                    'n8n-' . $importId . '.json',

                'stored_path' => $path,
                'file_type' => 'json',

                'file_hash' =>
                    hash('sha256', $payload),

                'status' => 'previewed',

                'total_employees' => count(
                    array_unique(
                        array_map(
                            fn ($row) => $row['user']->id,
                            $prepared
                        )
                    )
                ),

                'total_records' => count($records),
                'uploaded_by' => $actor->id,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $shiftResolver = app(
            AttendanceShiftResolver::class
        );

        $corrections = app(
            AttendanceCorrectionService::class
        );

        $policyCache = [];

        /*
         * Process matched rows.
         */
        foreach ($prepared as $row) {

            $user = $row['user'];
            $date = $row['date'];
            $name = $row['name'];
            $index = $row['index'];

            $result = 'skipped';
            $reason = null;

            try {
                DB::transaction(
                    function () use (
                        $row,
                        $import,
                        $user,
                        $date,
                        $shiftResolver,
                        $corrections,
                        $actor,
                        &$policyCache,
                        &$result,
                        &$reason
                    ) {
                        /*
                         * Lock payroll period.
                         */
                        $month = Carbon::parse($date)
                            ->startOfMonth()
                            ->toDateString();

                        DB::table('attendance_payroll_periods')
                            ->insertOrIgnore([
                                'month' => $month,
                                'closed' => false,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $period = DB::table(
                            'attendance_payroll_periods'
                        )
                            ->where('month', $month)
                            ->lockForUpdate()
                            ->first();

                        if ($period->closed) {
                            $result = 'blocked';
                            $reason = 'Payroll period closed';
                            return;
                        }

                        /*
                         * Find existing employee/date.
                         */
                        $existing = AttendanceRecord::
                            withoutGlobalScope(
                                'effective-attendance'
                            )
                            ->where('user_id', $user->id)
                            ->whereDate(
                                'attendance_date',
                                $date
                            )
                            ->lockForUpdate()
                            ->first();

                        /*
                         * Protect manually corrected
                         * or voided attendance.
                         */
                        if (
                            $existing &&
                            (
                                $existing->correction_voided_at !== null ||
                                (int) $existing->correction_revision > 0
                            )
                        ) {
                            $result = 'blocked';
                            $reason =
                                'Existing record has correction history or was voided';
                            return;
                        }

                        /*
                         * Protect pending corrections.
                         */
                        if (
                            $existing &&
                            DB::table('attendance_corrections')
                                ->where(
                                    'attendance_record_id',
                                    $existing->id
                                )
                                ->where('status', 'pending')
                                ->exists()
                        ) {
                            $result = 'blocked';
                            $reason =
                                'Pending attendance correction';
                            return;
                        }

                        /*
                         * Resolve shift policy for date.
                         */
                        if (!isset(
                            $policyCache[$user->id][$date]
                        )) {
                            $policyCache[$user->id][$date] =
                                $shiftResolver->resolveForDate(
                                    $user,
                                    Carbon::parse($date)
                                );
                        }

                        $policy =
                            $policyCache[$user->id][$date];

                        $values = $row['values'] + [
                            'paycode' => $existing
                                ? $existing->paycode
                                : null,
                        ];

                        $values += $this->snapshot(
                            $policy
                        );

                        $values['source_import_id'] =
                            $import->id;

                        /*
                         * Duplicate check.
                         *
                         * Compare only attendance data.
                         */
                        if ($existing) {

                            $same = true;

                            foreach (
                                $row['values'] as $field => $value
                            ) {
                                if (
                                    (string) (
                                        $existing->{$field} ?? ''
                                    ) !==
                                    (string) ($value ?? '')
                                ) {
                                    $same = false;
                                    break;
                                }
                            }

                            if ($same) {
                                $result = 'duplicate';
                                return;
                            }
                        }

                        /*
                         * Existing correction-aware
                         * attendance import method.
                         */
                        $version = $existing
                            ? $corrections->version($existing)
                            : hash('sha256', 'missing');

                        $outcome = $corrections->importRow(
                            $user->id,
                            $date,
                            $values,
                            'Automated n8n punch synchronization',
                            $version,
                            $actor,
                            $import->id
                        );

                        $result =
                            $outcome === 'created'
                                ? 'inserted'
                                : (
                                    $outcome === 'applied'
                                        ? 'updated'
                                        : (
                                            $outcome === 'unchanged'
                                                ? 'duplicate'
                                                : 'blocked'
                                        )
                                );

                        if ($result === 'blocked') {
                            $reason =
                                'Correction requires approval';
                        }
                    },
                    3
                );
            } catch (Throwable $e) {

                report($e);

                $result = 'skipped';
                $reason =
                    'Database or attendance validation conflict';
            }

            $stats[$result]++;

            $details[] = [
                'index' => $index,
                'name' => $name,
                'date' => $date,
                'result' => $result,
            ] + (
                $reason
                    ? ['reason' => $reason]
                    : []
            );
        }

        /*
         * Finalize source import.
         */
        $import->update([
            'status' => 'completed',
            'created_records' => $stats['inserted'],
            'updated_records' => $stats['updated'],
            'confirmed_at' => now(),
        ]);

        usort(
            $details,
            fn ($a, $b) =>
                $a['index'] <=> $b['index']
        );

        return [
            'success' => true,
            'import_id' => $import->id,
        ] + $stats + [
            'results' => $details,
        ];
    }
}