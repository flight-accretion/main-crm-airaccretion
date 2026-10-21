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
use App\Services\Hr\HrDashboardDataService;
use App\Services\Kpi\KpiDashboardFilterService;

class HrDashboardController extends Controller
{
    private const OPERATIONS_MANUAL_METRICS = [
        'operations_alternate_options',
        'operations_ivr_software',
        'operations_training',
        'operations_task_completion',
    ];

    public function index(Request $request, HrDashboardDataService $dashboard, KpiDashboardFilterService $filters)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'preset' => 'nullable|in:today,yesterday,custom',
            'sales_page' => 'nullable|integer|min:1',
            'operations_page' => 'nullable|integer|min:1',
            'from_date' => 'required_if:preset,custom|nullable|date_format:Y-m-d',
            'to_date' => 'required_if:preset,custom|nullable|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        // Preserve old month links while defaulting the new HR filter to Today.
        if (!isset($data['preset']) && isset($data['month'])) {
            $legacy = Carbon::createFromFormat('!Y-m', $data['month']);
            $request->merge(['preset' => 'custom', 'from_date' => $legacy->copy()->startOfMonth()->toDateString(),
                'to_date' => $legacy->isSameMonth(now()) ? now()->toDateString() : $legacy->copy()->endOfMonth()->toDateString()]);
        } elseif (!isset($data['preset'])) {
            $request->merge(['preset' => 'today']);
        }
        $filter = $filters->fromRequest($request);
        $from = $filter['from'];
        $to = $filter['to'];
        $period = $to->copy()->startOfMonth();
        $month = $period->format('Y-m');
        $sameMonth = $from->format('Y-m') === $month;
        $dynamic = $dashboard->forDashboard($request->user(), $request, $filter);
        $salesIds = app(\App\Services\Kpi\KpiDataScopeService::class)->allowedUsers($request->user(), 'sales')->pluck('id');
        $salesAchieved = $sameMonth ? app(\App\Services\TargetIncentiveService::class)
            ->ledgerForPeriod($from, $to)->whereIn('owner', $salesIds)->sum('amount') : 0;
        $salesTarget = $sameMonth ? app(\App\Services\TargetResolverService::class)
            ->amountForUsers($salesIds->all(), $period->year, $period->month) : 0;

        return view('admin.pages.hr.dashboard', array_merge($dynamic, [
            'filter' => $filter,
            'month' => $month,
            'targetAvailable' => $sameMonth,
            'incentiveSummary' => $sameMonth ? $dashboard->incentiveSummary($period) : null,
            'activeEmployees' => User::query()->where('status', 1)->count(),
            'attendanceImports' => AttendanceImport::query()
                ->whereNotNull('confirmed_at')
                ->whereBetween('confirmed_at', [$from, $to])
                ->count(),
            'totalLeads' => Lead::query()->count(),
            'monthLeads' => Lead::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'todayFollowups' => $this->followupCountForPeriod($from, $to),
            'missedFollowups' => $this->missedFollowupCount(now()->startOfDay()),
            'wantService' => Lead::query()->whereHas('rideSegments', fn ($query) => $query->whereBetween('from_date', [$from, $to]))
                ->whereRaw('COALESCE((SELECT status FROM lead_followups WHERE lead_id = leads.id ORDER BY created_at DESC, id DESC LIMIT 1), -1) <> ?', [LeadFollowup::STATUS_CANCELLED])
                ->count(),
            'pendingManualKpis' => $sameMonth ? $this->pendingManualKpis($period) : null,
            'paymentsApproved' => (float) PaymentAuditTrail::query()
                ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
                ->whereBetween('paid_date', [$from, $to])
                ->sum('paid_amount'),
            'salesTarget' => (float) $salesTarget,
            'salesAchieved' => (float) $salesAchieved,
        ]));
    }

    private function followupCountForPeriod(Carbon $from, Carbon $to): int
    {
        return $this->latestFollowupsQuery()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('next_followup_date', [$from, $to]);
                if ($from->lte(now()->endOfDay()) && $to->gte(now()->startOfDay())) {
                    $query->orWhere(fn ($query) => $query->whereNull('next_followup_date')->where('status', LeadFollowup::STATUS_ACTIVE));
                }
            })
            ->whereNotIn('status', LeadFollowup::TODAY_FOLLOWUP_HIDDEN_STATUSES)
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function missedFollowupCount(Carbon $date): int
    {
        return $this->latestFollowupsQuery()
            ->whereDate('next_followup_date', '<', $date->toDateString())
            ->whereIn('status', LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES)
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function latestFollowupsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return LeadFollowup::query()->where('id', function ($query) {
            $query->select('latest.id')->from('lead_followups as latest')
                ->whereColumn('latest.lead_id', 'lead_followups.lead_id')
                ->orderByDesc('latest.created_at')->orderByDesc('latest.next_followup_date')->limit(1);
        });
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
