<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public const FIELDS = ['correction_voided_at', 'paycode', 'in_time', 'out_time', 'raw_in', 'raw_out', 'raw_status', 'source_import_id',
        'resolved_shift_policy_id', 'resolved_shift_policy_name', 'resolved_shift_start_time',
        'resolved_shift_end_time', 'resolved_shift_grace_minutes'];

    public function version(AttendanceRecord $record): string
    {
        return hash('sha256', json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function values(AttendanceRecord $record): array
    {
        return $record->only(self::FIELDS);
    }

    private function lockPeriod(string $date)
    {
        $month = \Carbon\Carbon::parse($date)->startOfMonth()->toDateString();
        DB::table('attendance_payroll_periods')->insertOrIgnore(['month' => $month, 'closed' => false,
            'created_at' => now(), 'updated_at' => now()]);
        return DB::table('attendance_payroll_periods')->where('month', $month)->lockForUpdate()->first();
    }

    private function event(?string $id, string $action, User $actor, array $details): void
    {
        DB::table('attendance_correction_events')->insert(['id' => (string) Str::uuid(),
            'correction_id' => $id, 'action' => $action, 'actor_id' => $actor->id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function closePeriod(string $month, string $reason, User $actor): void
    {
        abort_unless($actor->userType?->user_type === UserType::SUPER_ADMIN, 403);
        DB::transaction(function () use ($month, $reason, $actor) {
            $period = $this->lockPeriod($month);
            if ($period->closed) return;
            DB::table('attendance_payroll_periods')->where('month', $period->month)->update([
                'closed' => true, 'closed_by' => $actor->id, 'closed_at' => now(),
                'closure_reason' => $reason, 'updated_at' => now()]);
            $this->event(null, 'period_closed', $actor, ['month' => $period->month, 'reason' => $reason]);
        });
    }

    public function correct(string $recordId, array $changes, string $reason, string $version, User $actor,
        string $source = 'individual', ?string $importId = null, ?string $reversesId = null)
    {
        abort_unless(in_array($actor->userType?->user_type, UserType::ATTENDANCE_IMPORT_ROLES, true), 403);
        if (trim($reason) === '') throw ValidationException::withMessages([$source === 'import' ? 'correction_reason' : 'reason' => 'A correction reason is required.']);
        return DB::transaction(function () use ($recordId, $changes, $reason, $version, $actor, $source, $importId, $reversesId) {
            $initial = AttendanceRecord::withoutGlobalScope('effective-attendance')->findOrFail($recordId);
            $period = $this->lockPeriod($initial->attendance_date->toDateString());
            $record = AttendanceRecord::withoutGlobalScope('effective-attendance')->whereKey($recordId)->lockForUpdate()->firstOrFail();
            $before = $this->values($record);
            $after = array_replace($before, array_intersect_key($changes, array_flip(self::FIELDS)));
            $existing = DB::table('attendance_corrections')->where('attendance_record_id', $recordId)
                ->where('base_version', $version)->where('requested_by', $actor->id)->orderByDesc('created_at')->get();
            foreach ($existing as $item) {
                if (json_decode($item->after_values, true) == $after && $item->reason === $reason && $item->status !== 'rejected') {
                    $expected = $item->status === 'applied' ? $item->applied_version : $item->base_version;
                    abort_unless(hash_equals((string) $expected, $this->version($record)), 409, 'Attendance changed after this correction.');
                    return $item;
                }
            }
            abort_unless(hash_equals($this->version($record), $version), 409, 'Attendance changed. Reload before correcting.');
            if (DB::table('attendance_corrections')->where('attendance_record_id', $recordId)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['record' => 'Resolve the pending correction first.']);
            }
            $id = (string) Str::uuid();
            $status = $period->closed ? 'pending' : 'applied';
            DB::table('attendance_corrections')->insert(['id' => $id, 'attendance_record_id' => $recordId,
                'before_values' => json_encode($before, JSON_THROW_ON_ERROR), 'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
                'user_id' => $record->user_id, 'attendance_date' => $record->attendance_date->toDateString(),
                'base_version' => $version, 'reason' => $reason, 'source' => $source, 'source_import_id' => $importId,
                'reverses_id' => $reversesId, 'requested_by' => $actor->id, 'status' => $status,
                'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'applied') {
                $record->forceFill($after + ['correction_revision' => (int) $record->correction_revision + 1])->save();
                DB::table('attendance_corrections')->where('id', $id)->update(['applied_version' => $this->version($record->fresh())]);
            }
            $this->event($id, $status === 'pending' ? 'requested' : 'applied', $actor, ['source' => $source]);
            return DB::table('attendance_corrections')->where('id', $id)->first();
        });
    }

    public function importRow(string $userId, string $date, array $values, string $reason, string $version, User $actor, string $importId): string
    {
        abort_unless(in_array($actor->userType?->user_type, UserType::ATTENDANCE_IMPORT_ROLES, true), 403);
        return DB::transaction(function () use ($userId, $date, $values, $reason, $version, $actor, $importId) {
            $period = $this->lockPeriod($date);
            $record = AttendanceRecord::withoutGlobalScope('effective-attendance')->where('user_id', $userId)->whereDate('attendance_date', $date)->lockForUpdate()->first();
            if ($record) {
                $fields = array_diff(self::FIELDS, ['source_import_id']);
                if ($record->only($fields) == array_intersect_key($values, array_flip($fields))) return 'unchanged';
                return $this->correct($record->id, $values, $reason, $version, $actor, 'import', $importId)->status;
            }
            abort_unless($version === hash('sha256', 'missing'), 409, 'Attendance changed after preview.');
            if (!$period->closed) {
                AttendanceRecord::create(array_merge(['user_id' => $userId, 'attendance_date' => $date,
                    'day_name' => \Carbon\Carbon::parse($date)->format('l')], $values));
                return 'created';
            }
            if (trim($reason) === '') throw ValidationException::withMessages(['correction_reason' => 'A correction reason is required.']);
            if (DB::table('attendance_corrections')->where('user_id', $userId)->whereDate('attendance_date', $date)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['record' => 'Resolve the pending correction first.']);
            }
            $id = (string) Str::uuid();
            DB::table('attendance_corrections')->insert(['id' => $id, 'attendance_record_id' => (string) Str::uuid(),
                'user_id' => $userId, 'attendance_date' => $date, 'before_values' => '{}',
                'after_values' => json_encode($values, JSON_THROW_ON_ERROR), 'base_version' => $version,
                'reason' => $reason, 'source' => 'import', 'source_import_id' => $importId,
                'requested_by' => $actor->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->event($id, 'requested', $actor, ['source' => 'import']);
            return 'pending';
        });
    }
    public function decide(string $id, bool $approve, string $reason, User $actor)
    {
        abort_unless($actor->userType?->user_type === UserType::SUPER_ADMIN, 403);
        return DB::transaction(function () use ($id, $approve, $reason, $actor) {
            $initial = DB::table('attendance_corrections')->where('id', $id)->first();
            abort_unless($initial, 404);
            $period = $this->lockPeriod($initial->attendance_date);
            $request = DB::table('attendance_corrections')->where('id', $id)->lockForUpdate()->first();
            abort_unless($request->status === 'pending', 409, 'Correction has already been decided.');
            $record = AttendanceRecord::withoutGlobalScope('effective-attendance')->where('user_id', $request->user_id)->whereDate('attendance_date', $request->attendance_date)->lockForUpdate()->first();
            if ($approve) {
                abort_unless(hash_equals($request->base_version, $record ? $this->version($record) : hash('sha256', 'missing')), 409, 'Attendance changed after submission.');
                if (!$record) {
                    $record = new AttendanceRecord(['id' => $request->attendance_record_id,
                        'user_id' => $request->user_id, 'attendance_date' => $request->attendance_date,
                        'day_name' => \Carbon\Carbon::parse($request->attendance_date)->format('l')]);
                }
                $record->forceFill(json_decode($request->after_values, true) + ['correction_revision' => (int) $record->correction_revision + 1])->save();
                if ($period->closed) {
                    DB::table('attendance_payroll_reviews')->insert(['id' => (string) Str::uuid(), 'correction_id' => $id,
                        'user_id' => $record->user_id, 'attendance_date' => $record->attendance_date->toDateString(),
                        'month' => $period->month, 'status' => 'pending', 'created_at' => now()]);
                }
            }
            DB::table('attendance_corrections')->where('id', $id)->update(['status' => $approve ? 'applied' : 'rejected',
                'applied_version' => $approve ? $this->version($record->fresh()) : null,
                'decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason, 'updated_at' => now()]);
            $this->event($id, $approve ? 'approved' : 'rejected', $actor, ['reason' => $reason]);
            return DB::table('attendance_corrections')->where('id', $id)->first();
        });
    }

    public function reverse(string $id, string $reason, User $actor)
    {
        $correction = DB::table('attendance_corrections')->where('id', $id)->first();
        abort_unless($correction && $correction->status === 'applied', 409, 'Only applied corrections can be reversed.');
        $record = AttendanceRecord::withoutGlobalScope('effective-attendance')->findOrFail($correction->attendance_record_id);
        abort_unless(hash_equals((string) $correction->applied_version, $this->version($record)), 409, 'Later corrections prevent this reversal.');
        $restore = json_decode($correction->before_values, true);
        if (!$restore) {
            $restore = $this->values($record);
            $restore['correction_voided_at'] = now()->format('Y-m-d H:i:s');
        }
        return $this->correct($record->id, $restore, $reason,
            $this->version($record), $actor, 'reversal', null, $id);
    }
}
