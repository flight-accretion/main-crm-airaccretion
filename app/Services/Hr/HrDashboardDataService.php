<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\OperationCase;
use App\Models\User;
use App\Models\UserType;
use App\Services\Kpi\KpiDashboardService;
use App\Services\Kpi\KpiDataScopeService;
use App\Services\Kpi\KpiWorkDoneService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class HrDashboardDataService
{
    public function __construct(
        private KpiDataScopeService $scope,
        private KpiDashboardService $kpi,
        private KpiWorkDoneService $salesWork
    ) {}

    public function forDashboard(User $actor, Request $request, array $filter): array
    {
        $actor->loadMissing('userType');
        // Owner has the same company-wide, read-only visibility as Super Admin
        // for this dashboard. Mutating HR routes remain guarded separately.
        abort_unless(in_array($actor->userType->user_type ?? '', array_merge(UserType::ADMIN_ROLES, [UserType::HR, UserType::OWNER]), true), 403);
        $from = $filter['from'];
        $to = $filter['to'];
        $staffIds = User::query()->where('status', 1)->pluck('id');
        $attendance = $this->attendance($staffIds->all(), $from, $to);
        $departments = [];

        foreach (['sales', 'operations'] as $department) {
            $users = $this->scope->allowedUsers($actor, $department);
            $pageName = $department . '_page';
            $page = max(1, (int) $request->input($pageName, 1));
            $page = min($page, max(1, (int) ceil($users->count() / 10)));
            $visible = $users->forPage($page, 10)->values();
            $operations = $department === 'operations'
                ? $this->operationsWork($visible->pluck('id')->all(), $from, $to)
                : [];
            $rows = $visible->map(function (User $user) use ($actor, $department, $filter, $attendance, $operations) {
                // Use the same current records as the detail pages on every request.
                $calculated = (function () use ($actor, $user, $department, $filter) {
                    $kpi = $this->kpi->forUser($user, $filter['to'], $filter['from']);
                    $weakest = collect($kpi['metrics'])->sort(function ($a, $b) {
                        return ($a['score'] <=> $b['score']) ?: ($b['weightage'] <=> $a['weightage']);
                    })->first();
                    $score = $kpi['overall_score'];
                    $result = [
                        'score' => $score,
                        'weakest' => $weakest['name'] ?? 'Not configured',
                        'status' => !$kpi['configured'] ? 'Not configured' : ($score >= 4 ? 'On track' : ($score >= 3 ? 'Watch' : 'Below')),
                        'tone' => !$kpi['configured'] ? 'neutral' : ($score >= 4 ? 'green' : ($score >= 3 ? 'amber' : 'red')),
                    ];
                    if ($department === 'sales') {
                        $work = $this->salesWork->dashboard($actor, array_merge($filter, ['user_id' => $user->id]));
                        $result['work'] = [
                            'completed' => $work['lead_history_available'] ? $work['summary']['completed'] : null,
                            'active' => $work['lead_history_available'] ? $work['summary']['active'] : null,
                            'cancelled' => $work['lead_history_available'] ? $work['summary']['cancelled'] : null,
                            'total' => $work['lead_history_available'] ? $work['summary']['total'] : null,
                            'outreach' => $work['summary']['outreach_completed'],
                            'notice' => $work['lead_history_notice'],
                        ];
                    }
                    return $result;
                })();
                return array_merge($calculated, [
                    'user' => $user,
                    'attendance' => $attendance['users'][$user->id] ?? ['label' => 'Not Available', 'tone' => 'neutral'],
                    'work' => $department === 'sales' ? $calculated['work'] : $operations[$user->id],
                ]);
            });
            $departments[$department] = new LengthAwarePaginator($rows, $users->count(), 10, $page, [
                'path' => $request->url(), 'pageName' => $pageName,
                'query' => $request->except($pageName),
            ]);
        }

        return ['attendanceSummary' => $attendance['summary'], 'departmentRows' => $departments];
    }

    public function attendance(array $userIds, Carbon $from, Carbon $to): array
    {
        $records = AttendanceRecord::query()->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('updated_at')->get(['user_id', 'attendance_date', 'raw_status', 'in_time', 'updated_at'])
            ->unique(fn ($row) => $row->user_id . '|' . $row->attendance_date->toDateString());
        $single = $from->isSameDay($to);
        $summary = ['present' => 0, 'recorded' => $records->count(), 'staff' => count($userIds), 'missing' => 0];
        $byUser = $records->groupBy('user_id');
        $users = [];
        foreach ($userIds as $userId) {
            $rows = $byUser->get($userId, collect());
            $present = $rows->filter(fn ($row) => $this->attendanceStatus($row)['label'] === 'Present')->count();
            $summary['present'] += $present;
            $summary['missing'] += max(0, $from->diffInDays($to) + 1 - $rows->count());
            $users[$userId] = $single
                ? ($rows->isEmpty() ? ['label' => 'Not Available', 'tone' => 'neutral'] : $this->attendanceStatus($rows->first()))
                : ['label' => $rows->isEmpty() ? 'Not Available' : $present . ' / ' . $rows->count() . ' days', 'tone' => 'neutral'];
        }
        return ['summary' => $summary, 'users' => $users];
    }

    public function incentiveSummary(Carbon $period): array
    {
        $targets = \App\Models\Target::where('year', $period->year)->where('month', $period->month)
            ->where('status', 'active')->whereHas('salesExecutive', fn ($query) => $query->where('status', 1))->get();
        if ($targets->groupBy('sales_executive_id')->contains(fn ($items) => $items->count() > 1)) {
            return ['amount'=>null, 'configured'=>0, 'earning'=>0, 'error'=>'Resolve duplicate active targets'];
        }
        $targets = $targets->filter(fn ($target) => $target->incentive_enabled);
        $service = app(\App\Services\TargetIncentiveService::class);
        $service->primeTeams($targets->where('incentive_basis', 'team')->pluck('sales_executive_id'));
        $end = $period->isSameMonth(now()) ? now() : $period->copy()->endOfMonth();
        $ledger = $targets->isEmpty() ? collect() : $service->ledgerForPeriod($period->copy()->startOfMonth(), $end);
        $amounts = $targets->map(fn ($target) => $service->calculate($target, $ledger)['incentive']);
        return ['amount'=>round($amounts->sum(), 2), 'configured'=>$targets->count(),
            'earning'=>$amounts->filter(fn ($amount) => $amount > 0)->count(), 'error'=>null];
    }

    private function attendanceStatus(AttendanceRecord $record): array
    {
        $status = strtoupper(trim((string) $record->raw_status));
        if (in_array($status, ['A', 'ABSENT'], true)) {
            return ['label' => 'Absent', 'tone' => 'red'];
        }
        if (in_array($status, ['L', 'LEAVE', 'CL', 'SL', 'PL', 'EL', 'ML', 'LOP'], true)) {
            return ['label' => 'On leave', 'tone' => 'amber'];
        }
        if (in_array($status, ['HD', 'HALF DAY', 'HALF-DAY'], true)) {
            return ['label' => 'Half day', 'tone' => 'amber'];
        }
        if (in_array($status, array_merge(['WO', 'W/O', 'OFF', 'HOLIDAY', 'H', 'HLD'], config('kpi.attendance.non_scheduled_statuses', [])), true)) {
            return ['label' => in_array($status, ['H', 'HLD', 'HOLIDAY'], true) ? 'Holiday' : 'Weekly off', 'tone' => 'neutral'];
        }
        if ($record->in_time || in_array($status, ['P', 'PRESENT', 'MIS'], true)) {
            return ['label' => 'Present', 'tone' => 'green'];
        }
        return ['label' => $status !== '' ? $status : 'Not Available', 'tone' => 'neutral'];
    }

    public function operationsWork(array $userIds, Carbon $from, Carbon $to): array
    {
        $completed = OperationCase::query()->whereIn('completed_by', $userIds)
            ->whereBetween('completed_at', [$from, $to])->selectRaw('completed_by, COUNT(DISTINCT lead_id) AS total')
            ->groupBy('completed_by')->pluck('total', 'completed_by');
        $active = OperationCase::query()->whereIn('assigned_to', $userIds)->whereNull('completed_at')
            ->where('status', OperationCase::STATUS_IN_PROGRESS)
            ->whereHas('activities', fn ($query) => $query->whereBetween('created_at', [$from, $to])->whereColumn('user_id', 'operation_cases.assigned_to'))
            ->selectRaw('assigned_to, COUNT(*) AS total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $due = OperationCase::query()->whereIn('assigned_to', $userIds)->whereNull('completed_at')
            ->whereIn('status', [OperationCase::STATUS_PENDING, OperationCase::STATUS_IN_PROGRESS]);
        // Cases without a scheduled follow-up are due when opened, not silently omitted.
        $dueDate = 'COALESCE(next_followup_at, opened_at, created_at)';
        $counts = (clone $due)->whereBetween(\Illuminate\Support\Facades\DB::raw($dueDate), [$from, $to])
            ->selectRaw('assigned_to, type, COUNT(*) AS total')->groupBy('assigned_to', 'type')->get()->groupBy('assigned_to');
        $reviews = (clone $due)->whereBetween(\Illuminate\Support\Facades\DB::raw($dueDate), [$from, $to])
            ->where('type', OperationCase::TYPE_REVIEW)
            ->where(fn ($query) => $query->whereNull('metadata->review_status')->orWhereNotIn('metadata->review_status', ['completed', 'cancelled']))
            ->where(fn ($query) => $query->whereNull('metadata->review_completed')->orWhere('metadata->review_completed', false))
            ->where(fn ($query) => $query->whereNull('metadata->review_cancelled')->orWhere('metadata->review_cancelled', false))
            ->selectRaw('assigned_to, COUNT(*) AS total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $overdue = (clone $due)->whereRaw($dueDate . ' < ?', [$from])
            ->selectRaw('assigned_to, COUNT(*) AS total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $rows = [];
        foreach ($userIds as $id) {
            $types = $counts->get($id, collect());
            $rows[$id] = [
                'completed' => (int) ($completed[$id] ?? 0), 'active' => (int) ($active[$id] ?? 0),
                'outreach' => null, 'pending' => (int) $types->sum('total'),
                'review' => (int) ($reviews[$id] ?? 0),
                'vendor' => (int) $types->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)->sum('total'),
                'overdue' => (int) ($overdue[$id] ?? 0), 'notice' => null,
            ];
        }
        return $rows;
    }
}
