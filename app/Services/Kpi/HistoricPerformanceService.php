<?php

namespace App\Services\Kpi;

use App\Models\KpiMonthlySnapshot;
use App\Models\KpiUserAssignment;
use App\Models\Target;
use App\Services\TargetIncentiveService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HistoricPerformanceService
{
    public function __construct(private KpiDashboardService $dashboard, private TargetIncentiveService $sales) {}

    public function rows(Collection $employees, Carbon $from, Carbon $to): Collection
    {
        if ($employees->isEmpty()) return collect();
        $ids = $employees->pluck('id');
        $snapshots = KpiMonthlySnapshot::whereIn('user_id', $ids)
            ->where('year', '>=', $from->year)->where('year', '<=', $to->year)->get()
            ->keyBy(fn ($s) => $s->user_id . ':' . sprintf('%04d-%02d', $s->year, $s->month));
        $targets = Target::whereIn('sales_executive_id', $ids)->where('year', '<=', $to->year)
            ->orderBy('year')->orderBy('month')->get()->groupBy('sales_executive_id');
        $assignments = KpiUserAssignment::whereIn('user_id', $ids)->where('active', true)->get()->groupBy('user_id');
        $result = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addMonth()) {
            $period = $date->format('Y-m');
            // The shared ledger is loaded once per month, not once per employee.
            $monthTargets = $targets->flatten(1)->filter(fn ($t) => $t->year === $date->year && $t->month === $date->month && $t->status === 'active');
            $asOf = $date->isSameMonth(now()) ? now() : $date->copy()->endOfMonth();
            $hasMonthlyAssignment = $assignments->flatten(1)->contains(fn ($a) => Carbon::parse($a->effective_from)->lte($asOf) && (!$a->effective_to || Carbon::parse($a->effective_to)->endOfDay()->gte($asOf)));
            $needsLedger = $monthTargets->isNotEmpty() || $hasMonthlyAssignment;
            $amounts = $needsLedger ? collect(Cache::remember('historic-net-sales:v1:' . $period, 300, function () use ($date) {
                return $this->sales->ledger($date->year, $date->month)->groupBy('owner')
                    ->map(fn ($sales) => round($sales->sum('amount'), 2))->all();
            })) : collect();
            foreach ($employees as $employee) {
                $allTargets = $targets->get($employee->id, collect());
                $exact = $allTargets->first(fn ($t) => $t->year === $date->year && $t->month === $date->month);
                $effective = $allTargets->filter(fn ($t) => sprintf('%04d-%02d', $t->year, $t->month) <= $period)->last();
                $effectiveAmount = $effective && $effective->status === 'active' ? (float) $effective->target_amount : 0.0;
                $snapshot = $snapshots->get($employee->id . ':' . $period);
                $hasAssignment = $assignments->get($employee->id, collect())->contains(fn ($a) => Carbon::parse($a->effective_from)->lte($asOf) && (!$a->effective_to || Carbon::parse($a->effective_to)->endOfDay()->gte($asOf)));
                $score = null; $raw = null; $source = 'Not available';
                if ($snapshot && $snapshot->finalized_at) {
                    $score = (int) $snapshot->overall_score; $raw = (float) $snapshot->weighted_raw_score; $source = 'Finalized';
                } elseif ($hasAssignment) {
                    $actual = (float) $amounts->get($employee->id, 0);
                    $resolved = ['actual_value'=>$actual, 'target_value'=>$effectiveAmount,
                        'achievement_percent'=>$effectiveAmount > 0 ? $actual / $effectiveAmount * 100 : 0,
                        'evidence'=>['target_amount'=>$effectiveAmount,'achieved_amount'=>$actual,'remaining_amount'=>max(0,$effectiveAmount-$actual)]];
                    $key = 'historic-kpi:v1:' . $employee->id . ':' . $period;
                    $kpi = Cache::remember($key, 300, function () use ($employee, $asOf, $date, $resolved) {
                        $result = $this->dashboard->forUser($employee, $asOf, $date->copy()->startOfMonth(), $resolved);
                        return array_intersect_key($result, array_flip(['configured','overall_score','weighted_raw_score']));
                    });
                    if ($kpi['configured']) {
                        $score = $kpi['overall_score']; $raw = $kpi['weighted_raw_score'];
                        $source = $date->isSameMonth(now()) ? 'Month to date' : 'Recalculated';
                    }
                }
                $assigned = $exact && $exact->status === 'active';
                $target = $assigned ? (float) $exact->target_amount : null;
                $achieved = $assigned ? (float) $amounts->get($employee->id, 0) : null;
                $result->push(['user_id'=>(string)$employee->id,'employee'=>$employee->name,
                    'department'=>app(KpiDataScopeService::class)->departmentFor($employee) ?: 'other',
                    'period'=>$period,'label'=>$date->format('M Y'),'score'=>$score,'raw_score'=>$raw,'source'=>$source,
                    'target'=>$target,'achieved'=>$achieved,'achievement'=>$target > 0 ? round($achieved/$target*100,2) : null,
                    'period_status'=>$date->isSameMonth(now()) ? 'Month to date' : 'Completed month']);
            }
        }
        return $result;
    }
}
