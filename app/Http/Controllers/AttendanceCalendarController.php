<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\UserType;
use App\Services\Attendance\AttendanceCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceCalendarController extends Controller
{
    private function authorizeCalendar(Request $request): void
    {
        abort_unless(in_array(optional(optional($request->user())->userType)->user_type,
            [UserType::HR, UserType::SUPER_ADMIN], true), 403);
    }

    public function weekoffs(Request $request)
    {
        $this->authorizeCalendar($request);
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'effective_from' => 'required|date_format:Y-m-d',
            'weekdays' => 'nullable|array|max:7',
            'weekdays.*' => 'required|integer|between:0,6|distinct',
        ]);
        $days = array_map('intval', $data['weekdays'] ?? []);
        sort($days);
        DB::transaction(function () use ($data, $days, $request) {
            $user = User::whereKey($data['user_id'])->where('status', 1)->lockForUpdate()->firstOrFail();
            $key = ['user_id' => $user->id, 'effective_from' => $data['effective_from']];
            $before = DB::table('attendance_week_off_schedules')->where($key)->first();
            DB::table('attendance_week_off_schedules')->updateOrInsert($key, [
                'id' => $before->id ?? (string) Str::uuid(), 'weekdays' => json_encode($days),
                'updated_by' => $request->user()->id, 'created_at' => $before->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->validateExistingSwaps($user);
            $this->audit('weekoff_saved', $user->id, $request, ['before' => $before, 'after' => $data + ['weekdays' => $days]]);
        });
        return $this->backToCalendar($data['user_id'], Carbon::parse($data['effective_from']));
    }

    public function holidays(Request $request)
    {
        $this->authorizeCalendar($request);
        $data = $request->validate([
            'year' => 'required|integer|between:2000,2100', 'month' => 'required|integer|between:1,12',
            'mode' => 'required|in:additional,inclusive', 'dates' => 'nullable|array|max:31',
            'dates.*' => 'required|date_format:Y-m-d|distinct',
        ]);
        $dates = $data['dates'] ?? [];
        foreach ($dates as $date) {
            if (Carbon::parse($date)->year !== (int) $data['year'] || Carbon::parse($date)->month !== (int) $data['month']) {
                throw ValidationException::withMessages(['dates' => 'Every holiday must belong to the selected month and year.']);
            }
        }
        sort($dates);
        DB::transaction(function () use ($data, $dates, $request) {
            // Use the same employee locks as schedule/swap writes to serialize calendar changes.
            $users = User::orderBy('id')->lockForUpdate()->get(['id']);
            $key = ['year' => $data['year'], 'month' => $data['month']];
            $before = DB::table('attendance_calendar_months')->where($key)->first();
            DB::table('attendance_calendar_months')->updateOrInsert($key, [
                'id' => $before->id ?? (string) Str::uuid(), 'mode' => $data['mode'], 'dates' => json_encode($dates),
                'updated_by' => $request->user()->id, 'created_at' => $before->created_at ?? now(), 'updated_at' => now(),
            ]);
            $affectedIds = DB::table('attendance_week_off_swaps')->where(function ($query) use ($data) {
                $query->whereYear('worked_date', $data['year'])->whereMonth('worked_date', $data['month'])
                    ->orWhere(function ($query) use ($data) {
                        $query->whereYear('off_date', $data['year'])->whereMonth('off_date', $data['month']);
                    });
            })->distinct()->pluck('user_id');
            foreach ($users->whereIn('id', $affectedIds) as $user) $this->validateExistingSwaps($user);
            $this->audit('holidays_saved', null, $request, ['before' => $before, 'after' => $data]);
        });
        return redirect()->route('admin.attendance.settings.index', ['year' => $data['year'], 'month' => $data['month']])
            ->with('success', 'Monthly holidays saved. Overlapping dates are counted once.');
    }

    public function swaps(Request $request)
    {
        $this->authorizeCalendar($request);
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'worked_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'off_date' => 'required|date_format:Y-m-d|different:worked_date',
            'note' => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($data['user_id'])->where('status', 1)->lockForUpdate()->firstOrFail();
            $conflict = DB::table('attendance_week_off_swaps')->where('user_id', $user->id)
                ->where(function ($query) use ($data) {
                    $query->whereIn('worked_date', [$data['worked_date'], $data['off_date']])
                        ->orWhereIn('off_date', [$data['worked_date'], $data['off_date']]);
                })->exists();
            if ($conflict) throw ValidationException::withMessages(['off_date' => 'One of these dates is already used by an approved swap.']);
            $this->validateSwap($user, $data['worked_date'], $data['off_date']);
            $id = (string) Str::uuid();
            DB::table('attendance_week_off_swaps')->insert($data + ['id' => $id,
                'approved_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit('swap_approved', $user->id, $request, $data + ['id' => $id]);
        });
        return $this->backToCalendar($data['user_id'], Carbon::parse($data['worked_date']));
    }

    public function destroySwap(Request $request, string $swap)
    {
        $this->authorizeCalendar($request);
        $record = DB::table('attendance_week_off_swaps')->where('id', $swap)->first();
        abort_unless($record, 404);
        DB::transaction(function () use ($request, $record) {
            User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            DB::table('attendance_week_off_swaps')->where('id', $record->id)->delete();
            $this->audit('swap_removed', $record->user_id, $request, (array) $record);
        });
        return $this->backToCalendar($record->user_id, Carbon::parse($record->worked_date));
    }

    private function validateExistingSwaps(User $user): void
    {
        foreach (DB::table('attendance_week_off_swaps')->where('user_id', $user->id)->get() as $swap) {
            $this->validateSwap($user, $swap->worked_date, $swap->off_date);
        }
    }

    private function validateSwap(User $user, string $worked, string $off): void
    {
        $calendar = new AttendanceCalendarService();
        $workedDate = Carbon::parse($worked); $offDate = Carbon::parse($off);
        $work = $calendar->summary($user, $workedDate->year, $workedDate->month, false)['dates']->map->toDateString();
        $leave = $calendar->summary($user, $offDate->year, $offDate->month, false)['dates']->map->toDateString();
        if ($work->contains($worked) || !$leave->contains($off)) {
            throw ValidationException::withMessages(['off_date' => 'Swap must link a non-working day worked to a different scheduled working day. Calendar changes must preserve approved swaps.']);
        }
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('attendance_date', $worked)->first();
        if (!$record || !in_array(strtoupper(trim((string) $record->raw_status)), ['P', 'MIS'], true)) {
            throw ValidationException::withMessages(['worked_date' => 'Import P or MIS attendance for the worked off-day before approving a swap.']);
        }
    }

    private function audit(string $action, ?string $userId, Request $request, array $details): void
    {
        DB::table('attendance_calendar_audits')->insert(['id' => (string) Str::uuid(),
            'action' => $action, 'user_id' => $userId, 'actor_id' => $request->user()->id,
            'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function backToCalendar(string $userId, Carbon $date)
    {
        return redirect()->route('admin.attendance.settings.index', [
            'calendar_user_id' => $userId, 'year' => $date->year, 'month' => $date->month,
        ])->with('success', 'Attendance calendar updated.');
    }
}
