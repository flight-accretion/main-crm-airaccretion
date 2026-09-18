<?php

namespace App\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class KpiSalesWorkService
{
    public function __construct(
        private KpiLeadSourceFilterService $sources
    ) {}

    public function summary(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource = 'all'
    ): array {
        return [
            'total_leads' => $this->totalLeads(
                $userIds,
                $from,
                $to,
                $leadSource
            ),
            'conversations' => $this->conversations(
                $userIds,
                $from,
                $to,
                $leadSource
            ),
            'active_leads' => $this->activeLeads(
                $userIds,
                $to,
                $leadSource
            ),
            'work_done' => $this->workDoneCount(
                $userIds,
                $from,
                $to,
                $leadSource
            ),
            'activity_score' => $this->activityScore(
                $userIds,
                $from,
                $to,
                $leadSource
            ),
        ];
    }

    public function cards(array $summary): array
    {
        return [
            [
                'code' => 'total_leads',
                'label' => 'Total Leads',
                'value' => (int) $summary['total_leads'],
                'subtitle' => 'Leads created in selected period',
            ],
            [
                'code' => 'conversations',
                'label' => 'Conversations',
                'value' => (int) $summary['conversations'],
                'subtitle' => 'Distinct Leads with human KPI activity',
            ],
            [
                'code' => 'active_leads',
                'label' => 'Active Leads',
                'value' => (int) $summary['active_leads'],
                'subtitle' => 'Current assigned active Lead journeys',
            ],
            [
                'code' => 'work_done',
                'label' => 'Work Done',
                'value' => (int) $summary['work_done'],
                'subtitle' => 'Note + actual status change on same Lead/day',
            ],
            [
                'code' => 'activity_score',
                'label' => 'Activity Score',
                'value' => (int) $summary['activity_score'],
                'subtitle' => 'Qualifying notes + status changes',
            ],
        ];
    }

    private function leadBase(
        array $userIds,
        ?string $leadSource
    ): Builder {
        $query = DB::table('leads as l')
            ->whereIn('l.representative_user_id', $userIds);

        return $this->sources->apply($query, 'l', $leadSource);
    }

    private function totalLeads(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource
    ): int {
        return (int) $this->leadBase($userIds, $leadSource)
            ->whereBetween('l.created_at', [$from, $to])
            ->distinct()
            ->count('l.id');
    }

    private function conversations(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource
    ): int {
        $query = DB::table('kpi_activity_events as e')
            ->where('e.department', 'sales')
            ->where('e.entity_type', 'lead')
            ->whereIn('e.user_id', $userIds)
            ->whereIn('e.event_type', [
                KpiActivityRecorder::EVENT_NOTE_ADDED,
                KpiActivityRecorder::EVENT_STATUS_CHANGED,
            ])
            ->whereBetween('e.occurred_at', [$from, $to]);

        $this->joinLeadActivity($query);
        $this->sources->apply($query, 'l', $leadSource);

        return (int) $query->distinct()->count('e.entity_id');
    }

    private function activeLeads(
        array $userIds,
        Carbon $to,
        ?string $leadSource
    ): int {
        $latest = DB::table('lead_followups as lf')
            ->select([
                'lf.lead_id',
                'lf.status',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY lf.lead_id ORDER BY lf.created_at DESC, lf.id DESC) as rn'
            )
            ->where('lf.created_at', '<=', $to);

        $query = $this->leadBase($userIds, $leadSource)
            ->leftJoinSub($latest, 'latest_lf', function ($join) {
                $join->on('latest_lf.lead_id', '=', 'l.id')
                    ->where('latest_lf.rn', '=', 1);
            })
            ->whereNotNull('latest_lf.status')
            ->whereNotIn('latest_lf.status', [2, 5]);

        return (int) $query->distinct()->count('l.id');
    }

    private function qualifyingGroups(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource
    ): Builder {
        $query = DB::table('kpi_activity_events as e')
            ->where('e.department', 'sales')
            ->where('e.entity_type', 'lead')
            ->whereIn('e.user_id', $userIds)
            ->whereBetween('e.occurred_at', [$from, $to])
            ->whereIn('e.event_type', [
                KpiActivityRecorder::EVENT_NOTE_ADDED,
                KpiActivityRecorder::EVENT_STATUS_CHANGED,
            ]);

        $this->joinLeadActivity($query);
        $this->sources->apply($query, 'l', $leadSource);

        return $query
            ->selectRaw('e.user_id')
            ->selectRaw('e.entity_id')
            ->selectRaw('DATE(e.occurred_at) as work_date')
            ->selectRaw(
                "SUM(CASE WHEN e.event_type = 'note_added' THEN 1 ELSE 0 END) as note_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN e.event_type = 'status_changed' THEN 1 ELSE 0 END) as status_change_count"
            )
            ->groupByRaw('e.user_id, e.entity_id, DATE(e.occurred_at)')
            ->havingRaw(
                "SUM(CASE WHEN e.event_type = 'note_added' THEN 1 ELSE 0 END) > 0"
            )
            ->havingRaw(
                "SUM(CASE WHEN e.event_type = 'status_changed' THEN 1 ELSE 0 END) > 0"
            );
    }

    private function workDoneCount(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource
    ): int {
        return (int) DB::query()
            ->fromSub(
                $this->qualifyingGroups($userIds, $from, $to, $leadSource),
                'q'
            )
            ->count();
    }

    private function activityScore(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $leadSource
    ): int {
        return (int) DB::query()
            ->fromSub(
                $this->qualifyingGroups($userIds, $from, $to, $leadSource),
                'q'
            )
            ->selectRaw(
                'COALESCE(SUM(q.note_count + q.status_change_count), 0) as score'
            )
            ->value('score');
    }

    private function joinLeadActivity(Builder $query): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $leadId = $query->getGrammar()->wrap('l.id');
            $entityId = $query->getGrammar()->wrap('e.entity_id');

            $query->join('leads as l', function ($join) use (
                $leadId,
                $entityId
            ) {
                $join->whereRaw(
                    'CAST(' . $leadId . ' AS TEXT) = ' . $entityId
                );
            });

            return;
        }

        $query->join('leads as l', 'l.id', '=', 'e.entity_id');
    }
}
