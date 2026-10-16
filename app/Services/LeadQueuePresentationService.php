<?php

namespace App\Services;

use App\Models\LeadFollowup;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeadQueuePresentationService
{
    public function followups(Collection $rows, Carbon $date, $settings = null): array
    {
        $counts = ['total' => $rows->count(), 'today' => 0, 'hot' => 0, 'no_date' => 0, 'overdue' => 0];
        foreach ($rows as $row) {
            if (!$row->next_followup_date) $counts['no_date']++;
            elseif ($row->next_followup_date->toDateString() === $date->toDateString()) $counts['today']++;
            elseif ($row->next_followup_date->lt($date)) $counts['overdue']++;
            $score = $row->enquiry?->latestAiScore;
            if ($score && $score->status === 'completed' && $score->displayTemperature($settings) === 'hot') $counts['hot']++;
        }
        return $counts;
    }

    public function dnp(Collection $leads): array
    {
        $buckets = ['0-30' => 0, '31-90' => 0, '90+' => 0];
        $staff = []; $ages = []; $unknown = 0;
        foreach ($leads as $lead) {
            $age = $lead->created_at ? max(0, Carbon::parse($lead->created_at)->startOfDay()->diffInDays(now()->startOfDay(), false)) : null;
            $ages[$lead->id] = $age;
            $key = $lead->representative_user_id ?: 'unassigned';
            if (!isset($staff[$key])) $staff[$key] = ['name' => $lead->representative?->name ?? 'Unassigned', 'total' => 0, '0-30' => 0, '31-90' => 0, '90+' => 0, 'unknown' => 0];
            $staff[$key]['total']++;
            if ($age === null) { $unknown++; $staff[$key]['unknown']++; continue; }
            $bucket = $age <= 30 ? '0-30' : ($age <= 90 ? '31-90' : '90+');
            $buckets[$bucket]++; $staff[$key][$bucket]++;
        }
        // Preserve the old latest_followup accessor's scheduled-date ordering, in one batch.
        $followups = $leads->isEmpty() ? collect() : LeadFollowup::whereIn('lead_id', $leads->pluck('id'))
            ->orderByDesc('next_followup_date')->orderByDesc('created_at')->get(['id', 'lead_id', 'next_followup_date', 'created_at'])
            ->unique('lead_id')->keyBy('lead_id');
        $total = $leads->count();
        return ['total' => $total, 'buckets' => $buckets,
            'percentages' => array_map(fn ($count) => $total ? round($count / $total * 100, 1) : 0, $buckets),
            'ages' => $ages, 'unknown' => $unknown,
            'staff' => collect($staff)->sortByDesc('total')->values(), 'followups' => $followups,
            'oldest' => collect($ages)->filter(fn ($age) => $age !== null)->max(),
            'scheduled' => $followups->filter(fn ($row) => $row->next_followup_date && $row->next_followup_date->gte(now()->startOfDay()))->count()];
    }

    public function serviceNames(Collection $leads): array
    {
        $idsByLead = []; $allIds = [];
        foreach ($leads as $lead) {
            $ids = $lead->service_ids_array;
            if (!$ids) foreach ($lead->leadFollowups as $followup) $ids = array_merge($ids, $this->ids($followup->service_ids));
            $idsByLead[$lead->id] = array_unique($ids);
            $allIds = array_merge($allIds, $ids);
        }
        $names = $allIds ? Service::where('status', 1)->whereIn('id', array_unique($allIds))->pluck('service', 'id') : collect();
        return array_map(fn ($ids) => array_values(array_filter(array_map(fn ($id) => $names->get($id), $ids))), $idsByLead);
    }

    private function ids($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
