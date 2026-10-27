<?php

namespace App\Services\Owner;

use App\Http\Controllers\DashboardController;
use App\Models\CallSummaryIntegration;
use App\Models\EmailLeadLog;
use App\Models\KpiMetricSnapshot;
use App\Models\LeadAiScore;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\PaymentAuditTrail;
use App\Models\SalesDailyUpdate;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use App\Models\WhatsAppAiReplyBatch;
use App\Services\Accounts\AccountsHomeService;
use App\Services\TargetResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Aggregates the Owner dashboard. Every section delegates to the existing
 * module services/queries — this class adds no new business rules.
 * Fragile probes (automation, source buckets) degrade to null, never to a 500.
 */
class OwnerDashboardService
{
    private AccountsHomeService $accounts;
    private TargetResolverService $targets;

    public function __construct(AccountsHomeService $accounts, TargetResolverService $targets)
    {
        $this->accounts = $accounts;
        $this->targets = $targets;
    }

    public function overview(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();

        $accounts = $this->accounts->forMonth($month);

        return [
            'month' => $month,
            'health' => $this->health($accounts['money'], $accounts['previous'], $month),
            'attention' => $this->attention($accounts['pending']),
            'salesChart' => $this->salesChart($month),
            'sources' => $this->leadSources($month),
            'funnel' => $this->funnel($month),
            'attainment' => $this->managerAttainment($month),
            'operations' => $this->operations(),
            'automation' => $this->automation(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* KPI row                                                             */
    /* ------------------------------------------------------------------ */

    private function health(array $money, array $previous, Carbon $month): array
    {
        $paidOut = $money['vendor_paid'] + $money['customer_refunded'];
        $paidOutPrev = $previous['vendor_paid'] + $previous['customer_refunded'];

        $activeBookings = LeadRide::query()
            ->whereLeadNotCancelled()
            ->whereNotNull('from_date')
            ->where('from_date', '>=', $month)
            ->where('from_date', '<', $month->copy()->addMonth())
            ->distinct()
            ->count('lead_id');

        return [
            // Same realised money calculation used by Accounts Home:
            // approved collections less vendor payments/refunds, plus vendor refunds.
            'profit_loss' => $money['net'],
            'profit_loss_delta' => $this->delta($money['net'], $previous['net']),
            'net' => $money['net'],
            'net_delta' => $this->delta($money['net'], $previous['net']),
            'collected' => $money['collected'],
            'collected_delta' => $this->delta($money['collected'], $previous['collected']),
            'paid_out' => $paidOut,
            'paid_out_delta' => $this->delta($paidOut, $paidOutPrev),
            'active_bookings' => $activeBookings,
        ];
    }

    private function delta(?float $current, ?float $previous): ?float
    {
        if (!$previous || $previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /* ------------------------------------------------------------------ */
    /* Needs attention                                                     */
    /* ------------------------------------------------------------------ */

    private function attention(array $pending): array
    {
        $age = function ($value) {
            return $value ? max(0, (int) Carbon::parse($value)->startOfDay()->diffInDays(today())) : null;
        };

        return [
            'payments' => [
                'count' => $pending['payments'],
                'amount' => $pending['payment_amount'],
                'oldest_days' => $age($pending['payment_oldest'] ?? null),
            ],
            'vendor_overdue' => [
                'count' => $pending['vendor_overdue'],
                'amount' => $pending['vendor_overdue_amount'],
            ],
            'customer_refunds' => [
                'count' => $pending['customer_refunds'],
                'amount' => $pending['customer_amount'],
                'oldest_days' => $age($pending['customer_oldest'] ?? null),
            ],
            'vendor_refunds' => [
                'count' => $pending['vendor_refunds'],
                'amount' => $pending['vendor_refund_amount'],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Sales chart (company / managers / people)                           */
    /* ------------------------------------------------------------------ */

    private function salesChart(Carbon $month): array
    {
        $end = $month->isSameMonth(now()) ? today() : $month->copy()->endOfMonth();
        $start = $end->copy()->subDays(13);

        $managers = $this->salesManagers();
        $people = $this->salesExecutives();

        $managerSeries = [];
        foreach ($managers as $manager) {
            $managerSeries[] = [
                'name' => $manager->name,
                'data' => $this->dailySales($start, $end, $this->teamRepIds($manager)),
            ];
        }

        $personSeries = [];
        foreach ($people as $person) {
            $personSeries[$person['id']] = [
                'name' => $person['name'],
                'data' => $this->dailySales($start, $end, [$person['id']]),
            ];
        }

        return [
            'days' => $this->dayRange($start, $end),
            'company' => $this->dailySales($start, $end, null),
            'managers' => $managerSeries,
            'people' => $personSeries,
            'people_options' => $people,
        ];
    }

    private function dayRange(Carbon $start, Carbon $end): array
    {
        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[] = $day->format('d M');
        }
        return $days;
    }

    /**
     * Daily approved booking value attributed by lead owner.
     * Uses the payment_audit_trail → lead_followups → leads chain, summed by paid_date.
     */
    private function dailySales(Carbon $start, Carbon $end, ?array $repIds): array
    {
        $query = DB::table('payment_audit_trail as a')
            ->join('lead_followups as f', 'f.id', '=', 'a.lead_followup_id')
            ->join('leads as l', 'l.id', '=', 'f.lead_id')
            ->where('a.payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->whereDate('a.paid_date', '>=', $start->toDateString())
            ->whereDate('a.paid_date', '<=', $end->toDateString())
            ->when($repIds, function ($q) use ($repIds) {
                $q->whereIn('l.representative_user_id', $repIds);
            })
            ->groupByRaw('DATE(a.paid_date)')
            ->selectRaw('DATE(a.paid_date) as day, SUM(a.paid_amount) as amount');

        $daily = [];
        foreach ($query->pluck('amount', 'day') as $day => $amount) {
            $daily[Carbon::parse($day)->toDateString()] = (float) $amount;
        }

        $series = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $series[] = (float) ($daily[$day->toDateString()] ?? 0);
        }
        return $series;
    }

    private function salesManagers(): array
    {
        return User::where('status', 1)
            ->whereHas('userType', function ($q) {
                $q->whereIn('user_type', [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER]);
            })
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function salesExecutives(): array
    {
        $executives = User::where('status', 1)
            ->whereHas('userType', function ($q) {
                $q->where('user_type', UserType::SALES_EXECUTIVE);
            })
            ->orderBy('name')
            ->get();

        $assignments = SalesExecutiveAssignment::where('status', 1)
            ->whereIn('sales_executive_id', $executives->pluck('id'))
            ->get()
            ->keyBy('sales_executive_id');

        $list = [];
        foreach ($executives as $executive) {
            $managerId = $assignments->get($executive->id)?->manager_id;
            $list[] = [
                'id' => $executive->id,
                'name' => $executive->name,
                'group' => $managerId ? (User::find($managerId)?->name ?? 'Sales Executives') : 'Sales Executives',
            ];
        }
        return $list;
    }

    private function teamRepIds(User $manager): array
    {
        return SalesExecutiveAssignment::getSalesExecutivesForManager($manager->id)
            ->pluck('id')
            ->push($manager->id)
            ->unique()
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Lead sources (donut)                                                */
    /* ------------------------------------------------------------------ */

    private function leadSources(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $nextMonth = $start->copy()->addMonth();

        $leads = Lead::query()
            ->select('id')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $nextMonth);
        $total = (clone $leads)->count();

        $emailLeadIds = function () use ($start, $nextMonth) {
            return DB::table('email_lead_logs')
                ->select('lead_id')
                ->whereNotNull('lead_id')
                ->where('received_at', '>=', $start)
                ->where('received_at', '<', $nextMonth);
        };
        $ivrLeadIds = function () use ($start, $nextMonth) {
            return DB::table('ivr_call_logs')
                ->select('lead_id')
                ->whereNotNull('lead_id')
                ->where('call_start_at', '>=', $start)
                ->where('call_start_at', '<', $nextMonth);
        };
        $whatsAppLeadIds = function () use ($start, $nextMonth) {
            return DB::table('whatsapp_lead_integrations')
                ->select('lead_id')
                ->whereNotNull('lead_id')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $nextMonth);
        };

        $email = (clone $leads)->whereIn('id', $emailLeadIds())->count();
        $whatsApp = (clone $leads)
            ->whereIn('id', $whatsAppLeadIds())
            ->whereNotIn('id', $emailLeadIds())
            ->count();
        $ivr = (clone $leads)
            ->whereIn('id', $ivrLeadIds())
            ->whereNotIn('id', $emailLeadIds())
            ->whereNotIn('id', $whatsAppLeadIds())
            ->count();
        $other = max(0, $total - $email - $whatsApp - $ivr);

        return [
            'total' => $total,
            'buckets' => [
                ['label' => 'Email', 'count' => $email, 'color' => '#d97706'],
                ['label' => 'WhatsApp', 'count' => $whatsApp, 'color' => '#12395b'],
                ['label' => 'IVR calls', 'count' => $ivr, 'color' => '#178a4c'],
                ['label' => 'Manual / Other', 'count' => $other, 'color' => '#2f6fed'],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Sales pipeline funnel                                               */
    /* ------------------------------------------------------------------ */

    private function funnel(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $nextMonth = $month->copy()->addMonth();

        $monthlyLeads = Lead::query()
            ->select('id')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $nextMonth);
        $new = (clone $monthlyLeads)->count();

        $latestStatuses = DB::table('lead_followups')
            ->whereIn('lead_id', $monthlyLeads)
            ->whereRaw(
                'id = (select latest.id from lead_followups as latest where latest.lead_id = lead_followups.lead_id order by latest.created_at desc, latest.id desc limit 1)'
            )
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $active = collect([
            LeadFollowup::STATUS_INITIATED,
            LeadFollowup::STATUS_ACTIVE,
            LeadFollowup::STATUS_PENDING,
            LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
        ])->sum(fn ($status) => (int) $latestStatuses->get($status, 0));
        $confirmed = (int) $latestStatuses->get(LeadFollowup::STATUS_CONFIRMED, 0);
        $paid = (int) $latestStatuses->get(LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED, 0);

        return [
            'new' => $new,
            'active' => $active,
            'confirmed' => $confirmed,
            'paid' => $paid,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Manager target attainment                                           */
    /* ------------------------------------------------------------------ */

    private function managerAttainment(Carbon $month): array
    {
        $year = (int) $month->format('Y');
        $monthNum = (int) $month->format('m');
        $progress = app(DashboardController::class);

        $rows = [];
        foreach ($this->salesManagers() as $manager) {
            $repIds = $this->teamRepIds($manager);
            $target = $this->targets->amountForUsers($repIds, $year, $monthNum);
            $achieved = $progress->calculateAchievedAmountForRepIds($repIds, $year, $monthNum);
            $pct = $target > 0 ? round($achieved / $target * 100) : null;

            $rows[] = [
                'name' => $manager->name,
                'role' => $manager->userType->user_type ?? '',
                'initials' => $this->initials($manager->name),
                'target' => $target,
                'achieved' => $achieved,
                'pct' => $pct,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['pct'] ?? -1) <=> ($a['pct'] ?? -1));
        return $rows;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
        $last = strtoupper(substr(end($parts) ?: '', 0, 1));
        return $first . ($last !== $first ? $last : '');
    }

    /* ------------------------------------------------------------------ */
    /* Operations & people                                                 */
    /* ------------------------------------------------------------------ */

    private function operations(): array
    {
        $today = today();

        $ridesToday = LeadRide::query()
            ->whereLeadNotCancelled()
            ->whereDate('from_date', $today)
            ->distinct()
            ->count('lead_id');
        $rides48h = LeadRide::query()
            ->whereLeadNotCancelled()
            ->whereBetween('from_date', [now(), now()->copy()->addHours(48)])
            ->distinct()
            ->count('lead_id');

        $openCases = OperationCase::whereIn('status', [
            OperationCase::STATUS_PENDING,
            OperationCase::STATUS_IN_PROGRESS,
        ]);

        $casesByType = (clone $openCases)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type')
            ->all();

        $attendance = $this->attendanceToday($today);
        $kpiAvg = round((float) KpiMetricSnapshot::where('created_at', '>=', now()->startOfMonth())->avg('achievement_percent'));

        $updatesSubmitted = SalesDailyUpdate::whereDate('update_date', $today)->distinct()->count('user_id');
        $salesTeamSize = User::where('status', 1)->whereHas('userType', function ($q) {
            $q->whereIn('user_type', UserType::SALES_ROLES);
        })->count();

        return [
            'rides_today' => $ridesToday,
            'rides_48h' => $rides48h,
            'cases_total' => array_sum($casesByType),
            'cases_by_type' => $casesByType,
            'attendance' => $attendance,
            'kpi_avg' => $kpiAvg,
            'updates_submitted' => $updatesSubmitted,
            'updates_expected' => $salesTeamSize,
        ];
    }

    private function attendanceToday(Carbon $today): array
    {
        try {
            $records = \App\Models\AttendanceRecord::whereDate('attendance_date', $today)->get();

            return [
                'present' => $records->whereNotNull('in_time')->pluck('user_id')->unique()->count(),
                'absent' => $records->where('paycode', 'A')->pluck('user_id')->unique()->count(),
                'leave' => $records->where('paycode', 'L')->pluck('user_id')->unique()->count(),
            ];
        } catch (\Throwable $e) {
            return ['present' => 0, 'absent' => 0, 'leave' => 0];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Automation health                                                   */
    /* ------------------------------------------------------------------ */

    private function automation(): array
    {
        return [
            'whatsapp_ai' => $this->probe(fn () => WhatsAppAiReplyBatch::whereDate('created_at', today())->count()),
            'call_summaries' => $this->probe(fn () => CallSummaryIntegration::where('status', 'pending')->count()),
            'email_leads_fetched' => $this->probe(fn () => EmailLeadLog::whereDate('received_at', today())->count()),
            'lead_ai_scoring' => $this->probe(fn () => LeadAiScore::whereIn('status', ['pending', 'processing'])->count()),
            'failed_jobs' => $this->probe(fn () => DB::table('failed_jobs')->count()),
            'heartbeat_stale' => $this->heartbeatStale(),
        ];
    }

    private function probe(callable $callback)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function heartbeatStale(): bool
    {
        try {
            $path = storage_path('framework/scheduler-heartbeat.txt');
            $maxAge = (int) config('crm.scheduler_heartbeat_max_age_minutes', 10);
            return !File::exists($path)
                || Carbon::parse(File::get($path))->lte(now()->subMinutes($maxAge));
        } catch (\Throwable $e) {
            return true;
        }
    }
}
