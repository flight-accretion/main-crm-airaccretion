<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\OperationCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationsWorkSummaryService
{
    private function workedActivity(array $filters, array $types)
    {
        $activity = DB::table('operation_case_activities as activity')
            ->join('operation_cases as cases', 'cases.id', '=', 'activity.operation_case_id')
            ->whereNotNull('activity.user_id')->whereIn('cases.type', $types)
            ->whereIn('cases.status', [OperationCase::STATUS_COMPLETED, OperationCase::STATUS_IN_PROGRESS, OperationCase::STATUS_PENDING]);
        if ($filters['from_date']) $activity->where('activity.created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        if ($filters['to_date']) $activity->where('activity.created_at', '<', Carbon::parse($filters['to_date'])->addDay()->startOfDay());
        if ($filters['operations_user_id']) $activity->where('activity.user_id', $filters['operations_user_id']);
        if ($filters['type']) $activity->where('cases.type', $filters['type']);
        if ($filters['status']) $activity->where('cases.status', $filters['status']);
        if (!empty($filters['completed_by'])) $activity->where('cases.completed_by', $filters['completed_by']);
        if (!empty($filters['search'])) {
            $activity->whereExists(function ($query) use ($filters) {
                $query->selectRaw('1')->from('leads')->join('clients', 'clients.id', '=', 'leads.client_id')
                    ->whereColumn('leads.id', 'cases.lead_id')->where('clients.name', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $activity;
    }

    public function detailQuery(array $filters, array $types, string $status, ?string $type)
    {
        $activity = $this->workedActivity($filters, $types);
        if ($status !== 'total') $activity->where('cases.status', $status);
        if ($type) $activity->where('cases.type', $type);
        return OperationCase::with(['lead.client', 'lead.rideSegments', 'assignee', 'completedBy'])
            ->whereIn('id', $activity->select('cases.id'))
            ->orderByDesc('updated_at')->orderByDesc('id');
    }

    public function details(array $filters, array $types, string $status, ?string $type)
    {
        return $this->detailQuery($filters, $types, $status, $type)->paginate(25)->withQueryString();
    }

    public function dashboard(array $filters, array $types): array
    {
        $activity = $this->workedActivity($filters, $types);
        // Deduplicate cases before aggregation; event counts are not work-completion counts.
        $caseRows = (clone $activity)->select(['cases.id', 'cases.type', 'cases.status'])->distinct();
        $totals = DB::query()->fromSub($caseRows, 'worked')->selectRaw('type, status, COUNT(*) as total')->groupBy('type', 'status')->get();
        $summary = $this->emptyCounts(); $typeSummary = [];
        foreach ($types as $type) $typeSummary[$type] = $this->emptyCounts();
        foreach ($totals as $row) {
            if (!array_key_exists($row->status, $summary)) continue;
            $count = (int) $row->total;
            $summary[$row->status] += $count; $summary['total'] += $count;
            $typeSummary[$row->type][$row->status] += $count; $typeSummary[$row->type]['total'] += $count;
        }
        $people = (clone $activity)->selectRaw('activity.user_id, cases.status, COUNT(DISTINCT cases.id) as total')
            ->groupBy('activity.user_id', 'cases.status')->get();
        // Include inactive/former employees with real historical work; avoid per-person queries.
        $users = User::whereIn('id', $people->pluck('user_id')->unique())->pluck('name', 'id');
        $breakdown = [];
        foreach ($people as $row) {
            if (!array_key_exists($row->status, $summary)) continue;
            if (!isset($breakdown[$row->user_id])) $breakdown[$row->user_id] = ['user_id' => $row->user_id, 'name' => $users->get($row->user_id, 'Former employee')] + $this->emptyCounts();
            $breakdown[$row->user_id][$row->status] += (int) $row->total;
            $breakdown[$row->user_id]['total'] += (int) $row->total;
        }
        return ['summary' => $summary, 'typeSummary' => $typeSummary,
            'breakdown' => collect($breakdown)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all()];
    }

    private function emptyCounts(): array
    {
        return ['completed' => 0, 'in_progress' => 0, 'pending' => 0, 'total' => 0];
    }
}
