<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceImport;
use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiUserAssignment;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Target;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    private const OPERATIONS_MANUAL_METRICS = [
        'operations_alternate_options',
        'operations_ivr_software',
        'operations_training',
        'operations_task_completion',
    ];

    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = $data['month'] ?? now()->format('Y-m');
        $period = Carbon::createFromFormat('!Y-m', $month);
        $from = $period->copy()->startOfMonth();
        $to = $period->isSameMonth(now())
            ? now()
            : $period->copy()->endOfMonth();

        return view('admin.pages.hr.dashboard', [
            'month' => $month,
            'activeEmployees' => User::query()->where('status', 1)->count(),
            'attendanceImports' => AttendanceImport::query()
                ->whereNotNull('confirmed_at')
                ->whereBetween('confirmed_at', [$from, $to])
                ->count(),
            'totalLeads' => Lead::query()->count(),
            'monthLeads' => Lead::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'todayFollowups' => $this->followupCountForDate(now()->startOfDay()),
            'missedFollowups' => $this->missedFollowupCount(now()->startOfDay()),
            'pendingManualKpis' => $this->pendingManualKpis($period),
            'paymentsApproved' => (float) PaymentAuditTrail::query()
                ->where('payment_status', 1)
                ->whereBetween('paid_date', [$from, $to])
                ->sum('paid_amount'),
            'salesTarget' => (float) Target::query()
                ->where('year', $period->year)
                ->where('month', $period->month)
                ->sum('target_amount'),
            'salesAchieved' => (float) Target::query()
                ->where('year', $period->year)
                ->where('month', $period->month)
                ->sum('achieved_amount'),
        ]);
    }

    private function followupCountForDate(Carbon $date): int
    {
        return LeadFollowup::query()
            ->whereDate('next_followup_date', $date->toDateString())
            ->whereNotIn('status', LeadFollowup::TODAY_FOLLOWUP_HIDDEN_STATUSES)
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function missedFollowupCount(Carbon $date): int
    {
        return LeadFollowup::query()
            ->whereDate('next_followup_date', '<', $date->toDateString())
            ->whereIn('status', LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES)
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function pendingManualKpis(Carbon $period): int
    {
        $assignments = KpiUserAssignment::query()
            ->with(['template.metrics' => function ($query) {
                $query->where('active', true)
                    ->whereIn('code', self::OPERATIONS_MANUAL_METRICS);
            }])
            ->where('active', true)
            ->whereHas('template', function ($query) {
                $query->where('department', 'operations')
                    ->where('active', true)
                    ->whereHas('metrics', function ($metricQuery) {
                        $metricQuery->where('active', true)
                            ->whereIn('code', self::OPERATIONS_MANUAL_METRICS);
                    });
            })
            ->whereDate('effective_from', '<=', $period->copy()->endOfMonth()->toDateString())
            ->where(function ($query) use ($period) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $period->copy()->startOfMonth()->toDateString());
            })
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        $expected = $assignments
            ->flatMap(function ($assignment) {
                return $assignment->template->metrics
                    ->map(fn ($metric) => $assignment->user_id . '|' . $metric->id);
            })
            ->unique();

        $entered = KpiManualValue::query()
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->get(['user_id', 'metric_id'])
            ->map(fn ($value) => $value->user_id . '|' . $value->metric_id)
            ->unique();

        return $expected->diff($entered)->count();
    }
}
