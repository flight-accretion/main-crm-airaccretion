<?php

namespace App\Services\Kpi;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class KpiDashboardDetailService
{
    public function __construct(
        private KpiLeadSourceFilterService $sources
    ) {}

    public function sales(
        string $metric,
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $source = 'all'
    ): array {
        return match ($metric) {
            'work_done', 'activity_score' => $this->workDoneRows(
                $userIds,
                $from,
                $to,
                $source
            ),
            'conversations' => $this->conversationRows(
                $userIds,
                $from,
                $to,
                $source
            ),
            'active_leads' => $this->activeRows(
                $userIds,
                $to,
                $source
            ),
            'total_leads' => $this->totalLeadRows(
                $userIds,
                $from,
                $to,
                $source
            ),
            default => abort(404, 'Unknown KPI detail metric.'),
        };
    }

    private function totalLeadRows(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $source
    ): array {
        $query = DB::table('leads as l')
            ->leftJoin('clients as c', 'c.id', '=', 'l.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.representative_user_id')
            ->whereIn('l.representative_user_id', $userIds)
            ->whereBetween('l.created_at', [$from, $to]);

        $this->sources->apply($query, 'l', $source);

        return [
            'columns' => [
                ['key' => 'lead_id', 'label' => 'Lead'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'agent', 'label' => 'Agent'],
                ['key' => 'created_at', 'label' => 'Created'],
            ],
            'rows' => $query
                ->select([
                    'l.id as lead_id',
                    'c.name as customer',
                    'u.name as agent',
                    'l.created_at',
                ])
                ->orderByDesc('l.created_at')
                ->limit(500)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
        ];
    }

    private function conversationRows(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $source
    ): array {
        $query = DB::table('kpi_activity_events as e');

        $this->joinLeadActivity($query);

        $query
            ->leftJoin('clients as c', 'c.id', '=', 'l.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.department', 'sales')
            ->where('e.entity_type', 'lead')
            ->whereIn('e.user_id', $userIds)
            ->whereBetween('e.occurred_at', [$from, $to]);

        $this->sources->apply($query, 'l', $source);

        return [
            'columns' => [
                ['key' => 'lead_id', 'label' => 'Lead'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'agent', 'label' => 'Agent'],
                ['key' => 'activity_count', 'label' => 'Activities'],
                ['key' => 'last_activity', 'label' => 'Last Activity'],
            ],
            'rows' => $query
                ->selectRaw('e.entity_id as lead_id')
                ->selectRaw('MAX(c.name) as customer')
                ->selectRaw('MAX(u.name) as agent')
                ->selectRaw('COUNT(*) as activity_count')
                ->selectRaw('MAX(e.occurred_at) as last_activity')
                ->groupBy('e.entity_id')
                ->orderByDesc('last_activity')
                ->limit(500)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
        ];
    }

    private function workDoneRows(
        array $userIds,
        Carbon $from,
        Carbon $to,
        ?string $source
    ): array {
        $query = DB::table('kpi_activity_events as e');

        $this->joinLeadActivity($query);

        $query
            ->leftJoin('clients as c', 'c.id', '=', 'l.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.department', 'sales')
            ->where('e.entity_type', 'lead')
            ->whereIn('e.user_id', $userIds)
            ->whereBetween('e.occurred_at', [$from, $to]);

        $this->sources->apply($query, 'l', $source);

        $rows = $query
            ->selectRaw('e.user_id')
            ->selectRaw('e.entity_id as lead_id')
            ->selectRaw('DATE(e.occurred_at) as work_date')
            ->selectRaw('MAX(c.name) as customer')
            ->selectRaw('MAX(u.name) as agent')
            ->selectRaw(
                "SUM(CASE WHEN e.event_type = 'note_added' THEN 1 ELSE 0 END) as notes"
            )
            ->selectRaw(
                "SUM(CASE WHEN e.event_type = 'status_changed' THEN 1 ELSE 0 END) as status_changes"
            )
            ->selectRaw('MAX(e.occurred_at) as last_activity')
            ->groupByRaw('e.user_id, e.entity_id, DATE(e.occurred_at)')
            ->havingRaw(
                "SUM(CASE WHEN e.event_type = 'note_added' THEN 1 ELSE 0 END) > 0"
            )
            ->havingRaw(
                "SUM(CASE WHEN e.event_type = 'status_changed' THEN 1 ELSE 0 END) > 0"
            )
            ->orderByDesc('work_date')
            ->orderByDesc('last_activity')
            ->limit(500)
            ->get()
            ->map(function ($row) {
                $array = (array) $row;
                $array['notes'] = (int) $array['notes'];
                $array['status_changes'] = (int) $array['status_changes'];
                $array['score'] = $array['notes'] + $array['status_changes'];

                return $array;
            })
            ->all();

        return [
            'columns' => [
                ['key' => 'lead_id', 'label' => 'Lead'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'agent', 'label' => 'Agent'],
                ['key' => 'work_date', 'label' => 'Date'],
                ['key' => 'notes', 'label' => 'Notes'],
                ['key' => 'status_changes', 'label' => 'Status Changes'],
                ['key' => 'score', 'label' => 'Activity Score'],
            ],
            'rows' => $rows,
        ];
    }

    private function activeRows(
        array $userIds,
        Carbon $to,
        ?string $source
    ): array {
        $latest = DB::table('lead_followups as lf')
            ->select(['lf.lead_id', 'lf.status', 'lf.created_at'])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY lf.lead_id ORDER BY lf.created_at DESC, lf.id DESC) as rn'
            )
            ->where('lf.created_at', '<=', $to);

        $query = DB::table('leads as l')
            ->joinSub($latest, 'latest_lf', function ($join) {
                $join->on('latest_lf.lead_id', '=', 'l.id')
                    ->where('latest_lf.rn', '=', 1);
            })
            ->leftJoin('clients as c', 'c.id', '=', 'l.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.representative_user_id')
            ->whereIn('l.representative_user_id', $userIds)
            ->whereNotIn('latest_lf.status', [2, 5]);

        $this->sources->apply($query, 'l', $source);

        return [
            'columns' => [
                ['key' => 'lead_id', 'label' => 'Lead'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'agent', 'label' => 'Agent'],
                ['key' => 'status', 'label' => 'Current Status'],
                ['key' => 'last_activity', 'label' => 'Last Follow-up'],
            ],
            'rows' => $query
                ->select([
                    'l.id as lead_id',
                    'c.name as customer',
                    'u.name as agent',
                    'latest_lf.status as status',
                    'latest_lf.created_at as last_activity',
                ])
                ->orderByDesc('latest_lf.created_at')
                ->limit(500)
                ->get()
                ->map(fn ($row) => (array) $row)
            ->all(),
        ];
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
